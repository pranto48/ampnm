<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM IP Address Management (IPAM) & Subnet Planner
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

$pdo = getDbConnection();
$devices = $pdo->query("SELECT id, name, ip, type, status FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-network-wired text-cyan-400"></i> IP Address Management (IPAM)
            </h1>
            <p class="text-slate-400 text-sm mt-1">Subnet planning, CIDR visualizer, live IP allocation grid, and conflict detection.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openNewSubnetModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Subnet
            </button>
        </div>
    </div>

    <!-- IPAM Summary Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-sitemap"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Subnets Tracked</span>
                <h3 class="text-xl font-bold text-white" id="stat-total-subnets">0</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400 text-xl">
                <i class="fas fa-desktop"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Allocated / Used IPs</span>
                <h3 class="text-xl font-bold text-white" id="stat-used-ips">0</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Free Available IPs</span>
                <h3 class="text-xl font-bold text-white" id="stat-free-ips">0</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Avg Utilization</span>
                <h3 class="text-xl font-bold text-white" id="stat-utilization">0%</h3>
            </div>
        </div>
    </div>

    <!-- Main IPAM Layout: Left Subnets List, Right IP Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Subnets List Panel (4 Cols) -->
        <div class="lg:col-span-4 bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl flex flex-col h-[750px]">
            <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-layer-group text-cyan-400"></i> Managed Subnets
                </h2>
                <span class="text-xs text-slate-400" id="subnet-count-badge">0 Subnets</span>
            </div>

            <div id="subnets-list" class="flex-1 overflow-y-auto space-y-3 pr-1">
                <div class="text-center py-10 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading subnets...</div>
            </div>
        </div>

        <!-- IP Address Matrix Visualizer (8 Cols) -->
        <div class="lg:col-span-8 bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl flex flex-col h-[750px]">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 border-b border-slate-700/60 pb-3">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2" id="current-subnet-title">
                        <i class="fas fa-th text-cyan-400"></i> IP Allocation Grid
                    </h2>
                    <span class="text-xs text-slate-400 font-mono" id="current-subnet-meta">Select a subnet from the left to view live IP addresses.</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="btn-scan-subnet" onclick="triggerSubnetScan()" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5">
                        <i class="fas fa-radar"></i> Sweep &amp; Sync
                    </button>
                </div>
            </div>

            <!-- Legend Bar -->
            <div class="flex flex-wrap items-center gap-4 text-xs mb-4 bg-slate-900/60 p-2.5 rounded-lg border border-slate-800">
                <span class="text-slate-400 font-semibold">Legend:</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500"></span> Free</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-500"></span> Allocated</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-500"></span> Gateway</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500"></span> Reserved/DHCP</span>
            </div>

            <!-- IP Grid Canvas Container -->
            <div id="ip-grid-container" class="flex-1 overflow-y-auto pr-1">
                <div class="text-center py-20 text-slate-500">
                    <i class="fas fa-arrow-left mr-2"></i>Select a subnet from the left to explore its IP matrix.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add New Subnet -->
<div id="new-subnet-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-plus-circle text-cyan-400"></i> Add IPAM Subnet
            </h3>
            <button type="button" onclick="closeNewSubnetModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="new-subnet-form" onsubmit="submitNewSubnet(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Subnet Name</label>
                <input type="text" id="sub-name" required placeholder="e.g. Server Room VLAN" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">CIDR Prefix</label>
                    <input type="text" id="sub-cidr" required placeholder="192.168.1.0/24" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Gateway IP</label>
                    <input type="text" id="sub-gateway" placeholder="192.168.1.1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">VLAN ID (Optional)</label>
                    <input type="number" id="sub-vlan" placeholder="10" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Description</label>
                    <input type="text" id="sub-desc" placeholder="Core distribution" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeNewSubnetModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Create Subnet</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Assign / Edit IP Address -->
<div id="edit-ip-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-edit text-cyan-400"></i> Manage IP Address
                </h3>
                <span class="text-xs text-cyan-400 font-mono" id="edit-ip-display"></span>
            </div>
            <button type="button" onclick="closeEditIpModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="edit-ip-form" onsubmit="submitEditIp(event)" class="space-y-3 text-xs">
            <input type="hidden" id="edit-ip-val">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Allocation Status</label>
                <select id="edit-ip-status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="free">🟢 Free (Available)</option>
                    <option value="allocated">🔴 Allocated (Active Device)</option>
                    <option value="reserved">🟡 Reserved Static</option>
                    <option value="gateway">🔵 Gateway</option>
                    <option value="dhcp">🟠 DHCP Dynamic Pool</option>
                </select>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Hostname / DNS</label>
                <input type="text" id="edit-ip-hostname" placeholder="e.g. srv-app-01.local" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">MAC Address</label>
                <input type="text" id="edit-ip-mac" placeholder="AA:BB:CC:DD:EE:FF" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-cyan-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Link to Monitored Device</label>
                <select id="edit-ip-device" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">None / Unmanaged</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Notes</label>
                <input type="text" id="edit-ip-notes" placeholder="e.g. Production Web Server NIC 1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeEditIpModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Save IP Details</button>
            </div>
        </form>
    </div>
</div>

<script>
let subnets = [];
let activeSubnetId = null;
let activeSubnetData = null;

async function loadSubnets() {
    try {
        const resp = await fetch('api.php?action=get_ipam_subnets');
        const data = await resp.json();
        subnets = data.subnets || [];
        
        let totalUsed = 0;
        let totalFree = 0;
        
        subnets.forEach(s => {
            const used = parseInt(s.used_ips || 0);
            const total = 254; // default estimate for /24
            totalUsed += used;
            totalFree += Math.max(0, total - used);
        });

        document.getElementById('stat-total-subnets').textContent = subnets.length;
        document.getElementById('stat-used-ips').textContent = totalUsed;
        document.getElementById('stat-free-ips').textContent = totalFree;
        document.getElementById('stat-utilization').textContent = totalUsed + totalFree > 0 
            ? Math.round((totalUsed / (totalUsed + totalFree)) * 100) + '%' : '0%';
        document.getElementById('subnet-count-badge').textContent = `${subnets.length} Subnets`;

        renderSubnetsList();

        if (subnets.length > 0 && !activeSubnetId) {
            selectSubnet(subnets[0].id);
        }
    } catch (e) {
        console.error('Error loading IPAM subnets', e);
    }
}

function renderSubnetsList() {
    const container = document.getElementById('subnets-list');
    if (subnets.length === 0) {
        container.innerHTML = `<div class="text-center py-10 text-slate-500">No subnets configured. Click "+ Add Subnet" to create one.</div>`;
        return;
    }

    container.innerHTML = subnets.map(s => {
        const isSelected = s.id === activeSubnetId;
        const used = parseInt(s.used_ips || 0);
        const total = 254;
        const pct = Math.min(100, Math.round((used / total) * 100));

        return `
            <div onclick="selectSubnet('${s.id}')" class="p-3.5 rounded-xl border transition-all cursor-pointer ${isSelected ? 'bg-cyan-950/40 border-cyan-500 shadow-md' : 'bg-slate-900/60 border-slate-700/60 hover:border-slate-600'}">
                <div class="flex items-center justify-between mb-1.5">
                    <h4 class="text-sm font-bold text-white">${s.name}</h4>
                    <span class="text-xs font-mono text-cyan-400 font-semibold">${s.cidr}</span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-400 mb-2 font-mono">
                    <span>GW: ${s.gateway_ip || 'N/A'}</span>
                    <span>VLAN: ${s.vlan_id ? '#' + s.vlan_id : 'Default'}</span>
                </div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-cyan-500 to-indigo-500 h-full rounded-full" style="width: ${pct}%"></div>
                </div>
                <div class="flex justify-between text-[11px] text-slate-400 mt-1">
                    <span>${used} Used</span>
                    <span>${pct}% Utilized</span>
                </div>
            </div>
        `;
    }).join('');
}

async function selectSubnet(subnetId) {
    activeSubnetId = subnetId;
    renderSubnetsList();

    const container = document.getElementById('ip-grid-container');
    container.innerHTML = `<div class="text-center py-20 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading IP allocation matrix...</div>`;

    try {
        const resp = await fetch(`api.php?action=get_ipam_subnet_ips&subnet_id=${encodeURIComponent(subnetId)}`);
        const data = await resp.json();
        if (!data.success) return;

        activeSubnetData = data;
        document.getElementById('current-subnet-title').innerHTML = `<i class="fas fa-th text-cyan-400"></i> ${data.subnet.name} (${data.subnet.cidr})`;
        document.getElementById('current-subnet-meta').textContent = `Gateway: ${data.subnet.gateway_ip || 'None'} | VLAN: ${data.subnet.vlan_id || 'Default'} | ${data.ips.length} tracked IP(s)`;

        renderIpGrid(data.subnet, data.ips);
    } catch (e) {
        container.innerHTML = `<div class="text-center py-20 text-red-400">Failed to load IP grid: ${e.message}</div>`;
    }
}

function renderIpGrid(subnet, trackedIps) {
    const container = document.getElementById('ip-grid-container');
    const trackedMap = {};
    trackedIps.forEach(ip => { trackedMap[ip.ip_address] = ip; });

    // Derive base IP and range from CIDR
    const baseParts = subnet.cidr.split('/')[0].split('.');
    const prefix = `${baseParts[0]}.${baseParts[1]}.${baseParts[2]}`;

    let gridHtml = `<div class="grid grid-cols-8 sm:grid-cols-12 md:grid-cols-16 gap-2">`;

    for (let i = 1; i <= 254; i++) {
        const currentIp = `${prefix}.${i}`;
        const info = trackedMap[currentIp];
        const status = info ? info.status : 'free';
        
        let colorClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30 hover:bg-emerald-500/40';
        let icon = '';

        if (status === 'allocated') {
            colorClass = 'bg-red-500/20 text-red-300 border-red-500/40 hover:bg-red-500/40';
        } else if (status === 'gateway') {
            colorClass = 'bg-blue-500/30 text-blue-200 border-blue-500/50 hover:bg-blue-500/50 font-bold';
            icon = '<i class="fas fa-crown text-[9px] mr-0.5"></i>';
        } else if (status === 'reserved' || status === 'dhcp') {
            colorClass = 'bg-amber-500/20 text-amber-300 border-amber-500/40 hover:bg-amber-500/40';
        }

        const tooltip = info 
            ? `${currentIp}\nStatus: ${status}\nHost: ${info.hostname || 'N/A'}\nDevice: ${info.linked_device_name || 'N/A'}` 
            : `${currentIp}\nStatus: Free (Unallocated)`;

        gridHtml += `
            <div onclick="openEditIpModal('${currentIp}', '${status}', '${info ? (info.hostname || '') : ''}', '${info ? (info.mac_address || '') : ''}', '${info ? (info.device_id || '') : ''}', '${info ? (info.notes || '') : ''}')" 
                 title="${tooltip}" 
                 class="h-10 rounded-lg border flex flex-col items-center justify-center font-mono text-xs cursor-pointer transition-all ${colorClass}">
                <span>${icon}.${i}</span>
            </div>
        `;
    }

    gridHtml += `</div>`;
    container.innerHTML = gridHtml;
}

function openNewSubnetModal() {
    document.getElementById('new-subnet-modal').classList.remove('hidden');
}

function closeNewSubnetModal() {
    document.getElementById('new-subnet-modal').classList.add('hidden');
}

async function submitNewSubnet(e) {
    e.preventDefault();
    const name = document.getElementById('sub-name').value;
    const cidr = document.getElementById('sub-cidr').value;
    const gateway_ip = document.getElementById('sub-gateway').value;
    const vlan_id = document.getElementById('sub-vlan').value;
    const description = document.getElementById('sub-desc').value;

    try {
        const resp = await fetch('api.php?action=create_ipam_subnet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, cidr, gateway_ip, vlan_id, description })
        });
        const res = await resp.json();
        alert(res.message || 'Subnet created!');
        closeNewSubnetModal();
        loadSubnets();
    } catch (err) {
        alert('Error creating subnet: ' + err.message);
    }
}

function openEditIpModal(ip, status, hostname, mac, deviceId, notes) {
    document.getElementById('edit-ip-display').textContent = ip;
    document.getElementById('edit-ip-val').value = ip;
    document.getElementById('edit-ip-status').value = status;
    document.getElementById('edit-ip-hostname').value = hostname;
    document.getElementById('edit-ip-mac').value = mac;
    document.getElementById('edit-ip-device').value = deviceId;
    document.getElementById('edit-ip-notes').value = notes;
    document.getElementById('edit-ip-modal').classList.remove('hidden');
}

function closeEditIpModal() {
    document.getElementById('edit-ip-modal').classList.add('hidden');
}

async function submitEditIp(e) {
    e.preventDefault();
    const ip = document.getElementById('edit-ip-val').value;
    const status = document.getElementById('edit-ip-status').value;
    const hostname = document.getElementById('edit-ip-hostname').value;
    const mac_address = document.getElementById('edit-ip-mac').value;
    const device_id = document.getElementById('edit-ip-device').value;
    const notes = document.getElementById('edit-ip-notes').value;

    try {
        const resp = await fetch('api.php?action=assign_ipam_ip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subnet_id: activeSubnetId, ip_address: ip, status, hostname, mac_address, device_id, notes })
        });
        const res = await resp.json();
        closeEditIpModal();
        selectSubnet(activeSubnetId);
    } catch (err) {
        alert('Error updating IP: ' + err.message);
    }
}

async function triggerSubnetScan() {
    if (!activeSubnetId) return;
    const btn = document.getElementById('btn-scan-subnet');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Sweeping...`;

    try {
        const resp = await fetch('api.php?action=scan_ipam_subnet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subnet_id: activeSubnetId })
        });
        const res = await resp.json();
        alert(res.message || 'Subnet scan completed!');
        selectSubnet(activeSubnetId);
        loadSubnets();
    } catch (err) {
        alert('Scan error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-radar"></i> Sweep &amp; Sync`;
    }
}

document.addEventListener('DOMContentLoaded', loadSubnets);
</script>

<?php include 'footer.php'; ?>
