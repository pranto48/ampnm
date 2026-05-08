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
            <h1 class="text-3xl font-bold text-white">Dashboard</h1>
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
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
                    <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-6">
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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Ping Test -->
                    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
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
                    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
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

                <div class="mt-8 bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
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
    </div>
</main>

<?php include 'footer.php'; ?>
