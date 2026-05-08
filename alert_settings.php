<?php
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';

// Only admins can manage alert settings
if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-bell text-cyan-400"></i> Alert Settings
            </h1>
            <p class="text-slate-400 text-sm mt-1">Configure global and per-host alert thresholds for host metrics monitoring.</p>
        </div>
        <a href="host_metrics.php" class="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Back to Host Metrics
        </a>
    </div>

    <!-- Global Alert Settings -->
    <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-globe text-cyan-400"></i> Global Thresholds
        </h2>
        <p class="text-slate-400 text-sm mb-4">These thresholds apply to all hosts unless overridden per-host below.</p>

        <form id="global-settings-form" class="space-y-4">
            <div class="flex items-center gap-3 mb-4">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="alerts-enabled" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-slate-600 peer-focus:ring-2 peer-focus:ring-cyan-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                </label>
                <span class="text-slate-300 text-sm font-medium">Alerts Enabled</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- CPU -->
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-yellow-400 mb-3"><i class="fas fa-microchip mr-1"></i> CPU</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="text-xs text-slate-400">Warning (%)</label>
                            <input type="number" id="cpu-warning" min="1" max="100" value="80" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400">Critical (%)</label>
                            <input type="number" id="cpu-critical" min="1" max="100" value="95" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                    </div>
                </div>
                <!-- Memory -->
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-blue-400 mb-3"><i class="fas fa-memory mr-1"></i> Memory</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="text-xs text-slate-400">Warning (%)</label>
                            <input type="number" id="memory-warning" min="1" max="100" value="80" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400">Critical (%)</label>
                            <input type="number" id="memory-critical" min="1" max="100" value="95" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                    </div>
                </div>
                <!-- Disk -->
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-green-400 mb-3"><i class="fas fa-hard-drive mr-1"></i> Disk</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="text-xs text-slate-400">Warning (%)</label>
                            <input type="number" id="disk-warning" min="1" max="100" value="80" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400">Critical (%)</label>
                            <input type="number" id="disk-critical" min="1" max="100" value="95" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                    </div>
                </div>
                <!-- Cooldown -->
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-purple-400 mb-3"><i class="fas fa-clock mr-1"></i> Cooldown</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="text-xs text-slate-400">Minutes between alerts</label>
                            <input type="number" id="cooldown-minutes" min="1" max="1440" value="30" class="w-full mt-1 bg-slate-600 border border-slate-500 rounded px-3 py-1.5 text-white text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Prevents alert fatigue by throttling duplicate alerts.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Global Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Per-Host Overrides -->
    <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fas fa-server text-cyan-400"></i> Per-Host Overrides
            </h2>
            <div class="flex gap-2">
                <a href="api.php?action=export_host_overrides&handler=metrics" class="text-slate-400 hover:text-cyan-400 text-sm flex items-center gap-1 transition-colors" title="Export CSV">
                    <i class="fas fa-file-export"></i> Export
                </a>
                <button id="btn-add-override" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1">
                    <i class="fas fa-plus"></i> Add Override
                </button>
            </div>
        </div>
        <p class="text-slate-400 text-sm mb-4">Override global thresholds for specific hosts. Hosts without an override use the global settings above.</p>

        <div id="overrides-loading" class="text-center py-8">
            <div class="inline-block border-4 border-slate-700 border-t-cyan-500 rounded-full w-8 h-8 animate-spin"></div>
            <p class="text-slate-400 text-sm mt-2">Loading overrides...</p>
        </div>

        <div id="overrides-empty" class="hidden text-center py-8">
            <i class="fas fa-check-circle text-slate-600 text-4xl mb-2"></i>
            <p class="text-slate-400 text-sm">No per-host overrides configured. All hosts use global thresholds.</p>
        </div>

        <div id="overrides-table-wrapper" class="hidden overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-slate-400 uppercase bg-slate-700/50">
                    <tr>
                        <th class="px-3 py-2">Host</th>
                        <th class="px-3 py-2">Enabled</th>
                        <th class="px-3 py-2">CPU W/C</th>
                        <th class="px-3 py-2">Mem W/C</th>
                        <th class="px-3 py-2">Disk W/C</th>
                        <th class="px-3 py-2">GPU W/C</th>
                        <th class="px-3 py-2">Delay</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="overrides-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Override Modal -->
<div id="override-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 id="modal-title" class="text-lg font-semibold text-white">Add Host Override</h3>
            <button id="modal-close" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form id="override-form" class="space-y-4">
            <input type="hidden" id="override-original-ip">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-slate-400">Host IP *</label>
                    <input type="text" id="override-host-ip" required class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">Host Name</label>
                    <input type="text" id="override-host-name" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white text-sm">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="override-enabled" checked class="rounded bg-slate-700 border-slate-600 text-cyan-500 focus:ring-cyan-500">
                <label for="override-enabled" class="text-sm text-slate-300">Override Enabled</label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400">CPU Warning (%)</label>
                    <input type="number" id="override-cpu-w" min="1" max="100" value="80" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">CPU Critical (%)</label>
                    <input type="number" id="override-cpu-c" min="1" max="100" value="95" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">Memory Warning (%)</label>
                    <input type="number" id="override-mem-w" min="1" max="100" value="80" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">Memory Critical (%)</label>
                    <input type="number" id="override-mem-c" min="1" max="100" value="95" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">Disk Warning (%)</label>
                    <input type="number" id="override-disk-w" min="1" max="100" value="85" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">Disk Critical (%)</label>
                    <input type="number" id="override-disk-c" min="1" max="100" value="95" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">GPU Warning (%)</label>
                    <input type="number" id="override-gpu-w" min="1" max="100" value="80" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-400">GPU Critical (%)</label>
                    <input type="number" id="override-gpu-c" min="1" max="100" value="95" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-1.5 text-white text-sm">
                </div>
            </div>
            <div>
                <label class="text-xs text-slate-400">Status Delay (seconds)</label>
                <input type="number" id="override-delay" min="0" max="3600" value="0" class="w-full mt-1 bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white text-sm">
                <p class="text-xs text-slate-500 mt-1">Delay before marking host as offline after missed check-in.</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="modal-cancel" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors">Cancel</button>
                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-save mr-1"></i> Save Override
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const API_URL = 'api.php';

// Global Settings
async function loadGlobalSettings() {
    try {
        const res = await fetch(`${API_URL}?action=get_alert_settings&handler=metrics`);
        const data = await res.json();
        if (data) {
            document.getElementById('alerts-enabled').checked = data.enabled != 0;
            document.getElementById('cpu-warning').value = data.cpu_warning_threshold ?? 80;
            document.getElementById('cpu-critical').value = data.cpu_critical_threshold ?? 95;
            document.getElementById('memory-warning').value = data.memory_warning_threshold ?? 80;
            document.getElementById('memory-critical').value = data.memory_critical_threshold ?? 95;
            document.getElementById('disk-warning').value = data.disk_warning_threshold ?? 80;
            document.getElementById('disk-critical').value = data.disk_critical_threshold ?? 95;
            document.getElementById('cooldown-minutes').value = data.cooldown_minutes ?? 30;
        }
    } catch (e) {
        console.error('Failed to load global settings:', e);
    }
}

document.getElementById('global-settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        const body = {
            enabled: document.getElementById('alerts-enabled').checked ? 1 : 0,
            cpu_warning_threshold: +document.getElementById('cpu-warning').value,
            cpu_critical_threshold: +document.getElementById('cpu-critical').value,
            memory_warning_threshold: +document.getElementById('memory-warning').value,
            memory_critical_threshold: +document.getElementById('memory-critical').value,
            disk_warning_threshold: +document.getElementById('disk-warning').value,
            disk_critical_threshold: +document.getElementById('disk-critical').value,
            cooldown_minutes: +document.getElementById('cooldown-minutes').value
        };
        const res = await fetch(`${API_URL}?action=save_alert_settings&handler=metrics`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)
        });
        const result = await res.json();
        if (result.success) {
            showNotice('Global alert settings saved!', 'success');
        } else {
            showNotice('Failed to save settings', 'error');
        }
    } catch (e) {
        showNotice('Error saving settings: ' + e.message, 'error');
    }
});

// Per-Host Overrides
async function loadOverrides() {
    try {
        const res = await fetch(`${API_URL}?action=get_all_host_overrides&handler=metrics`);
        const overrides = await res.json();

        document.getElementById('overrides-loading').classList.add('hidden');

        if (!overrides || overrides.length === 0) {
            document.getElementById('overrides-empty').classList.remove('hidden');
            document.getElementById('overrides-table-wrapper').classList.add('hidden');
            return;
        }

        document.getElementById('overrides-empty').classList.add('hidden');
        document.getElementById('overrides-table-wrapper').classList.remove('hidden');

        const tbody = document.getElementById('overrides-tbody');
        tbody.innerHTML = overrides.map(o => `
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30">
                <td class="px-3 py-2">
                    <div class="font-medium text-white">${escHtml(o.host_name || o.host_ip)}</div>
                    <div class="text-xs text-slate-500">${escHtml(o.host_ip)}</div>
                </td>
                <td class="px-3 py-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${o.enabled == 1 ? 'bg-green-500/20 text-green-400' : 'bg-slate-600/50 text-slate-400'}">
                        ${o.enabled == 1 ? 'Yes' : 'No'}
                    </span>
                </td>
                <td class="px-3 py-2 text-slate-300">${o.cpu_warning ?? '-'}/${o.cpu_critical ?? '-'}</td>
                <td class="px-3 py-2 text-slate-300">${o.memory_warning ?? '-'}/${o.memory_critical ?? '-'}</td>
                <td class="px-3 py-2 text-slate-300">${o.disk_warning ?? '-'}/${o.disk_critical ?? '-'}</td>
                <td class="px-3 py-2 text-slate-300">${o.gpu_warning ?? '-'}/${o.gpu_critical ?? '-'}</td>
                <td class="px-3 py-2 text-slate-300">${o.status_delay_seconds ?? 0}s</td>
                <td class="px-3 py-2 text-right">
                    <button onclick="editOverride('${escHtml(o.host_ip)}')" class="text-cyan-400 hover:text-cyan-300 mr-2" title="Edit"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteOverride('${escHtml(o.host_ip)}')" class="text-red-400 hover:text-red-300" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error('Failed to load overrides:', e);
        document.getElementById('overrides-loading').innerHTML = '<p class="text-red-400 text-sm">Failed to load overrides.</p>';
    }
}

// Modal controls
const modal = document.getElementById('override-modal');
const openModal = (title = 'Add Host Override') => {
    document.getElementById('modal-title').textContent = title;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
};
const closeModal = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('override-form').reset();
    document.getElementById('override-original-ip').value = '';
};

document.getElementById('btn-add-override').addEventListener('click', () => openModal());
document.getElementById('modal-close').addEventListener('click', closeModal);
document.getElementById('modal-cancel').addEventListener('click', closeModal);

async function editOverride(hostIp) {
    try {
        const res = await fetch(`${API_URL}?action=get_host_override&handler=metrics&host_ip=${encodeURIComponent(hostIp)}`);
        const o = await res.json();
        if (!o || o.error) return;

        document.getElementById('override-original-ip').value = hostIp;
        document.getElementById('override-host-ip').value = o.host_ip ?? hostIp;
        document.getElementById('override-host-name').value = o.host_name ?? '';
        document.getElementById('override-enabled').checked = o.enabled == 1;
        document.getElementById('override-cpu-w').value = o.cpu_warning ?? 80;
        document.getElementById('override-cpu-c').value = o.cpu_critical ?? 95;
        document.getElementById('override-mem-w').value = o.memory_warning ?? 80;
        document.getElementById('override-mem-c').value = o.memory_critical ?? 95;
        document.getElementById('override-disk-w').value = o.disk_warning ?? 85;
        document.getElementById('override-disk-c').value = o.disk_critical ?? 95;
        document.getElementById('override-gpu-w').value = o.gpu_warning ?? 80;
        document.getElementById('override-gpu-c').value = o.gpu_critical ?? 95;
        document.getElementById('override-delay').value = o.status_delay_seconds ?? 0;
        openModal('Edit Host Override');
    } catch (e) {
        showNotice('Failed to load override: ' + e.message, 'error');
    }
}

document.getElementById('override-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        const body = {
            host_ip: document.getElementById('override-host-ip').value,
            host_name: document.getElementById('override-host-name').value || document.getElementById('override-host-ip').value,
            enabled: document.getElementById('override-enabled').checked ? 1 : 0,
            cpu_warning: +document.getElementById('override-cpu-w').value,
            cpu_critical: +document.getElementById('override-cpu-c').value,
            memory_warning: +document.getElementById('override-mem-w').value,
            memory_critical: +document.getElementById('override-mem-c').value,
            disk_warning: +document.getElementById('override-disk-w').value,
            disk_critical: +document.getElementById('override-disk-c').value,
            gpu_warning: +document.getElementById('override-gpu-w').value,
            gpu_critical: +document.getElementById('override-gpu-c').value,
            status_delay_seconds: +document.getElementById('override-delay').value
        };
        const res = await fetch(`${API_URL}?action=save_host_override&handler=metrics`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)
        });
        const result = await res.json();
        if (result.success) {
            showNotice('Host override saved!', 'success');
            closeModal();
            loadOverrides();
        } else {
            showNotice(result.error || 'Failed to save override', 'error');
        }
    } catch (e) {
        showNotice('Error: ' + e.message, 'error');
    }
});

async function deleteOverride(hostIp) {
    if (!confirm(`Delete override for ${hostIp}? This host will use global thresholds.`)) return;
    try {
        const res = await fetch(`${API_URL}?action=delete_host_override&handler=metrics`, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ host_ip: hostIp })
        });
        const result = await res.json();
        if (result.success) {
            showNotice('Override deleted', 'success');
            loadOverrides();
        }
    } catch (e) {
        showNotice('Error: ' + e.message, 'error');
    }
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function showNotice(msg, type) {
    const el = document.createElement('div');
    el.className = `fixed top-20 right-4 z-50 px-4 py-3 rounded-lg text-sm font-medium shadow-lg transition-all ${
        type === 'success' ? 'bg-green-500/90 text-white' : 'bg-red-500/90 text-white'
    }`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3000);
}

// Initialize
loadGlobalSettings();
loadOverrides();
</script>

<?php require_once 'footer.php'; ?>
