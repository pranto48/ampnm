<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Data Center 42U / 24U Server Rack Elevation Designer
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
                <i class="fas fa-server text-cyan-400"></i> Data Center Rack Elevation Designer
            </h1>
            <p class="text-slate-400 text-sm mt-1">42U / 24U Cabinet visualizer, equipment layout manager, power load, and thermal status tracking.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openNewCabinetModal()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-lg text-sm transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> New Cabinet
            </button>
            <button type="button" onclick="openMountModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-wrench"></i> Mount Equipment
            </button>
        </div>
    </div>

    <!-- Rack Metrics Header -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-cubes"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Occupancy</span>
                <h3 class="text-xl font-bold text-white" id="stat-rack-occupancy">0 / 42 U</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Power Draw</span>
                <h3 class="text-xl font-bold text-white" id="stat-rack-power">0 W</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Mounted Units</span>
                <h3 class="text-xl font-bold text-white" id="stat-mounted-count">0 Devices</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 shadow-md">
            <label class="block text-xs text-slate-400 font-medium mb-1.5">Select Rack Cabinet</label>
            <select id="cabinet-select" onchange="switchCabinet()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-white text-xs focus:border-cyan-500 focus:outline-none">
                <!-- Dynamically populated -->
            </select>
        </div>
    </div>

    <!-- Main Rack Cabinet Canvas Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- 42U Rack Elevation Chassis Column (7 Cols) -->
        <div class="lg:col-span-7 bg-slate-950 p-6 rounded-2xl border border-slate-800 shadow-2xl flex flex-col items-center">
            <div class="w-full max-w-lg mb-4 flex items-center justify-between px-2 text-xs text-slate-400 border-b border-slate-800 pb-2">
                <span class="font-bold text-white uppercase tracking-wider" id="rack-view-title">Cabinet Elevation View</span>
                <span class="font-mono text-cyan-400" id="rack-view-location">Main Data Center</span>
            </div>

            <!-- Standard 19-inch 42U Rack Frame -->
            <div class="w-full max-w-lg bg-slate-900 border-4 border-slate-700 rounded-lg shadow-inner overflow-hidden relative">
                <!-- Rack Top Rail -->
                <div class="bg-slate-800 px-4 py-2 border-b-2 border-slate-700 flex justify-between items-center text-[10px] text-slate-400 uppercase font-mono tracking-widest">
                    <span>Rack Top</span>
                    <span>19" Standard Rail</span>
                </div>

                <!-- Slots Frame -->
                <div id="rack-slots-container" class="divide-y divide-slate-800 font-mono text-xs">
                    <!-- 42U slots generated via JS -->
                </div>

                <!-- Rack Bottom Rail -->
                <div class="bg-slate-800 px-4 py-2 border-t-2 border-slate-700 flex justify-between items-center text-[10px] text-slate-400 uppercase font-mono tracking-widest">
                    <span>Rack Base / Floor</span>
                    <span id="rack-power-budget-label">Max 5000W</span>
                </div>
            </div>
        </div>

        <!-- Right Inspector & Inventory Column (5 Cols) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Mounted Equipment Inventory Card -->
            <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl">
                <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fas fa-list text-cyan-400"></i> Mounted Inventory
                    </h2>
                    <span class="text-xs text-slate-400" id="inventory-count-badge">0 Devices</span>
                </div>

                <div id="mounted-inventory-list" class="space-y-2.5 max-h-[500px] overflow-y-auto pr-1">
                    <!-- Populated via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create New Cabinet -->
<div id="new-cabinet-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-plus-circle text-cyan-400"></i> Add Server Rack Cabinet
            </h3>
            <button type="button" onclick="closeNewCabinetModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="new-cabinet-form" onsubmit="submitNewCabinet(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Cabinet Name</label>
                <input type="text" id="cab-name" required placeholder="e.g. Rack A-01 (Core Network)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Location / Facility</label>
                    <input type="text" id="cab-location" placeholder="Main DC" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Room</label>
                    <input type="text" id="cab-room" placeholder="Server Room 1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Total Unit Height</label>
                    <select id="cab-units" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="42" selected>42U Standard Full Rack</option>
                        <option value="24">24U Mid-Size Cabinet</option>
                        <option value="12">12U Wall Mount Rack</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Power Budget (Watts)</label>
                    <input type="number" id="cab-power" value="5000" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeNewCabinetModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Create Cabinet</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Mount Device -->
<div id="mount-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-wrench text-cyan-400"></i> Mount Equipment in Rack
            </h3>
            <button type="button" onclick="closeMountModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="mount-form" onsubmit="submitMount(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Equipment Label</label>
                <input type="text" id="mount-label" required placeholder="e.g. Cisco Nexus 9000 Core Switch" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Start Unit (U Slot)</label>
                    <input type="number" id="mount-slot" required min="1" max="42" value="20" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Unit Height (U)</label>
                    <select id="mount-height" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="1">1U Slot</option>
                        <option value="2">2U Chassis</option>
                        <option value="3">3U Chassis</option>
                        <option value="4">4U Server / UPS</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Category</label>
                    <select id="mount-category" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="switch">Managed Switch</option>
                        <option value="router">Router / Gateway</option>
                        <option value="server">Application Server</option>
                        <option value="patch_panel">Patch Panel</option>
                        <option value="ups">UPS Power Backup</option>
                        <option value="storage">SAN / NAS Storage</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Power Draw (W)</label>
                    <input type="number" id="mount-power" value="200" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Link to Monitored Device (Optional)</label>
                <select id="mount-device-id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">None / Passive Hardware</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeMountModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Mount Unit</button>
            </div>
        </form>
    </div>
</div>

<script>
let cabinets = [];
let activeCabinetId = null;
let currentMounted = [];

async function loadCabinets() {
    try {
        const resp = await fetch('api.php?action=get_rack_cabinets');
        const data = await resp.json();
        cabinets = data.cabinets || [];

        const select = document.getElementById('cabinet-select');
        select.innerHTML = cabinets.map(c => `<option value="${c.id}">${c.name} (${c.total_units}U - ${c.location || 'DC'})</option>`).join('');

        if (cabinets.length > 0) {
            activeCabinetId = cabinets[0].id;
            loadCabinetDevices(activeCabinetId);
        }
    } catch (e) {
        console.error('Error loading cabinets', e);
    }
}

async function loadCabinetDevices(rackId) {
    try {
        const resp = await fetch(`api.php?action=get_rack_devices&rack_id=${encodeURIComponent(rackId)}`);
        const data = await resp.json();
        if (!data.success) return;

        const cab = data.cabinet;
        currentMounted = data.mounted_devices || [];

        document.getElementById('rack-view-title').textContent = `${cab.name} (${cab.total_units}U)`;
        document.getElementById('rack-view-location').textContent = `${cab.location || 'DC'} / ${cab.room || 'Room'}`;
        document.getElementById('rack-power-budget-label').textContent = `Max ${cab.power_budget_watts || 5000}W Budget`;

        let usedUnits = 0;
        let totalPower = 0;
        currentMounted.forEach(m => {
            usedUnits += parseInt(m.unit_height);
            totalPower += parseInt(m.power_watts || 0);
        });

        document.getElementById('stat-rack-occupancy').textContent = `${usedUnits} / ${cab.total_units} U`;
        document.getElementById('stat-rack-power').textContent = `${totalPower} W`;
        document.getElementById('stat-mounted-count').textContent = `${currentMounted.length} Units`;
        document.getElementById('inventory-count-badge').textContent = `${currentMounted.length} Devices`;

        renderRackChassis(parseInt(cab.total_units), currentMounted);
        renderMountedInventory(currentMounted);
    } catch (e) {
        console.error('Error loading rack devices', e);
    }
}

function renderRackChassis(totalUnits, mountedDevices) {
    const container = document.getElementById('rack-slots-container');
    container.innerHTML = '';

    // Map units to mounted devices
    const slotMap = {};
    mountedDevices.forEach(m => {
        const start = parseInt(m.start_unit);
        const height = parseInt(m.unit_height);
        for (let u = start; u < start + height; u++) {
            slotMap[u] = { device: m, isTop: u === start + height - 1 };
        }
    });

    for (let u = totalUnits; u >= 1; u--) {
        const slotData = slotMap[u];
        
        if (slotData) {
            const dev = slotData.device;
            if (slotData.isTop) {
                const heightPx = parseInt(dev.unit_height) * 28;
                let bgGradient = 'from-slate-800 to-slate-900 border-cyan-500/60';
                let icon = 'fa-server text-cyan-400';
                
                if (dev.category === 'switch') { bgGradient = 'from-indigo-950 to-slate-900 border-indigo-500/60'; icon = 'fa-network-wired text-indigo-400'; }
                else if (dev.category === 'patch_panel') { bgGradient = 'from-slate-900 to-slate-950 border-slate-600'; icon = 'fa-grip-horizontal text-slate-400'; }
                else if (dev.category === 'ups') { bgGradient = 'from-amber-950 to-slate-900 border-amber-500/60'; icon = 'fa-bolt text-amber-400'; }

                const row = document.createElement('div');
                row.className = `flex items-center px-3 bg-gradient-to-r ${bgGradient} border-l-4 shadow-md text-white select-none`;
                row.style.height = `${heightPx}px`;
                row.innerHTML = `
                    <span class="w-8 text-[11px] text-slate-400 font-mono font-bold">U${u}</span>
                    <div class="flex-1 flex items-center justify-between px-2 overflow-hidden">
                        <div class="flex items-center gap-2 truncate">
                            <span class="w-2 h-2 rounded-full ${dev.device_status === 'offline' ? 'bg-red-500 shadow-red-500/50' : 'bg-emerald-400 shadow-emerald-400/50'} shadow-sm animate-pulse"></span>
                            <i class="fas ${icon} text-xs"></i>
                            <span class="text-xs font-semibold truncate">${dev.label}</span>
                            ${dev.device_ip ? `<span class="text-[10px] text-cyan-300 font-mono">(${dev.device_ip})</span>` : ''}
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">${dev.power_watts || 0}W</span>
                    </div>
                `;
                container.appendChild(row);
            }
        } else {
            const emptyRow = document.createElement('div');
            emptyRow.className = 'h-7 flex items-center px-3 bg-slate-950/40 hover:bg-slate-900/60 text-slate-600 transition-colors cursor-pointer group';
            emptyRow.onclick = () => { document.getElementById('mount-slot').value = u; openMountModal(); };
            emptyRow.innerHTML = `
                <span class="w-8 text-[11px] font-mono group-hover:text-slate-400">U${u}</span>
                <div class="flex-1 flex justify-between items-center text-[10px] opacity-0 group-hover:opacity-100 text-cyan-400 transition-opacity">
                    <span>-- Empty Slot --</span>
                    <span><i class="fas fa-plus mr-1"></i>Mount here</span>
                </div>
            `;
            container.appendChild(emptyRow);
        }
    }
}

function renderMountedInventory(mounted) {
    const list = document.getElementById('mounted-inventory-list');
    if (mounted.length === 0) {
        list.innerHTML = `<div class="text-center py-8 text-slate-500">No hardware mounted in this cabinet yet.</div>`;
        return;
    }

    list.innerHTML = mounted.map(m => `
        <div class="p-3 bg-slate-900/70 border border-slate-700/70 rounded-xl flex items-center justify-between hover:border-slate-600 transition-colors">
            <div class="flex items-center gap-3">
                <span class="px-2 py-1 bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 rounded font-mono text-xs font-bold">U${m.start_unit} (${m.unit_height}U)</span>
                <div>
                    <h4 class="text-xs font-bold text-white">${m.label}</h4>
                    <span class="text-[11px] text-slate-400 font-mono">${m.category.toUpperCase()} • ${m.power_watts || 0}W</span>
                </div>
            </div>
            <button type="button" onclick="unmountDevice('${m.id}')" class="text-slate-500 hover:text-red-400 p-1.5 transition-colors" title="Unmount from Rack">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    `).join('');
}

function switchCabinet() {
    activeCabinetId = document.getElementById('cabinet-select').value;
    loadCabinetDevices(activeCabinetId);
}

function openNewCabinetModal() {
    document.getElementById('new-cabinet-modal').classList.remove('hidden');
}

function closeNewCabinetModal() {
    document.getElementById('new-cabinet-modal').classList.add('hidden');
}

async function submitNewCabinet(e) {
    e.preventDefault();
    const name = document.getElementById('cab-name').value;
    const location = document.getElementById('cab-location').value;
    const room = document.getElementById('cab-room').value;
    const total_units = document.getElementById('cab-units').value;
    const power_budget_watts = document.getElementById('cab-power').value;

    try {
        const resp = await fetch('api.php?action=create_rack_cabinet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, location, room, total_units, power_budget_watts })
        });
        const res = await resp.json();
        alert(res.message || 'Cabinet created!');
        closeNewCabinetModal();
        loadCabinets();
    } catch (err) {
        alert('Error creating cabinet: ' + err.message);
    }
}

function openMountModal() {
    document.getElementById('mount-modal').classList.remove('hidden');
}

function closeMountModal() {
    document.getElementById('mount-modal').classList.add('hidden');
}

async function submitMount(e) {
    e.preventDefault();
    const start_unit = document.getElementById('mount-slot').value;
    const unit_height = document.getElementById('mount-height').value;
    const label = document.getElementById('mount-label').value;
    const category = document.getElementById('mount-category').value;
    const power_watts = document.getElementById('mount-power').value;
    const device_id = document.getElementById('mount-device-id').value;

    try {
        const resp = await fetch('api.php?action=mount_rack_device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rack_id: activeCabinetId, start_unit, unit_height, label, category, power_watts, device_id })
        });
        const res = await resp.json();
        alert(res.message || 'Mounted!');
        closeMountModal();
        loadCabinetDevices(activeCabinetId);
    } catch (err) {
        alert('Error mounting device: ' + err.message);
    }
}

async function unmountDevice(mountId) {
    if (!confirm('Are you sure you want to unmount this equipment from the rack?')) return;
    try {
        const resp = await fetch('api.php?action=unmount_rack_device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: mountId })
        });
        const res = await resp.json();
        loadCabinetDevices(activeCabinetId);
    } catch (err) {
        alert('Error unmounting: ' + err.message);
    }
}

document.addEventListener('DOMContentLoaded', loadCabinets);
</script>

<?php include 'footer.php'; ?>
