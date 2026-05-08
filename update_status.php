<?php
require_once 'includes/auth_check.php';
require_once 'includes/update_state.php';

if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    http_response_code(403);
    include 'header.php';
    echo '<main class="container mx-auto px-4 py-10"><div class="bg-red-500/10 border border-red-500/40 rounded-lg p-6 text-red-200"><h1 class="text-2xl font-semibold text-white mb-2">Access denied</h1><p>Only admin users can access update controls.</p></div></main>';
    include 'footer.php';
    exit;
}

$repoPath = realpath(__DIR__) ?: __DIR__;
$statusMessage = '';
$statusType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $cmd = 'cd ' . escapeshellarg($repoPath) . ' && bash ' . escapeshellarg($repoPath . '/scripts/update.sh') . ' 2>&1';
        exec($cmd, $out, $code);
        $statusMessage = $code === 0 ? 'Update completed successfully.' : 'Update command failed.';
        $statusType = $code === 0 ? 'success' : 'error';
    } elseif ($action === 'restore') {
        $backupBase = rtrim(getenv('BACKUP_BASE') ?: '/var/www/html/docker-ampnm/data/code_backups', '/\\');
        $candidates = glob($backupBase . '/backup_*', GLOB_ONLYDIR) ?: [];
        rsort($candidates);
        $latest = $candidates[0] ?? '';
        if ($latest !== '') {
            $cmd = 'HOST_APP_DIR=' . escapeshellarg($repoPath) . ' BACKUP_PATH=' . escapeshellarg($latest) . ' bash ' . escapeshellarg($repoPath . '/scripts/restore_backup.sh') . ' 2>&1';
            exec($cmd, $out, $code);
            $statusMessage = $code === 0 ? 'Restore completed from latest backup.' : 'Restore command failed.';
            $statusType = $code === 0 ? 'success' : 'error';
        } else {
            $statusMessage = 'No backups found to restore.';
            $statusType = 'error';
        }
    }
}

include 'header.php';
$commitHash = trim(shell_exec('cd ' . escapeshellarg($repoPath) . ' && git rev-parse --short HEAD 2>/dev/null') ?? '');
$branchName = trim(shell_exec('cd ' . escapeshellarg($repoPath) . ' && git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? '');
$updateState = readUpdateStateFile();
$updateAvailable = !empty($updateState['update_available']);
$behindCount = isset($updateState['behind_count']) ? (int) $updateState['behind_count'] : null;
$checkedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;
?>
<main class="container mx-auto px-4 py-8">
  <div class="max-w-3xl mx-auto bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-6">
    <h1 class="text-2xl font-bold text-white mb-6">Update Status</h1>
    <?php if ($statusMessage !== ''): ?><div class="mb-4 p-3 rounded <?= $statusType === 'success' ? 'bg-green-500/20 text-green-200 border border-green-500/30' : 'bg-red-500/20 text-red-200 border border-red-500/30' ?>"><?= htmlspecialchars($statusMessage) ?></div><?php endif; ?>
    <div class="space-y-3 mb-6 text-slate-200">
      <p><span class="text-slate-400">Current version/commit:</span> <code class="bg-slate-900 px-2 py-1 rounded"><?= htmlspecialchars($commitHash ?: 'unknown') ?></code></p>
      <p><span class="text-slate-400">Branch:</span> <code class="bg-slate-900 px-2 py-1 rounded"><?= htmlspecialchars($branchName ?: 'unknown') ?></code></p>
      <p><span class="text-slate-400">Update status:</span> <?php if ($updateAvailable): ?><span class="inline-flex px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 text-sm font-semibold">Update available<?= $behindCount !== null ? ' (' . $behindCount . ' behind)' : '' ?></span><?php else: ?><span class="inline-flex px-2.5 py-1 rounded-full bg-green-500/20 text-green-300 border border-green-500/40 text-sm font-semibold">Up to date</span><?php endif; ?></p>
      <p><span class="text-slate-400">Last checked:</span> <?= htmlspecialchars($checkedAt ?: 'Never') ?><?= $checkedAt ? ' (UTC)' : '' ?></p>
    </div>
    <div class="flex flex-wrap gap-3">
      <form method="post"><input type="hidden" name="action" value="update"><button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-medium"><i class="fas fa-sync-alt mr-2"></i>Update</button></form>
      <form method="post"><input type="hidden" name="action" value="restore"><button type="submit" class="px-4 py-2 bg-yellow-600/80 hover:bg-yellow-700 text-white rounded-lg font-medium"><i class="fas fa-undo mr-2"></i>Restore Previous Version</button></form>
    </div>
  </div>
</main>
<?php include 'footer.php'; ?>
