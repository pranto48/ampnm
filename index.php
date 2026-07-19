<?php
require_once 'includes/auth_check.php';
require_once 'includes/update_state.php';
require_once 'includes/functions.php';
include 'header.php';

$csrfToken = ensureCsrfTokenInSession();

$updateState = readUpdateStateFile();
$dashboardUpdateAvailable = !empty($updateState['update_available']);
$dashboardBehindCount = isset($updateState['behind_count']) ? (int) $updateState['behind_count'] : null;
$dashboardLastCheckedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-white">Dashboard</h1>
                <button id="customize-widgets-btn" class="p-1.5 text-slate-400 hover:text-cyan-400 hover:bg-slate-800/80 rounded-lg transition-all" title="Customize Dashboard Widgets">
                    <i class="fas fa-sliders-h text-lg"></i>
                </button>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <?php if (($_SESSION['user_role'] ?? 'viewer') === 'admin'): ?>
                    <div id="update-status-widget" data-csrf-token="<?= htmlspecialchars($csrfToken) ?>" class="hidden rounded-lg border border-slate-600 bg-slate-800/70 px-3 py-2 text-xs text-slate-200">
                        <div class="flex items-center gap-2">
                            <span id="update-status-pill" class="inline-flex px-2 py-0.5 rounded-full border text-[11px]"></span>
                            <span id="update-last-checked" class="text-slate-400"></span>
                            <button id="update-now-btn" class="hidden px-2 py-1 rounded bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-medium">Update now</button>
                        </div>
                    </div>
                <?php endif; ?>
                <div id="map-selector-container" class="flex items-center gap-2">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
        <?php if ($dashboardUpdateAvailable): ?>
            <div class="mb-6 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-amber-100">
                <p class="font-semibold">
                    Update available<?php echo $dashboardBehindCount !== null ? ': ' . $dashboardBehindCount . ' commit(s) behind upstream.' : '.'; ?>
                </p>
                <p class="text-sm mt-1">
                    Last checked at <?php echo $dashboardLastCheckedAt ? htmlspecialchars($dashboardLastCheckedAt) . ' (UTC)' : 'unknown time'; ?>.
                    Open <a href="update_status.php" class="underline font-semibold hover:text-amber-50">Update Status</a> for one-click update and restore controls.
                </p>
            </div>
        <?php endif; ?>

        <div id="dashboard-content">
            <div class="text-center py-16" id="dashboardLoader"><div class="loader mx-auto"></div></div>
            <div id="dashboard-widgets" class="hidden">
                <!-- Docker Server Metrics Widget -->
                <div id="widget-server-metrics" class="bg-slate-800/50 border border-slate-700 rounded-xl shadow-lg p-6 mb-8 hover:border-cyan-500/30 transition-all duration-300">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-700/60 pb-4 mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fas fa-server text-cyan-400"></i>
                                Docker Host Server Status
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">Real-time health statistics for container host daemon</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-xs">
                            <div class="flex items-center gap-1.5 bg-slate-900/60 px-2.5 py-1.5 rounded border border-slate-700">
                                <span class="text-slate-500 font-medium">Hostname:</span>
                                <span id="srv-hostname" class="text-slate-200 font-mono">--</span>
                            </div>
                            <div class="flex items-center gap-1.5 bg-slate-900/60 px-2.5 py-1.5 rounded border border-slate-700">
                                <span class="text-slate-500 font-medium">OS:</span>
                                <span id="srv-os" class="text-slate-200">--</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-green-500/10 text-green-400 border border-green-500/20 rounded-full font-medium">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-ping"></span>
                                Live Monitoring
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <!-- Progress Bars Column -->
                        <div class="xl:col-span-1 space-y-4">
                            <!-- CPU Usage -->
                            <div class="bg-slate-900/30 border border-slate-700/40 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider">CPU Usage</span>
                                    <span id="srv-cpu-val" class="text-cyan-400 font-bold text-sm">--%</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-2">
                                    <div id="srv-cpu-bar" class="bg-cyan-500 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <!-- RAM Usage -->
                            <div class="bg-slate-900/30 border border-slate-700/40 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Memory (RAM)</span>
                                    <span id="srv-ram-val" class="text-purple-400 font-bold text-sm">--%</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-2">
                                    <div id="srv-ram-bar" class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-500 mt-2 font-mono">
                                    <span>Used: <span id="srv-ram-used">--</span> GB</span>
                                    <span>Total: <span id="srv-ram-total">--</span> GB</span>
                                </div>
                            </div>
                            
                            <!-- Disk Space -->
                            <div class="bg-slate-900/30 border border-slate-700/40 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Storage Capacity</span>
                                    <span id="srv-disk-val" class="text-emerald-400 font-bold text-sm">--%</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-2">
                                    <div id="srv-disk-bar" class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-500 mt-2 font-mono">
                                    <span>Used: <span id="srv-disk-used">--</span> GB</span>
                                    <span>Total: <span id="srv-disk-total">--</span> GB</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Network Graph Column -->
                        <div class="xl:col-span-2 bg-slate-900/20 border border-slate-700/40 rounded-lg p-4 flex flex-col justify-between h-[280px]">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-slate-400 text-xs font-semibold uppercase tracking-wider">LAN Network Throughput</span>
                                <div class="flex gap-4 text-xs font-mono">
                                    <span class="text-emerald-400"><i class="fas fa-arrow-down mr-1"></i>In: <span id="srv-net-in">0.000</span> Mbps</span>
                                    <span class="text-blue-400"><i class="fas fa-arrow-up mr-1"></i>Out: <span id="srv-net-out">0.000</span> Mbps</span>
                                </div>
                            </div>
                            <div class="flex-1 relative min-h-0">
                                <canvas id="serverNetChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="widget-device-overview" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Status Chart -->
                    <div class="lg:col-span-1 bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 flex flex-col items-center justify-center">
                        <h3 class="text-lg font-semibold text-white mb-4">Device Status Overview</h3>
                        <div class="w-48 h-48 relative">
                            <canvas id="statusChart"></canvas>
                            <div id="totalDevicesText" class="absolute inset-0 flex flex-col items-center justify-center text-white">
                                <span class="text-4xl font-bold">--</span>
                                <span class="text-sm text-slate-400">Total Devices</span>
                            </div>
                        </div>
                    </div>
                    <!-- Status Counters -->
                <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Online</h3>
                            <div id="onlineCount" class="text-4xl font-bold text-green-400 mt-2">--</div>
                        </div>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Warning</h3>
                            <div id="warningCount" class="text-4xl font-bold text-yellow-400 mt-2">--</div>
                        </div>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Critical</h3>
                            <div id="criticalCount" class="text-4xl font-bold text-red-400 mt-2">--</div>
                        </div>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Offline</h3>
                            <div id="offlineCount" class="text-4xl font-bold text-slate-500 mt-2">--</div>
                        </div>
                    </div>
                </div>

                <div id="widget-grid-middle" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Ping Test -->
                    <div id="widget-ping-test" class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                        <h2 class="text-xl font-semibold text-white mb-4">Manual Ping Test</h2>
                        <form id="pingForm" class="flex flex-col sm:flex-row gap-4 mb-4">
                            <input type="text" id="pingHostInput" name="ping_host" placeholder="Enter hostname or IP" value="192.168.1.1" class="flex-1 px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                            <button type="submit" id="pingButton" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
                                <i class="fas fa-bolt mr-2"></i>Ping
                            </button>
                        </form>
                        <div id="pingResultContainer" class="hidden mt-4">
                            <pre id="pingResultPre" class="bg-slate-900/50 text-white text-sm p-4 rounded-lg overflow-x-auto"></pre>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div id="widget-recent-activity" class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                        <h2 class="text-xl font-semibold text-white mb-4">Recent Activity</h2>
                        <div id="recentActivityList" class="space-y-3 max-h-60 overflow-y-auto">
                            <!-- Recent activity items will be loaded here by JS -->
                        </div>
                        <div id="noRecentActivityMessage" class="text-center py-4 text-slate-500 hidden">
                            <i class="fas fa-bell text-4xl mb-2"></i>
                            <p>No recent activity for this map.</p>
                        </div>
                    </div>
                </div>

                <div id="widget-device-explorer" class="mt-8 bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-xl font-semibold text-white">Device Information Explorer</h2>
                            <p class="text-sm text-slate-400">Switch between animated card and compact list view with quick status filters.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select id="deviceInfoStatusFilter" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-cyan-500">
                                <option value="all">All Statuses</option>
                                <option value="online">Online</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                                <option value="offline">Offline</option>
                            </select>
                            <button id="deviceInfoGridBtn" class="px-3 py-2 bg-cyan-600 text-white rounded-lg text-sm"><i class="fas fa-th-large mr-1"></i>Cards</button>
                            <button id="deviceInfoListBtn" class="px-3 py-2 bg-slate-700 text-slate-200 rounded-lg text-sm"><i class="fas fa-list mr-1"></i>List</button>
                            <label class="flex items-center text-xs text-slate-300 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2">
                                <input type="checkbox" id="deviceInfoAnimateToggle" class="mr-2 h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500" checked>
                                Animation
                            </label>
                        </div>
                    </div>
                    <div id="deviceInfoContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3"></div>
                    <div id="noDeviceInfoMessage" class="text-center py-6 text-slate-500 hidden">
                        <i class="fas fa-server text-3xl mb-2"></i>
                        <p>No matching device information for this filter.</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Customize Widgets Modal -->
        <div id="customize-widgets-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/85 backdrop-blur-sm p-4">
            <div class="bg-slate-800 border border-slate-700 w-full max-w-md rounded-xl p-6 shadow-2xl relative animate-in fade-in zoom-in-95 duration-200">
                <button id="close-customize-btn" class="absolute top-4 right-4 text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
                <h3 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-sliders-h text-cyan-400 mr-2"></i>Customize Dashboard
                </h3>
                <p class="text-slate-400 text-sm mb-6">Choose which widgets are visible on your home dashboard page. Your preferences are saved automatically.</p>
                
                <div class="space-y-4">
                    <label class="flex items-center justify-between p-3 bg-slate-900/40 rounded-lg border border-slate-700/60 hover:border-cyan-500/30 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-server text-cyan-400 text-lg w-6 text-center"></i>
                            <div>
                                <div class="text-white text-sm font-medium">Docker Server Metrics</div>
                                <div class="text-[11px] text-slate-500">Live CPU, RAM, Disk & network graph</div>
                            </div>
                        </div>
                        <input type="checkbox" id="chk-widget-server-metrics" class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" checked>
                    </label>

                    <label class="flex items-center justify-between p-3 bg-slate-900/40 rounded-lg border border-slate-700/60 hover:border-cyan-500/30 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-chart-pie text-cyan-400 text-lg w-6 text-center"></i>
                            <div>
                                <div class="text-white text-sm font-medium">Device Status Overview</div>
                                <div class="text-[11px] text-slate-500">Pie chart and counters for status</div>
                            </div>
                        </div>
                        <input type="checkbox" id="chk-widget-device-overview" class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" checked>
                    </label>

                    <label class="flex items-center justify-between p-3 bg-slate-900/40 rounded-lg border border-slate-700/60 hover:border-cyan-500/30 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-bolt text-cyan-400 text-lg w-6 text-center"></i>
                            <div>
                                <div class="text-white text-sm font-medium">Manual Ping Test</div>
                                <div class="text-[11px] text-slate-500">Fast utility to run manual pings</div>
                            </div>
                        </div>
                        <input type="checkbox" id="chk-widget-ping-test" class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" checked>
                    </label>

                    <label class="flex items-center justify-between p-3 bg-slate-900/40 rounded-lg border border-slate-700/60 hover:border-cyan-500/30 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-list text-cyan-400 text-lg w-6 text-center"></i>
                            <div>
                                <div class="text-white text-sm font-medium">Recent Activity</div>
                                <div class="text-[11px] text-slate-500">Log of recent device alerts & pings</div>
                            </div>
                        </div>
                        <input type="checkbox" id="chk-widget-recent-activity" class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" checked>
                    </label>

                    <label class="flex items-center justify-between p-3 bg-slate-900/40 rounded-lg border border-slate-700/60 hover:border-cyan-500/30 cursor-pointer transition-colors">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-search text-cyan-400 text-lg w-6 text-center"></i>
                            <div>
                                <div class="text-white text-sm font-medium">Device Info Explorer</div>
                                <div class="text-[11px] text-slate-500">Detailed list or grid of active devices</div>
                            </div>
                        </div>
                        <input type="checkbox" id="chk-widget-device-explorer" class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" checked>
                    </label>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button id="close-customize-btn-ok" class="px-5 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg font-medium transition-colors text-sm shadow-md">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
