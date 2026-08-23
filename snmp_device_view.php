<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * SNMP Switch & Router Deep Interface / Port Traffic Dashboard
 */

require_once 'auth.php';
require_once 'db.php';
require_once 'includes/snmp_monitor.php';

$current_user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'viewer';
$current_user_group = $_SESSION['user_group'] ?? 'default_group';

// Group isolation
$stmtGroup = $pdo->prepare("SELECT id FROM users WHERE user_group = ?");
$stmtGroup->execute([$current_user_group]);
$groupUserIds = $stmtGroup->fetchAll(PDO::FETCH_COLUMN) ?: [$current_user_id];
$groupIdsStr = implode(',', array_map('intval', $groupUserIds));

$deviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($deviceId <= 0) {
    header('Location: devices.php');
    exit;
}

if ($user_role === 'admin') {
    $stmt = $pdo->prepare("SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ?");
    $stmt->execute([$deviceId]);
} else {
    $stmt = $pdo->prepare("SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ? AND d.user_id IN ($groupIdsStr)");
    $stmt->execute([$deviceId]);
}
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    die("Device not found or access denied.");
}

$pageTitle = "SNMP Port Dashboard - " . htmlspecialchars($device['name']);
include 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Top Header Bar -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6 bg-slate-800/80 backdrop-blur-md p-5 rounded-2xl border border-slate-700 shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-tr from-cyan-600 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-cyan-500/20 text-2xl">
                <i class="fas fa-server"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-black text-white"><?= htmlspecialchars($device['name']) ?></h1>
                    <span class="px-2.5 py-0.5 text-xs font-bold rounded-full <?= ($device['status'] ?? 'unknown') === 'online' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40' ?>">
                        <?= strtoupper($device['status'] ?? 'UNKNOWN') ?>
                    </span>
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-cyan-900/60 text-cyan-300 border border-cyan-700/50">
                        SNMP <?= strtoupper($device['snmp_version'] ?? 'v2c') ?>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-4 mt-1 text-xs text-slate-400">
                    <span><i class="fas fa-network-wired mr-1 text-cyan-400"></i> IP: <strong class="text-slate-200"><?= htmlspecialchars($device['ip'] ?? 'N/A') ?></strong></span>
                    <span><i class="fas fa-layer-group mr-1 text-cyan-400"></i> Type: <strong class="text-slate-200"><?= htmlspecialchars($device['type'] ?? 'switch') ?></strong></span>
                    <?php if (!empty($device['map_name'])): ?>
                        <span><i class="fas fa-map mr-1 text-cyan-400"></i> Map: <strong class="text-slate-200"><?= htmlspecialchars($device['map_name']) ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button id="btnPollNow" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-lg flex items-center gap-2">
                <i class="fas fa-sync-alt" id="pollIcon"></i>
                <span>Poll SNMP Now</span>
            </button>
            <a href="edit-device.php?id=<?= $deviceId ?>" class="px-3.5 py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-xl text-xs font-semibold transition-all">
                <i class="fas fa-cog mr-1"></i> Edit Device
            </a>
            <?php if (!empty($device['map_id'])): ?>
                <a href="map.php?map_id=<?= $device['map_id'] ?>" class="px-3.5 py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-xl text-xs font-semibold transition-all">
                    <i class="fas fa-map-marked-alt mr-1"></i> Return to Map
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hardware & Telemetry Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- CPU Card -->
        <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">CPU Utilization</span>
                <i class="fas fa-microchip text-cyan-400"></i>
            </div>
            <div class="text-2xl font-black text-white" id="statCpu">-- %</div>
            <div class="w-full bg-slate-700 rounded-full h-1.5 mt-2">
                <div id="barCpu" class="bg-cyan-500 h-1.5 rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>

        <!-- Total Inbound Traffic -->
        <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Inbound Bandwidth</span>
                <i class="fas fa-arrow-down text-emerald-400"></i>
            </div>
            <div class="text-2xl font-black text-emerald-400" id="statTotalIn">0.0 Mbps</div>
            <div class="text-[11px] text-slate-400 mt-1">Aggregated across all active ports</div>
        </div>

        <!-- Total Outbound Traffic -->
        <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Outbound Bandwidth</span>
                <i class="fas fa-arrow-up text-cyan-400"></i>
            </div>
            <div class="text-2xl font-black text-cyan-400" id="statTotalOut">0.0 Mbps</div>
            <div class="text-[11px] text-slate-400 mt-1">Aggregated across all active ports</div>
        </div>

        <!-- Active Ports Summary -->
        <div class="bg-slate-800/90 rounded-xl p-4 border border-slate-700/80 shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Port Matrix Status</span>
                <i class="fas fa-ethernet text-purple-400"></i>
            </div>
            <div class="text-2xl font-black text-white flex items-center gap-2">
                <span id="statActivePorts" class="text-emerald-400">0</span>
                <span class="text-slate-500 text-base font-normal">/</span>
                <span id="statTotalPorts" class="text-slate-400 text-lg">0 Ports</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-1" id="statUptime">Uptime: Checking...</div>
        </div>
    </div>

    <!-- System Description Banner -->
    <div id="sysDescrBanner" class="bg-slate-900/90 border border-slate-800 rounded-xl p-3.5 mb-6 text-xs font-mono text-slate-400 flex items-start gap-3">
        <i class="fas fa-info-circle text-cyan-400 text-sm mt-0.5"></i>
        <div class="flex-1 truncate">
            <span class="text-slate-300 font-bold" id="sysNameText"><?= htmlspecialchars($device['name']) ?></span>: 
            <span id="sysDescrText"><?= htmlspecialchars($device['snmp_sys_descr'] ?? 'Click "Poll SNMP Now" to discover interfaces and system description.') ?></span>
        </div>
        <span class="text-[10px] text-slate-500 whitespace-nowrap" id="lastPollBadge">Last Poll: <?= htmlspecialchars($device['snmp_last_poll'] ?? 'Never') ?></span>
    </div>

    <!-- Switch Hardware Frontpanel Port Matrix -->
    <div class="bg-slate-800/95 border border-slate-700 rounded-2xl p-5 shadow-2xl mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-700/70">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fas fa-server text-cyan-400"></i>
                    Switch Port Frontpanel & Live LED Matrix
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Click on any port jack to inspect real-time throughput and historical traffic chart.</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="flex items-center gap-1.5 text-slate-300"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/50"></span> Port Up (Traffic Active)</span>
                <span class="flex items-center gap-1.5 text-slate-300"><span class="w-2.5 h-2.5 rounded-full bg-slate-600"></span> Port Down</span>
                <span class="flex items-center gap-1.5 text-slate-300"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-sm shadow-amber-500/50"></span> Errors / Warning</span>
            </div>
        </div>

        <!-- Port Jack Grid View -->
        <div id="portMatrixGrid" class="flex flex-wrap gap-2.5 p-4 bg-slate-950/70 rounded-xl border border-slate-800 min-h-[120px] items-center justify-start">
            <div class="text-xs text-slate-500 w-full text-center py-6">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading switch port matrix...
            </div>
        </div>
    </div>

    <!-- Real-time Interface Traffic Chart -->
    <div class="bg-slate-800/95 border border-slate-700 rounded-2xl p-5 shadow-2xl mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-700/70">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fas fa-chart-area text-cyan-400"></i>
                    Live Bandwidth & Interface Traffic Flow
                </h2>
                <p class="text-xs text-slate-400 mt-0.5" id="activePortGraphLabel">Showing traffic timeline</p>
            </div>
            <div class="flex items-center gap-3">
                <label class="text-xs font-semibold text-slate-300">Selected Port:</label>
                <select id="interfaceSelect" class="bg-slate-900 border border-slate-700 text-white text-xs font-semibold rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-cyan-500">
                    <option value="0">All Interfaces Summary</option>
                </select>
            </div>
        </div>

        <div class="relative h-64 w-full">
            <canvas id="trafficChart"></canvas>
        </div>
    </div>

    <!-- Full Interfaces Table -->
    <div class="bg-slate-800/95 border border-slate-700 rounded-2xl p-5 shadow-2xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                <i class="fas fa-list-ol text-cyan-400"></i>
                Discovered Interfaces & Port Status Table
            </h2>
            <div class="flex items-center gap-3">
                <input type="text" id="filterInput" placeholder="Filter ports (e.g. ether1, Gi0/1)..." class="bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-cyan-500 w-64">
                <select id="statusFilter" class="bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-3 py-1.5">
                    <option value="all">All Status</option>
                    <option value="up">Up Only</option>
                    <option value="down">Down Only</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/80 uppercase text-[10px] text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="py-3 px-3">Port / Index</th>
                        <th class="py-3 px-3">Description / Alias</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3">Admin / Oper</th>
                        <th class="py-3 px-3">Speed Capacity</th>
                        <th class="py-3 px-3 text-emerald-400">Inbound Rate</th>
                        <th class="py-3 px-3 text-cyan-400">Outbound Rate</th>
                        <th class="py-3 px-3">Utilization</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="interfacesTableBody" class="divide-y divide-slate-700/60">
                    <tr>
                        <td colspan="9" class="py-6 text-center text-slate-500">Loading interfaces...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function() {
    const deviceId = <?= json_encode($deviceId) ?>;
    let interfacesData = [];
    let trafficChart = null;
    let selectedIfIndex = 0;
    let chartHistory = { labels: [], inRates: [], outRates: [] };

    // DOM Elements
    const btnPoll = document.getElementById('btnPollNow');
    const pollIcon = document.getElementById('pollIcon');
    const portMatrixGrid = document.getElementById('portMatrixGrid');
    const tableBody = document.getElementById('interfacesTableBody');
    const ifSelect = document.getElementById('interfaceSelect');
    const filterInput = document.getElementById('filterInput');
    const statusFilter = document.getElementById('statusFilter');

    // Initialize Chart
    const ctx = document.getElementById('trafficChart').getContext('2d');
    trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Inbound (Download) Mbps',
                    data: [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2
                },
                {
                    label: 'Outbound (Upload) Mbps',
                    data: [],
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6, 182, 212, 0.15)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: '#cbd5e1', font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2) + ' Mbps'; }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(51, 65, 85, 0.4)' },
                    ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 8 }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(51, 65, 85, 0.4)' },
                    ticks: { color: '#94a3b8', font: { size: 10 } }
                }
            }
        }
    });

    // Fetch initial interface data
    function loadInterfaces(autoPollIfEmpty = false) {
        fetch('api.php?action=get_snmp_interfaces&device_id=' + encodeURIComponent(deviceId))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.interfaces && data.interfaces.length > 0) {
                    interfacesData = data.interfaces;
                    renderAll();
                } else if (autoPollIfEmpty) {
                    triggerPoll();
                } else {
                    portMatrixGrid.innerHTML = `<div class="text-xs text-slate-400 w-full text-center py-6">No interfaces stored yet. Click <strong>"Poll SNMP Now"</strong> above.</div>`;
                    tableBody.innerHTML = `<tr><td colspan="9" class="py-6 text-center text-slate-500">No interface records found.</td></tr>`;
                }
            })
            .catch(() => {
                portMatrixGrid.innerHTML = `<div class="text-xs text-rose-400 w-full text-center py-6">Error loading interfaces.</div>`;
            });
    }

    // Trigger Poll Action
    function triggerPoll() {
        pollIcon.classList.add('fa-spin');
        btnPoll.disabled = true;

        fetch('api.php?action=poll_snmp&device_id=' + encodeURIComponent(deviceId))
            .then(r => r.json())
            .then(data => {
                pollIcon.classList.remove('fa-spin');
                btnPoll.disabled = false;
                if (data.success) {
                    interfacesData = data.interfaces || [];
                    if (data.system) {
                        document.getElementById('sysDescrText').textContent = data.system.sys_descr || 'Online';
                        if (data.system.sys_name) document.getElementById('sysNameText').textContent = data.system.sys_name;
                        if (data.system.sys_uptime) document.getElementById('statUptime').textContent = 'Uptime: ' + data.system.sys_uptime;
                        if (data.system.cpu_percent !== null && data.system.cpu_percent !== undefined) {
                            document.getElementById('statCpu').textContent = data.system.cpu_percent + '%';
                            document.getElementById('barCpu').style.width = Math.min(100, data.system.cpu_percent) + '%';
                        }
                    }
                    if (data.last_poll) {
                        document.getElementById('lastPollBadge').textContent = 'Last Poll: ' + data.last_poll;
                    }
                    renderAll();
                    updateChartWithLatest();
                } else {
                    alert('SNMP Poll failed: ' + (data.error || 'Timeout'));
                }
            })
            .catch(err => {
                pollIcon.classList.remove('fa-spin');
                btnPoll.disabled = false;
                alert('Network error while polling SNMP.');
            });
    }

    btnPoll.addEventListener('click', triggerPoll);

    function renderAll() {
        renderPortMatrix();
        renderTable();
        populateSelect();
        updateSummaryStats();
    }

    function updateSummaryStats() {
        let totalIn = 0;
        let totalOut = 0;
        let activeCount = 0;

        interfacesData.forEach(iface => {
            totalIn += parseFloat(iface.in_rate_bps || 0);
            totalOut += parseFloat(iface.out_rate_bps || 0);
            if ((iface.if_oper_status || '').toLowerCase() === 'up') activeCount++;
        });

        document.getElementById('statTotalIn').textContent = (totalIn / 1000000).toFixed(2) + ' Mbps';
        document.getElementById('statTotalOut').textContent = (totalOut / 1000000).toFixed(2) + ' Mbps';
        document.getElementById('statActivePorts').textContent = activeCount;
        document.getElementById('statTotalPorts').textContent = interfacesData.length + ' Ports';
    }

    function renderPortMatrix() {
        if (!interfacesData.length) {
            portMatrixGrid.innerHTML = '<div class="text-xs text-slate-500 w-full text-center py-6">No ports discovered.</div>';
            return;
        }

        portMatrixGrid.innerHTML = '';
        interfacesData.forEach(iface => {
            const isUp = (iface.if_oper_status || '').toLowerCase() === 'up';
            const hasErrors = parseInt(iface.if_in_errors || 0) > 0 || parseInt(iface.if_out_errors || 0) > 0;
            const inMbps = (parseFloat(iface.in_rate_bps || 0) / 1000000).toFixed(2);
            const outMbps = (parseFloat(iface.out_rate_bps || 0) / 1000000).toFixed(2);

            let ledColor = 'bg-slate-600';
            if (isUp) ledColor = hasErrors ? 'bg-amber-400 shadow-sm shadow-amber-400/50' : 'bg-emerald-400 shadow-sm shadow-emerald-400/50 animate-pulse';

            const card = document.createElement('div');
            card.className = `port-matrix-card flex flex-col items-center justify-between p-2.5 bg-slate-900 border ${selectedIfIndex === iface.if_index ? 'border-cyan-400 ring-2 ring-cyan-500/30' : 'border-slate-800 hover:border-slate-600'} rounded-xl cursor-pointer transition-all duration-200 w-24 h-24 select-none`;
            card.setAttribute('title', `${iface.if_descr} (${iface.if_alias || 'No alias'})\nStatus: ${iface.if_oper_status}\nIn: ${inMbps} Mbps | Out: ${outMbps} Mbps`);

            card.innerHTML = `
                <div class="flex items-center justify-between w-full">
                    <span class="w-2 h-2 rounded-full ${ledColor}"></span>
                    <span class="text-[9px] font-bold text-slate-400">#${iface.if_index}</span>
                </div>
                <div class="text-center my-1">
                    <i class="fas fa-ethernet text-xl ${isUp ? 'text-cyan-400' : 'text-slate-600'}"></i>
                    <div class="text-[10px] font-extrabold text-white truncate max-w-[70px]">${escapeHtml(iface.if_descr)}</div>
                </div>
                <div class="text-[8px] font-mono text-slate-400 truncate w-full text-center">
                    ${isUp ? `${inMbps}M` : 'Down'}
                </div>
            `;

            card.addEventListener('click', () => {
                selectedIfIndex = iface.if_index;
                ifSelect.value = iface.if_index;
                renderPortMatrix();
                document.getElementById('activePortGraphLabel').textContent = `Showing live traffic for port: ${iface.if_descr} (${iface.if_alias || 'No alias'})`;
                loadPortHistory(iface.if_index);
            });

            portMatrixGrid.appendChild(card);
        });
    }

    function renderTable() {
        const query = (filterInput.value || '').toLowerCase();
        const statusVal = statusFilter.value;

        const filtered = interfacesData.filter(i => {
            const matchQuery = (i.if_descr || '').toLowerCase().includes(query) || (i.if_alias || '').toLowerCase().includes(query);
            if (!matchQuery) return false;
            if (statusVal === 'up') return (i.if_oper_status || '').toLowerCase() === 'up';
            if (statusVal === 'down') return (i.if_oper_status || '').toLowerCase() !== 'up';
            return true;
        });

        if (!filtered.length) {
            tableBody.innerHTML = `<tr><td colspan="9" class="py-6 text-center text-slate-500">No matching interfaces found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = '';
        filtered.forEach(iface => {
            const isUp = (iface.if_oper_status || '').toLowerCase() === 'up';
            const inFormatted = iface.in_rate_formatted || ((parseFloat(iface.in_rate_bps || 0) / 1000000).toFixed(2) + ' Mbps');
            const outFormatted = iface.out_rate_formatted || ((parseFloat(iface.out_rate_bps || 0) / 1000000).toFixed(2) + ' Mbps');
            const speedMbps = iface.if_speed ? (parseInt(iface.if_speed) >= 1000000000 ? (parseInt(iface.if_speed)/1000000000) + ' Gbps' : (parseInt(iface.if_speed)/1000000) + ' Mbps') : 'N/A';

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-700/30 transition-colors';
            tr.innerHTML = `
                <td class="py-3 px-3 font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full ${isUp ? 'bg-emerald-400' : 'bg-slate-600'}"></span>
                    <span>${escapeHtml(iface.if_descr)}</span>
                </td>
                <td class="py-3 px-3 text-slate-300">${escapeHtml(iface.if_alias || '--')}</td>
                <td class="py-3 px-3 text-slate-400 font-mono">${escapeHtml(iface.if_type || 'ethernet')}</td>
                <td class="py-3 px-3">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold ${isUp ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-700/50' : 'bg-slate-800 text-slate-400'}">
                        ${escapeHtml(iface.if_oper_status || 'unknown')}
                    </span>
                </td>
                <td class="py-3 px-3 font-mono text-slate-300">${speedMbps}</td>
                <td class="py-3 px-3 font-mono font-bold text-emerald-400">${inFormatted}</td>
                <td class="py-3 px-3 font-mono font-bold text-cyan-400">${outFormatted}</td>
                <td class="py-3 px-3">
                    <div class="w-16 bg-slate-700 rounded-full h-1.5">
                        <div class="bg-cyan-500 h-1.5 rounded-full" style="width: ${Math.min(100, iface.utilization_percent || 0)}%"></div>
                    </div>
                </td>
                <td class="py-3 px-3 text-center">
                    <button class="px-2.5 py-1 bg-slate-700 hover:bg-cyan-600 text-white rounded text-[10px] font-bold transition-all btn-inspect-port" data-index="${iface.if_index}">
                        Graph &rarr;
                    </button>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        tableBody.querySelectorAll('.btn-inspect-port').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                selectedIfIndex = idx;
                ifSelect.value = idx;
                renderPortMatrix();
                loadPortHistory(idx);
                window.scrollTo({ top: document.getElementById('trafficChart').offsetTop - 100, behavior: 'smooth' });
            });
        });
    }

    function populateSelect() {
        const currentVal = ifSelect.value;
        ifSelect.innerHTML = '<option value="0">All Interfaces Summary</option>';
        interfacesData.forEach(iface => {
            const opt = document.createElement('option');
            opt.value = iface.if_index;
            opt.textContent = `${iface.if_descr} ${iface.if_alias ? '— ' + iface.if_alias : ''}`;
            ifSelect.appendChild(opt);
        });
        ifSelect.value = currentVal;
    }

    ifSelect.addEventListener('change', function() {
        selectedIfIndex = parseInt(this.value);
        renderPortMatrix();
        loadPortHistory(selectedIfIndex);
    });

    filterInput.addEventListener('input', renderTable);
    statusFilter.addEventListener('change', renderTable);

    function updateChartWithLatest() {
        const nowTime = new Date().toLocaleTimeString();
        let currentIn = 0;
        let currentOut = 0;

        if (selectedIfIndex === 0) {
            interfacesData.forEach(i => {
                currentIn += parseFloat(i.in_rate_bps || 0) / 1000000;
                currentOut += parseFloat(i.out_rate_bps || 0) / 1000000;
            });
        } else {
            const iface = interfacesData.find(i => i.if_index === selectedIfIndex);
            if (iface) {
                currentIn = parseFloat(iface.in_rate_bps || 0) / 1000000;
                currentOut = parseFloat(iface.out_rate_bps || 0) / 1000000;
            }
        }

        trafficChart.data.labels.push(nowTime);
        trafficChart.data.datasets[0].data.push(currentIn);
        trafficChart.data.datasets[1].data.push(currentOut);

        if (trafficChart.data.labels.length > 25) {
            trafficChart.data.labels.shift();
            trafficChart.data.datasets[0].data.shift();
            trafficChart.data.datasets[1].data.shift();
        }

        trafficChart.update('none');
    }

    function loadPortHistory(ifIndex) {
        fetch(`api.php?action=get_snmp_history&device_id=${encodeURIComponent(deviceId)}&if_index=${encodeURIComponent(ifIndex)}&limit=30`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.history) {
                    const labels = [];
                    const inRates = [];
                    const outRates = [];
                    data.history.forEach(h => {
                        labels.push(new Date(h.created_at).toLocaleTimeString());
                        inRates.push(parseFloat(h.in_rate_bps || 0) / 1000000);
                        outRates.push(parseFloat(h.out_rate_bps || 0) / 1000000);
                    });
                    trafficChart.data.labels = labels;
                    trafficChart.data.datasets[0].data = inRates;
                    trafficChart.data.datasets[1].data = outRates;
                    trafficChart.update();
                }
            })
            .catch(() => {});
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }

    // Initialize
    loadInterfaces(true);
    // Auto-poll every 30 seconds
    setInterval(triggerPoll, 30000);
})();
</script>

<?php include 'footer.php'; ?>
