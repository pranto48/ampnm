<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Planned Maintenance Windows & Alert Silence Engine
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

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-tools text-cyan-400"></i> Planned Maintenance &amp; Alert Silence Engine
            </h1>
            <p class="text-slate-400 text-sm mt-1">Schedule planned maintenance windows to automatically silence down-alerts and mark devices with maintenance badges.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openNewWindowModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-calendar-plus"></i> Schedule Window
            </button>
        </div>
    </div>

    <!-- Summary Stats Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Total Scheduled</span>
                <h3 class="text-xl font-bold text-white" id="stat-total-windows">0</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-bell-slash"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Active Now (Silenced)</span>
                <h3 class="text-xl font-bold text-white" id="stat-active-windows">0</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Upcoming Windows</span>
                <h3 class="text-xl font-bold text-white" id="stat-upcoming-windows">0</h3>
            </div>
        </div>
    </div>

    <!-- Maintenance Windows Table -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-list-alt text-cyan-400"></i> Scheduled Maintenance Schedules
            </h2>
            <span class="text-xs text-slate-400" id="table-count-label">Loading schedules...</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase border-b border-slate-700/60 font-semibold font-mono">
                    <tr>
                        <th class="px-6 py-3.5">Window Title</th>
                        <th class="px-6 py-3.5">Target Scope</th>
                        <th class="px-6 py-3.5">Start Time</th>
                        <th class="px-6 py-3.5">End Time</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Alerts</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="windows-table-body" class="divide-y divide-slate-700/40">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading maintenance windows...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Schedule New Window -->
<div id="new-window-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-calendar-plus text-cyan-400"></i> Schedule Maintenance Window
            </h3>
            <button type="button" onclick="closeNewWindowModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="new-window-form" onsubmit="submitNewWindow(event)" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Window Title / Description</label>
                <input type="text" id="win-title" required placeholder="e.g. Core Switch Firmware Upgrade & Power Check" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Target Scope</label>
                    <select id="win-target-type" onchange="toggleTargetDeviceSelect()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="device">Single Specific Device</option>
                        <option value="all">All Network Infrastructure (Global)</option>
                    </select>
                </div>
                <div id="target-device-group">
                    <label class="block text-slate-300 font-medium mb-1">Select Target Device</label>
                    <select id="win-target-id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <?php foreach ($devices as $d): ?>
                            <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Start Date &amp; Time</label>
                    <input type="datetime-local" id="win-start" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">End Date &amp; Time</label>
                    <input type="datetime-local" id="win-end" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Maintenance Notes / Work Order</label>
                <textarea id="win-notes" rows="2" placeholder="Planned datacenter electrical maintenance and switch OS reload..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-2">
                <label class="flex items-center gap-2 text-slate-300 cursor-pointer">
                    <input type="checkbox" id="win-suppress" checked class="rounded border-slate-600 bg-slate-800 text-cyan-500">
                    <span>Suppress Alerts (Do NOT dispatch Telegram, SMS, WhatsApp, Webhooks during window)</span>
                </label>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-700">
                <button type="button" onclick="closeNewWindowModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors">Schedule Window</button>
            </div>
        </form>
    </div>
</div>

<script>
let windowsList = [];

async function loadMaintenanceWindows() {
    try {
        const resp = await fetch('api.php?action=get_maintenance_windows');
        const data = await resp.json();
        windowsList = data.windows || [];

        const now = new Date();
        let activeCount = 0;
        let upcomingCount = 0;

        windowsList.forEach(w => {
            const start = new Date(w.start_time);
            const end = new Date(w.end_time);
            if (now >= start && now <= end) activeCount++;
            else if (now < start) upcomingCount++;
        });

        document.getElementById('stat-total-windows').textContent = windowsList.length;
        document.getElementById('stat-active-windows').textContent = activeCount;
        document.getElementById('stat-upcoming-windows').textContent = upcomingCount;
        document.getElementById('table-count-label').textContent = `${windowsList.length} schedule(s) found`;

        renderWindowsTable(windowsList);
    } catch (e) {
        console.error('Error loading maintenance windows', e);
    }
}

function renderWindowsTable(windows) {
    const tbody = document.getElementById('windows-table-body');
    if (windows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No maintenance windows scheduled. Click "+ Schedule Window" to create one.</td></tr>`;
        return;
    }

    const now = new Date();
    tbody.innerHTML = windows.map(w => {
        const start = new Date(w.start_time);
        const end = new Date(w.end_time);
        let statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-700 text-slate-400">Expired</span>';

        if (now >= start && now <= end) {
            statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30 animate-pulse"><i class="fas fa-tools mr-1"></i>Active Now</span>';
        } else if (now < start) {
            statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">Upcoming</span>';
        }

        const targetScope = w.target_type === 'all' 
            ? '<span class="font-bold text-white"><i class="fas fa-globe text-cyan-400 mr-1"></i>All Global Devices</span>'
            : `<div class="font-bold text-white">${w.target_device_name || 'Device'}</div><div class="text-[11px] text-slate-400 font-mono">${w.target_device_ip || ''}</div>`;

        const alertBadge = w.suppress_alerts == 1
            ? '<span class="text-amber-400 text-xs flex items-center gap-1 font-mono"><i class="fas fa-bell-slash"></i> Silenced</span>'
            : '<span class="text-slate-500 text-xs font-mono">Active</span>';

        return `
            <tr class="hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-bold text-white text-sm">${w.title}</div>
                    ${w.notes ? `<div class="text-xs text-slate-400 mt-0.5">${w.notes}</div>` : ''}
                </td>
                <td class="px-6 py-4">${targetScope}</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-300">${w.start_time}</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-300">${w.end_time}</td>
                <td class="px-6 py-4">${statusBadge}</td>
                <td class="px-6 py-4">${alertBadge}</td>
                <td class="px-6 py-4 text-right">
                    <button type="button" onclick="deleteWindow('${w.id}')" class="p-1.5 text-slate-400 hover:text-red-400 transition-colors" title="Delete Schedule">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function openNewWindowModal() {
    // Set default start time to now + 5 mins, end time to now + 2 hours
    const now = new Date();
    const start = new Date(now.getTime() + 5 * 60000);
    const end = new Date(now.getTime() + 120 * 60000);

    document.getElementById('win-start').value = start.toISOString().slice(0, 16);
    document.getElementById('win-end').value = end.toISOString().slice(0, 16);
    document.getElementById('new-window-modal').classList.remove('hidden');
}

function closeNewWindowModal() {
    document.getElementById('new-window-modal').classList.add('hidden');
}

function toggleTargetDeviceSelect() {
    const isAll = document.getElementById('win-target-type').value === 'all';
    document.getElementById('target-device-group').style.display = isAll ? 'none' : 'block';
}

async function submitNewWindow(e) {
    e.preventDefault();
    const title = document.getElementById('win-title').value;
    const target_type = document.getElementById('win-target-type').value;
    const target_id = target_type === 'device' ? document.getElementById('win-target-id').value : null;
    const start_time = document.getElementById('win-start').value;
    const end_time = document.getElementById('win-end').value;
    const suppress_alerts = document.getElementById('win-suppress').checked ? 1 : 0;
    const notes = document.getElementById('win-notes').value;

    try {
        const resp = await fetch('api.php?action=create_maintenance_window', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, target_type, target_id, start_time, end_time, suppress_alerts, notes })
        });
        const res = await resp.json();
        alert(res.message || 'Scheduled!');
        closeNewWindowModal();
        loadMaintenanceWindows();
    } catch (err) {
        alert('Error scheduling maintenance window: ' + err.message);
    }
}

async function deleteWindow(id) {
    if (!confirm('Cancel and delete this maintenance window?')) return;
    try {
        await fetch('api.php?action=delete_maintenance_window', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        loadMaintenanceWindows();
    } catch (err) {
        alert('Error deleting: ' + err.message);
    }
}

document.addEventListener('DOMContentLoaded', loadMaintenanceWindows);
</script>

<?php include 'footer.php'; ?>
