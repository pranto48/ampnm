<?php
require_once 'includes/auth_check.php';
require_once 'header.php';

$pdo = getDbConnection();
$host_id = (int)($_GET['id'] ?? 0);

if ($host_id <= 0) {
    $ip = $_GET['ip'] ?? '';
    $hostname = $_GET['host'] ?? '';
    if ($ip !== '') {
        $stmt = $pdo->prepare("SELECT * FROM host_metrics WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
    } elseif ($hostname !== '') {
        $stmt = $pdo->prepare("SELECT * FROM host_metrics WHERE hostname = ? LIMIT 1");
        $stmt->execute([$hostname]);
    } else {
        header('Location: host_metrics.php');
        exit;
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM host_metrics WHERE id = ? LIMIT 1");
    $stmt->execute([$host_id]);
}
$d = $stmt->fetch();

if (!$d) {
    echo '<div class="container mx-auto px-4 py-8"><div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-lg text-center">Monitored host not found.</div></div>';
    if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>';
    exit;
}

$hostname = $d['hostname'];
$ip = $d['ip_address'];

// Retrieve delay settings for online detection
$stmt_override = $pdo->prepare("SELECT status_delay_seconds FROM host_alert_overrides WHERE host_ip = ? LIMIT 1");
$stmt_override->execute([$ip]);
$delay = $stmt_override->fetchColumn();
$delay = $delay ? (int)$delay : 300; // default 300s

$isOnline = false;
if (!empty($d['last_seen'])) {
    $lastSeenTime = strtotime($d['last_seen']);
    if ((time() - $lastSeenTime) < $delay) {
        $isOnline = true;
    }
}
$status = $isOnline ? 'online' : 'offline';

$status_colors = [
    'online'  => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
    'offline' => 'text-red-400 bg-red-500/10 border-red-500/30',
];
$sc = $status_colors[$status] ?? $status_colors['offline'];

$cpu  = round($d['cpu_usage'] ?? 0, 1);
$mem  = round($d['memory_usage'] ?? 0, 1);
$disk = round($d['disk_usage'] ?? 0, 1);
$memTotal = $d['memory_total'] ?? 0;
$diskTotal = $d['disk_total'] ?? 0;

// Uptime calculation
$uptimeSeconds = $d['uptime_seconds'] ?? 0;
$uptimeStr = '—';
if ($uptimeSeconds > 0) {
    $days = floor($uptimeSeconds / 86400);
    $hours = floor(($uptimeSeconds % 86400) / 3600);
    $minutes = floor(($uptimeSeconds % 3600) / 60);
    if ($days > 0) {
        $uptimeStr = "{$days}d {$hours}h {$minutes}m";
    } else {
        $uptimeStr = "{$hours}h {$minutes}m";
    }
}

// History aggregation range (hours query)
$hours = (int)($_GET['hours'] ?? 24);
if ($hours <= 0 || $hours > 168) $hours = 24;

$stmt_history = $pdo->prepare("
    SELECT 
        DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i:00') AS bucket,
        ROUND(AVG(cpu_usage), 2) AS avg_cpu,
        ROUND(AVG(memory_usage), 2) AS avg_mem,
        ROUND(AVG(disk_usage), 2) AS avg_disk,
        ROUND(AVG(network_in), 2) AS avg_rx_mb,
        ROUND(AVG(network_out), 2) AS avg_tx_mb
    FROM host_metrics_history
    WHERE hostname = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    GROUP BY DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i:00')
    ORDER BY bucket ASC
    LIMIT 200
");
$stmt_history->execute([$hostname, $hours]);
$chart_rows = $stmt_history->fetchAll();

$chart_labels = array_column($chart_rows, 'bucket');
$chart_cpu = array_column($chart_rows, 'avg_cpu');
$chart_mem = array_column($chart_rows, 'avg_mem');
$chart_disk = array_column($chart_rows, 'avg_disk');
$chart_rx = array_column($chart_rows, 'avg_rx_mb');
$chart_tx = array_column($chart_rows, 'avg_tx_mb');
?>

<div class="container mx-auto px-4 py-6">
    <!-- Back + Header -->
    <div class="flex items-center gap-3 mb-6">
        <a href="host_metrics.php" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-300 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-desktop text-cyan-400"></i>
                <?= htmlspecialchars($hostname) ?>
            </h1>
            <p class="text-slate-400 text-sm">
                <?= htmlspecialchars($ip) ?> &bull; <?= htmlspecialchars($d['platform'] ?: 'Windows') ?> (<?= htmlspecialchars($d['os_version'] ?: 'Version Unknown') ?>)
            </p>
        </div>
        <span class="ml-auto px-3 py-1.5 rounded-full text-xs font-bold border <?= $sc ?>">
            <?= ucfirst($status) ?>
        </span>
    </div>

    <!-- Info Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php 
        $info_cards = [
            ['CPU', $cpu . '%', 'fa-microchip', 'cyan', $cpu],
            ['Memory', $mem . '%', 'fa-memory', 'purple', $mem],
            ['Disk', $disk . '%', 'fa-hdd', 'green', $disk],
            ['Uptime', $uptimeStr, 'fa-clock', 'slate', 0],
        ];
        foreach ($info_cards as [$label, $value, $icon, $col, $pct]):
            $gauge_col = ($pct >= 90) ? 'text-red-400' : (($pct >= 70) ? 'text-amber-400' : "text-{$col}-400");
        ?>
        <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-4 text-center">
            <i class="fas <?= $icon ?> text-xl <?= $gauge_col ?> mb-2 block"></i>
            <p class="text-2xl font-bold text-white"><?= $value ?></p>
            <p class="text-slate-400 text-xs mt-0.5"><?= $label ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Device Details Panel -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fas fa-info-circle text-cyan-400"></i>System Details
            </h3>
            <dl class="space-y-2 text-sm">
                <?php
                $info = [
                    'OS Platform' => $d['platform'] ?: 'Windows',
                    'OS Version' => $d['os_version'] ?: '—',
                    'Total Memory' => $memTotal > 0 ? number_format($memTotal, 2) . ' GB' : '—',
                    'Total Disk' => $diskTotal > 0 ? number_format($diskTotal, 2) . ' GB' : '—',
                    'Uptime' => $uptimeStr,
                    'Load Average' => ($d['load_average'] !== null) ? $d['load_average'] : '—',
                    'CPU Temp' => ($d['temperature_celsius'] !== null) ? $d['temperature_celsius'] . ' °C' : '—',
                ];
                foreach ($info as $k => $v): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500 flex-shrink-0"><?= htmlspecialchars($k) ?></dt>
                        <dd class="text-slate-200 text-right truncate" title="<?= htmlspecialchars((string)$v) ?>"><?= htmlspecialchars((string)$v) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fas fa-network-wired text-green-400"></i>Network & Identity
            </h3>
            <dl class="space-y-2 text-sm">
                <?php
                $net = [
                    'Hostname' => $hostname,
                    'IP Address' => $ip,
                    'Current Network In' => ($d['network_in'] !== null) ? floatval($d['network_in']) . ' Mbps' : '—',
                    'Current Network Out' => ($d['network_out'] !== null) ? floatval($d['network_out']) . ' Mbps' : '—',
                ];
                foreach ($net as $k => $v): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500 flex-shrink-0"><?= htmlspecialchars($k) ?></dt>
                        <dd class="text-slate-200 text-right truncate"><?= htmlspecialchars((string)$v) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fas fa-calendar-check text-purple-400"></i>Timestamps
            </h3>
            <dl class="space-y-2 text-sm">
                <?php
                $times = [
                    'First Registered' => $d['first_seen'] ? date('M d, Y H:i', strtotime($d['first_seen'])) : '—',
                    'Last Seen' => $d['last_seen'] ? date('M d, Y H:i:s', strtotime($d['last_seen'])) : 'Never',
                    'Boot Time' => $d['boot_time'] ? date('M d, Y H:i:s', strtotime($d['boot_time'])) : '—',
                    'Status Check Delay' => $delay . 's',
                ];
                foreach ($times as $k => $v): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500 flex-shrink-0"><?= htmlspecialchars($k) ?></dt>
                        <dd class="text-slate-200 text-right truncate"><?= htmlspecialchars((string)$v) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <!-- Time-Series Charts -->
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mb-6">
        <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
            <h3 class="text-white font-semibold flex items-center gap-2">
                <i class="fas fa-chart-area text-cyan-400"></i>Performance History
            </h3>
            <select id="chart-range" onchange="changeChartRange(this.value)" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-sm text-white">
                <option value="1" <?= $hours == 1 ? 'selected' : '' ?>>Last 1 Hour</option>
                <option value="6" <?= $hours == 6 ? 'selected' : '' ?>>Last 6 Hours</option>
                <option value="24" <?= $hours == 24 ? 'selected' : '' ?>>Last 24 Hours</option>
                <option value="72" <?= $hours == 72 ? 'selected' : '' ?>>Last 3 Days</option>
                <option value="168" <?= $hours == 168 ? 'selected' : '' ?>>Last 7 Days</option>
            </select>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-microchip text-cyan-400 mr-1"></i>CPU Usage (%)</p>
                <canvas id="chart-cpu" height="120"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-memory text-purple-400 mr-1"></i>Memory Usage (%)</p>
                <canvas id="chart-mem" height="120"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-hdd text-green-400 mr-1"></i>Disk Usage (%)</p>
                <canvas id="chart-disk" height="120"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-network-wired text-orange-400 mr-1"></i>Network Throughput (Mbps)</p>
                <canvas id="chart-net" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const chartLabels = <?= json_encode($chart_labels) ?>;
const chartCpu   = <?= json_encode($chart_cpu) ?>;
const chartMem   = <?= json_encode($chart_mem) ?>;
const chartDisk  = <?= json_encode($chart_disk) ?>;
const chartRx    = <?= json_encode($chart_rx) ?>;
const chartTx    = <?= json_encode($chart_tx) ?>;

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: { legend: { display: false } },
    scales: {
        x: {
            ticks: { color: '#64748b', maxTicksLimit: 6, font: { size: 10 } },
            grid: { color: 'rgba(100,116,139,0.1)' }
        },
        y: {
            ticks: { color: '#64748b', font: { size: 10 } },
            grid: { color: 'rgba(100,116,139,0.1)' }
        }
    }
};

function makeChart(id, data, color, fillColor, label) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label,
                data,
                borderColor: color,
                backgroundColor: fillColor,
                borderWidth: 1.5,
                pointRadius: 0,
                tension: 0.3,
                fill: true,
            }]
        },
        options: { ...chartDefaults, scales: { ...chartDefaults.scales, y: { ...chartDefaults.scales.y, min: 0, max: 100 } } }
    });
}

makeChart('chart-cpu',  chartCpu,  '#22d3ee', 'rgba(34,211,238,0.08)', 'CPU %');
makeChart('chart-mem',  chartMem,  '#a78bfa', 'rgba(167,139,250,0.08)', 'Memory %');
makeChart('chart-disk', chartDisk, '#34d399', 'rgba(52,211,153,0.08)', 'Disk %');

// Network throughput graph
(function() {
    const ctx = document.getElementById('chart-net');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                { label: 'Inbound Mbps', data: chartRx, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.06)', borderWidth: 1.5, pointRadius: 0, tension: 0.3, fill: false },
                { label: 'Outbound Mbps', data: chartTx, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.06)', borderWidth: 1.5, pointRadius: 0, tension: 0.3, fill: false }
            ]
        },
        options: {
            ...chartDefaults,
            plugins: { legend: { display: true, labels: { color: '#94a3b8', font: { size: 11 } } } },
            scales: { ...chartDefaults.scales, y: { ...chartDefaults.scales.y, min: 0 } }
        }
    });
})();

function changeChartRange(hours) {
    const url = new URL(window.location.href);
    url.searchParams.set('hours', hours);
    window.location.href = url.toString();
}
</script>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
