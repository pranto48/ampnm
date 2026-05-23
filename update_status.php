<?php
require_once 'includes/auth_check.php';
require_once 'includes/update_state.php';

function getRestorePointsPath(): string
{
    return __DIR__ . '/storage/update_restore_points.json';
}

function readRestorePoints(): array
{
    $path = getRestorePointsPath();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function writeRestorePoints(array $items): void
{
    $path = getRestorePointsPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function appendRestorePoint(array $entry): void
{
    $existing = readRestorePoints();
    array_unshift($existing, $entry);
    writeRestorePoints(array_slice($existing, 0, 20));
}

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
$lockPath = '/tmp/ampnm-code-update.lock';
$lockHandle = null;

function acquireOpsLock(string $lockPath)
{
    $handle = fopen($lockPath, 'c');
    if ($handle === false) {
        return false;
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return false;
    }
    return $handle;
}

function releaseOpsLock($handle): void
{
    if (!is_resource($handle)) {
        return;
    }
    flock($handle, LOCK_UN);
    fclose($handle);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockHandle = acquireOpsLock($lockPath);
    if ($lockHandle === false) {
        $statusMessage = 'Another update/restore operation is already running. Please wait and retry.';
        $statusType = 'error';
    }
    $action = $_POST['action'] ?? '';
    if ($statusType !== 'error' && $action === 'update') {
        $previousCommit = trim(shell_exec('cd ' . escapeshellarg($repoPath) . ' && git rev-parse HEAD 2>/dev/null') ?? '');
        $cmd = 'cd ' . escapeshellarg($repoPath) . ' && bash ' . escapeshellarg($repoPath . '/scripts/update.sh') . ' 2>&1';
        exec($cmd, $out, $code);
        $statusMessage = $code === 0 ? 'Update completed successfully.' : 'Update command failed.';
        $statusType = $code === 0 ? 'success' : 'error';
        if ($code === 0) {
            $backupPath = '';
            foreach ($out as $line) {
                if (str_contains($line, 'Creating backup at ')) {
                    $backupPath = trim((string) substr($line, strpos($line, 'Creating backup at ') + strlen('Creating backup at ')));
                }
            }
            $newCommit = trim(shell_exec('cd ' . escapeshellarg($repoPath) . ' && git rev-parse HEAD 2>/dev/null') ?? '');
            appendRestorePoint([
                'timestamp' => gmdate('c'),
                'previous_commit' => $previousCommit,
                'backup_path' => $backupPath,
                'new_commit' => $newCommit,
            ]);
        }
    } elseif ($statusType !== 'error' && $action === 'restore') {
        $selected = trim((string) ($_POST['backup_path'] ?? ''));
        $backupBase = rtrim(getenv('BACKUP_BASE') ?: '/var/www/html/docker-ampnm/data/code_backups', '/\\');
        $resolvedBase = realpath($backupBase);
        $resolvedSelected = $selected !== '' ? realpath($selected) : false;
        $valid = $resolvedBase !== false && $resolvedSelected !== false && str_starts_with($resolvedSelected, rtrim($resolvedBase, '/\\') . DIRECTORY_SEPARATOR);
        if ($valid) {
            $cmd = 'HOST_APP_DIR=' . escapeshellarg($repoPath) . ' BACKUP_PATH=' . escapeshellarg($resolvedSelected) . ' bash ' . escapeshellarg($repoPath . '/scripts/restore_backup.sh') . ' 2>&1';
            exec($cmd, $out, $code);
            $restoredCommit = '';
            foreach ($out as $line) {
                if (stripos($line, 'Restarting services') !== false) {
                    $restartResult = 'Restart attempted';
                }
            }
            $commitFile = $resolvedSelected . '/previous_commit.txt';
            if (is_file($commitFile)) {
                $restoredCommit = trim((string) file_get_contents($commitFile));
            }
            $statusMessage = $code === 0
                ? 'Restore completed. Restored commit: ' . ($restoredCommit !== '' ? $restoredCommit : 'unknown') . '. Service restart: ' . ($restartResult ?? 'see logs') . '.'
                : 'Restore command failed.';
            $statusType = $code === 0 ? 'success' : 'error';
        } else {
            $statusMessage = 'No valid backup selected to restore.';
            $statusType = 'error';
        }
    }
    releaseOpsLock($lockHandle);
}

include 'header.php';
$versionStr = getFormattedVersion($repoPath, 'HEAD');
$branchName = trim(shell_exec('cd ' . escapeshellarg($repoPath) . ' && git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? '');
$updateState = readUpdateStateFile();
$restorePoints = readRestorePoints();
$latestRestorePoint = $restorePoints[0] ?? null;
$updateAvailable = !empty($updateState['update_available']);
$behindCount = isset($updateState['behind_count']) ? (int) $updateState['behind_count'] : null;
$checkedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;
?>
<main class="container mx-auto px-4 py-8">
  <div class="max-w-3xl mx-auto bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-6">
    <h1 class="text-2xl font-bold text-white mb-6">Update Status</h1>
    <?php if ($statusMessage !== ''): ?><div class="mb-4 p-3 rounded <?= $statusType === 'success' ? 'bg-green-500/20 text-green-200 border border-green-500/30' : 'bg-red-500/20 text-red-200 border border-red-500/30' ?>"><?= htmlspecialchars($statusMessage) ?></div><?php endif; ?>
    <div class="space-y-3 mb-6 text-slate-200">
      <p><span class="text-slate-400">Current version/commit:</span> <code class="bg-slate-900 px-2 py-1 rounded"><?= htmlspecialchars($versionStr) ?></code></p>
      <p><span class="text-slate-400">Branch:</span> <code class="bg-slate-900 px-2 py-1 rounded"><?= htmlspecialchars($branchName ?: 'unknown') ?></code></p>
      <p><span class="text-slate-400">Update status:</span> <?php if ($updateAvailable): ?><span class="inline-flex px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 text-sm font-semibold">Update available<?= $behindCount !== null ? ' (' . $behindCount . ' behind)' : '' ?></span><?php else: ?><span class="inline-flex px-2.5 py-1 rounded-full bg-green-500/20 text-green-300 border border-green-500/40 text-sm font-semibold">Up to date</span><?php endif; ?></p>
      <p><span class="text-slate-400">Last checked:</span> <?= htmlspecialchars($checkedAt ?: 'Never') ?><?= $checkedAt ? ' (UTC)' : '' ?></p>
    </div>
    <div class="flex flex-wrap gap-3">
      <form method="post"><input type="hidden" name="action" value="update"><button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-medium"><i class="fas fa-sync-alt mr-2"></i>Update</button></form>
      <form method="post" onsubmit="return confirm('Restore selected backup and restart services?');">
        <input type="hidden" name="action" value="restore">
        <select name="backup_path" class="px-3 py-2 rounded bg-slate-900 text-slate-200 border border-slate-700" <?= empty($restorePoints) ? 'disabled' : '' ?>>
          <?php foreach ($restorePoints as $point): ?>
            <option value="<?= htmlspecialchars((string) ($point['backup_path'] ?? '')) ?>"><?= htmlspecialchars((string) (($point['timestamp'] ?? '') . ' | ' . (($point['backup_path'] ?? 'n/a')))) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" <?= empty($restorePoints) ? 'disabled' : '' ?> class="px-4 py-2 bg-yellow-600/80 hover:bg-yellow-700 disabled:opacity-50 text-white rounded-lg font-medium"><i class="fas fa-undo mr-2"></i>Restore Previous Version</button>
      </form>
    </div>
    <div class="mt-6 text-slate-300">
      <h2 class="text-lg font-semibold text-white mb-2">Recent Restore Points</h2>
      <?php if ($latestRestorePoint): ?>
        <p class="text-sm">Latest: <code><?= htmlspecialchars((string) ($latestRestorePoint['timestamp'] ?? 'n/a')) ?></code> | prev <code><?= htmlspecialchars((string) ($latestRestorePoint['previous_commit'] ?? 'n/a')) ?></code> | new <code><?= htmlspecialchars((string) ($latestRestorePoint['new_commit'] ?? 'n/a')) ?></code></p>
      <?php else: ?>
        <p class="text-sm text-amber-300">No restore points available yet.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include 'footer.php'; ?>
