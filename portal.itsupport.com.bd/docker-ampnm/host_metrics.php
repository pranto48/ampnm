<?php
require_once 'includes/auth_check.php';
require_once 'header.php';
$user_role = $_SESSION['user_role'] ?? 'viewer';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-1">
                <i class="fas fa-microchip text-cyan-400 mr-2"></i>Host Metrics
            </h1>
            <p class="text-slate-400 text-sm">Monitor CPU, Memory, Disk, Network and GPU from Windows agents</p>
        </div>
        
        <?php if ($user_role === 'admin'): ?>
        <div class="flex gap-2">
            <button onclick="openAlertSettingsModal()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-bell mr-2"></i>Alert Thresholds
            </button>
            <button onclick="openTokenModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-key mr-2"></i>Manage Agent Tokens
            </button>
            <button onclick="downloadAgent()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-download mr-2"></i>Download Agent
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Monitored Hosts Grid -->
    <div id="hosts-container" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        <div class="col-span-full flex items-center justify-center py-12 text-slate-500">
            <i class="fas fa-spinner fa-spin mr-2"></i> Loading monitored hosts...
        </div>
    </div>
    
    <!-- Selected Host Detail -->
    <div id="host-detail" class="hidden bg-slate-800/50 rounded-xl border border-slate-700 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 id="detail-host-name" class="text-xl font-bold text-white">Host Details</h2>
            <div class="flex gap-4 items-center">
                <select id="chart-range" class="px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-sm text-white">
                    <option value="1">Last 1 Hour</option>
                    <option value="6">Last 6 Hours</option>
                    <option value="24" selected>Last 24 Hours</option>
                    <option value="72">Last 3 Days</option>
                    <option value="168">Last 7 Days</option>
                </select>
                <button onclick="closeHostDetail()" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <h3 class="text-sm font-medium text-slate-400 mb-3"><i class="fas fa-microchip text-cyan-400 mr-2"></i>CPU Usage</h3>
                <canvas id="chart-cpu" height="150"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <h3 class="text-sm font-medium text-slate-400 mb-3"><i class="fas fa-memory text-purple-400 mr-2"></i>Memory Usage</h3>
                <canvas id="chart-memory" height="150"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <h3 class="text-sm font-medium text-slate-400 mb-3"><i class="fas fa-hdd text-green-400 mr-2"></i>Disk Usage</h3>
                <canvas id="chart-disk" height="150"></canvas>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                <h3 class="text-sm font-medium text-slate-400 mb-3"><i class="fas fa-network-wired text-orange-400 mr-2"></i>Network Throughput</h3>
                <canvas id="chart-network" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Agent Tokens Modal -->
<div id="token-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-2xl max-h-[80vh] overflow-hidden shadow-xl">
        <div class="flex justify-between items-center p-4 border-b border-slate-700">
            <h3 class="text-lg font-bold text-white"><i class="fas fa-key text-cyan-400 mr-2"></i>Agent Tokens</h3>
            <button onclick="closeTokenModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[60vh]">
            <div class="flex justify-between items-center mb-4">
                <p class="text-slate-400 text-sm">Tokens are used to authenticate Windows monitoring agents.</p>
                <button onclick="createToken()" class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i>Create Token
                </button>
            </div>
            <div id="tokens-list" class="space-y-3">
                <div class="text-center text-slate-500 py-4">
                    <i class="fas fa-spinner fa-spin mr-2"></i> Loading tokens...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Settings Modal -->
<div id="alert-settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-lg max-h-[80vh] overflow-hidden shadow-xl">
        <div class="flex justify-between items-center p-4 border-b border-slate-700">
            <h3 class="text-lg font-bold text-white"><i class="fas fa-bell text-amber-400 mr-2"></i>Alert Thresholds</h3>
            <button onclick="closeAlertSettingsModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[60vh]">
            <p class="text-slate-400 text-sm mb-4">Configure warning and critical thresholds for system alerts. Email notifications will be sent when thresholds are exceeded.</p>
            
            <div class="space-y-5">
                <!-- CPU Thresholds -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-microchip text-cyan-400 mr-2"></i>CPU Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="cpu-warning" min="0" max="100" value="80" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="cpu-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        </div>
                    </div>
                </div>
                
                <!-- Memory Thresholds -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-memory text-purple-400 mr-2"></i>Memory Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="memory-warning" min="0" max="100" value="80" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="memory-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        </div>
                    </div>
                </div>
                
                <!-- Disk Thresholds -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-hdd text-green-400 mr-2"></i>Disk Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="disk-warning" min="0" max="100" value="85" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="disk-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        </div>
                    </div>
                </div>
                
                <!-- GPU Thresholds -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-tv text-orange-400 mr-2"></i>GPU Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="gpu-warning" min="0" max="100" value="80" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="gpu-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        </div>
                    </div>
                </div>
                
                <!-- Alert Cooldown -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-clock text-slate-400 mr-2"></i>Alert Cooldown</h4>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Minutes between repeat alerts for same host</label>
                        <input type="number" id="alert-cooldown" min="5" max="1440" value="30" 
                               class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-slate-700 flex justify-end gap-3">
            <button onclick="closeAlertSettingsModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors">
                Cancel
            </button>
            <button onclick="saveAlertSettings()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });
let selectedHostIp = null;
let charts = {};
let autoRefreshInterval = null;

// Host card template
function createHostCard(host) {
    const lastUpdate = host.created_at ? new Date(host.created_at).toLocaleString() : 'Never';
    const isRecent = host.created_at && (Date.now() - new Date(host.created_at).getTime()) < 300000; // 5 min
    const statusClass = isRecent ? 'bg-green-500' : 'bg-red-500';
    const statusText = isRecent ? 'Online' : 'Offline';
    
    return `
        <div class="host-card bg-slate-800/50 rounded-xl border border-slate-700 hover:border-cyan-500/50 transition-all cursor-pointer p-4" 
             onclick="selectHost('${host.host_ip}', '${host.host_name || host.host_ip}')">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full ${statusClass}"></span>
                        <h3 class="text-white font-medium">${host.host_name || 'Unknown Host'}</h3>
                    </div>
                    <p class="text-slate-400 text-xs">${host.host_ip}</p>
                    ${host.device_name ? `<p class="text-cyan-400 text-xs mt-1"><i class="fas fa-link mr-1"></i>${host.device_name}</p>` : ''}
                </div>
                <span class="px-2 py-1 text-xs rounded ${isRecent ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}">${statusText}</span>
            </div>
            
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs">CPU</span>
                        <span class="text-cyan-400 font-medium">${host.cpu_percent !== null ? host.cpu_percent + '%' : 'N/A'}</span>
                    </div>
                    <div class="h-1 bg-slate-700 rounded mt-1">
                        <div class="h-1 bg-cyan-500 rounded" style="width: ${host.cpu_percent || 0}%"></div>
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs">Memory</span>
                        <span class="text-purple-400 font-medium">${host.memory_percent !== null ? host.memory_percent + '%' : 'N/A'}</span>
                    </div>
                    <div class="h-1 bg-slate-700 rounded mt-1">
                        <div class="h-1 bg-purple-500 rounded" style="width: ${host.memory_percent || 0}%"></div>
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs">Disk</span>
                        <span class="text-green-400 font-medium">${host.disk_percent !== null ? host.disk_percent + '%' : 'N/A'}</span>
                    </div>
                    <div class="h-1 bg-slate-700 rounded mt-1">
                        <div class="h-1 bg-green-500 rounded" style="width: ${host.disk_percent || 0}%"></div>
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-xs">GPU</span>
                        <span class="text-orange-400 font-medium">${host.gpu_percent !== null ? host.gpu_percent + '%' : 'N/A'}</span>
                    </div>
                    <div class="h-1 bg-slate-700 rounded mt-1">
                        <div class="h-1 bg-orange-500 rounded" style="width: ${host.gpu_percent || 0}%"></div>
                    </div>
                </div>
            </div>
            
            <p class="text-slate-500 text-xs mt-3 text-right">Updated: ${lastUpdate}</p>
        </div>
    `;
}

// Load all hosts
async function loadHosts() {
    try {
        const response = await fetch('api.php?action=get_all_hosts');
        const hosts = await response.json();
        
        const container = document.getElementById('hosts-container');
        
        if (!hosts || hosts.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-desktop text-4xl text-slate-600 mb-4"></i>
                    <p class="text-slate-400">No monitored hosts yet</p>
                    <p class="text-slate-500 text-sm mt-2">Install the Windows Agent on your servers to start monitoring</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = hosts.map(createHostCard).join('');
    } catch (error) {
        console.error('Failed to load hosts:', error);
        notyf.error('Failed to load hosts');
    }
}

// Select and show host details
async function selectHost(ip, name) {
    selectedHostIp = ip;
    document.getElementById('detail-host-name').textContent = name;
    document.getElementById('host-detail').classList.remove('hidden');
    
    // Scroll to detail
    document.getElementById('host-detail').scrollIntoView({ behavior: 'smooth' });
    
    await loadCharts();
    
    // Start auto-refresh
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(loadCharts, 60000);
}

function closeHostDetail() {
    document.getElementById('host-detail').classList.add('hidden');
    selectedHostIp = null;
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
}

// Load charts for selected host
async function loadCharts() {
    if (!selectedHostIp) return;
    
    const hours = document.getElementById('chart-range').value;
    
    try {
        const response = await fetch(`api.php?action=get_metrics_history&host_ip=${selectedHostIp}&hours=${hours}`);
        const data = await response.json();
        
        if (!data || data.length === 0) {
            notyf.error('No historical data available');
            return;
        }
        
        const labels = data.map(d => new Date(d.created_at));
        
        // Initialize or update charts
        updateChart('chart-cpu', labels, data.map(d => d.cpu_percent), 'CPU %', '#22d3ee');
        updateChart('chart-memory', labels, data.map(d => d.memory_percent), 'Memory %', '#a855f7');
        updateChart('chart-disk', labels, data.map(d => d.disk_percent), 'Disk %', '#22c55e');
        updateNetworkChart('chart-network', labels, 
            data.map(d => d.network_in_mbps), 
            data.map(d => d.network_out_mbps)
        );
        
    } catch (error) {
        console.error('Failed to load chart data:', error);
        notyf.error('Failed to load metrics history');
    }
}

function updateChart(canvasId, labels, data, label, color) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    if (charts[canvasId]) {
        charts[canvasId].data.labels = labels;
        charts[canvasId].data.datasets[0].data = data;
        charts[canvasId].update('none');
        return;
    }
    
    charts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: color + '20',
                fill: true,
                tension: 0.3,
                pointRadius: 0,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    type: 'time',
                    time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                    grid: { color: '#334155' },
                    ticks: { color: '#94a3b8' }
                },
                y: {
                    min: 0,
                    max: 100,
                    grid: { color: '#334155' },
                    ticks: { color: '#94a3b8', callback: v => v + '%' }
                }
            }
        }
    });
}

function updateNetworkChart(canvasId, labels, inData, outData) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    if (charts[canvasId]) {
        charts[canvasId].data.labels = labels;
        charts[canvasId].data.datasets[0].data = inData;
        charts[canvasId].data.datasets[1].data = outData;
        charts[canvasId].update('none');
        return;
    }
    
    charts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'In (Mbps)',
                    data: inData,
                    borderColor: '#22c55e',
                    backgroundColor: '#22c55e20',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                },
                {
                    label: 'Out (Mbps)',
                    data: outData,
                    borderColor: '#f97316',
                    backgroundColor: '#f9731620',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    display: true, 
                    position: 'top',
                    labels: { color: '#94a3b8', usePointStyle: true }
                } 
            },
            scales: {
                x: {
                    type: 'time',
                    time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                    grid: { color: '#334155' },
                    ticks: { color: '#94a3b8' }
                },
                y: {
                    min: 0,
                    grid: { color: '#334155' },
                    ticks: { color: '#94a3b8', callback: v => v + ' Mbps' }
                }
            }
        }
    });
}

// Token management
function openTokenModal() {
    document.getElementById('token-modal').classList.remove('hidden');
    document.getElementById('token-modal').classList.add('flex');
    loadTokens();
}

function closeTokenModal() {
    document.getElementById('token-modal').classList.add('hidden');
    document.getElementById('token-modal').classList.remove('flex');
}

async function loadTokens() {
    try {
        const response = await fetch('api.php?action=get_agent_tokens');
        const tokens = await response.json();
        
        const container = document.getElementById('tokens-list');
        
        if (!tokens || tokens.length === 0) {
            container.innerHTML = `
                <div class="text-center text-slate-500 py-4">
                    <p>No tokens created yet</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = tokens.map(token => {
            const tokenStr = token.token || '';
            const tokenPreview = tokenStr.length > 16 ? tokenStr.substring(0, 16) + '...' : tokenStr;
            const isEnabled = token.enabled == 1 || token.enabled === true;
            const lastUsed = token.last_used_at ? new Date(token.last_used_at).toLocaleString() : 'Never';
            
            return `
                <div class="bg-slate-900/50 rounded-lg p-3 border border-slate-700">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium">${token.name || 'Unnamed Token'}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <code class="text-xs text-cyan-400 bg-slate-800 px-2 py-1 rounded cursor-pointer hover:bg-slate-700 transition-colors" 
                                      onclick="copyToken(this, '${tokenStr}')" title="Click to copy full token">
                                    ${tokenPreview}
                                </code>
                                <button onclick="copyToken(this, '${tokenStr}')" class="text-xs text-slate-400 hover:text-cyan-400 transition-colors" title="Copy token">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <p class="text-slate-500 text-xs mt-1">
                                Last used: ${lastUsed}
                            </p>
                        </div>
                        <div class="flex gap-2 ml-2">
                            <button onclick="toggleToken(${token.id}, ${!isEnabled})" 
                                    class="px-2 py-1 text-xs rounded ${isEnabled ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}">
                                ${isEnabled ? 'Enabled' : 'Disabled'}
                            </button>
                            <button onclick="deleteToken(${token.id})" class="px-2 py-1 text-xs bg-red-500/20 text-red-400 rounded hover:bg-red-500/30">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Failed to load tokens:', error);
        notyf.error('Failed to load tokens');
    }
}

async function createToken() {
    const name = prompt('Enter a name for this token (e.g., "Production Server"):');
    if (!name) return;
    
    try {
        const response = await fetch('api.php?action=create_agent_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        const result = await response.json();
        
        if (result.success) {
            notyf.success('Token created!');
            // Show the full token once
            alert(`Token created successfully!\n\nFull Token (copy now, it won't be shown again in full):\n\n${result.token}`);
            loadTokens();
        } else {
            notyf.error(result.error || 'Failed to create token');
        }
    } catch (error) {
        console.error('Failed to create token:', error);
        notyf.error('Failed to create token');
    }
}

async function deleteToken(id) {
    if (!confirm('Delete this token? Any agents using it will stop working.')) return;
    
    try {
        const response = await fetch('api.php?action=delete_agent_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await response.json();
        
        if (result.success) {
            notyf.success('Token deleted');
            loadTokens();
        } else {
            notyf.error(result.error || 'Failed to delete token');
        }
    } catch (error) {
        console.error('Failed to delete token:', error);
        notyf.error('Failed to delete token');
    }
}

async function toggleToken(id, enabled) {
    try {
        const response = await fetch('api.php?action=toggle_agent_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, enabled })
        });
        const result = await response.json();
        
        if (result.success) {
            notyf.success(enabled ? 'Token enabled' : 'Token disabled');
            loadTokens();
        }
    } catch (error) {
        console.error('Failed to toggle token:', error);
    }
}

function copyToken(element, token) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(token).then(() => {
            notyf.success('Token copied to clipboard');
        }).catch(() => {
            fallbackCopy(token);
        });
    } else {
        fallbackCopy(token);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        notyf.success('Token copied to clipboard');
    } catch (err) {
        notyf.error('Failed to copy. Please copy manually.');
        prompt('Copy this token:', text);
    }
    document.body.removeChild(textArea);
}

function downloadAgent() {
    window.location.href = 'download-agent.php?file=AMPNM-Agent-Installer.ps1';
}

// Alert Settings Management
function openAlertSettingsModal() {
    document.getElementById('alert-settings-modal').classList.remove('hidden');
    document.getElementById('alert-settings-modal').classList.add('flex');
    loadAlertSettings();
}

function closeAlertSettingsModal() {
    document.getElementById('alert-settings-modal').classList.add('hidden');
    document.getElementById('alert-settings-modal').classList.remove('flex');
}

async function loadAlertSettings() {
    try {
        const response = await fetch('api.php?action=get_alert_settings');
        const data = await response.json();
        
        if (data && data.success !== false) {
            document.getElementById('cpu-warning').value = data.cpu_warning ?? 80;
            document.getElementById('cpu-critical').value = data.cpu_critical ?? 95;
            document.getElementById('memory-warning').value = data.memory_warning ?? 80;
            document.getElementById('memory-critical').value = data.memory_critical ?? 95;
            document.getElementById('disk-warning').value = data.disk_warning ?? 85;
            document.getElementById('disk-critical').value = data.disk_critical ?? 95;
            document.getElementById('gpu-warning').value = data.gpu_warning ?? 80;
            document.getElementById('gpu-critical').value = data.gpu_critical ?? 95;
            document.getElementById('alert-cooldown').value = data.cooldown_minutes ?? 30;
        }
    } catch (error) {
        console.error('Failed to load alert settings:', error);
    }
}

async function saveAlertSettings() {
    const settings = {
        cpu_warning: parseInt(document.getElementById('cpu-warning').value) || 80,
        cpu_critical: parseInt(document.getElementById('cpu-critical').value) || 95,
        memory_warning: parseInt(document.getElementById('memory-warning').value) || 80,
        memory_critical: parseInt(document.getElementById('memory-critical').value) || 95,
        disk_warning: parseInt(document.getElementById('disk-warning').value) || 85,
        disk_critical: parseInt(document.getElementById('disk-critical').value) || 95,
        gpu_warning: parseInt(document.getElementById('gpu-warning').value) || 80,
        gpu_critical: parseInt(document.getElementById('gpu-critical').value) || 95,
        cooldown_minutes: parseInt(document.getElementById('alert-cooldown').value) || 30
    };
    
    // Validate thresholds
    const metrics = ['cpu', 'memory', 'disk', 'gpu'];
    for (const metric of metrics) {
        if (settings[`${metric}_warning`] >= settings[`${metric}_critical`]) {
            notyf.error(`${metric.toUpperCase()} warning must be less than critical`);
            return;
        }
    }
    
    try {
        const response = await fetch('api.php?action=save_alert_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });
        const result = await response.json();
        
        if (result.success) {
            notyf.success('Alert settings saved');
            closeAlertSettingsModal();
        } else {
            notyf.error(result.error || 'Failed to save settings');
        }
    } catch (error) {
        console.error('Failed to save alert settings:', error);
        notyf.error('Failed to save settings');
    }
}

// Range change handler
document.getElementById('chart-range').addEventListener('change', loadCharts);

// Initial load
loadHosts();

// Auto-refresh hosts every 30 seconds
setInterval(loadHosts, 30000);
</script>

<?php require_once 'footer.php'; ?>
