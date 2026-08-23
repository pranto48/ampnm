<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Status Page Admin & Live Incident Management
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    header('Location: index.php');
    exit;
}

$pdo = getDbConnection();
$devices = $pdo->query("SELECT id, name, ip FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-6xl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-bullhorn text-cyan-400"></i> Public Status Page &amp; Incident Manager
            </h1>
            <p class="text-slate-400 text-sm mt-1">Manage public-facing service components, broadcast live outage incidents, and post postmortems.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="public_status.php" target="_blank" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-700 rounded-lg text-sm transition-all flex items-center gap-2">
                <i class="fas fa-external-link-alt"></i> View Public Page
            </a>
            <button type="button" onclick="openNewIncidentModal()" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-red-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-plus-circle"></i> Create Incident
            </button>
        </div>
    </div>

    <!-- General Settings Form -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-6 mb-6 shadow-xl">
        <h2 class="text-base font-semibold text-white mb-4 flex items-center gap-2 border-b border-slate-700/60 pb-3">
            <i class="fas fa-cog text-cyan-400"></i> General Status Page Branding &amp; Access
        </h2>
        <form id="status-settings-form" onsubmit="saveGeneralSettings(event)" class="space-y-4 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Company / Organization Name</label>
                    <input type="text" id="set-company" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Status Page Title</label>
                    <input type="text" id="set-title" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Header Announcement Message</label>
                <input type="text" id="set-header-msg" placeholder="All core systems and communication backbones are operating normally." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center gap-2 text-slate-300 cursor-pointer">
                    <input type="checkbox" id="set-public-enabled" class="rounded border-slate-600 bg-slate-800 text-cyan-500">
                    <span>Enable Public Access (No Authentication Required to View)</span>
                </label>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition-colors">Save Settings</button>
            </div>
        </form>
    </div>

    <!-- Components & Incidents Split Section -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Service Components (5 Cols) -->
        <div class="lg:col-span-5 bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl flex flex-col">
            <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-cubes text-cyan-400"></i> Service Components
                </h2>
                <button type="button" onclick="openComponentModal()" class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-cyan-400 rounded text-xs font-semibold">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <div id="components-list" class="space-y-3 flex-1 overflow-y-auto max-h-[500px] pr-1">
                <div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading components...</div>
            </div>
        </div>

        <!-- Live Incidents Stream (7 Cols) -->
        <div class="lg:col-span-7 bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl flex flex-col">
            <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-stream text-cyan-400"></i> Incidents &amp; Postmortems
                </h2>
                <span class="text-xs text-slate-400" id="incidents-badge">0 Incidents</span>
            </div>

            <div id="admin-incidents-list" class="space-y-4 flex-1 overflow-y-auto max-h-[500px] pr-1">
                <div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading incidents...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Component -->
<div id="component-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-cube text-cyan-400"></i> Manage Service Component
            </h3>
            <button type="button" onclick="closeComponentModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="component-form" onsubmit="submitComponent(event)" class="space-y-3.5 text-xs">
            <input type="hidden" id="comp-id">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Component Name</label>
                <input type="text" id="comp-name" required placeholder="e.g. Core BGP Gateway" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Group Category</label>
                    <input type="text" id="comp-group" value="Core Infrastructure" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Status Override</label>
                    <select id="comp-status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="operational">🟢 Operational</option>
                        <option value="degraded">🟡 Degraded</option>
                        <option value="outage">🔴 Major Outage</option>
                        <option value="maintenance">🔵 Maintenance</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Link to Monitored Device</label>
                <select id="comp-device" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">None / Custom Service</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeComponentModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Save Component</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create New Incident -->
<div id="new-incident-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-red-400"></i> Report Outage / Incident
            </h3>
            <button type="button" onclick="closeNewIncidentModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="new-incident-form" onsubmit="submitNewIncident(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Incident Title</label>
                <input type="text" id="inc-title" required placeholder="e.g. Core Switch Latency & Packet Loss" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Initial Status</label>
                    <select id="inc-status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="investigating">Investigating</option>
                        <option value="identified">Identified</option>
                        <option value="monitoring">Monitoring</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Severity / Impact</label>
                    <select id="inc-impact" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="minor">Minor Outage</option>
                        <option value="major">Major Outage</option>
                        <option value="critical">Critical Outage</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Initial Status Message</label>
                <textarea id="inc-message" rows="3" required placeholder="Our engineering team is actively investigating degraded performance on core uplink..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeNewIncidentModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-lg shadow-lg shadow-red-600/30 transition-colors">Publish Incident</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Post Incident Update -->
<div id="update-incident-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-edit text-cyan-400"></i> Post Incident Update
            </h3>
            <button type="button" onclick="closeUpdateIncidentModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="update-incident-form" onsubmit="submitIncidentUpdate(event)" class="space-y-3.5 text-xs">
            <input type="hidden" id="upd-inc-id">
            <div>
                <label class="block text-slate-300 font-medium mb-1">New Incident State</label>
                <select id="upd-inc-status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="investigating">Investigating</option>
                    <option value="identified">Identified</option>
                    <option value="monitoring">Monitoring</option>
                    <option value="resolved">Resolved (Close Incident)</option>
                </select>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Update Message</label>
                <textarea id="upd-inc-message" rows="3" required placeholder="Uplink fibers have been re-routed and traffic is normalizing..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeUpdateIncidentModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Post Update</button>
            </div>
        </form>
    </div>
</div>

<script>
let adminData = null;

async function loadAdminData() {
    try {
        const resp = await fetch('api.php?action=get_status_page_admin');
        const data = await resp.json();
        if (!data.success) return;

        adminData = data;
        if (data.settings) {
            document.getElementById('set-company').value = data.settings.company_name || '';
            document.getElementById('set-title').value = data.settings.title || '';
            document.getElementById('set-header-msg').value = data.settings.header_message || '';
            document.getElementById('set-public-enabled').checked = data.settings.is_public_enabled == 1;
        }

        renderComponents(data.components || []);
        renderIncidents(data.incidents || []);
    } catch (e) {
        console.error('Failed to load admin data', e);
    }
}

function renderComponents(comps) {
    const list = document.getElementById('components-list');
    if (comps.length === 0) {
        list.innerHTML = `<div class="text-center py-6 text-slate-500">No components added yet.</div>`;
        return;
    }

    list.innerHTML = comps.map(c => `
        <div class="p-3 bg-slate-900/70 border border-slate-700/70 rounded-xl flex items-center justify-between text-xs">
            <div>
                <div class="font-bold text-white">${c.name}</div>
                <span class="text-[11px] text-slate-400 font-mono">${c.group_name} • ${c.status.toUpperCase()}</span>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" onclick="editComponent('${c.id}', '${c.name}', '${c.group_name}', '${c.status}', '${c.device_id || ''}')" class="p-1.5 text-slate-400 hover:text-cyan-400"><i class="fas fa-edit"></i></button>
                <button type="button" onclick="deleteComponent('${c.id}')" class="p-1.5 text-slate-400 hover:text-red-400"><i class="fas fa-trash-alt"></i></button>
            </div>
        </div>
    `).join('');
}

function renderIncidents(incs) {
    const list = document.getElementById('admin-incidents-list');
    document.getElementById('incidents-badge').textContent = `${incs.length} Incidents`;
    if (incs.length === 0) {
        list.innerHTML = `<div class="text-center py-6 text-slate-500">No incidents recorded.</div>`;
        return;
    }

    list.innerHTML = incs.map(i => `
        <div class="p-4 bg-slate-900/70 border ${i.status !== 'resolved' ? 'border-amber-500/40' : 'border-slate-700/70'} rounded-xl space-y-3 text-xs">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded font-mono uppercase font-bold text-[10px] ${i.status !== 'resolved' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400'}">${i.status}</span>
                    <h4 class="font-bold text-white">${i.title}</h4>
                </div>
                <button type="button" onclick="openUpdateIncidentModal('${i.id}', '${i.status}')" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded text-xs">
                    <i class="fas fa-plus mr-1"></i>Update
                </button>
            </div>
            <div class="space-y-1.5 pl-2 border-l-2 border-slate-800 font-mono text-[11px]">
                ${(i.updates || []).map(u => `
                    <div>
                        <span class="text-cyan-400 font-semibold uppercase">${u.status_state}:</span> 
                        <span class="text-slate-300 font-sans">${u.message}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');
}

async function saveGeneralSettings(e) {
    e.preventDefault();
    const company_name = document.getElementById('set-company').value;
    const title = document.getElementById('set-title').value;
    const header_message = document.getElementById('set-header-msg').value;
    const is_public_enabled = document.getElementById('set-public-enabled').checked ? 1 : 0;

    try {
        const resp = await fetch('api.php?action=save_status_page_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ company_name, title, header_message, is_public_enabled })
        });
        const res = await resp.json();
        alert(res.message || 'Settings saved!');
    } catch (err) {
        alert('Error saving settings: ' + err.message);
    }
}

function openComponentModal() {
    document.getElementById('comp-id').value = '';
    document.getElementById('comp-name').value = '';
    document.getElementById('comp-group').value = 'Core Infrastructure';
    document.getElementById('comp-status').value = 'operational';
    document.getElementById('comp-device').value = '';
    document.getElementById('component-modal').classList.remove('hidden');
}

function editComponent(id, name, group, status, devId) {
    document.getElementById('comp-id').value = id;
    document.getElementById('comp-name').value = name;
    document.getElementById('comp-group').value = group;
    document.getElementById('comp-status').value = status;
    document.getElementById('comp-device').value = devId;
    document.getElementById('component-modal').classList.remove('hidden');
}

function closeComponentModal() {
    document.getElementById('component-modal').classList.add('hidden');
}

async function submitComponent(e) {
    e.preventDefault();
    const id = document.getElementById('comp-id').value;
    const name = document.getElementById('comp-name').value;
    const group_name = document.getElementById('comp-group').value;
    const status = document.getElementById('comp-status').value;
    const device_id = document.getElementById('comp-device').value;

    try {
        const resp = await fetch('api.php?action=save_status_component', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name, group_name, status, device_id })
        });
        const res = await resp.json();
        closeComponentModal();
        loadAdminData();
    } catch (err) {
        alert('Error saving component: ' + err.message);
    }
}

async function deleteComponent(id) {
    if (!confirm('Delete this service component?')) return;
    try {
        await fetch('api.php?action=delete_status_component', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        loadAdminData();
    } catch (err) {
        alert('Error deleting component: ' + err.message);
    }
}

function openNewIncidentModal() {
    document.getElementById('new-incident-modal').classList.remove('hidden');
}

function closeNewIncidentModal() {
    document.getElementById('new-incident-modal').classList.add('hidden');
}

async function submitNewIncident(e) {
    e.preventDefault();
    const title = document.getElementById('inc-title').value;
    const status = document.getElementById('inc-status').value;
    const impact = document.getElementById('inc-impact').value;
    const message = document.getElementById('inc-message').value;

    try {
        const resp = await fetch('api.php?action=create_status_incident', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, status, impact, message })
        });
        const res = await resp.json();
        closeNewIncidentModal();
        loadAdminData();
    } catch (err) {
        alert('Error publishing incident: ' + err.message);
    }
}

function openUpdateIncidentModal(incId, currentStatus) {
    document.getElementById('upd-inc-id').value = incId;
    document.getElementById('upd-inc-status').value = currentStatus;
    document.getElementById('upd-inc-message').value = '';
    document.getElementById('update-incident-modal').classList.remove('hidden');
}

function closeUpdateIncidentModal() {
    document.getElementById('update-incident-modal').classList.add('hidden');
}

async function submitIncidentUpdate(e) {
    e.preventDefault();
    const incident_id = document.getElementById('upd-inc-id').value;
    const status = document.getElementById('upd-inc-status').value;
    const message = document.getElementById('upd-inc-message').value;

    try {
        const resp = await fetch('api.php?action=update_status_incident', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ incident_id, status, message })
        });
        const res = await resp.json();
        closeUpdateIncidentModal();
        loadAdminData();
    } catch (err) {
        alert('Error updating incident: ' + err.message);
    }
}

document.addEventListener('DOMContentLoaded', loadAdminData);
</script>

<?php include 'footer.php'; ?>
