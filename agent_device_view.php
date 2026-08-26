<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Windows Agent Client Device Live Inspection & Remote Management
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

// Multi-Drive storage
$stmt = $pdo->prepare("SELECT * FROM agent_device_drives WHERE agent_device_id = ? ORDER BY drive_letter ASC");
$stmt->execute([$device_id]);
$drives = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Windows Services
$stmt = $pdo->prepare("SELECT * FROM agent_device_services WHERE agent_device_id = ? ORDER BY status ASC, service_name ASC");
$stmt->execute([$device_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Recent events
$stmt = $pdo->prepare("SELECT * FROM agent_events WHERE agent_device_id = ? ORDER BY created_at DESC LIMIT 15");
$stmt->execute([$device_id]);
$events = $stmt->fetchAll();

// Recent Remote Commands
$stmt = $pdo->prepare("SELECT * FROM agent_command_queue WHERE agent_device_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$device_id]);
$recentCommands = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Back + Header -->
    <div class="flex items-center gap-3 mb-6">
        <a href="agent_devices.php" class="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 transition-colors border border-slate-700">
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
        <span class="ml-auto px-3 py-1.5 rounded-full text-xs font-bold border <?= $sc ?> uppercase tracking-wider">
            <?= ucfirst($status) ?>
        </span>
    </div>

    <!-- Info Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 text-center">
            <i class="fas fa-microchip text-xl <?= $cpu >= 90 ? 'text-red-400' : ($cpu >= 70 ? 'text-amber-400' : 'text-cyan-400') ?> mb-2 block"></i>
            <p class="text-2xl font-bold text-white"><?= $cpu ?>%</p>
            <p class="text-slate-400 text-xs mt-0.5">CPU Load</p>
        </div>
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 text-center">
            <i class="fas fa-memory text-xl <?= $mem >= 90 ? 'text-red-400' : ($mem >= 70 ? 'text-amber-400' : 'text-purple-400') ?> mb-2 block"></i>
            <p class="text-2xl font-bold text-white"><?= $mem ?>%</p>
            <p class="text-slate-400 text-xs mt-0.5">RAM Usage (<?= $hb ? round(($hb['memory_used_mb'] ?? 0)/1024, 1) . ' / ' . round(($hb['memory_total_mb'] ?? 1)/1024, 1) . ' GB' : '' ?>)</p>
        </div>
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 text-center">
            <i class="fas fa-hdd text-xl <?= $disk >= 90 ? 'text-red-400' : ($disk >= 70 ? 'text-amber-400' : 'text-emerald-400') ?> mb-2 block"></i>
            <p class="text-2xl font-bold text-white"><?= $disk ?>%</p>
            <p class="text-slate-400 text-xs mt-0.5">Primary Storage</p>
        </div>
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 text-center">
            <i class="fas fa-clock text-xl text-slate-400 mb-2 block"></i>
            <p class="text-2xl font-bold text-white"><?= $hb ? gmdate('H:i:s', $hb['uptime_seconds'] ?? 0) : '—' ?></p>
            <p class="text-slate-400 text-xs mt-0.5">System Uptime</p>
        </div>
    </div>

    <!-- Multi-Drive Storage Section if available -->
    <?php if (!empty($drives)): ?>
    <div class="bg-slate-850 border border-slate-800 rounded-xl p-5 mb-6">
        <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-800 pb-3 text-sm">
            <i class="fas fa-hard-drive text-emerald-400"></i> Mounted Disks & Storage Volumes
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($drives as $drv): 
                $used = (float)$drv['used_percent'];
                $barCol = $used >= 90 ? 'bg-red-500' : ($used >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
            ?>
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-white text-sm"><i class="fas fa-hdd text-slate-400 mr-1.5"></i> <?= htmlspecialchars($drv['drive_letter']) ?> (<?= htmlspecialchars($drv['volume_name'] ?: 'Local Disk') ?>)</span>
                    <span class="text-xs font-bold text-cyan-400"><?= $used ?>%</span>
                </div>
                <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden mb-2">
                    <div class="h-2 rounded-full <?= $barCol ?>" style="width: <?= min(100, $used) ?>%"></div>
                </div>
                <div class="flex justify-between text-2xs text-slate-400">
                    <span>Free: <?= round($drv['free_gb'], 1) ?> GB</span>
                    <span>Total: <?= round($drv['total_gb'], 1) ?> GB (<?= htmlspecialchars($drv['file_system']) ?>)</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Interactive PowerShell Remote Command Console -->
    <div class="bg-slate-850 border border-slate-800 rounded-xl p-5 mb-6 shadow-xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4 border-b border-slate-800 pb-3">
            <div>
                <h3 class="text-white font-semibold flex items-center gap-2 text-sm">
                    <i class="fas fa-terminal text-cyan-400"></i> Live Remote PowerShell Console
                </h3>
                <p class="text-slate-400 text-xs mt-0.5">Execute diagnostic commands & scripts on client machine via background queue.</p>
            </div>
            <!-- Quick Command Presets -->
            <div class="flex flex-wrap gap-1.5">
                <button onclick="setConsoleCommand('Get-Service | Where-Object {$_.Status -eq \'Running\'} | Select-Object -First 10')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-2xs rounded border border-slate-700 transition">Running Services</button>
                <button onclick="setConsoleCommand('Get-Process | Sort-Object CPU -Descending | Select-Object -First 8 Name, Id, CPU, WorkingSet64')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-2xs rounded border border-slate-700 transition">Top CPU Processes</button>
                <button onclick="setConsoleCommand('ipconfig /all')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-2xs rounded border border-slate-700 transition">ipconfig /all</button>
                <button onclick="setConsoleCommand('Test-NetConnection 8.8.8.8 -Port 53')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-2xs rounded border border-slate-700 transition">DNS Ping Test</button>
            </div>
        </div>

        <div class="flex gap-2 mb-3">
            <input type="text" id="cmdInput" placeholder="Enter PowerShell command (e.g. Restart-Service Spooler, Get-Volume)..." class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm font-mono text-cyan-300 focus:border-cyan-500 outline-none">
            <button onclick="dispatchRemoteCommand()" id="btnSendCmd" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold transition flex items-center gap-1.5">
                <i class="fas fa-paper-plane"></i> Execute
            </button>
        </div>

        <!-- Terminal Output View -->
        <div class="bg-slate-950 rounded-xl p-4 border border-slate-800 font-mono text-xs text-emerald-400 overflow-x-auto min-h-[140px] max-h-[280px]" id="terminalOutput">
            <span class="text-slate-600">PS <?= htmlspecialchars($d['hostname']) ?>&gt; Ready. Enter command above and click Execute.</span>
        </div>
    </div>

    <!-- Windows Services Manager Tab -->
    <?php if (!empty($services)): ?>
    <div class="bg-slate-850 border border-slate-800 rounded-xl p-5 mb-6 shadow-xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
            <h3 class="text-white font-semibold flex items-center gap-2 text-sm">
                <i class="fas fa-cogs text-purple-400"></i> Windows Services Manager (<?= count($services) ?> Services)
            </h3>
            <input type="text" id="serviceSearch" onkeyup="filterServices()" placeholder="Search service name..." class="bg-slate-950 border border-slate-800 rounded px-2.5 py-1 text-xs text-slate-200 outline-none">
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="w-full text-left text-xs text-slate-300" id="servicesTable">
                <thead class="bg-slate-900/80 text-slate-400 uppercase tracking-wider sticky top-0">
                    <tr>
                        <th class="py-2 px-3">Service Name</th>
                        <th class="py-2 px-3">Display Name</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3">Startup</th>
                        <th class="py-2 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php foreach ($services as $srv): 
                        $isRunning = strtolower($srv['status']) === 'running';
                    ?>
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="py-2 px-3 font-mono font-semibold text-white"><?= htmlspecialchars($srv['service_name']) ?></td>
                        <td class="py-2 px-3 text-slate-400 truncate max-w-xs"><?= htmlspecialchars($srv['display_name'] ?: $srv['service_name']) ?></td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded text-2xs font-bold <?= $isRunning ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-800 text-slate-400' ?>">
                                <?= htmlspecialchars($srv['status']) ?>
                            </span>
                        </td>
                        <td class="py-2 px-3 text-slate-400"><?= htmlspecialchars($srv['start_type'] ?? 'Automatic') ?></td>
                        <td class="py-2 px-3 text-right">
                            <button onclick="restartServiceAction('<?= htmlspecialchars($srv['service_name']) ?>')" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded text-2xs transition">
                                <i class="fas fa-sync-alt"></i> Restart
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Device Details Panel -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-800 pb-2 text-sm">
                <i class="fas fa-info-circle text-cyan-400"></i> System Specs & Architecture
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
                    <div class="flex justify-between gap-2 text-xs">
                        <dt class="text-slate-500 flex-shrink-0"><?= htmlspecialchars($k) ?></dt>
                        <dd class="text-slate-200 text-right truncate" title="<?= htmlspecialchars((string)$v) ?>"><?= htmlspecialchars((string)$v) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-800 pb-2 text-sm">
                <i class="fas fa-network-wired text-green-400"></i> Network & Identity
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
                    <div class="flex justify-between gap-2 text-xs">
                        <dt class="text-slate-500 flex-shrink-0"><?= htmlspecialchars($k) ?></dt>
                        <dd class="text-slate-200 text-right truncate"><?= htmlspecialchars((string)$v) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-800 pb-2 text-sm">
                <i class="fas fa-calendar-check text-purple-400"></i> Heartbeat & Server Status
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
                    <div class="flex justify-between gap-2 text-xs">
                        <dt class="text-slate-500 flex-shrink-0"><?= htmlspecialchars($k) ?></dt>
                        <dd class="text-slate-200 text-right truncate"><?= htmlspecialchars((string)$v) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <!-- Time-Series Charts (24h) -->
    <div class="bg-slate-850 border border-slate-800 rounded-xl p-5 mb-6 shadow-xl">
        <h3 class="text-white font-semibold mb-4 flex items-center gap-2 border-b border-slate-800 pb-2 text-sm">
            <i class="fas fa-chart-area text-cyan-400"></i> Performance Telemetry (Last 24 Hours)
        </h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-800">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-microchip text-cyan-400 mr-1"></i>CPU Usage (%)</p>
                <canvas id="chart-cpu" height="120"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-800">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-memory text-purple-400 mr-1"></i>Memory Usage (%)</p>
                <canvas id="chart-mem" height="120"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-800">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-hdd text-green-400 mr-1"></i>Disk Usage (%)</p>
                <canvas id="chart-disk" height="120"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-800">
                <p class="text-xs text-slate-400 font-semibold mb-3"><i class="fas fa-network-wired text-orange-400 mr-1"></i>Network MB (RX/TX)</p>
                <canvas id="chart-net" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const agentDeviceId = <?= $device_id ?>;
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

// Remote Console Methods
function setConsoleCommand(cmd) {
    document.getElementById('cmdInput').value = cmd;
}

async function dispatchRemoteCommand() {
    const input = document.getElementById('cmdInput');
    const cmdText = input.value.trim();
    if (!cmdText) return;

    const term = document.getElementById('terminalOutput');
    term.innerHTML += `<div class="text-cyan-300 mt-2">PS &gt; ${cmdText}</div><div class="text-amber-400 text-2xs animate-pulse" id="runningLine"><i class="fas fa-spinner fa-spin"></i> Dispatched to agent queue, waiting for heartbeat execution...</div>`;
    term.scrollTop = term.scrollHeight;

    try {
        const res = await fetch('api.php?action=dispatch_agent_command', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ agent_device_id: agentDeviceId, command_text: cmdText, command_type: 'powershell' })
        });
        const json = await res.json();
        if (json.success && json.command_id) {
            pollCommandOutput(json.command_id);
        } else {
            const rl = document.getElementById('runningLine');
            if (rl) rl.outerHTML = `<div class="text-red-400">Error: ${json.error || 'Failed to dispatch'}</div>`;
        }
    } catch (e) {
        const rl = document.getElementById('runningLine');
        if (rl) rl.outerHTML = `<div class="text-red-400">Network error while dispatching</div>`;
    }
}

function pollCommandOutput(commandId, attempts = 0) {
    if (attempts > 30) {
        const rl = document.getElementById('runningLine');
        if (rl) rl.outerHTML = `<div class="text-slate-500">Execution timed out after 30s.</div>`;
        return;
    }

    setTimeout(async () => {
        try {
            const res = await fetch(`api.php?action=get_agent_command_status&command_id=${commandId}`);
            const json = await res.json();
            if (json.success && json.command) {
                if (json.command.status === 'completed' || json.command.status === 'failed') {
                    const rl = document.getElementById('runningLine');
                    const outHtml = json.command.output ? json.command.output.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '(No output returned)';
                    const color = json.command.status === 'completed' ? 'text-emerald-400' : 'text-red-400';
                    if (rl) rl.outerHTML = `<pre class="${color} whitespace-pre-wrap mt-1">${outHtml}</pre>`;
                    return;
                }
            }
            pollCommandOutput(commandId, attempts + 1);
        } catch (e) {
            pollCommandOutput(commandId, attempts + 1);
        }
    }, 1500);
}

function filterServices() {
    const q = document.getElementById('serviceSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#servicesTable tbody tr');
    rows.forEach(r => {
        const txt = r.textContent.toLowerCase();
        r.style.display = txt.includes(q) ? '' : 'none';
    });
}

async function restartServiceAction(serviceName) {
    if (!confirm(`Are you sure you want to restart service '${serviceName}'?`)) return;
    try {
        const res = await fetch('api.php?action=restart_agent_service', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ agent_device_id: agentDeviceId, service_name: serviceName })
        });
        const json = await res.json();
        if (json.success) {
            alert(json.message);
            if (json.command_id) {
                document.getElementById('cmdInput').value = `Restart-Service ${serviceName}`;
                dispatchRemoteCommand();
            }
        }
    } catch (e) {
        alert('Network error');
    }
}
</script>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
