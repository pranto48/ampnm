<?php
require_once 'includes/auth_check.php';
require_once 'includes/update_state.php';
require_once 'includes/functions.php';
include 'header.php';

$updateState = readUpdateStateFile();
$dashboardUpdateAvailable = !empty($updateState['update_available']);
$dashboardBehindCount = isset($updateState['behind_count']) ? (int) $updateState['behind_count'] : null;
$dashboardLastCheckedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;
$csrfToken = ensureCsrfTokenInSession();
$updateActionMessage = '';
$updateActionType = '';

function runScriptWithResult(string $scriptPath, array $env = []): array
{
    $resultFile = '/tmp/ampnm_dashboard_update_result.env';
    $env['RESULT_ENV_FILE'] = $resultFile;
    $envString = implode(' ', array_map(static fn($k, $v) => escapeshellarg("{$k}={$v}"), array_keys($env), $env));
    $command = 'env ' . $envString . ' bash ' . escapeshellarg($scriptPath) . ' 2>&1';
    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);
    $parsed = [];
    if (is_file($resultFile)) {
        foreach ((file($resultFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) as $line) {
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $parsed[trim($k)] = trim($v);
        }
    }
    return ['exitCode' => $exitCode, 'output' => implode("\n", $output), 'result' => $parsed];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['user_role'] ?? 'viewer') === 'admin') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $updateActionMessage = 'Invalid request token.';
        $updateActionType = 'error';
    } else {
        $action = (string) ($_POST['update_action'] ?? '');
        if ($action === 'direct_update') {
            $run = runScriptWithResult(__DIR__ . '/scripts/direct_update.sh', ['TARGET_DIR' => __DIR__]);
            $ok = $run['exitCode'] === 0 && strtolower((string)($run['result']['STATUS'] ?? '')) === 'success';
            $updateActionMessage = $ok ? 'Docker app updated successfully.' : 'Update failed. Check container logs.';
            $updateActionType = $ok ? 'success' : 'error';
        } elseif ($action === 'restore_latest') {
            $base = __DIR__ . '/data/code_backups';
            $folders = glob($base . '/*', GLOB_ONLYDIR) ?: [];
            rsort($folders);
            if (empty($folders)) {
                $updateActionMessage = 'No backup versions found to restore.';
                $updateActionType = 'error';
            } else {
                $run = runScriptWithResult(__DIR__ . '/scripts/restore_backup.sh', ['APP_DIR' => __DIR__, 'HOST_APP_DIR' => __DIR__, 'BACKUP_PATH' => $folders[0]]);
                $ok = $run['exitCode'] === 0 && strtolower((string)($run['result']['STATUS'] ?? '')) === 'success';
                $updateActionMessage = $ok ? 'Restored previous version successfully.' : 'Restore failed. Check container logs.';
                $updateActionType = $ok ? 'success' : 'error';
            }
        }
    }
}
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Dashboard</h1>
            <div id="map-selector-container" class="flex items-center gap-2">
                <!-- Populated by JS -->
            </div>
        </div>
        <div class="mb-6 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-amber-100">
            <p class="font-semibold">Docker Update Status: <?php echo $dashboardUpdateAvailable ? 'Update available' : 'No git-based update signal'; ?></p>
            <p class="text-sm mt-1">Last checked at <?php echo $dashboardLastCheckedAt ? htmlspecialchars($dashboardLastCheckedAt) . ' (UTC)' : 'unknown time'; ?>.</p>
            <?php if (($_SESSION['user_role'] ?? 'viewer') === 'admin'): ?>
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="update_action" value="direct_update">
                        <button type="submit" class="px-4 py-2 rounded bg-cyan-600 hover:bg-cyan-500 text-white text-sm"><i class="fas fa-download mr-1"></i>Update Now</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Restore latest backup version?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="update_action" value="restore_latest">
                        <button type="submit" class="px-4 py-2 rounded bg-amber-600 hover:bg-amber-500 text-white text-sm"><i class="fas fa-history mr-1"></i>Restore Previous Version</button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if ($updateActionMessage !== ''): ?>
                <p class="text-sm mt-3 <?php echo $updateActionType === 'success' ? 'text-emerald-200' : 'text-red-200'; ?>"><?php echo htmlspecialchars($updateActionMessage); ?></p>
            <?php endif; ?>
        </div>

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
