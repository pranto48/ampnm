<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/auth_check.php';
require_once 'header.php';

$pdo = getDbConnection();
$device_id = (int)($_GET['id'] ?? 0);

if ($device_id <= 0) {
    header('Location: agent_devices.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM agent_devices WHERE id = ? LIMIT 1");
$stmt->execute([$device_id]);
$d = $stmt->fetch();

if (!$d) {
    echo '<div class="container mx-auto px-4 py-8"><div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-lg">Agent device not found.</div></div>';
    if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>';
    exit;
}

// Latest heartbeat
$stmt = $pdo->prepare("SELECT * FROM agent_heartbeats WHERE agent_device_id = ? ORDER BY collected_at DESC LIMIT 1");
$stmt->execute([$device_id]);
$hb = $stmt->fetch();

// Recent events
$stmt = $pdo->prepare("SELECT * FROM agent_events WHERE agent_device_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$device_id]);
$events = $stmt->fetchAll();

// 24h chart data (1 point per 15-min bucket)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(collected_at, '%Y-%m-%d %H:%i:00') AS bucket,
        ROUND(AVG(cpu_usage_percent), 2) AS avg_cpu,
        ROUND(AVG(memory_usage_percent), 2) AS avg_mem,
        ROUND(AVG(disk_usage_percent), 2) AS avg_disk,
        ROUND(AVG(network_rx_bytes)/1024/1024, 3) AS avg_rx_mb,
        ROUND(AVG(network_tx_bytes)/1024/1024, 3) AS avg_tx_mb
    FROM agent_heartbeats
    WHERE agent_device_id = ? AND collected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY DATE_FORMAT(collected_at, '%Y-%m-%d %H:%i:00')
    ORDER BY bucket ASC
    LIMIT 200
");
$stmt->execute([$device_id]);
$chart_rows = $stmt->fetchAll();

$chart_labels = array_column($chart_rows, 'bucket');
$chart_cpu = array_column($chart_rows, 'avg_cpu');
$chart_mem = array_column($chart_rows, 'avg_mem');
$chart_disk = array_column($chart_rows, 'avg_disk');
$chart_rx = array_column($chart_rows, 'avg_rx_mb');
$chart_tx = array_column($chart_rows, 'avg_tx_mb');

$status = $d['status'] ?? 'offline';
$status_colors = [
    'online'  => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
    'warning' => 'text-amber-400 bg-amber-500/10 border-amber-500/30',
    'offline' => 'text-red-400 bg-red-500/10 border-red-500/30',
];
$sc = $status_colors[$status] ?? $status_colors['offline'];

$cpu  = round($hb['cpu_usage_percent']    ?? 0, 1);
$mem  = round($hb['memory_usage_percent'] ?? 0, 1);
$disk = round($hb['disk_usage_percent']   ?? 0, 1);

function gaugeColor($v) {
    if ($v >= 90) return '#ef4444';
    if ($v >= 70) return '#f59e0b';
    return '#22d3ee';
}
?>

<div class="container mx-auto px-4 py-6">
    <!-- Back + Header -->
    <div class="flex items-center gap-3 mb-6">
        <a href="agent_devices.php" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-300 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-desktop text-cyan-400"></i>
                <?= htmlspecialchars($d['agent_name'] ?: $d['hostname']) ?>
            </h1>
            <p class="text-slate-400 text-sm">
                <?= htmlspecialchars($d['hostname']) ?> &bull; <?= htmlspecialchars($d['os_name']) ?> <?= htmlspecialchars($d['os_version']) ?>
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
            ['Uptime', $hb ? gmdate('H:i:s', $hb['uptime_seconds'] ?? 0) : '—', 'fa-clock', 'slate', 0],
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
                    'Agent UUID' => $d['agent_uuid'],
                    'OS' => $d['os_name'] . ' ' . $d['os_version'],
                    'Architecture' => $d['architecture'],
                    'CPU Model' => $d['cpu_model'] ?? '—',
                    'CPU Cores' => $d['cpu_cores'] ?? '—',
                    'Total RAM' => $d['total_memory_mb'] ? number_format($d['total_memory_mb']) . ' MB' : '—',
                    'Total Disk' => $d['total_disk_gb'] ? $d['total_disk_gb'] . ' GB' : '—',
                    'Agent Version' => $d['app_version'] ?? '—',
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
                    'Hostname' => $d['hostname'],
                    'Local IP' => $d['local_ip'] ?? '—',
                    'Public IP' => $d['public_ip'] ?? '—',
                    'MAC Address' => $d['mac_address'] ?? '—',
                    'Domain' => $d['domain'] ?? '—',
                    'Active User' => $hb['active_user'] ?? '—',
                    'Processes' => $hb['process_count'] ?? '—',
                    'Services' => $hb['service_count'] ?? '—',
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
                    'Registered' => $d['registered_at'] ? date('M d, Y H:i', strtotime($d['registered_at'])) : '—',
                    'Last Heartbeat' => $d['last_seen_at'] ? date('M d, Y H:i:s', strtotime($d['last_seen_at'])) : 'Never',
                    'HB Interval' => ($d['heartbeat_interval_seconds'] ?? 5) . 's',
                    'Battery' => ($hb && $hb['battery_percent'] !== null) ? $hb['battery_percent'] . '% (' . ($hb['battery_status'] ?? 'unknown') . ')' : '—',
                    'Server' => $d['server_address'] ? parse_url($d['server_address'], PHP_URL_HOST) ?? $d['server_address'] : '—',
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

    <!-- Time-Series Charts (24h) -->
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mb-6">
        <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-700 pb-2">
            <i class="fas fa-chart-area text-cyan-400"></i>Performance — Last 24 Hours
        </h3>
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
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-network-wired text-orange-400 mr-1"></i>Network MB (RX/TX)</p>
                <canvas id="chart-net" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Events -->
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-2">
            <h3 class="text-white font-semibold flex items-center gap-2">
                <i class="fas fa-list-ul text-amber-400"></i>Recent Events
            </h3>
            <a href="agent_logs.php?agent_id=<?= $device_id ?>" class="text-cyan-400 hover:text-cyan-300 text-xs flex items-center gap-1">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <?php if (empty($events)): ?>
            <p class="text-slate-500 text-sm text-center py-6">No events logged yet.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($events as $e):
                    $sev_colors = [
                        'info' => 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
                        'warning' => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
                        'error' => 'text-red-400 bg-red-500/10 border-red-500/20',
                        'critical' => 'text-red-300 bg-red-600/20 border-red-600/30',
                    ];
                    $sev_c = $sev_colors[$e['severity']] ?? $sev_colors['info'];
                ?>
                    <div class="flex items-start gap-3 text-sm py-2 border-b border-slate-800 last:border-0">
                        <span class="flex-shrink-0 px-2 py-0.5 rounded text-xs font-semibold border <?= $sev_c ?>">
                            <?= htmlspecialchars(strtoupper($e['severity'])) ?>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-300 truncate" title="<?= htmlspecialchars($e['message']) ?>"><?= htmlspecialchars($e['message']) ?></p>
                            <p class="text-slate-600 text-xs mt-0.5"><?= htmlspecialchars($e['event_type']) ?> · <?= date('M d H:i:s', strtotime($e['created_at'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

// Network: dual series
(function() {
    const ctx = document.getElementById('chart-net');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                { label: 'RX MB', data: chartRx, borderColor: '#fb923c', backgroundColor: 'rgba(251,146,60,0.06)', borderWidth: 1.5, pointRadius: 0, tension: 0.3, fill: false },
                { label: 'TX MB', data: chartTx, borderColor: '#60a5fa', backgroundColor: 'rgba(96,165,250,0.06)', borderWidth: 1.5, pointRadius: 0, tension: 0.3, fill: false }
            ]
        },
        options: {
            ...chartDefaults,
            plugins: { legend: { display: true, labels: { color: '#94a3b8', font: { size: 11 } } } },
            scales: { ...chartDefaults.scales, y: { ...chartDefaults.scales.y, min: 0 } }
        }
    });
})();
</script>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
