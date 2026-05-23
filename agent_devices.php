<?php
require_once 'includes/auth_check.php';
require_once 'header.php';

$user_role = $_SESSION['user_role'] ?? 'viewer';
$pdo = getDbConnection();

// Handle deactivate/delete/rotate actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'admin') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isValidCsrfToken($csrf)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $device_id = (int)($_POST['device_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        
        if ($device_id > 0 && in_array($action, ['deactivate', 'activate', 'delete', 'rotate_secret'])) {
            try {
                if ($action === 'deactivate') {
                    $pdo->prepare("UPDATE agent_devices SET is_active = 0 WHERE id = ?")->execute([$device_id]);
                    $message = 'Agent deactivated.';
                    $message_type = 'success';
                } elseif ($action === 'activate') {
                    $pdo->prepare("UPDATE agent_devices SET is_active = 1 WHERE id = ?")->execute([$device_id]);
                    $message = 'Agent activated.';
                    $message_type = 'success';
                } elseif ($action === 'delete') {
                    $pdo->prepare("DELETE FROM agent_heartbeats WHERE agent_device_id = ?")->execute([$device_id]);
                    $pdo->prepare("DELETE FROM agent_events WHERE agent_device_id = ?")->execute([$device_id]);
                    $pdo->prepare("DELETE FROM agent_device_secrets WHERE agent_device_id = ?")->execute([$device_id]);
                    $pdo->prepare("DELETE FROM agent_devices WHERE id = ?")->execute([$device_id]);
                    $message = 'Agent device deleted.';
                    $message_type = 'success';
                } elseif ($action === 'rotate_secret') {
                    $now_str = date('Y-m-d H:i:s');
                    $pdo->prepare("UPDATE agent_device_secrets SET revoked_at = ? WHERE agent_device_id = ? AND revoked_at IS NULL")->execute([$now_str, $device_id]);
                    $message = 'Agent secret rotated. The agent must re-register with an enrollment token.';
                    $message_type = 'info';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Fetch all devices with their latest heartbeat
$stmt = $pdo->query("
    SELECT d.*,
           hb.cpu_usage_percent,
           hb.memory_usage_percent,
           hb.disk_usage_percent,
           hb.agent_version as hb_version,
           hb.collected_at as last_heartbeat_at
    FROM agent_devices d
    LEFT JOIN agent_heartbeats hb ON hb.id = (
        SELECT id FROM agent_heartbeats WHERE agent_device_id = d.id ORDER BY collected_at DESC LIMIT 1
    )
    ORDER BY d.last_seen_at DESC
");
$devices = $stmt->fetchAll();

// Summary counts
$total = count($devices);
$online = count(array_filter($devices, fn($d) => $d['status'] === 'online'));
$warning = count(array_filter($devices, fn($d) => $d['status'] === 'warning'));
$offline = $total - $online - $warning;

$csrf_token = ensureCsrfTokenInSession();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-1">
                <i class="fas fa-desktop text-cyan-400 mr-2"></i>Windows Agents
            </h1>
            <p class="text-slate-400 text-sm">Monitor all registered Windows Usage Agent client devices in real-time.</p>
        </div>
        <?php if ($user_role === 'admin'): ?>
        <div class="flex gap-2 flex-wrap">
            <a href="agent_enrollment.php" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-key"></i>Manage Tokens
            </a>
            <a href="agent_settings.php" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-sliders"></i>Settings
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status Messages -->
    <?php if ($message): ?>
        <div class="p-4 rounded-lg mb-6 flex items-center gap-2
            <?= $message_type === 'success' ? 'bg-green-500/10 border border-green-500/30 text-green-400' :
                ($message_type === 'error' ? 'bg-red-500/10 border border-red-500/30 text-red-400' :
                'bg-cyan-500/10 border border-cyan-500/30 text-cyan-400') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-white"><?= $total ?></p>
            <p class="text-slate-400 text-sm mt-1">Total Agents</p>
        </div>
        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-emerald-400"><?= $online ?></p>
            <p class="text-slate-400 text-sm mt-1">Online</p>
        </div>
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-amber-400"><?= $warning ?></p>
            <p class="text-slate-400 text-sm mt-1">Warning</p>
        </div>
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-red-400"><?= $offline ?></p>
            <p class="text-slate-400 text-sm mt-1">Offline</p>
        </div>
    </div>

    <!-- Agent Device Grid -->
    <?php if (empty($devices)): ?>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-12 text-center">
            <i class="fas fa-desktop text-slate-600 text-5xl mb-4 block"></i>
            <h3 class="text-white font-semibold text-lg mb-2">No Agents Registered</h3>
            <p class="text-slate-400 text-sm mb-6">Create an enrollment token, install the Windows Usage Agent on a client PC, and it will appear here.</p>
            <?php if ($user_role === 'admin'): ?>
                <a href="agent_enrollment.php" class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-key"></i>Create Enrollment Token
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($devices as $d):
                $status = $d['status'] ?? 'offline';
                $status_colors = [
                    'online' => ['dot' => 'bg-emerald-500 animate-pulse', 'badge' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30', 'border' => 'border-emerald-500/20'],
                    'warning' => ['dot' => 'bg-amber-500 animate-pulse', 'badge' => 'bg-amber-500/10 text-amber-400 border-amber-500/30', 'border' => 'border-amber-500/20'],
                    'offline' => ['dot' => 'bg-red-500', 'badge' => 'bg-red-500/10 text-red-400 border-red-500/30', 'border' => 'border-red-500/20'],
                ];
                $sc = $status_colors[$status] ?? $status_colors['offline'];
                
                $cpu = round($d['cpu_usage_percent'] ?? 0, 1);
                $mem = round($d['memory_usage_percent'] ?? 0, 1);
                $disk = round($d['disk_usage_percent'] ?? 0, 1);
                
                $last_seen = $d['last_seen_at'] ? (time() - strtotime($d['last_seen_at'])) : null;
                $last_seen_text = 'Never';
                if ($last_seen !== null) {
                    if ($last_seen < 60) $last_seen_text = $last_seen . 's ago';
                    elseif ($last_seen < 3600) $last_seen_text = floor($last_seen/60) . 'm ago';
                    elseif ($last_seen < 86400) $last_seen_text = floor($last_seen/3600) . 'h ago';
                    else $last_seen_text = floor($last_seen/86400) . 'd ago';
                }
            ?>
                <div class="bg-slate-800/60 border border-slate-700 <?= $sc['border'] ?> rounded-xl p-5 hover:border-slate-600 transition-all hover:shadow-xl">
                    <!-- Card Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                                <i class="fas fa-desktop text-cyan-400 text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-white font-bold truncate" title="<?= htmlspecialchars($d['hostname']) ?>">
                                    <?= htmlspecialchars($d['agent_name'] ?: $d['hostname']) ?>
                                </h3>
                                <p class="text-slate-500 text-xs truncate"><?= htmlspecialchars($d['hostname']) ?></p>
                            </div>
                        </div>
                        <span class="flex-shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border <?= $sc['badge'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                            <?= ucfirst($status) ?>
                        </span>
                    </div>

                    <!-- System Info -->
                    <div class="text-xs text-slate-400 space-y-1 mb-4">
                        <div class="flex justify-between">
                            <span><i class="fas fa-windows mr-1"></i><?= htmlspecialchars($d['os_name']) ?> <?= htmlspecialchars($d['os_version']) ?></span>
                            <span class="text-slate-500"><?= htmlspecialchars($d['architecture']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span><i class="fas fa-network-wired mr-1"></i><?= htmlspecialchars($d['local_ip'] ?? 'N/A') ?></span>
                            <span class="text-slate-500">Seen: <?= $last_seen_text ?></span>
                        </div>
                    </div>

                    <!-- Metric Bars -->
                    <div class="space-y-2 mb-4">
                        <?php foreach (['CPU' => [$cpu, 'cyan'], 'RAM' => [$mem, 'purple'], 'Disk' => [$disk, 'green']] as $label => [$value, $col]): ?>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400 w-8 flex-shrink-0"><?= $label ?></span>
                            <div class="flex-1 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                <?php $bar_color = ($value >= 90) ? 'bg-red-500' : (($value >= 70) ? 'bg-amber-500' : "bg-{$col}-500"); ?>
                                <div class="h-full rounded-full <?= $bar_color ?> transition-all" style="width: <?= min(100, $value) ?>%"></div>
                            </div>
                            <span class="text-xs text-slate-300 w-10 text-right flex-shrink-0"><?= $value ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-3 border-t border-slate-700/50">
                        <a href="agent_device_view.php?id=<?= $d['id'] ?>" 
                           class="flex-1 py-1.5 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-400 border border-cyan-500/30 rounded-lg text-xs font-medium transition-colors text-center">
                            <i class="fas fa-chart-line mr-1"></i>View Details
                        </a>
                        <?php if ($user_role === 'admin'): ?>
                        <form action="agent_devices.php" method="POST" class="contents" onsubmit="return confirm('Are you sure?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                            <?php if ($d['is_active']): ?>
                                <input type="hidden" name="action" value="deactivate">
                                <button class="py-1.5 px-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 rounded-lg text-xs transition-colors" title="Deactivate">
                                    <i class="fas fa-pause"></i>
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="activate">
                                <button class="py-1.5 px-2 bg-green-500/10 hover:bg-green-500/20 text-green-400 border border-green-500/20 rounded-lg text-xs transition-colors" title="Activate">
                                    <i class="fas fa-play"></i>
                                </button>
                            <?php endif; ?>
                        </form>
                        <form action="agent_devices.php" method="POST" class="contents" onsubmit="return confirm('Delete this agent and all its data? This cannot be undone.')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="py-1.5 px-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 rounded-lg text-xs transition-colors" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
