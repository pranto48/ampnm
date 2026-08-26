<?php
/**
 * AMPNM Planned Maintenance Windows & Silence Periods Manager
 * Copyright (c) IT Support BD. All rights reserved.
 */

require_once 'includes/auth_check.php';
require_once 'includes/maintenance_engine.php';
include 'header.php';

$pdo = getDbConnection();
$engine = new MaintenanceEngine($pdo);
$allWindows = $engine->getAllWindows();
$activeWindows = $engine->getActiveWindows();
$devices = $pdo->query("SELECT id, name, ip_address FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$maps = $pdo->query("SELECT id, name FROM maps ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="container-fluid px-4 py-4 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-calendar-alt text-amber-400"></i>
                <span>Planned Maintenance Windows & Alert Silence</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Schedule blackout periods, suppress false alert storms during upgrades, and isolate planned downtime from SLA calculations.
            </p>
        </div>
        <div>
            <button onclick="openScheduleModal()" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-amber-900/30 transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Schedule Maintenance
            </button>
        </div>
    </div>

    <!-- Active Maintenance Alert Banner if any -->
    <?php if (!empty($activeWindows)): ?>
    <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 mb-6">
        <div class="flex items-center gap-3">
            <span class="flex h-3 w-3 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
            </span>
            <div>
                <h4 class="font-bold text-amber-300 text-sm">Active Maintenance In Progress</h4>
                <p class="text-xs text-amber-200/80 mt-0.5">
                    <?= count($activeWindows) ?> window(s) are currently active. Alert notifications are suppressed for targeted systems.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Maintenance Windows List -->
    <div class="bg-slate-850 border border-slate-800 rounded-xl overflow-hidden shadow-xl mb-8">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="font-semibold text-white flex items-center gap-2">
                <i class="fas fa-clock text-cyan-400"></i> Scheduled & Historical Maintenance
            </h3>
            <span class="text-xs text-slate-400"><?= count($allWindows) ?> Total Schedules</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Title / Scope</th>
                        <th class="py-3 px-4">Target Type</th>
                        <th class="py-3 px-4">Start Time</th>
                        <th class="py-3 px-4">End Time</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Alert Suppression</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if (empty($allWindows)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                <i class="fas fa-calendar-check text-4xl mb-2 block"></i>
                                No planned maintenance windows found. Click "Schedule Maintenance" to add one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $nowTs = time();
                        foreach ($allWindows as $w): 
                            $startTs = strtotime($w['start_time']);
                            $endTs = strtotime($w['end_time']);
                            $isActive = ($nowTs >= $startTs && $nowTs <= $endTs);
                            $isUpcoming = ($nowTs < $startTs);
                            $isExpired = ($nowTs > $endTs);
                        ?>
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <?= htmlspecialchars($w['title']) ?>
                                <?php if (!empty($w['notes'])): ?>
                                    <span class="block text-xs font-normal text-slate-400 truncate max-w-xs"><?= htmlspecialchars($w['notes']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono capitalize">
                                <span class="px-2 py-0.5 rounded bg-slate-900 text-cyan-300 border border-slate-700">
                                    <?= htmlspecialchars($w['target_type']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-300">
                                <?= date('Y-m-d H:i', $startTs) ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-300">
                                <?= date('Y-m-d H:i', $endTs) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($isActive): ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 animate-pulse">
                                        In Progress
                                    </span>
                                <?php elseif ($isUpcoming): ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-cyan-500/10 text-cyan-300 border border-cyan-500/30">
                                        Upcoming
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-800 text-slate-400">
                                        Completed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <?php if ($w['suppress_alerts']): ?>
                                    <span class="text-emerald-400 flex items-center gap-1"><i class="fas fa-bell-slash"></i> Suppressed</span>
                                <?php else: ?>
                                    <span class="text-slate-500 flex items-center gap-1"><i class="fas fa-bell"></i> Alerts Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button onclick="deleteMaintenanceWindow('<?= $w['id'] ?>')" class="text-red-400 hover:text-red-300 text-xs transition">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Schedule Maintenance Window -->
<div id="scheduleModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-calendar-plus text-amber-400"></i> Schedule Maintenance Window
            </h3>
            <button onclick="closeScheduleModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>
        <form onsubmit="saveMaintenanceWindow(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Title / Maintenance Purpose</label>
                <input type="text" id="maintTitle" required placeholder="e.g. Core Switch Firmware Upgrade" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Target Scope</label>
                <select id="maintTargetType" onchange="toggleScopeInputs()" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none">
                    <option value="all">Entire Infrastructure (Global)</option>
                    <option value="device">Specific Network Device</option>
                    <option value="map">Specific Topology Map</option>
                </select>
            </div>

            <div id="scopeDeviceWrapper" class="hidden">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Select Device</label>
                <select id="maintDeviceId" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none">
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip_address']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="scopeMapWrapper" class="hidden">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Select Topology Map</label>
                <select id="maintMapId" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none">
                    <?php foreach ($maps as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Start Date & Time</label>
                    <input type="datetime-local" id="maintStartTime" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">End Date & Time</label>
                    <input type="datetime-local" id="maintEndTime" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Notes / Scope Details</label>
                <textarea id="maintNotes" rows="2" placeholder="Engineers involved, rollback plans, ticket #..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 outline-none"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" id="maintSuppressAlerts" checked class="w-4 h-4 rounded text-amber-500 bg-slate-950 border-slate-800">
                <label for="maintSuppressAlerts" class="text-xs text-slate-300">Suppress Telegram / SMS / Email alerts during maintenance</label>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">Schedule Window</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleScopeInputs() {
    const val = document.getElementById('maintTargetType').value;
    document.getElementById('scopeDeviceWrapper').classList.toggle('hidden', val !== 'device');
    document.getElementById('scopeMapWrapper').classList.toggle('hidden', val !== 'map');
}

function openScheduleModal() {
    const now = new Date();
    const startStr = now.toISOString().slice(0, 16);
    const end = new Date(now.getTime() + 2 * 60 * 60 * 1000); // 2 hours default
    const endStr = end.toISOString().slice(0, 16);

    document.getElementById('maintStartTime').value = startStr;
    document.getElementById('maintEndTime').value = endStr;

    const m = document.getElementById('scheduleModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeScheduleModal() {
    const m = document.getElementById('scheduleModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

async function saveMaintenanceWindow(e) {
    e.preventDefault();
    const type = document.getElementById('maintTargetType').value;
    let targetId = null;
    if (type === 'device') targetId = document.getElementById('maintDeviceId').value;
    if (type === 'map') targetId = document.getElementById('maintMapId').value;

    const payload = {
        title: document.getElementById('maintTitle').value,
        target_type: type,
        target_id: targetId,
        start_time: document.getElementById('maintStartTime').value.replace('T', ' ') + ':00',
        end_time: document.getElementById('maintEndTime').value.replace('T', ' ') + ':00',
        suppress_alerts: document.getElementById('maintSuppressAlerts').checked ? 1 : 0,
        notes: document.getElementById('maintNotes').value
    };

    try {
        const res = await fetch('api.php?action=create_maintenance_window', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            alert('Failed: ' + (json.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error');
    }
}

async function deleteMaintenanceWindow(id) {
    if (!confirm('Are you sure you want to delete this maintenance schedule?')) return;
    try {
        const res = await fetch(`api.php?action=delete_maintenance_window&id=${id}`, { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            alert('Delete failed');
        }
    } catch (e) {
        alert('Network error');
    }
}
</script>

<?php include 'footer.php'; ?>
