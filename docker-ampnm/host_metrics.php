<?php
require_once 'includes/auth_check.php';
require_once 'header.php';
$user_role = $_SESSION['user_role'] ?? 'viewer';

// Auto-detect server URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// Include the app base path (e.g. /docker-ampnm) so generated links work behind subpaths
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$serverUrl = $protocol . $_SERVER['HTTP_HOST'] . ($basePath === '/' ? '' : $basePath);
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-1">
                <i class="fas fa-microchip text-cyan-400 mr-2"></i>Host Metrics
            </h1>
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-slate-400 text-sm">Monitor CPU, Memory, Disk, Network and GPU from Windows agents</p>
                <span id="agent-health-badge" class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium border border-slate-600 bg-slate-800/60 text-slate-300">
                    <span id="agent-health-dot" class="w-2 h-2 rounded-full bg-slate-500"></span>
                    <span id="agent-health-text">Agent API: Checking…</span>
                </span>
            </div>
        </div>
        
        <?php if ($user_role === 'admin'): ?>
        <div class="flex gap-2 flex-wrap">
            <button onclick="openInstallGuideModal()" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg text-sm font-medium transition-colors shadow-lg">
                <i class="fas fa-book-open mr-2"></i>Installation Guide
            </button>
            <button onclick="openAlertSettingsModal()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-bell mr-2"></i>Alert Thresholds
            </button>
            <button onclick="openTokenModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-key mr-2"></i>Manage Tokens
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Agent Installation Status Summary -->
    <div id="agent-status-summary" class="bg-slate-800/50 rounded-xl border border-slate-700 p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <i class="fas fa-satellite-dish text-cyan-400 text-xl"></i>
                <div>
                    <h3 class="text-white font-medium">Agent Installation Status</h3>
                    <p class="text-slate-400 text-xs">Track which hosts have successfully installed and registered the monitoring agent</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <!-- Notification Controls -->
                <div class="flex items-center gap-1 mr-2">
                    <button id="notification-toggle" onclick="toggleNotifications()" 
                            class="p-2 rounded-lg hover:bg-slate-700 transition-colors text-green-400" 
                            title="Notifications On">
                        <i class="fas fa-bell"></i>
                    </button>
                    <button id="sound-toggle" onclick="toggleSound()" 
                            class="p-2 rounded-lg hover:bg-slate-700 transition-colors text-cyan-400" 
                            title="Sound On">
                        <i class="fas fa-volume-up"></i>
                    </button>
                </div>
                
                <!-- Status Counts -->
                <div class="flex items-center gap-2 px-3 py-2 bg-green-500/10 rounded-lg border border-green-500/30">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="text-green-400 font-medium text-sm" id="registered-count">0</span>
                    <span class="text-slate-400 text-xs">Registered</span>
                </div>
                <div class="flex items-center gap-2 px-3 py-2 bg-cyan-500/10 rounded-lg border border-cyan-500/30">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan-500"></span>
                    <span class="text-cyan-400 font-medium text-sm" id="online-count">0</span>
                    <span class="text-slate-400 text-xs">Online Now</span>
                </div>
                <div class="flex items-center gap-2 px-3 py-2 bg-red-500/10 rounded-lg border border-red-500/30">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <span class="text-red-400 font-medium text-sm" id="offline-count">0</span>
                    <span class="text-slate-400 text-xs">Offline</span>
                </div>
            </div>
        </div>
        
        <!-- First Seen Info -->
        <div id="recent-registrations" class="mt-4 hidden">
            <div class="border-t border-slate-700 pt-3">
                <p class="text-xs text-slate-400 mb-2"><i class="fas fa-clock mr-1"></i>Recent Registrations</p>
                <div id="recent-registrations-list" class="flex flex-wrap gap-2"></div>
            </div>
        </div>
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
    
    <?php if ($user_role === 'admin'): ?>
    <!-- Admin: Bulk Per-Host Overrides Table -->
    <div class="mt-8 bg-slate-800/50 rounded-xl border border-slate-700 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
            <div>
                <h3 class="text-white font-medium flex items-center gap-2"><i class="fas fa-list-ul text-purple-400"></i>Per-Host Threshold Overrides</h3>
                <p class="text-xs text-slate-400">View and adjust status delay and alert thresholds for all hosts with overrides.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs md:text-sm">
                <span class="text-slate-400 mr-1">Quick status delay presets for selected hosts:</span>
                <button type="button" onclick="setBulkStatusDelayPreset(300)" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded border border-slate-600">5m</button>
                <button type="button" onclick="setBulkStatusDelayPreset(900)" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded border border-slate-600">15m</button>
                <button type="button" onclick="setBulkStatusDelayPreset(3600)" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 text-slate-100 rounded border border-slate-600">1h</button>
                <button type="button" onclick="applyBulkStatusDelay()" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded font-medium"><i class="fas fa-check-double mr-1"></i>Apply & Save</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs md:text-sm text-slate-300">
                <thead class="bg-slate-900/60">
                    <tr>
                        <th class="px-3 py-2 text-center whitespace-nowrap">
                            <input type="checkbox" id="host-overrides-select-all" class="rounded border-slate-600 bg-slate-900" onclick="toggleSelectAllOverrides(this)" />
                        </th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Host IP</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Host Name</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">Status Delay (s)</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">CPU W/C</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">Memory W/C</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">Disk W/C</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">GPU W/C</th>
                        <th class="px-3 py-2 text-center whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody id="host-overrides-table-body">
                    <tr>
                        <td colspan="9" class="px-3 py-4 text-center text-slate-500">Loading overrides...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // Expose admin state to the page JS (used to render admin-only controls in host cards)
    const IS_ADMIN = <?= json_encode($user_role === 'admin') ?>;
</script>

<!-- Installation Guide Modal -->
<div id="install-guide-modal" class="fixed inset-0 z-50 hidden flex items-start justify-center bg-black/70 p-4 pt-6">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-5xl h-[95vh] overflow-hidden shadow-xl">
        <div class="flex justify-between items-center p-4 border-b border-slate-700 bg-gradient-to-r from-cyan-600/20 to-blue-600/20">
            <h3 class="text-xl font-bold text-white"><i class="fas fa-book-open text-cyan-400 mr-2"></i>Windows Agent Installation Guide</h3>
            <button onclick="closeInstallGuideModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto h-[calc(95vh-80px)]">
            <!-- Step 1: Prerequisites -->
            <div class="mb-6">
                <h4 class="text-lg font-bold text-white mb-3"><i class="fas fa-check-circle text-green-400 mr-2"></i>Step 1: Prerequisites</h4>
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <ul class="space-y-2 text-slate-300 text-sm">
                        <li><i class="fas fa-check text-cyan-400 mr-2"></i>Windows 10/11 or Windows Server 2016+</li>
                        <li><i class="fas fa-check text-cyan-400 mr-2"></i>PowerShell 5.1 or higher</li>
                        <li><i class="fas fa-check text-cyan-400 mr-2"></i>Administrator privileges</li>
                        <li><i class="fas fa-check text-cyan-400 mr-2"></i>Internet connection to this server</li>
                    </ul>
                </div>
            </div>

            <!-- Step 2: Create Token -->
            <div class="mb-6">
                <h4 class="text-lg font-bold text-white mb-3"><i class="fas fa-key text-amber-400 mr-2"></i>Step 2: Create Agent Token</h4>
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <p class="text-slate-300 text-sm mb-3">Before installing the agent, you need to create an authentication token:</p>
                    <button onclick="openTokenModal(); closeInstallGuideModal();" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-key mr-2"></i>Manage Agent Tokens
                    </button>
                    <p class="text-slate-400 text-xs mt-2"><i class="fas fa-info-circle mr-1"></i>Copy the token after creation - you'll need it for installation</p>
                </div>
            </div>

            <!-- Step 3: Download & Install -->
            <div class="mb-6">
                <h4 class="text-lg font-bold text-white mb-3"><i class="fas fa-download text-purple-400 mr-2"></i>Step 3: Download & Install Agent</h4>
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700 space-y-4">
                    <div class="bg-slate-800/80 rounded-lg p-4 border border-slate-700">
                        <h5 class="text-white font-semibold mb-2"><i class="fas fa-gauge-high text-cyan-400 mr-2"></i>Optimized for High Performance</h5>
                        <p class="text-slate-300 text-sm leading-relaxed">
                            Besides powerful agentless monitoring, the AMPNM agent offers high-performance monitoring for operating systems and application-specific metrics.
                            It uses minimal CPU and memory while remaining compatible with Linux, UNIX, and Windows environments.
                        </p>
                    </div>
                    <!-- Option 1: One-line Install -->
                    <div>
                        <h5 class="text-white font-medium mb-2"><i class="fas fa-bolt text-yellow-400 mr-2"></i>Option 1: One-Line Install (Recommended)</h5>
                        <p class="text-slate-400 text-xs mb-2">Run this command in PowerShell (as Administrator):</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label for="agent-server-url" class="block text-xs font-medium text-slate-400 mb-1">Server URL</label>
                                <input type="text" id="agent-server-url" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-xs text-slate-200 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($serverUrl . '/api/agent/windows-metrics') ?>">
                            </div>
                            <div>
                                <label for="agent-token" class="block text-xs font-medium text-slate-400 mb-1">Agent Token</label>
                                <input type="text" id="agent-token" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-xs text-slate-200 focus:ring-2 focus:ring-cyan-500" placeholder="Paste your token here">
                            </div>
                        </div>
                        <div class="bg-slate-800 rounded-lg p-3 border border-slate-600">
                            <div class="flex items-start gap-2">
                                <code id="install-command" class="text-xs text-green-400 leading-relaxed flex-1 break-all">powershell -ExecutionPolicy Bypass -Command "& { Invoke-WebRequest -Uri '<?= $serverUrl ?>/download-agent.php?file=AMPNM-Agent-Installer.ps1' -OutFile 'AMPNM-Agent-Installer.ps1'; .\AMPNM-Agent-Installer.ps1 -ServerUrl \"<?= htmlspecialchars($serverUrl . '/api/agent/windows-metrics') ?>\" -AgentToken \"&lt;agent-token&gt;\" }"</code>
                                <button onclick="copyInstallCommand()" class="flex-shrink-0 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium transition-colors">
                                    <i class="fas fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                        </div>
                        <div class="mt-2 bg-blue-500/10 border border-blue-500/30 rounded-lg p-3">
                            <p class="text-blue-300 text-xs"><i class="fas fa-info-circle mr-1"></i><strong>Tip:</strong> This command automatically downloads and runs the installer script</p>
                        </div>
                    </div>

                    <!-- Option 2: Manual Download -->
                    <div class="border-t border-slate-700 pt-4">
                        <h5 class="text-white font-medium mb-2"><i class="fas fa-hand-pointer text-cyan-400 mr-2"></i>Option 2: Manual Download</h5>
                        <ol class="space-y-2 text-slate-300 text-sm">
                            <li>1. <button onclick="downloadAgent()" class="text-cyan-400 hover:text-cyan-300 underline">Download the installer script</button></li>
                            <li>2. Right-click the downloaded file and select "Run with PowerShell"</li>
                            <li>3. Follow the on-screen prompts</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Step 4: Configuration -->
            <div class="mb-6">
                <h4 class="text-lg font-bold text-white mb-3"><i class="fas fa-cog text-orange-400 mr-2"></i>Step 4: Configure Agent</h4>
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <p class="text-slate-300 text-sm mb-3">During installation, you'll be prompted to enter:</p>
                    <div class="space-y-3">
                        <div class="bg-slate-800 rounded p-3 border border-slate-600">
                            <p class="text-cyan-400 font-medium text-sm mb-1">Server URL:</p>
                            <code class="text-green-400 text-xs"><?= htmlspecialchars($serverUrl . '/api/agent/windows-metrics') ?></code>
                        </div>
                        <div class="bg-slate-800 rounded p-3 border border-slate-600">
                            <p class="text-amber-400 font-medium text-sm mb-1">Agent Token:</p>
                            <p class="text-slate-400 text-xs">Use the token you created in Step 2</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 5: Verification -->
            <div class="mb-6">
                <h4 class="text-lg font-bold text-white mb-3"><i class="fas fa-check-double text-green-400 mr-2"></i>Step 5: Verify Installation</h4>
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <p class="text-slate-300 text-sm mb-3">After installation completes:</p>
                    <ul class="space-y-2 text-slate-300 text-sm">
                        <li><i class="fas fa-check text-green-400 mr-2"></i>The agent will appear in the "Monitored Hosts" section above</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Status should show as <span class="px-2 py-0.5 bg-green-500/20 text-green-400 rounded text-xs">Online</span></li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Metrics will start appearing within 60 seconds</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Check Windows Services for "AMPNM Agent" service</li>
                    </ul>
                    <p class="text-slate-400 text-xs mt-3"><i class="fas fa-info-circle mr-1"></i>The metrics endpoint requires the <code>X-Agent-Token</code> header, so calling it without a token will return an authentication error.</p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
                <h4 class="text-amber-400 font-bold mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Troubleshooting</h4>
                <ul class="space-y-2 text-slate-300 text-sm">
                    <li><i class="fas fa-circle text-amber-400 mr-2 text-xs"></i><strong>Agent not appearing:</strong> Check Windows Firewall and verify network connectivity to this server</li>
                    <li><i class="fas fa-circle text-amber-400 mr-2 text-xs"></i><strong>Installation fails:</strong> Ensure PowerShell is run as Administrator</li>
                    <li><i class="fas fa-circle text-amber-400 mr-2 text-xs"></i><strong>Token invalid:</strong> Verify the token is enabled in "Manage Agent Tokens"</li>
                    <li><i class="fas fa-circle text-amber-400 mr-2 text-xs"></i><strong>Service not starting:</strong> Check Windows Event Viewer for error messages</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Agent Tokens Modal -->
<div id="token-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/70">
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

<!-- Per-Host Alert Override Modal -->
<div id="host-override-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
    <div class="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-lg max-h-[80vh] overflow-hidden shadow-xl">
        <div class="flex justify-between items-center p-4 border-b border-slate-700">
            <h3 class="text-lg font-bold text-white"><i class="fas fa-sliders text-purple-400 mr-2"></i>Per-Host Thresholds</h3>
            <button onclick="closeHostOverrideModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[60vh]">
            <div class="bg-slate-900/50 rounded-lg p-3 mb-4 border border-slate-700">
                <p class="text-white font-medium" id="override-host-name">Host Name</p>
                <p class="text-slate-400 text-sm" id="override-host-ip">IP Address</p>
            </div>
            
            <div class="flex items-center justify-between mb-4 p-3 bg-purple-500/10 border border-purple-500/30 rounded-lg">
                <label class="text-sm text-slate-300">Enable Custom Thresholds</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="override-enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>
            
            <div id="override-thresholds" class="space-y-4">
                <!-- CPU -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-microchip text-cyan-400 mr-2"></i>CPU Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="override-cpu-warning" min="0" max="100" value="80" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="override-cpu-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- Memory -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-memory text-purple-400 mr-2"></i>Memory Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="override-memory-warning" min="0" max="100" value="80" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="override-memory-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- Disk -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-hdd text-green-400 mr-2"></i>Disk Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="override-disk-warning" min="0" max="100" value="85" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="override-disk-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- GPU -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-tv text-orange-400 mr-2"></i>GPU Usage</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Warning (%)</label>
                            <input type="number" id="override-gpu-warning" min="0" max="100" value="80" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Critical (%)</label>
                            <input type="number" id="override-gpu-critical" min="0" max="100" value="95" 
                                   class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- Status Delay (seconds) -->
                <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
                    <h4 class="text-white font-medium mb-3"><i class="fas fa-clock text-slate-400 mr-2"></i>Status Delay</h4>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Seconds before a host is considered Offline</label>
                        <input type="number" id="override-status-delay" min="30" max="86400" value="300" 
                               class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-slate-700 flex justify-between">
            <button onclick="deleteHostOverride()" class="px-4 py-2 bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-trash mr-2"></i>Reset to Global
            </button>
            <div class="flex gap-3">
                <button onclick="closeHostOverrideModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors">
                    Cancel
                </button>
                <button onclick="saveHostOverride()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-save mr-2"></i>Save Override
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
const SERVER_URL = '<?= $serverUrl ?>';
const notyf = new Notyf({ 
    duration: 5000, 
    position: { x: 'right', y: 'top' },
    types: [
        {
            type: 'new-agent',
            background: '#10b981',
            icon: {
                className: 'fas fa-satellite-dish',
                tagName: 'i',
                color: 'white'
            }
        }
    ]
});

// Optional deep-link: host_metrics.php?modal=tokens
window.addEventListener('load', () => {
    try {
        const modal = new URLSearchParams(window.location.search).get('modal');
        if (modal === 'tokens') {
            openTokenModal();
        }
    } catch (e) {
        // ignore
    }
});
let selectedHostIp = null;
let charts = {};
let autoRefreshInterval = null;
let knownHostIps = JSON.parse(localStorage.getItem('ampnm_known_hosts') || '[]');
let isFirstLoad = true;

// Notification sound - pleasant chime
const notificationSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleU48teleU48teleU48teleU48teleU48teleU48j2VG3vOPLzuzq5eLg3drZ1tbV1NTT09LS0dDQz87Ozc3MzMzMzMzMzM3Nzs7P0NHR0tPU1dbX2Nnb3N7g4ePl5+nq7O7w8vT29/n7/f//');
notificationSound.volume = 0.6;

// Modal functions
function openInstallGuideModal() {
    document.getElementById('install-guide-modal').classList.remove('hidden');
    document.getElementById('install-guide-modal').classList.add('flex');
}

function closeInstallGuideModal() {
    document.getElementById('install-guide-modal').classList.add('hidden');
    document.getElementById('install-guide-modal').classList.remove('flex');
}

const agentServerInput = document.getElementById('agent-server-url');
const agentTokenInput = document.getElementById('agent-token');
const installCommandEl = document.getElementById('install-command');

function buildAgentDownloadUrl() {
    const url = new URL('download-agent.php', window.location.href);
    url.searchParams.set('file', 'AMPNM-Agent-Installer.ps1');

    const serverUrl = agentServerInput?.value.trim();
    const agentToken = agentTokenInput?.value.trim();

    if (serverUrl) {
        url.searchParams.set('server_url', serverUrl);
    }

    if (agentToken) {
        url.searchParams.set('agent_token', agentToken);
    }

    return url.toString();
}

function buildInstallCommand() {
    const serverUrl = agentServerInput?.value.trim() || '';
    const agentToken = agentTokenInput?.value.trim() || '';
    const downloadUrl = buildAgentDownloadUrl();
    const serverArg = serverUrl ? `-ServerUrl "${serverUrl}"` : '-ServerUrl "<server-url>"';
    const tokenArg = agentToken ? `-AgentToken "${agentToken}"` : '-AgentToken "<agent-token>"';

    return `powershell -ExecutionPolicy Bypass -Command "& { Invoke-WebRequest -Uri '${downloadUrl}' -OutFile 'AMPNM-Agent-Installer.ps1'; .\\AMPNM-Agent-Installer.ps1 ${serverArg} ${tokenArg} }"`;
}

function updateInstallCommand() {
    if (!installCommandEl) return;
    installCommandEl.textContent = buildInstallCommand();
}

function downloadAgent() {
    const downloadUrl = buildAgentDownloadUrl();
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = 'AMPNM-Agent-Installer.ps1';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    notyf.success('Agent installer downloaded');
}

if (agentServerInput) {
    agentServerInput.addEventListener('input', updateInstallCommand);
}

if (agentTokenInput) {
    agentTokenInput.addEventListener('input', updateInstallCommand);
}

updateInstallCommand();

function copyInstallCommand() {
    const installCommand = document.getElementById('install-command').textContent;
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(installCommand).then(() => {
            notyf.success('Install command copied to clipboard!');
        }).catch(() => {
            fallbackCopy(installCommand);
        });
    } else {
        fallbackCopy(installCommand);
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
        notyf.success('Install command copied to clipboard!');
    } catch (err) {
        notyf.error('Failed to copy. Please copy manually.');
    }
    document.body.removeChild(textArea);
}

// Play notification sound
function playNotificationSound() {
    if (!soundEnabled) return;
    notificationSound.currentTime = 0;
    notificationSound.play().catch(e => console.log('Audio play failed:', e));
}

// Show new agent notification
function showNewAgentNotification(host) {
    if (!notificationsEnabled) return;
    
    playNotificationSound();
    
    notyf.open({
        type: 'new-agent',
        message: `<div class="flex items-center gap-2">
            <i class="fas fa-satellite-dish"></i>
            <div>
                <div class="font-bold">New Agent Registered!</div>
                <div class="text-sm opacity-90">${host.host_name || host.host_ip}</div>
            </div>
        </div>`,
        dismissible: true,
        duration: 8000
    });
    
    const summaryEl = document.getElementById('agent-status-summary');
    if (summaryEl) {
        summaryEl.classList.add('ring-2', 'ring-green-500', 'ring-offset-2', 'ring-offset-slate-900');
        setTimeout(() => {
            summaryEl.classList.remove('ring-2', 'ring-green-500', 'ring-offset-2', 'ring-offset-slate-900');
        }, 3000);
    }
}

// Host card template
function createHostCard(host) {
    const lastUpdate = host.created_at ? new Date(host.created_at).toLocaleString() : 'Never';
    const isOnline = isHostOnline(host);
    const statusClass = isOnline ? 'bg-green-500' : 'bg-red-500';
    const statusText = isOnline ? 'Online' : 'Offline';

    const idSafe = (host.host_ip || '').replace(/[^a-zA-Z0-9_-]/g, '_');
    const statusDelaySeconds = (host.status_delay_seconds !== null && host.status_delay_seconds !== undefined && String(host.status_delay_seconds) !== '')
        ? parseInt(host.status_delay_seconds, 10)
        : 300;
    
    const firstSeen = host.first_seen_at ? new Date(host.first_seen_at) : null;
    const firstSeenDisplay = firstSeen ? getTimeAgo(firstSeen) : 'Unknown';
    
    return `
        <div class="host-card bg-slate-800/50 rounded-xl border border-slate-700 hover:border-cyan-500/50 transition-all p-4">
            <div class="flex justify-between items-start mb-4">
                <div class="cursor-pointer flex-1" onclick="selectHost('${host.host_ip}', '${host.host_name || host.host_ip}')">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full ${statusClass}"></span>
                        <h3 class="text-white font-medium">${host.host_name || 'Unknown Host'}</h3>
                        ${host.has_override ? '<i class="fas fa-sliders text-purple-400 text-xs" title="Custom thresholds"></i>' : ''}
                        <span class="px-1.5 py-0.5 text-[10px] bg-green-500/20 text-green-400 rounded border border-green-500/30" title="Agent registered ${firstSeenDisplay}">
                            <i class="fas fa-check-circle mr-0.5"></i>Registered
                        </span>
                    </div>
                    <p class="text-slate-400 text-xs">${host.host_ip}</p>
                    ${host.device_name ? `<p class="text-cyan-400 text-xs mt-1"><i class="fas fa-link mr-1"></i>${host.device_name}</p>` : ''}
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="event.stopPropagation(); openHostOverrideModal('${host.host_ip}', '${host.host_name || host.host_ip}')" 
                            class="p-1.5 text-slate-400 hover:text-purple-400 hover:bg-purple-500/20 rounded transition-colors" 
                            title="Custom alert thresholds">
                        <i class="fas fa-sliders text-xs"></i>
                    </button>
                    <span class="px-2 py-1 text-xs rounded ${isRecent ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'}">${statusText}</span>
                </div>
            </div>

            ${IS_ADMIN ? `
                <div class="mb-3">
                    <div class="flex items-center justify-between gap-2 bg-slate-900/40 rounded-lg border border-slate-700 px-2.5 py-2">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 text-xs"><i class="fas fa-stopwatch mr-1"></i>Status Delay</span>
                            <span id="status-delay-label-${idSafe}" class="text-slate-300 text-xs">${formatStatusDelayLabel(statusDelaySeconds)}</span>
                        </div>
                        <div class="flex items-center gap-2" onclick="event.stopPropagation();">
                            <input
                                id="status-delay-input-${idSafe}"
                                type="number"
                                min="30"
                                max="86400"
                                value="${statusDelaySeconds}"
                                class="w-24 px-2 py-1 bg-slate-900 border border-slate-600 rounded text-xs text-slate-100 text-center"
                                title="Seconds before host is considered stale"
                                onkeydown="if(event.key==='Enter'){ event.preventDefault(); quickSaveStatusDelay('${host.host_ip}', '${host.host_name || host.host_ip}', this); }"
                            />
                            <button
                                class="px-2.5 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs font-medium"
                                onclick="event.stopPropagation(); quickSaveStatusDelay('${host.host_ip}', '${host.host_name || host.host_ip}', document.getElementById('status-delay-input-${idSafe}'))"
                                title="Save status delay override">
                                <i class="fas fa-save mr-1"></i>Save
                            </button>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Controls how long a host can go without reporting before it shows as Offline.</p>
                </div>
            ` : ''}
            
            <div class="grid grid-cols-2 gap-3 text-sm cursor-pointer" onclick="selectHost('${host.host_ip}', '${host.host_name || host.host_ip}')">
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
            
            <div class="flex justify-between items-center mt-3 text-xs">
                <span class="text-slate-500" title="First registered"><i class="fas fa-calendar-plus mr-1"></i>${firstSeenDisplay}</span>
                <span class="text-slate-500">Updated: ${lastUpdate}</span>
            </div>
        </div>
    `;
}

async function quickSaveStatusDelay(hostIp, hostName, inputEl) {
    try {
        if (!inputEl) return;
        const raw = (inputEl.value || '').trim();
        const seconds = parseInt(raw, 10);
        if (isNaN(seconds) || seconds < 30 || seconds > 86400) {
            notyf.error('Status Delay must be between 30 and 86400 seconds');
            return;
        }

        inputEl.disabled = true;
        await saveHostStatusDelay(hostIp, hostName, seconds);

        const idSafe = (hostIp || '').replace(/[^a-zA-Z0-9_-]/g, '_');
        const labelEl = document.getElementById(`status-delay-label-${idSafe}`);
        if (labelEl) labelEl.textContent = formatStatusDelayLabel(seconds);

        notyf.success('Status Delay saved');
        // Keep UI consistent everywhere (host cards + admin overrides table)
        if (typeof loadHosts === 'function') loadHosts();
        if (typeof loadHostOverridesTable === 'function') loadHostOverridesTable();
    } catch (e) {
        console.error('Failed to save status delay:', e);
        notyf.error('Failed to save Status Delay');
    } finally {
        if (inputEl) inputEl.disabled = false;
    }
}

async function saveHostStatusDelay(hostIp, hostName, statusDelaySeconds) {
    // Preserve existing thresholds by reading current override first
    const existingRes = await fetch(`api.php?action=get_host_override&host_ip=${encodeURIComponent(hostIp)}`);
    const existing = await existingRes.json().catch(() => ({}));

    const payload = {
        host_ip: hostIp,
        host_name: hostName,
        enabled: 1,
        cpu_warning: existing.cpu_warning ?? 80,
        cpu_critical: existing.cpu_critical ?? 95,
        memory_warning: existing.memory_warning ?? 80,
        memory_critical: existing.memory_critical ?? 95,
        disk_warning: existing.disk_warning ?? 85,
        disk_critical: existing.disk_critical ?? 95,
        gpu_warning: existing.gpu_warning ?? 80,
        gpu_critical: existing.gpu_critical ?? 95,
        status_delay_seconds: statusDelaySeconds
    };

    const res = await fetch('api.php?action=save_host_override', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
        throw new Error((data && data.error) ? data.error : `HTTP ${res.status}`);
    }
    return data;
}

function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };
    
    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
        }
    }
    return 'Just now';
}

function getHostStatusDelaySeconds(host) {
    const overrideDelay = host.status_delay_seconds !== null && host.status_delay_seconds !== undefined
        ? parseInt(host.status_delay_seconds, 10)
        : NaN;
    const delay = !isNaN(overrideDelay) && overrideDelay > 0 ? overrideDelay : 300; // default 300s
    return delay * 1000; // ms
}

function isHostOnline(host) {
    if (!host.created_at) return false;
    const maxAgeMs = getHostStatusDelaySeconds(host);
    return (Date.now() - new Date(host.created_at).getTime()) < maxAgeMs;
}

// Quick dev verification: ensure the agent REST endpoint is reachable.
const agentHealthUrl = <?= json_encode($serverUrl . '/api/agent/windows-metrics/health') ?>;
async function checkAgentApiHealth() {
    const badge = document.getElementById('agent-health-badge');
    const dot = document.getElementById('agent-health-dot');
    const text = document.getElementById('agent-health-text');
    if (!badge || !dot || !text) return;

    try {
        const res = await fetch(agentHealthUrl, { cache: 'no-store' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json().catch(() => ({}));
        if (data && data.status === 'ok') {
            dot.className = 'w-2 h-2 rounded-full bg-green-500';
            text.textContent = 'Agent API: OK';
            badge.className = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium border border-green-500/30 bg-green-500/10 text-green-300';
        } else {
            throw new Error('Bad payload');
        }
    } catch (e) {
        dot.className = 'w-2 h-2 rounded-full bg-red-500';
        text.textContent = 'Agent API: Down';
        badge.className = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium border border-red-500/30 bg-red-500/10 text-red-300';
    }
}

async function loadHosts() {
    try {
        const response = await fetch('api.php?action=get_all_hosts');
        const hosts = await response.json();
        
        const container = document.getElementById('hosts-container');
        
        if (hosts && hosts.length > 0) {
            const currentHostIps = hosts.map(h => h.host_ip);
            
            if (!isFirstLoad) {
                const newHosts = hosts.filter(h => !knownHostIps.includes(h.host_ip));
                newHosts.forEach(host => {
                    console.log('New agent detected:', host.host_name || host.host_ip);
                    showNewAgentNotification(host);
                });
            }
            
            knownHostIps = currentHostIps;
            localStorage.setItem('ampnm_known_hosts', JSON.stringify(knownHostIps));
            isFirstLoad = false;
        }
        
        updateAgentStatusSummary(hosts || []);
        
        if (!hosts || hosts.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-desktop text-4xl text-slate-600 mb-4"></i>
                    <p class="text-slate-400">No monitored hosts yet</p>
                    <p class="text-slate-500 text-sm mt-2">Install the Windows Agent on your servers to start monitoring</p>
                    <button onclick="openInstallGuideModal()" class="mt-4 px-6 py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg font-medium transition-colors shadow-lg">
                        <i class="fas fa-book-open mr-2"></i>View Installation Guide
                    </button>
                </div>
            `;
            isFirstLoad = false;
            return;
        }
        
        container.innerHTML = hosts.map(createHostCard).join('');
    } catch (error) {
        console.error('Failed to load hosts:', error);
        notyf.error('Failed to load hosts');
    }
}

function updateAgentStatusSummary(hosts) {
    const registeredCount = hosts.length;
    const onlineCount = hosts.filter(h => isHostOnline(h)).length;
    const offlineCount = registeredCount - onlineCount;
    
    document.getElementById('registered-count').textContent = registeredCount;
    document.getElementById('online-count').textContent = onlineCount;
    document.getElementById('offline-count').textContent = offlineCount;
    
    const recentHosts = hosts.filter(h => {
        if (!h.first_seen_at) return false;
        return (Date.now() - new Date(h.first_seen_at).getTime()) < 86400000;
    });
    
    const recentContainer = document.getElementById('recent-registrations');
    const recentList = document.getElementById('recent-registrations-list');
    
    if (recentHosts.length > 0) {
        recentContainer.classList.remove('hidden');
        recentList.innerHTML = recentHosts.map(host => `
            <span class="inline-flex items-center gap-1.5 px-2 py-1 bg-slate-800 rounded-lg border border-slate-600 text-xs">
                <i class="fas fa-desktop text-cyan-400"></i>
                <span class="text-white">${host.host_name || host.host_ip}</span>
                <span class="text-slate-400">- ${getTimeAgo(new Date(host.first_seen_at))}</span>
            </span>
        `).join('');
    } else {
        recentContainer.classList.add('hidden');
    }
}

async function selectHost(ip, name) {
    selectedHostIp = ip;
    document.getElementById('detail-host-name').textContent = name;
    document.getElementById('host-detail').classList.remove('hidden');
    document.getElementById('host-detail').scrollIntoView({ behavior: 'smooth' });
    
    await loadCharts();
    
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(loadCharts, 60000);
}

function closeHostDetail() {
    document.getElementById('host-detail').classList.add('hidden');
    selectedHostIp = null;
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
}

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

let currentOverrideHostIp = null;
let currentOverrideHostName = null;

function openHostOverrideModal(hostIp, hostName) {
    currentOverrideHostIp = hostIp;
    currentOverrideHostName = hostName;
    
    document.getElementById('override-host-name').textContent = hostName;
    document.getElementById('override-host-ip').textContent = hostIp;
    
    document.getElementById('host-override-modal').classList.remove('hidden');
    document.getElementById('host-override-modal').classList.add('flex');
    
    loadHostOverride(hostIp);
}

function closeHostOverrideModal() {
    document.getElementById('host-override-modal').classList.add('hidden');
    document.getElementById('host-override-modal').classList.remove('flex');
    currentOverrideHostIp = null;
    currentOverrideHostName = null;
}

async function loadHostOverride(hostIp) {
    try {
        const response = await fetch(`api.php?action=get_host_override&host_ip=${encodeURIComponent(hostIp)}`);
        const data = await response.json();
        
        const enabledCheckbox = document.getElementById('override-enabled');
        const thresholdsDiv = document.getElementById('override-thresholds');
        
        if (data && data.id) {
            enabledCheckbox.checked = data.enabled == 1;
            document.getElementById('override-cpu-warning').value = data.cpu_warning ?? 80;
            document.getElementById('override-cpu-critical').value = data.cpu_critical ?? 95;
            document.getElementById('override-memory-warning').value = data.memory_warning ?? 80;
            document.getElementById('override-memory-critical').value = data.memory_critical ?? 95;
            document.getElementById('override-disk-warning').value = data.disk_warning ?? 85;
            document.getElementById('override-disk-critical').value = data.disk_critical ?? 95;
            document.getElementById('override-gpu-warning').value = data.gpu_warning ?? 80;
            document.getElementById('override-gpu-critical').value = data.gpu_critical ?? 95;
            document.getElementById('override-status-delay').value = data.status_delay_seconds ?? 300;
        } else {
            enabledCheckbox.checked = false;
            document.getElementById('override-cpu-warning').value = 80;
            document.getElementById('override-cpu-critical').value = 95;
            document.getElementById('override-memory-warning').value = 80;
            document.getElementById('override-memory-critical').value = 95;
            document.getElementById('override-disk-warning').value = 85;
            document.getElementById('override-disk-critical').value = 95;
            document.getElementById('override-gpu-warning').value = 80;
            document.getElementById('override-gpu-critical').value = 95;
            document.getElementById('override-status-delay').value = 300;
        }
        
        thresholdsDiv.style.opacity = enabledCheckbox.checked ? '1' : '0.5';
        thresholdsDiv.style.pointerEvents = enabledCheckbox.checked ? 'auto' : 'none';
        
        enabledCheckbox.onchange = function() {
            thresholdsDiv.style.opacity = this.checked ? '1' : '0.5';
            thresholdsDiv.style.pointerEvents = this.checked ? 'auto' : 'none';
        };
    } catch (error) {
        console.error('Failed to load host override:', error);
    }
}

async function saveHostOverride() {
    if (!currentOverrideHostIp) return;
    
    const enabled = document.getElementById('override-enabled').checked;
    const settings = {
        host_ip: currentOverrideHostIp,
        host_name: currentOverrideHostName,
        enabled: enabled,
        cpu_warning: parseInt(document.getElementById('override-cpu-warning').value) || 80,
        cpu_critical: parseInt(document.getElementById('override-cpu-critical').value) || 95,
        memory_warning: parseInt(document.getElementById('override-memory-warning').value) || 80,
        memory_critical: parseInt(document.getElementById('override-memory-critical').value) || 95,
        disk_warning: parseInt(document.getElementById('override-disk-warning').value) || 85,
        disk_critical: parseInt(document.getElementById('override-disk-critical').value) || 95,
        gpu_warning: parseInt(document.getElementById('override-gpu-warning').value) || 80,
        gpu_critical: parseInt(document.getElementById('override-gpu-critical').value) || 95,
        status_delay_seconds: parseInt(document.getElementById('override-status-delay').value) || 300
    };
    
    const metrics = ['cpu', 'memory', 'disk', 'gpu'];
    for (const metric of metrics) {
        if (settings[`${metric}_warning`] >= settings[`${metric}_critical`]) {
            notyf.error(`${metric.toUpperCase()} warning must be less than critical`);
            return;
        }
    }
    
    try {
        const response = await fetch('api.php?action=save_host_override', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });
        const result = await response.json();
        
        if (result.success) {
            notyf.success('Host thresholds saved');
            closeHostOverrideModal();
            loadHosts();
        } else {
            notyf.error(result.error || 'Failed to save');
        }
    } catch (error) {
        console.error('Failed to save host override:', error);
        notyf.error('Failed to save');
    }
}

async function deleteHostOverride() {
    if (!currentOverrideHostIp) return;
    
    if (!confirm('Reset this host to use global thresholds?')) return;
    
    try {
        const response = await fetch('api.php?action=delete_host_override', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ host_ip: currentOverrideHostIp })
        });
        const result = await response.json();
        
        if (result.success) {
            notyf.success('Reset to global thresholds');
            closeHostOverrideModal();
            loadHosts();
        } else {
            notyf.error(result.error || 'Failed to reset');
        }
    } catch (error) {
        console.error('Failed to delete host override:', error);
        notyf.error('Failed to reset');
    }
}

document.getElementById('chart-range').addEventListener('change', loadCharts);

let notificationsEnabled = localStorage.getItem('ampnm_notifications_enabled') !== 'false';
let soundEnabled = localStorage.getItem('ampnm_sound_enabled') !== 'false';

function toggleNotifications() {
    notificationsEnabled = !notificationsEnabled;
    localStorage.setItem('ampnm_notifications_enabled', notificationsEnabled);
    updateNotificationToggle();
    notyf.success(notificationsEnabled ? 'Notifications enabled' : 'Notifications disabled');
}

function toggleSound() {
    soundEnabled = !soundEnabled;
    localStorage.setItem('ampnm_sound_enabled', soundEnabled);
    updateSoundToggle();
    notyf.success(soundEnabled ? 'Sound enabled' : 'Sound muted');
}

function updateNotificationToggle() {
    const btn = document.getElementById('notification-toggle');
    if (btn) {
        btn.innerHTML = notificationsEnabled 
            ? '<i class="fas fa-bell"></i>' 
            : '<i class="fas fa-bell-slash"></i>';
        btn.title = notificationsEnabled ? 'Notifications On' : 'Notifications Off';
        btn.classList.toggle('text-green-400', notificationsEnabled);
        btn.classList.toggle('text-slate-500', !notificationsEnabled);
    }
}

function updateSoundToggle() {
    const btn = document.getElementById('sound-toggle');
    if (btn) {
        btn.innerHTML = soundEnabled 
            ? '<i class="fas fa-volume-up"></i>' 
            : '<i class="fas fa-volume-mute"></i>';
        btn.title = soundEnabled ? 'Sound On' : 'Sound Muted';
        btn.classList.toggle('text-cyan-400', soundEnabled);
        btn.classList.toggle('text-slate-500', !soundEnabled);
    }
}

function formatStatusDelayLabel(seconds) {
    const s = parseInt(seconds, 10);
    if (!s || isNaN(s) || s <= 0) {
        return '<span class="ml-1 text-slate-500 text-[10px] md:text-xs" title="Uses global default: 300s ≈ 5m">(default 300s ≈ 5m)</span>';
    }
    const minutes = s / 60;
    if (minutes < 1) {
        return `<span class="ml-1 text-slate-400 text-[10px] md:text-xs" title="${s} seconds">(${s}s)</span>`;
    }
    if (minutes < 60) {
        return `<span class="ml-1 text-slate-400 text-[10px] md:text-xs" title="${s} seconds">(${minutes}m)</span>`;
    }
    const hours = minutes / 60;
    return `<span class="ml-1 text-slate-400 text-[10px] md:text-xs" title="${s} seconds">(${hours}h)</span>`;
}

function initHostOverridesFilters() {
    const tableBody = document.getElementById('host-overrides-table-body');
    if (!tableBody) return;

    const tableWrapper = tableBody.closest('div.overflow-x-auto');
    if (!tableWrapper) return;

    // Avoid duplicating controls if they already exist in the DOM
    if (document.getElementById('host-overrides-search')) {
        return;
    }

    const controls = document.createElement('div');
    controls.className = 'flex flex-col md:flex-row md:items-center md:justify-between gap-2 text-xs md:text-sm mb-3';
    controls.innerHTML = `
        <div class="flex items-center gap-2 w-full md:w-1/2">
            <label for="host-overrides-search" class="text-slate-400 whitespace-nowrap">Search hosts:</label>
            <input id="host-overrides-search" type="text" placeholder="Filter by IP or name" class="flex-1 px-3 py-1.5 bg-slate-900 border border-slate-600 rounded text-xs md:text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500" />
        </div>
        <label class="inline-flex items-center gap-2 text-slate-300 mt-2 md:mt-0">
            <input id="host-overrides-enabled-only" type="checkbox" class="rounded border-slate-600 bg-slate-900" />
            <span>Show only enabled overrides</span>
        </label>
    `;

    tableWrapper.parentNode.insertBefore(controls, tableWrapper);

    // Wire up events to existing filter function
    const searchInput = controls.querySelector('#host-overrides-search');
    const enabledOnly = controls.querySelector('#host-overrides-enabled-only');
    if (searchInput) {
        searchInput.addEventListener('input', filterHostOverridesTable);
    }
    if (enabledOnly) {
        enabledOnly.addEventListener('change', filterHostOverridesTable);
    }
}


async function loadHostOverridesTable() {
    const tableBody = document.getElementById('host-overrides-table-body');
    if (!tableBody) return;

    try {
        const response = await fetch('api.php?action=get_all_host_overrides');
        const overrides = await response.json();

        if (!overrides || overrides.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" class="px-3 py-4 text-center text-slate-500">No per-host overrides configured yet.</td></tr>';
            return;
        }

        tableBody.innerHTML = overrides.map(o => `
            <tr data-host-ip="${o.host_ip}">
                <td class="px-3 py-2 text-center">
                    <input type="checkbox" name="row_select" class="rounded border-slate-600 bg-slate-900" />
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-300 text-xs md:text-sm">${o.host_ip}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                    <input type="text" name="host_name" value="${(o.host_name || o.host_ip).replace(/"/g, '&quot;')}" class="w-full px-2 py-1 bg-slate-900 border border-slate-700 rounded text-xs md:text-sm text-slate-100" />
                </td>
                <td class="px-3 py-2 text-center">
                    <div class="flex flex-col items-center gap-1">
                        <input type="number" name="status_delay_seconds" min="30" max="86400" value="${o.status_delay_seconds !== null ? o.status_delay_seconds : ''}" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-xs md:text-sm text-slate-100 text-center" />
                        ${formatStatusDelayLabel(o.status_delay_seconds)}
                    </div>
                </td>
                <td class="px-3 py-2 text-center">
                    <input type="number" name="cpu_warning" min="0" max="100" value="${o.cpu_warning}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" /> /
                    <input type="number" name="cpu_critical" min="0" max="100" value="${o.cpu_critical}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" />
                </td>
                <td class="px-3 py-2 text-center">
                    <input type="number" name="memory_warning" min="0" max="100" value="${o.memory_warning}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" /> /
                    <input type="number" name="memory_critical" min="0" max="100" value="${o.memory_critical}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" />
                </td>
                <td class="px-3 py-2 text-center">
                    <input type="number" name="disk_warning" min="0" max="100" value="${o.disk_warning}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" /> /
                    <input type="number" name="disk_critical" min="0" max="100" value="${o.disk_critical}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" />
                </td>
                <td class="px-3 py-2 text-center">
                    <input type="number" name="gpu_warning" min="0" max="100" value="${o.gpu_warning}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" /> /
                    <input type="number" name="gpu_critical" min="0" max="100" value="${o.gpu_critical}" class="w-14 px-1 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100 text-center" />
                </td>
                <td class="px-3 py-2 text-center whitespace-nowrap">
                    <label class="inline-flex items-center gap-1 mr-3 text-xs md:text-sm text-slate-300">
                        <input type="checkbox" name="enabled" ${o.enabled ? 'checked' : ''} class="rounded border-slate-600 bg-slate-900" />
                        <span>Enabled</span>
                    </label>
                    <button onclick="bulkSaveHostOverride('${o.host_ip}')" class="px-2 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs md:text-sm">
                        <i class="fas fa-save mr-1"></i>Save
                    </button>
                </td>
            </tr>
        `).join('');

        filterHostOverridesTable();
    } catch (error) {
        console.error('Failed to load host overrides:', error);
        tableBody.innerHTML = '<tr><td colspan="9" class="px-3 py-4 text-center text-red-400">Failed to load overrides</td></tr>';
    }
}

function filterHostOverridesTable() {
    const tableBody = document.getElementById('host-overrides-table-body');
    if (!tableBody) return;

    const searchInput = document.getElementById('host-overrides-search');
    const enabledOnlyCheckbox = document.getElementById('host-overrides-enabled-only');

    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const enabledOnly = enabledOnlyCheckbox ? enabledOnlyCheckbox.checked : false;

    const rows = Array.from(tableBody.querySelectorAll('tr'));

    if (!rows.length || !rows[0].hasAttribute('data-host-ip')) {
        // Nothing to filter (likely an empty/error state row)
        return;
    }

    rows.forEach(row => {
        const hostIpCell = row.querySelector('td:nth-child(2)');
        const hostNameInput = row.querySelector('input[name="host_name"]');
        const enabledCheckbox = row.querySelector('input[name="enabled"]');

        const hostIp = hostIpCell ? hostIpCell.textContent.trim().toLowerCase() : '';
        const hostName = hostNameInput ? hostNameInput.value.trim().toLowerCase() : '';
        const isEnabled = enabledCheckbox ? enabledCheckbox.checked : false;

        const matchesSearch = !searchTerm || hostIp.includes(searchTerm) || hostName.includes(searchTerm);
        const matchesEnabled = !enabledOnly || isEnabled;

        row.style.display = (matchesSearch && matchesEnabled) ? '' : 'none';
    });
}

async function bulkSaveHostOverride(hostIp) {
    const row = document.querySelector(`tr[data-host-ip="${hostIp}"]`);
    if (!row) return;

    const enabled = row.querySelector('input[name="enabled"]').checked;
    const hostNameInput = row.querySelector('input[name="host_name"]');
    const delayInput = row.querySelector('input[name="status_delay_seconds"]');

    const settings = {
        host_ip: hostIp,
        host_name: hostNameInput && hostNameInput.value ? hostNameInput.value : hostIp,
        enabled: enabled,
        cpu_warning: parseInt(row.querySelector('input[name="cpu_warning"]').value) || 80,
        cpu_critical: parseInt(row.querySelector('input[name="cpu_critical"]').value) || 95,
        memory_warning: parseInt(row.querySelector('input[name="memory_warning"]').value) || 80,
        memory_critical: parseInt(row.querySelector('input[name="memory_critical"]').value) || 95,
        disk_warning: parseInt(row.querySelector('input[name="disk_warning"]').value) || 85,
        disk_critical: parseInt(row.querySelector('input[name="disk_critical"]').value) || 95,
        gpu_warning: parseInt(row.querySelector('input[name="gpu_warning"]').value) || 80,
        gpu_critical: parseInt(row.querySelector('input[name="gpu_critical"]').value) || 95,
        status_delay_seconds: delayInput && delayInput.value ? parseInt(delayInput.value) : 300
    };

    const metrics = ['cpu', 'memory', 'disk', 'gpu'];
    for (const metric of metrics) {
        if (settings[`${metric}_warning`] >= settings[`${metric}_critical`]) {
            notyf.error(`${metric.toUpperCase()} warning must be less than critical`);
            return;
        }
    }

    try {
        const response = await fetch('api.php?action=save_host_override', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settings)
        });
        const result = await response.json();

        if (result.success) {
            notyf.success('Host override saved');
            loadHosts();
            loadHostOverridesTable();
        } else {
            notyf.error(result.error || 'Failed to save');
        }
    } catch (error) {
        console.error('Failed to save host override:', error);
        notyf.error('Failed to save');
    }
}

function getSelectedOverrideRows() {
    const tableBody = document.getElementById('host-overrides-table-body');
    if (!tableBody) return [];
    return Array.from(tableBody.querySelectorAll('input[name="row_select"]:checked')).map(cb => cb.closest('tr'));
}

function toggleSelectAllOverrides(masterCheckbox) {
    const tableBody = document.getElementById('host-overrides-table-body');
    if (!tableBody) return;
    const checkboxes = tableBody.querySelectorAll('input[name="row_select"]');
    checkboxes.forEach(cb => {
        cb.checked = masterCheckbox.checked;
    });
}

function setBulkStatusDelayPreset(seconds) {
    const rows = getSelectedOverrideRows();
    if (!rows.length) {
        notyf.error('Select at least one host to apply a preset');
        return;
    }

    rows.forEach(row => {
        const delayInput = row.querySelector('input[name="status_delay_seconds"]');
        if (delayInput) {
            delayInput.value = seconds;
        }
    });
}

async function applyBulkStatusDelay() {
    const rows = getSelectedOverrideRows();
    if (!rows.length) {
        notyf.error('Select at least one host to apply changes');
        return;
    }

    for (const row of rows) {
        const hostIp = row.getAttribute('data-host-ip');
        if (hostIp) {
            await bulkSaveHostOverride(hostIp);
        }
    }
}

function exportHostOverridesCsv() {
    window.location.href = 'api.php?action=export_host_overrides';
}

async function importHostOverridesCsv(input) {
    if (!input.files || !input.files[0]) {
        return;
    }

    const file = input.files[0];
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('api.php?action=import_host_overrides', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            notyf.success(`Imported ${result.imported} host override(s)`);
            input.value = '';
            loadHosts();
            loadHostOverridesTable();
        } else {
            notyf.error(result.error || 'Failed to import CSV');
        }
    } catch (error) {
        console.error('Failed to import CSV:', error);
        notyf.error('Failed to import CSV');
    }
}

loadHosts();
checkAgentApiHealth();
updateNotificationToggle();
updateSoundToggle();
initHostOverridesFilters();
loadHostOverridesTable();
setInterval(loadHosts, 10000);
setInterval(checkAgentApiHealth, 15000);
</script>

<?php require_once 'footer.php'; ?>
