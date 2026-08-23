<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Advanced Network Subnet & IP Auto-Discovery Dashboard
 */
require_once 'includes/auth_check.php';
require_once 'header.php';

$pdo = getDbConnection();
$userId = $_SESSION['user_id'] ?? 1;

// Fetch maps for import target dropdown
$stmt = $pdo->prepare("SELECT id, name FROM maps WHERE user_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$userId]);
$maps = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Guess local server subnet
$serverIp = $_SERVER['SERVER_ADDR'] ?? '192.168.1.1';
$defaultSubnet = '192.168.1.0/24';
if (filter_var($serverIp, FILTER_VALIDATE_IP)) {
    $parts = explode('.', $serverIp);
    if (count($parts) === 4) {
        $defaultSubnet = "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
    }
}
?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-white flex items-center gap-3">
                <i class="fas fa-radar text-cyan-400"></i>
                Network Auto-Discovery Scanner
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Fast multi-socket subnet sweep, hardware OUI vendor resolution, port probing, and 1-click topology import.
            </p>
        </div>
    </div>

    <!-- Scanner Control Bar -->
    <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-6 mb-8 shadow-xl">
        <form id="scannerForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-8">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
                        Target Subnet (CIDR) or IP Range
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-network-wired text-sm"></i>
                        </div>
                        <input type="text" id="targetSubnet" value="<?= htmlspecialchars($defaultSubnet) ?>" placeholder="e.g. 192.168.1.0/24 or 10.0.0.1-50" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl pl-10 pr-4 py-3 text-sm font-mono focus:ring-2 focus:ring-cyan-500 focus:outline-none shadow-inner" required>
                    </div>
                </div>

                <div class="md:col-span-4 flex gap-2">
                    <button type="submit" id="btnStartScan" class="flex-1 px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition shadow-lg shadow-cyan-500/25">
                        <i class="fas fa-search" id="scanIcon"></i>
                        <span id="scanBtnText">Start Network Scan</span>
                    </button>
                </div>
            </div>

            <!-- Quick Subnet Presets -->
            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-700/60 text-xs">
                <span class="text-slate-400 font-semibold mr-1">Quick Presets:</span>
                <button type="button" class="preset-btn px-2.5 py-1 bg-slate-700/60 hover:bg-cyan-600/30 hover:border-cyan-500/50 border border-slate-600 rounded-lg text-slate-300 transition" data-target="192.168.1.0/24">192.168.1.0/24</button>
                <button type="button" class="preset-btn px-2.5 py-1 bg-slate-700/60 hover:bg-cyan-600/30 hover:border-cyan-500/50 border border-slate-600 rounded-lg text-slate-300 transition" data-target="192.168.0.0/24">192.168.0.0/24</button>
                <button type="button" class="preset-btn px-2.5 py-1 bg-slate-700/60 hover:bg-cyan-600/30 hover:border-cyan-500/50 border border-slate-600 rounded-lg text-slate-300 transition" data-target="10.0.0.0/24">10.0.0.0/24</button>
                <button type="button" class="preset-btn px-2.5 py-1 bg-slate-700/60 hover:bg-cyan-600/30 hover:border-cyan-500/50 border border-slate-600 rounded-lg text-slate-300 transition" data-target="172.16.0.0/24">172.16.0.0/24</button>
                <button type="button" class="preset-btn px-2.5 py-1 bg-slate-700/60 hover:bg-cyan-600/30 hover:border-cyan-500/50 border border-slate-600 rounded-lg text-slate-300 transition" data-target="192.168.9.0/24">192.168.9.0/24</button>
            </div>
        </form>
    </div>

    <!-- Scan Metrics Stat Bar (Visible after scan) -->
    <div id="statsBar" class="hidden grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium">Scanned Addresses</span>
            <p class="text-2xl font-extrabold text-white mt-1" id="statScanned">0</p>
        </div>
        <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium">Live Hosts Found</span>
            <p class="text-2xl font-extrabold text-emerald-400 mt-1" id="statDiscovered">0</p>
        </div>
        <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium">Routers & Switches</span>
            <p class="text-2xl font-extrabold text-cyan-400 mt-1" id="statNetworkGear">0</p>
        </div>
        <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium">Servers & Endpoints</span>
            <p class="text-2xl font-extrabold text-purple-400 mt-1" id="statServers">0</p>
        </div>
    </div>

    <!-- Scan Results Card -->
    <div class="bg-slate-800/90 border border-slate-700 rounded-2xl shadow-xl overflow-hidden">
        <div class="p-5 border-b border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-list-check text-cyan-400"></i>
                    Discovered Network Devices
                </h3>
                <span id="resultCountBadge" class="hidden px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">
                    0 Found
                </span>
            </div>

            <!-- Import Action Bar -->
            <div id="importControls" class="hidden flex flex-wrap items-center gap-3">
                <label class="text-xs text-slate-400 font-medium">Add to Map:</label>
                <select id="importMapSelect" class="bg-slate-900 border border-slate-700 text-white text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                    <option value="0">Do not place on map (Inventory only)</option>
                    <?php foreach ($maps as $map): ?>
                        <option value="<?= (int)$map['id'] ?>"><?= htmlspecialchars($map['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="btnImportSelected" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition shadow-lg shadow-emerald-600/20">
                    <i class="fas fa-cloud-arrow-down"></i>
                    <span>Import Selected (<span id="selectedCount">0</span>)</span>
                </button>
            </div>
        </div>

        <!-- Discovered Device Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-900/60 border-b border-slate-700/80 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-10 text-center">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded bg-slate-800 border-slate-600 text-cyan-500 focus:ring-cyan-500">
                        </th>
                        <th class="py-3.5 px-4">Device IP & Name</th>
                        <th class="py-3.5 px-4">Device Type</th>
                        <th class="py-3.5 px-4">MAC & Hardware Vendor</th>
                        <th class="py-3.5 px-4">Discovered Open Ports</th>
                        <th class="py-3.5 px-4">Status</th>
                    </tr>
                </thead>
                <tbody id="devicesTableBody" class="divide-y divide-slate-700/60 text-slate-200">
                    <tr id="emptyRow">
                        <td colspan="6" class="py-14 text-center text-slate-500">
                            <i class="fas fa-radar text-4xl mb-3 block text-slate-600"></i>
                            Ready to scan. Enter your subnet above and click <strong>"Start Network Scan"</strong>.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let discoveredDevices = [];

// Preset Click Handlers
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('targetSubnet').value = this.getAttribute('data-target');
    });
});

// Scanner Execution
document.getElementById('scannerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const subnet = document.getElementById('targetSubnet').value.trim();
    if (!subnet) return;

    const btn = document.getElementById('btnStartScan');
    const icon = document.getElementById('scanIcon');
    const btnText = document.getElementById('scanBtnText');
    const tbody = document.getElementById('devicesTableBody');

    btn.disabled = true;
    icon.className = 'fas fa-spinner fa-spin';
    btnText.textContent = 'Scanning Subnet...';
    tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-cyan-400 font-medium animate-pulse"><i class="fas fa-satellite-dish text-3xl mb-3 block"></i>Sweeping ${subnet} for live hosts and probing open service ports...</td></tr>`;

    try {
        const res = await fetch('api.php?action=scan_network', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subnet })
        });
        const data = await res.json();

        if (data.success && data.devices) {
            discoveredDevices = data.devices;
            renderDiscoveredDevices(data.devices);
            
            // Update Stat Bar
            document.getElementById('statsBar').classList.remove('hidden');
            document.getElementById('statScanned').textContent = data.scanned_count || data.devices.length;
            document.getElementById('statDiscovered').textContent = data.discovered_count || data.devices.length;
            
            let netGear = 0;
            let servers = 0;
            data.devices.forEach(d => {
                if (d.device_type === 'router' || d.device_type === 'switch') netGear++;
                else if (d.device_type === 'server' || d.device_type === 'generic') servers++;
            });
            document.getElementById('statNetworkGear').textContent = netGear;
            document.getElementById('statServers').textContent = servers;

            // Show import bar
            document.getElementById('importControls').classList.remove('hidden');
            document.getElementById('resultCountBadge').classList.remove('hidden');
            document.getElementById('resultCountBadge').textContent = `${data.devices.length} Discovered`;
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-rose-400"><i class="fas fa-exclamation-circle text-3xl mb-3 block"></i>Scan failed: ${data.error || 'No response from scanner'}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-rose-400"><i class="fas fa-exclamation-circle text-3xl mb-3 block"></i>Network Error: ${err.message}</td></tr>`;
    } finally {
        btn.disabled = false;
        icon.className = 'fas fa-search';
        btnText.textContent = 'Start Network Scan';
    }
});

function renderDiscoveredDevices(devices) {
    const tbody = document.getElementById('devicesTableBody');
    if (!devices || devices.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-500"><i class="fas fa-ban text-3xl mb-3 block"></i>No live devices detected in the specified subnet range.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    devices.forEach((d, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-700/30 transition';

        // Ports badges
        let portsHtml = '<span class="text-xs text-slate-500">—</span>';
        if (d.open_ports && d.open_ports.length > 0) {
            portsHtml = d.open_ports.map(p => {
                let name = p;
                let color = 'bg-slate-700 text-slate-300';
                if (p === 80 || p === 443) { name = `${p} HTTP`; color = 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'; }
                else if (p === 22) { name = '22 SSH'; color = 'bg-purple-500/20 text-purple-300 border border-purple-500/30'; }
                else if (p === 3389) { name = '3389 RDP'; color = 'bg-blue-500/20 text-blue-300 border border-blue-500/30'; }
                else if (p === 8291) { name = '8291 Winbox'; color = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold'; }
                else if (p === 161) { name = '161 SNMP'; color = 'bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold'; }
                else if (p === 554 || p === 8000) { name = `${p} RTSP`; color = 'bg-rose-500/20 text-rose-300 border border-rose-500/30'; }
                return `<span class="px-2 py-0.5 rounded text-[10px] font-mono ${color}">${name}</span>`;
            }).join(' ');
        }

        // Type badge
        let typeBadge = `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize bg-slate-700 text-slate-300">${d.device_type}</span>`;
        if (d.device_type === 'router') typeBadge = `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize bg-cyan-500/20 text-cyan-400 border border-cyan-500/30"><i class="fas fa-network-wired mr-1"></i>Router</span>`;
        else if (d.device_type === 'switch') typeBadge = `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize bg-indigo-500/20 text-indigo-400 border border-indigo-500/30"><i class="fas fa-diagram-project mr-1"></i>Switch</span>`;
        else if (d.device_type === 'camera') typeBadge = `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize bg-rose-500/20 text-rose-400 border border-rose-500/30"><i class="fas fa-video mr-1"></i>Camera</span>`;
        else if (d.device_type === 'printer') typeBadge = `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize bg-amber-500/20 text-amber-400 border border-amber-500/30"><i class="fas fa-print mr-1"></i>Printer</span>`;
        else if (d.device_type === 'server') typeBadge = `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"><i class="fas fa-server mr-1"></i>Server</span>`;

        tr.innerHTML = `
            <td class="py-4 px-4 text-center">
                <input type="checkbox" class="device-checkbox rounded bg-slate-800 border-slate-600 text-cyan-500 focus:ring-cyan-500" data-index="${idx}" checked>
            </td>
            <td class="py-4 px-4">
                <div class="font-mono font-bold text-white">${d.ip}</div>
                <div class="text-xs text-slate-400 truncate max-w-xs">${d.hostname || d.device_name || 'No Hostname'}</div>
            </td>
            <td class="py-4 px-4">
                ${typeBadge}
            </td>
            <td class="py-4 px-4">
                <div class="font-mono text-xs text-slate-300">${d.mac || '—'}</div>
                <div class="text-[11px] text-cyan-400 font-semibold">${d.vendor || 'Unknown Vendor'}</div>
            </td>
            <td class="py-4 px-4">
                <div class="flex flex-wrap gap-1">${portsHtml}</div>
            </td>
            <td class="py-4 px-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5 w-max">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Online
                </span>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateSelectedCount();
}

// Checkbox Logic
document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    const isChecked = this.checked;
    document.querySelectorAll('.device-checkbox').forEach(cb => cb.checked = isChecked);
    updateSelectedCount();
});

document.getElementById('devicesTableBody').addEventListener('change', function(e) {
    if (e.target.classList.contains('device-checkbox')) {
        updateSelectedCount();
    }
});

function updateSelectedCount() {
    const selected = document.querySelectorAll('.device-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selected;
}

// Bulk Import
document.getElementById('btnImportSelected').addEventListener('click', async function() {
    const selectedBoxes = document.querySelectorAll('.device-checkbox:checked');
    if (selectedBoxes.length === 0) {
        alert('Please select at least one device to import.');
        return;
    }

    const selectedDevices = [];
    selectedBoxes.forEach(cb => {
        const idx = parseInt(cb.getAttribute('data-index'), 10);
        if (discoveredDevices[idx]) {
            selectedDevices.push(discoveredDevices[idx]);
        }
    });

    const mapId = parseInt(document.getElementById('importMapSelect').value, 10) || 0;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Importing...';

    try {
        const res = await fetch('api.php?action=bulk_import_scanned_devices', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ devices: selectedDevices, map_id: mapId })
        });
        const data = await res.json();
        if (data.success) {
            alert(`Success: ${data.message}`);
            if (mapId > 0) {
                window.location.href = `map.php?id=${mapId}`;
            } else {
                window.location.href = 'devices.php';
            }
        } else {
            alert(data.error || 'Failed to import devices');
        }
    } catch (err) {
        alert('Import Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-arrow-down mr-1"></i> Import Selected (<span id="selectedCount">' + selectedDevices.length + '</span>)';
    }
});
</script>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
