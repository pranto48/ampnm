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
    $lastSeenTime = strtotime($d['last_seen'] . ' UTC');
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
        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
            <button onclick="deleteHost('<?= htmlspecialchars($ip) ?>', '<?= htmlspecialchars($hostname) ?>')" 
                    class="ml-3 px-3 py-1.5 bg-red-600/20 hover:bg-red-600 border border-red-500/30 text-red-400 hover:text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1"
                    title="Delete host from monitoring">
                <i class="fas fa-trash-alt"></i> Delete Host
            </button>
        <?php endif; ?>
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
                    'First Registered' => $d['first_seen'] ? date('M d, Y H:i', strtotime($d['first_seen'] . ' UTC')) : '—',
                    'Last Seen' => $d['last_seen'] ? date('M d, Y H:i:s', strtotime($d['last_seen'] . ' UTC')) : 'Never',
                    'Boot Time' => $d['boot_time'] ? date('M d, Y H:i:s', strtotime($d['boot_time'] . ' UTC')) : '—',
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
    <!-- Remote Host Diagnostic & Command Console -->
    <?php
    $agent_token_str = '';
    if (!empty($d['agent_token_id'])) {
        $stmtTok = $pdo->prepare("SELECT token FROM agent_tokens WHERE id = ?");
        $stmtTok->execute([(int)$d['agent_token_id']]);
        $agent_token_str = $stmtTok->fetchColumn() ?: '';
    }
    if (empty($agent_token_str)) {
        // Fallback default token
        $agent_token_str = 'ampnm_1dc3c51eb6872b8eabcd2717e0b7bcf3';
    }
    ?>
    <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-5 mb-6 shadow-xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-700">
            <div>
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fas fa-terminal text-cyan-400"></i>
                    Remote Host Diagnostic Console & Shell Actions
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Execute real-time diagnostics securely through the connected Go agent daemon.</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-400">Agent Channel:</span>
                <span class="px-2 py-0.5 bg-slate-900 font-mono text-cyan-300 rounded border border-slate-700 text-[10px]">
                    <?= htmlspecialchars(substr($agent_token_str, 0, 14)) ?>...
                </span>
            </div>
        </div>

        <!-- Quick Action Trigger Buttons -->
        <div class="flex flex-wrap gap-2.5 mb-4">
            <button type="button" class="btn-agent-cmd px-3.5 py-2 bg-slate-700/80 hover:bg-cyan-600 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow" data-cmd="system_info" data-payload="">
                <i class="fas fa-info-circle text-cyan-400"></i>
                <span>System Overview</span>
            </button>
            <button type="button" class="btn-agent-cmd px-3.5 py-2 bg-slate-700/80 hover:bg-cyan-600 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow" data-cmd="process_list" data-payload="">
                <i class="fas fa-tasks text-emerald-400"></i>
                <span>Top Processes</span>
            </button>
            <button type="button" class="btn-agent-cmd px-3.5 py-2 bg-slate-700/80 hover:bg-cyan-600 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow" data-cmd="flush_dns" data-payload="">
                <i class="fas fa-broom text-amber-400"></i>
                <span>Flush DNS Cache</span>
            </button>
            <button type="button" class="btn-agent-cmd-prompt px-3.5 py-2 bg-slate-700/80 hover:bg-cyan-600 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow" data-cmd="ping" data-prompt="Enter IP or domain to ping from this host (e.g. 8.8.8.8):" data-default="8.8.8.8">
                <i class="fas fa-satellite-dish text-purple-400"></i>
                <span>Ping from Host...</span>
            </button>
            <button type="button" class="btn-agent-cmd-prompt px-3.5 py-2 bg-slate-700/80 hover:bg-cyan-600 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow" data-cmd="traceroute" data-prompt="Enter IP or domain to traceroute:" data-default="1.1.1.1">
                <i class="fas fa-route text-blue-400"></i>
                <span>Traceroute Path...</span>
            </button>
            <button type="button" class="btn-agent-cmd-prompt px-3.5 py-2 bg-slate-700/80 hover:bg-rose-600 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow" data-cmd="service_restart" data-prompt="Enter exact Windows or systemd Service Name to restart:" data-default="Spooler">
                <i class="fas fa-redo text-rose-400"></i>
                <span>Restart Service...</span>
            </button>
        </div>

        <!-- Custom Command Input -->
        <div class="flex gap-2 mb-4">
            <input type="text" id="customCmdInput" placeholder="Enter custom PowerShell / Bash command (e.g. Get-Service | Where Status -eq 'Running')..." class="flex-1 bg-slate-900 border border-slate-700 text-white text-xs font-mono rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <button type="button" id="btnRunCustom" class="px-5 py-2.5 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow flex items-center gap-2">
                <i class="fas fa-play"></i>
                <span>Execute</span>
            </button>
        </div>

        <!-- Live Terminal Stream Output Box -->
        <div class="relative bg-slate-950 rounded-xl border border-slate-800 p-4 font-mono text-xs text-slate-300">
            <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 text-[10px] text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-500/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500/80"></span>
                    <span class="ml-2 text-slate-400 font-bold" id="terminalTitle">Remote Shell: Ready</span>
                </div>
                <div class="flex items-center gap-3">
                    <span id="terminalStatus" class="text-cyan-400 font-semibold">Idle</span>
                    <button type="button" id="btnClearTerminal" class="text-slate-400 hover:text-white text-xs"><i class="fas fa-trash-alt mr-1"></i>Clear</button>
                </div>
            </div>
            <pre id="terminalOutput" class="h-56 overflow-y-auto whitespace-pre-wrap leading-relaxed select-text font-mono text-emerald-400">AMPNM Agent Diagnostic Shell v1.20 ready.
Click any diagnostic button above or enter a command to execute on <?= htmlspecialchars($hostname) ?>.</pre>
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

function deleteHost(hostIp, hostName) {
    if (!confirm(`Are you sure you want to delete host "${hostName || hostIp}"? This will delete all collected metrics, logs, and override configurations and redirect you to the main dashboard.`)) {
        return;
    }
    fetch('api.php?action=delete_host', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ host_ip: hostIp, host_name: hostName })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            alert('Host deleted successfully');
            window.location.href = 'host_metrics.php';
        } else {
            alert(result.error || 'Failed to delete host');
        }
    })
    .catch(e => {
        console.error('Failed to delete host:', e);
        alert('Failed to delete host');
    });
}

// Remote Agent Command Console Logic
(function() {
    const agentToken = <?= json_encode($agent_token_str) ?>;
    const termOutput = document.getElementById('terminalOutput');
    const termStatus = document.getElementById('terminalStatus');
    const termTitle = document.getElementById('terminalTitle');
    const customInput = document.getElementById('customCmdInput');
    const btnRunCustom = document.getElementById('btnRunCustom');
    const btnClear = document.getElementById('btnClearTerminal');

    if (!termOutput) return;

    if (btnClear) {
        btnClear.addEventListener('click', () => {
            termOutput.textContent = 'Terminal cleared. Ready for next command.\n';
        });
    }

    function executeCommand(type, payload) {
        termStatus.className = 'text-amber-400 font-semibold animate-pulse';
        termStatus.textContent = 'Queuing...';
        termTitle.textContent = `Running: ${type}`;
        
        appendTerminal(`\n> [${new Date().toLocaleTimeString()}] Dispatching command: ${type} ${payload ? `(${payload})` : ''}...`);

        fetch('api.php?action=queue_agent_command', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                agent_token: agentToken,
                command_type: type,
                command_payload: payload
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.command_id) {
                appendTerminal(`> Command queued (ID: #${data.command_id}). Waiting for agent execution...`);
                termStatus.textContent = 'Agent Executing...';
                pollCommandResult(data.command_id, 0);
            } else {
                termStatus.className = 'text-rose-400 font-semibold';
                termStatus.textContent = 'Failed';
                appendTerminal(`\n[ERROR] Could not queue command: ${data.error || 'Unknown error'}`);
            }
        })
        .catch(err => {
            termStatus.className = 'text-rose-400 font-semibold';
            termStatus.textContent = 'Network Error';
            appendTerminal(`\n[ERROR] Request failed: ${err.message}`);
        });
    }

    function pollCommandResult(cmdId, attempts) {
        if (attempts > 30) {
            termStatus.className = 'text-rose-400 font-semibold';
            termStatus.textContent = 'Timed Out';
            appendTerminal(`\n[TIMEOUT] Agent did not return results within 60 seconds.`);
            return;
        }

        setTimeout(() => {
            fetch(`api.php?action=get_agent_command&command_id=${encodeURIComponent(cmdId)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.command) {
                        const cmd = data.command;
                        if (cmd.status === 'completed') {
                            termStatus.className = 'text-emerald-400 font-semibold';
                            termStatus.textContent = `Completed (${cmd.execution_time_ms || 0}ms)`;
                            appendTerminal(`\n--- OUTPUT (Exit Code: ${cmd.exit_code || 0}) ---\n${cmd.result_output || '(No output returned)'}\n-----------------------------`);
                        } else if (cmd.status === 'failed') {
                            termStatus.className = 'text-rose-400 font-semibold';
                            termStatus.textContent = 'Failed';
                            appendTerminal(`\n[FAILED] Execution failed:\n${cmd.result_output || 'Unknown error'}`);
                        } else {
                            pollCommandResult(cmdId, attempts + 1);
                        }
                    } else {
                        pollCommandResult(cmdId, attempts + 1);
                    }
                })
                .catch(() => pollCommandResult(cmdId, attempts + 1));
        }, 2000);
    }

    function appendTerminal(text) {
        termOutput.textContent += text + '\n';
        termOutput.scrollTop = termOutput.scrollHeight;
    }

    // Direct Action Buttons
    document.querySelectorAll('.btn-agent-cmd').forEach(btn => {
        btn.addEventListener('click', function() {
            const cmd = this.getAttribute('data-cmd');
            const payload = this.getAttribute('data-payload') || '';
            executeCommand(cmd, payload);
        });
    });

    // Prompted Action Buttons
    document.querySelectorAll('.btn-agent-cmd-prompt').forEach(btn => {
        btn.addEventListener('click', function() {
            const cmd = this.getAttribute('data-cmd');
            const promptText = this.getAttribute('data-prompt') || 'Enter parameter:';
            const defaultVal = this.getAttribute('data-default') || '';
            const input = prompt(promptText, defaultVal);
            if (input !== null && input.trim() !== '') {
                executeCommand(cmd, input.trim());
            }
        });
    });

    // Custom Script Runner
    if (btnRunCustom && customInput) {
        btnRunCustom.addEventListener('click', () => {
            const val = customInput.value.trim();
            if (!val) {
                alert('Please enter a command to execute.');
                return;
            }
            executeCommand('custom_script', val);
        });
        customInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                btnRunCustom.click();
            }
        });
    }
})();
</script>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
