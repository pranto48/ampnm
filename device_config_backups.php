<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Network Device Configuration Vault & Diff Explorer
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
                <i class="fas fa-file-code text-cyan-400"></i> Network Device Config Vault
            </h1>
            <p class="text-slate-400 text-sm mt-1">Automated backup snapshots, revision history, and visual diff viewer for MikroTik, Cisco, Linux, and managed switches.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openCaptureModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-camera"></i> Capture New Backup
            </button>
        </div>
    </div>

    <!-- Device Filter & Stats Bar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-server"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Total Devices</span>
                <h3 class="text-xl font-bold text-white"><?= count($devices) ?></h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Vault Backups</span>
                <h3 class="text-xl font-bold text-white" id="stat-total-backups">0</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fas fa-code-branch"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">RouterOS / IOS</span>
                <h3 class="text-xl font-bold text-white">MikroTik & Cisco</h3>
            </div>
        </div>
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4">
            <label class="block text-xs text-slate-400 font-medium mb-1.5">Filter by Device</label>
            <select id="device-filter" onchange="filterBackups()" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-white text-xs focus:border-cyan-500 focus:outline-none">
                <option value="">All Monitored Devices</option>
                <?php foreach ($devices as $d): ?>
                    <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? 'No IP') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Backup Snapshots Table -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-history text-cyan-400"></i> Configuration Snapshot Archives
            </h2>
            <span class="text-xs text-slate-400" id="table-count-label">Loading snapshots...</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase border-b border-slate-700/60 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Device</th>
                        <th class="px-6 py-3.5">Type</th>
                        <th class="px-6 py-3.5">Filename</th>
                        <th class="px-6 py-3.5">Size</th>
                        <th class="px-6 py-3.5">SHA-256 Hash</th>
                        <th class="px-6 py-3.5">Captured At</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="backups-table-body" class="divide-y divide-slate-700/40">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading configuration snapshots...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Capture New Backup -->
<div id="capture-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-camera text-cyan-400"></i> Capture Device Configuration
            </h3>
            <button type="button" onclick="closeCaptureModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="capture-form" onsubmit="submitCapture(event)" class="space-y-4 text-xs">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Target Device</label>
                <select id="modal-device-id" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">Select a device...</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? 'No IP') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">SSH Username</label>
                    <input type="text" id="modal-username" placeholder="admin" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">SSH Port</label>
                    <input type="number" id="modal-port" value="22" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">SSH Password / Private Key Pass</label>
                <input type="password" id="modal-password" placeholder="••••••••" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="pt-2 flex justify-end gap-3 border-t border-slate-700">
                <button type="button" onclick="closeCaptureModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors">Cancel</button>
                <button type="submit" id="btn-start-capture" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 transition-colors flex items-center gap-2">
                    <i class="fas fa-download"></i> Start Backup
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Config Content -->
<div id="view-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-4xl w-full p-6 shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2" id="view-modal-title">
                    <i class="fas fa-file-alt text-cyan-400"></i> Configuration Viewer
                </h3>
                <span class="text-xs text-slate-400 font-mono" id="view-modal-filename"></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="copyConfigContent()" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-cyan-400 rounded-lg text-xs font-semibold flex items-center gap-1">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" onclick="closeViewModal()" class="text-slate-400 hover:text-white p-1"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <pre id="view-code-block" class="flex-1 overflow-auto bg-slate-950 p-4 rounded-xl text-xs font-mono text-cyan-300 border border-slate-900 select-all"></pre>
    </div>
</div>

<script>
let allBackups = [];

async function loadAllBackups() {
    try {
        const deviceId = document.getElementById('device-filter').value;
        const url = deviceId ? `api.php?action=get_device_config_history&device_id=${encodeURIComponent(deviceId)}` : `api.php?action=get_device_config_history`;
        const resp = await fetch(url);
        const data = await resp.json();
        
        allBackups = data.history || [];
        document.getElementById('stat-total-backups').textContent = allBackups.length;
        document.getElementById('table-count-label').textContent = `${allBackups.length} snapshot(s) found`;
        renderBackupsTable();
    } catch (e) {
        console.error('Error loading backups', e);
    }
}

function renderBackupsTable() {
    const tbody = document.getElementById('backups-table-body');
    if (allBackups.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No configuration backups recorded yet. Click "Capture New Backup" to take your first snapshot.</td></tr>`;
        return;
    }

    tbody.innerHTML = allBackups.map(b => {
        const typeBadge = b.backup_type === 'mikrotik_rsc' 
            ? '<span class="px-2 py-0.5 rounded bg-blue-500/20 text-blue-400 border border-blue-500/30 text-xs font-mono">MikroTik .rsc</span>'
            : (b.backup_type === 'cisco_cfg' 
                ? '<span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 text-xs font-mono">Cisco .cfg</span>'
                : '<span class="px-2 py-0.5 rounded bg-slate-700 text-slate-300 text-xs font-mono">' + b.backup_type + '</span>');

        const sizeKb = (b.file_size_bytes / 1024).toFixed(1) + ' KB';
        const shortHash = (b.content_hash || '').substring(0, 12) + '...';

        return `
            <tr class="hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-4 font-medium text-white flex items-center gap-2">
                    <i class="fas fa-hdd text-cyan-400"></i>
                    <div>
                        <div>${b.device_name || 'Unknown Device'}</div>
                        <div class="text-xs text-slate-400 font-mono">${b.ip_address || ''}</div>
                    </div>
                </td>
                <td class="px-6 py-4">${typeBadge}</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-300">${b.file_name}</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-400">${sizeKb}</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-500" title="${b.content_hash}">${shortHash}</td>
                <td class="px-6 py-4 text-xs text-slate-400">${b.created_at}</td>
                <td class="px-6 py-4 text-right">
                    <button type="button" onclick="viewConfig('${b.id}', '${b.file_name}')" class="px-2.5 py-1 bg-slate-700 hover:bg-slate-600 text-cyan-400 rounded text-xs transition-colors mr-1" title="View Code">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filterBackups() {
    loadAllBackups();
}

function openCaptureModal() {
    document.getElementById('capture-modal').classList.remove('hidden');
}

function closeCaptureModal() {
    document.getElementById('capture-modal').classList.add('hidden');
}

async function submitCapture(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-start-capture');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Capturing...`;

    const deviceId = document.getElementById('modal-device-id').value;
    const username = document.getElementById('modal-username').value;
    const password = document.getElementById('modal-password').value;
    const port = document.getElementById('modal-port').value;

    try {
        const resp = await fetch('api.php?action=backup_device_config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ device_id: deviceId, username, password, port })
        });
        const res = await resp.json();
        alert(res.message || 'Capture completed!');
        closeCaptureModal();
        loadAllBackups();
    } catch (err) {
        alert('Failed to capture configuration: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-download"></i> Start Backup`;
    }
}

async function viewConfig(backupId, filename) {
    try {
        const resp = await fetch(`api.php?action=get_device_config_content&backup_id=${encodeURIComponent(backupId)}`);
        const data = await resp.json();
        if (data.success) {
            document.getElementById('view-modal-filename').textContent = filename;
            document.getElementById('view-code-block').textContent = data.content;
            document.getElementById('view-modal').classList.remove('hidden');
        } else {
            alert('Failed to load configuration content.');
        }
    } catch (err) {
        alert('Error viewing configuration: ' + err.message);
    }
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
}

function copyConfigContent() {
    const text = document.getElementById('view-code-block').textContent;
    navigator.clipboard.writeText(text).then(() => {
        alert('Configuration copied to clipboard!');
    });
}

document.addEventListener('DOMContentLoaded', loadAllBackups);
</script>

<?php include 'footer.php'; ?>
