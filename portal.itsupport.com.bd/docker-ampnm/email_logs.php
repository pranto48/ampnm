<?php
require_once 'includes/auth_check.php';

// Only admins can access this page
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white mb-1">
                    <i class="fas fa-envelope text-cyan-400 mr-2"></i>Email Logs & Queue
                </h1>
                <p class="text-slate-400 text-sm">Monitor email delivery, manage queue, and configure log retention</p>
            </div>
            <div class="flex gap-2">
                <button onclick="openSettingsModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-cog mr-2"></i>Settings
                </button>
                <button onclick="processQueue()" id="processQueueBtn" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-play mr-2"></i>Process Queue
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-xs uppercase">Pending</p>
                        <p class="text-2xl font-bold text-amber-400" id="stat-pending">0</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-amber-500/20 flex items-center justify-center">
                        <i class="fas fa-clock text-amber-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-xs uppercase">Sent (24h)</p>
                        <p class="text-2xl font-bold text-green-400" id="stat-sent">0</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-green-500/20 flex items-center justify-center">
                        <i class="fas fa-check text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-xs uppercase">Failed (24h)</p>
                        <p class="text-2xl font-bold text-red-400" id="stat-failed">0</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center">
                        <i class="fas fa-times text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-xs uppercase">Total Sent</p>
                        <p class="text-2xl font-bold text-cyan-400" id="stat-total">0</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-cyan-500/20 flex items-center justify-center">
                        <i class="fas fa-envelope text-cyan-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-slate-700 mb-6">
            <button onclick="switchTab('queue')" id="tab-queue" class="px-4 py-3 text-sm font-medium border-b-2 border-cyan-500 text-cyan-400">
                <i class="fas fa-list-ul mr-2"></i>Email Queue
            </button>
            <button onclick="switchTab('logs')" id="tab-logs" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-400 hover:text-white">
                <i class="fas fa-history mr-2"></i>Delivery Logs
            </button>
            <button onclick="switchTab('alerts')" id="tab-alerts" class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-400 hover:text-white">
                <i class="fas fa-bell mr-2"></i>Alert History
            </button>
        </div>

        <!-- Email Queue Tab -->
        <div id="panel-queue" class="tab-panel">
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 overflow-hidden">
                <div class="p-4 border-b border-slate-700 flex justify-between items-center">
                    <div class="flex gap-2">
                        <select id="queue-status-filter" onchange="loadQueue()" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-white">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="sent">Sent</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <button onclick="loadQueue()" class="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Recipient</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Priority</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Attempts</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Created</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="queue-table" class="divide-y divide-slate-700">
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="queue-pagination" class="p-4 border-t border-slate-700 flex justify-between items-center"></div>
            </div>
        </div>

        <!-- Delivery Logs Tab -->
        <div id="panel-logs" class="tab-panel hidden">
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 overflow-hidden">
                <div class="p-4 border-b border-slate-700 flex justify-between items-center gap-4">
                    <div class="flex gap-2 flex-1">
                        <select id="logs-status-filter" onchange="loadLogs()" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-white">
                            <option value="">All Statuses</option>
                            <option value="sent">Sent</option>
                            <option value="failed">Failed</option>
                        </select>
                        <input type="text" id="logs-search" placeholder="Search recipient or subject..." onkeyup="debounceSearch()" class="flex-1 max-w-xs px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-white">
                    </div>
                    <button onclick="cleanupLogs()" class="px-3 py-2 bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded-lg text-sm">
                        <i class="fas fa-trash mr-1"></i>Cleanup Old Logs
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Recipient</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Attempts</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Sent At</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Error</th>
                            </tr>
                        </thead>
                        <tbody id="logs-table" class="divide-y divide-slate-700">
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="logs-pagination" class="p-4 border-t border-slate-700 flex justify-between items-center"></div>
            </div>
        </div>

        <!-- Alert History Tab -->
        <div id="panel-alerts" class="tab-panel hidden">
            <div class="bg-slate-800/50 rounded-xl border border-slate-700 overflow-hidden">
                <div class="p-4 border-b border-slate-700 flex justify-between items-center gap-4">
                    <div class="flex gap-2">
                        <select id="alerts-level-filter" onchange="loadAlertLogs()" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-white">
                            <option value="">All Levels</option>
                            <option value="warning">Warning</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <button onclick="cleanupAlertLogs()" class="px-3 py-2 bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded-lg text-sm">
                        <i class="fas fa-trash mr-1"></i>Cleanup Old Alerts
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Host</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">IP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Level</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Value</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Threshold</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Sent At</th>
                            </tr>
                        </thead>
                        <tbody id="alerts-table" class="divide-y divide-slate-700">
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="alerts-pagination" class="p-4 border-t border-slate-700 flex justify-between items-center"></div>
            </div>
        </div>
    </div>
</main>

<!-- Settings Modal -->
<div id="settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-lg max-h-[80vh] overflow-hidden shadow-xl">
        <div class="flex justify-between items-center p-4 border-b border-slate-700">
            <h3 class="text-lg font-bold text-white"><i class="fas fa-cog text-cyan-400 mr-2"></i>Log & Queue Settings</h3>
            <button onclick="closeSettingsModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[60vh] space-y-4">
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <h4 class="text-white font-medium mb-3"><i class="fas fa-clock text-amber-400 mr-2"></i>Log Retention</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Email Log Retention (days)</label>
                        <input type="number" id="setting-log_retention_days" min="1" max="365" 
                               class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Alert Log Retention (days)</label>
                        <input type="number" id="setting-alert_log_retention_days" min="1" max="365" 
                               class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                </div>
            </div>
            
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <h4 class="text-white font-medium mb-3"><i class="fas fa-envelope text-cyan-400 mr-2"></i>Email Queue</h4>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-cyan-500/10 border border-cyan-500/30 rounded-lg">
                        <label class="text-sm text-slate-300">Enable Email Queue System</label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="setting-email_queue_enabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Max Retry Attempts</label>
                            <input type="number" id="setting-email_queue_max_retries" min="1" max="10" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Retry Delay (minutes)</label>
                            <input type="number" id="setting-email_queue_retry_delay" min="1" max="60" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-slate-700 flex justify-end gap-3">
            <button onclick="closeSettingsModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium">
                Cancel
            </button>
            <button onclick="saveSettings()" id="saveSettingsBtn" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</div>

<script>
const API_URL = 'api.php';
let currentQueuePage = 1;
let currentLogsPage = 1;
let currentAlertsPage = 1;
let searchTimeout = null;

const api = {
    get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
    post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
};

// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('[id^="tab-"]').forEach(t => {
        t.classList.remove('border-cyan-500', 'text-cyan-400');
        t.classList.add('border-transparent', 'text-slate-400');
    });
    
    document.getElementById(`panel-${tab}`).classList.remove('hidden');
    const tabBtn = document.getElementById(`tab-${tab}`);
    tabBtn.classList.add('border-cyan-500', 'text-cyan-400');
    tabBtn.classList.remove('border-transparent', 'text-slate-400');
    
    if (tab === 'queue') loadQueue();
    else if (tab === 'logs') loadLogs();
    else if (tab === 'alerts') loadAlertLogs();
}

// Load stats
async function loadStats() {
    try {
        const stats = await api.get('get_email_stats');
        document.getElementById('stat-pending').textContent = stats.queue?.pending || 0;
        document.getElementById('stat-sent').textContent = stats.delivery_24h?.sent || 0;
        document.getElementById('stat-failed').textContent = stats.delivery_24h?.failed || 0;
        document.getElementById('stat-total').textContent = stats.total_sent || 0;
    } catch (e) {
        console.error('Failed to load stats:', e);
    }
}

// Load queue
async function loadQueue(page = 1) {
    currentQueuePage = page;
    const status = document.getElementById('queue-status-filter').value;
    const tbody = document.getElementById('queue-table');
    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>';
    
    try {
        const data = await api.get('get_email_queue', { status, page, per_page: 20 });
        
        if (data.queue.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No emails in queue</td></tr>';
        } else {
            tbody.innerHTML = data.queue.map(item => `
                <tr class="hover:bg-slate-700/30">
                    <td class="px-4 py-3 text-white text-sm">${escapeHtml(item.recipient)}</td>
                    <td class="px-4 py-3 text-slate-300 text-sm max-w-xs truncate">${escapeHtml(item.subject.substring(0, 50))}${item.subject.length > 50 ? '...' : ''}</td>
                    <td class="px-4 py-3">${getPriorityBadge(item.priority)}</td>
                    <td class="px-4 py-3">${getStatusBadge(item.status)}</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${item.attempts}/${item.max_attempts}</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${formatDate(item.created_at)}</td>
                    <td class="px-4 py-3">
                        ${item.status === 'failed' ? `<button onclick="retryEmail(${item.id})" class="text-cyan-400 hover:text-cyan-300 text-sm mr-2"><i class="fas fa-redo"></i> Retry</button>` : ''}
                        ${item.status === 'pending' ? `<button onclick="cancelEmail(${item.id})" class="text-red-400 hover:text-red-300 text-sm"><i class="fas fa-ban"></i> Cancel</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }
        
        renderPagination('queue-pagination', data, loadQueue);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-400">Failed to load queue</td></tr>';
    }
}

// Load logs
async function loadLogs(page = 1) {
    currentLogsPage = page;
    const status = document.getElementById('logs-status-filter').value;
    const search = document.getElementById('logs-search').value;
    const tbody = document.getElementById('logs-table');
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>';
    
    try {
        const data = await api.get('get_email_logs', { status, search, page, per_page: 20 });
        
        if (data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No logs found</td></tr>';
        } else {
            tbody.innerHTML = data.logs.map(log => `
                <tr class="hover:bg-slate-700/30">
                    <td class="px-4 py-3 text-white text-sm">${escapeHtml(log.recipient)}</td>
                    <td class="px-4 py-3 text-slate-300 text-sm max-w-xs truncate">${escapeHtml(log.subject.substring(0, 50))}${log.subject.length > 50 ? '...' : ''}</td>
                    <td class="px-4 py-3">${getStatusBadge(log.status)}</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${log.attempts}</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${formatDate(log.sent_at)}</td>
                    <td class="px-4 py-3 text-red-400 text-xs max-w-xs truncate">${log.error_message || '-'}</td>
                </tr>
            `).join('');
        }
        
        renderPagination('logs-pagination', data, loadLogs);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-400">Failed to load logs</td></tr>';
    }
}

// Load alert logs
async function loadAlertLogs(page = 1) {
    currentAlertsPage = page;
    const level = document.getElementById('alerts-level-filter').value;
    const tbody = document.getElementById('alerts-table');
    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>';
    
    try {
        const data = await api.get('get_alert_logs', { level, page, per_page: 20 });
        
        if (data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No alert logs found</td></tr>';
        } else {
            tbody.innerHTML = data.logs.map(log => `
                <tr class="hover:bg-slate-700/30">
                    <td class="px-4 py-3 text-white text-sm">${escapeHtml(log.host_name || 'Unknown')}</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${escapeHtml(log.host_ip)}</td>
                    <td class="px-4 py-3 text-slate-300 text-sm capitalize">${log.alert_type}</td>
                    <td class="px-4 py-3">${getAlertLevelBadge(log.alert_level)}</td>
                    <td class="px-4 py-3 text-white text-sm font-medium">${log.value}%</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${log.threshold}%</td>
                    <td class="px-4 py-3 text-slate-400 text-sm">${formatDate(log.sent_at)}</td>
                </tr>
            `).join('');
        }
        
        renderPagination('alerts-pagination', data, loadAlertLogs);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-400">Failed to load alert logs</td></tr>';
    }
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString();
}

function getStatusBadge(status) {
    const colors = {
        pending: 'bg-amber-500/20 text-amber-400',
        processing: 'bg-blue-500/20 text-blue-400',
        sent: 'bg-green-500/20 text-green-400',
        failed: 'bg-red-500/20 text-red-400',
        cancelled: 'bg-slate-500/20 text-slate-400',
        bounced: 'bg-orange-500/20 text-orange-400'
    };
    return `<span class="px-2 py-1 rounded-full text-xs ${colors[status] || colors.pending}">${status}</span>`;
}

function getPriorityBadge(priority) {
    const colors = {
        high: 'bg-red-500/20 text-red-400',
        normal: 'bg-slate-500/20 text-slate-400',
        low: 'bg-slate-600/20 text-slate-500'
    };
    return `<span class="px-2 py-1 rounded-full text-xs ${colors[priority] || colors.normal}">${priority}</span>`;
}

function getAlertLevelBadge(level) {
    const colors = {
        warning: 'bg-amber-500/20 text-amber-400',
        critical: 'bg-red-500/20 text-red-400'
    };
    return `<span class="px-2 py-1 rounded-full text-xs ${colors[level] || colors.warning}">${level}</span>`;
}

function renderPagination(containerId, data, loadFn) {
    const container = document.getElementById(containerId);
    if (data.total_pages <= 1) {
        container.innerHTML = `<span class="text-slate-400 text-sm">Showing ${data.logs?.length || data.queue?.length || 0} of ${data.total} entries</span><span></span>`;
        return;
    }
    
    let buttons = '';
    for (let i = 1; i <= data.total_pages; i++) {
        if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
            buttons += `<button onclick="${loadFn.name}(${i})" class="px-3 py-1 rounded ${i === data.page ? 'bg-cyan-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'}">${i}</button>`;
        } else if (i === data.page - 3 || i === data.page + 3) {
            buttons += '<span class="text-slate-500">...</span>';
        }
    }
    
    container.innerHTML = `
        <span class="text-slate-400 text-sm">Showing ${(data.page - 1) * data.per_page + 1}-${Math.min(data.page * data.per_page, data.total)} of ${data.total} entries</span>
        <div class="flex gap-1">${buttons}</div>
    `;
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadLogs(1), 300);
}

// Actions
async function retryEmail(id) {
    try {
        const result = await api.post('retry_email', { id });
        if (result.success) {
            window.notyf.success(result.message);
            loadQueue();
            loadStats();
        } else {
            window.notyf.error(result.message || 'Failed to retry email');
        }
    } catch (e) {
        window.notyf.error('Failed to retry email');
    }
}

async function cancelEmail(id) {
    if (!confirm('Are you sure you want to cancel this email?')) return;
    try {
        const result = await api.post('cancel_email', { id });
        if (result.success) {
            window.notyf.success(result.message);
            loadQueue();
            loadStats();
        } else {
            window.notyf.error(result.message || 'Failed to cancel email');
        }
    } catch (e) {
        window.notyf.error('Failed to cancel email');
    }
}

async function processQueue() {
    const btn = document.getElementById('processQueueBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const result = await api.post('process_email_queue', { limit: 20 });
        if (result.success) {
            window.notyf.success(`Processed ${result.result.processed} emails: ${result.result.success} sent, ${result.result.failed} failed`);
            loadQueue();
            loadLogs();
            loadStats();
        }
    } catch (e) {
        window.notyf.error('Failed to process queue');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play mr-2"></i>Process Queue';
    }
}

async function cleanupLogs() {
    if (!confirm('This will delete old email logs based on retention settings. Continue?')) return;
    try {
        const result = await api.post('cleanup_email_logs');
        if (result.success) {
            window.notyf.success(`Deleted ${result.result.deleted_logs} logs and ${result.result.deleted_queue} queue items`);
            loadLogs();
            loadStats();
        }
    } catch (e) {
        window.notyf.error('Failed to cleanup logs');
    }
}

async function cleanupAlertLogs() {
    if (!confirm('This will delete old alert logs based on retention settings. Continue?')) return;
    try {
        const result = await api.post('cleanup_alert_logs');
        if (result.success) {
            window.notyf.success(`Deleted ${result.deleted} alert log entries`);
            loadAlertLogs();
        }
    } catch (e) {
        window.notyf.error('Failed to cleanup alert logs');
    }
}

// Settings modal
function openSettingsModal() {
    document.getElementById('settings-modal').classList.remove('hidden');
    document.getElementById('settings-modal').classList.add('flex');
    loadSettings();
}

function closeSettingsModal() {
    document.getElementById('settings-modal').classList.add('hidden');
    document.getElementById('settings-modal').classList.remove('flex');
}

async function loadSettings() {
    try {
        const settings = await api.get('get_system_settings');
        Object.keys(settings).forEach(key => {
            const el = document.getElementById(`setting-${key}`);
            if (el) {
                if (el.type === 'checkbox') {
                    el.checked = settings[key].value === 'true';
                } else {
                    el.value = settings[key].value;
                }
            }
        });
    } catch (e) {
        console.error('Failed to load settings:', e);
    }
}

async function saveSettings() {
    const btn = document.getElementById('saveSettingsBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    const settings = {
        log_retention_days: document.getElementById('setting-log_retention_days').value,
        alert_log_retention_days: document.getElementById('setting-alert_log_retention_days').value,
        email_queue_enabled: document.getElementById('setting-email_queue_enabled').checked ? 'true' : 'false',
        email_queue_max_retries: document.getElementById('setting-email_queue_max_retries').value,
        email_queue_retry_delay: document.getElementById('setting-email_queue_retry_delay').value
    };
    
    try {
        const result = await api.post('save_system_settings', settings);
        if (result.success) {
            window.notyf.success('Settings saved successfully');
            closeSettingsModal();
        }
    } catch (e) {
        window.notyf.error('Failed to save settings');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Settings';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadQueue();
});
</script>

<?php include 'footer.php'; ?>
