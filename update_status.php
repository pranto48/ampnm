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

function getDockerHubUpdateStatus(): array {
    $repo = 'arifmahmudpranto/ampnm';
    $tag = 'latest';
    $socketPath = '/var/run/docker.sock';
    $isSocketWritable = is_writable($socketPath);
    
    // 1. Get local image digest if socket is writable
    $localDigest = '';
    if ($isSocketWritable) {
        $cid = trim(shell_exec('hostname') ?? '');
        if ($cid !== '') {
            $localDigest = trim(shell_exec("docker inspect --format='{{range .RepoDigests}}{{.}}{{end}}' " . escapeshellarg($cid)) ?? '');
            if (str_contains($localDigest, '@')) {
                $localDigest = explode('@', $localDigest)[1] ?? '';
            }
        }
    }
    
    // 2. Fetch latest tag information from Docker Hub Registry v2 API
    $ch = curl_init("https://hub.docker.com/v2/repositories/{$repo}/tags/{$tag}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $remoteDigest = '';
    $lastUpdated = '';
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        $remoteDigest = $data['digest'] ?? '';
        $lastUpdated = $data['last_updated'] ?? '';
        if (empty($remoteDigest) && !empty($data['images'])) {
            foreach ($data['images'] as $img) {
                if (($img['architecture'] ?? '') === 'amd64') {
                    $remoteDigest = $img['digest'] ?? '';
                    break;
                }
            }
            if (empty($remoteDigest)) {
                $remoteDigest = $data['images'][0]['digest'] ?? '';
            }
        }
    }
    
    // 3. Fallback: if socket is not mounted, compare current hardcoded version tag with Docker Hub's latest tag version
    $updateAvailable = false;
    $method = 'digest';
    if ($isSocketWritable && !empty($localDigest) && !empty($remoteDigest)) {
        $updateAvailable = ($localDigest !== $remoteDigest);
    } else {
        $method = 'version';
        $ch = curl_init("https://hub.docker.com/v2/repositories/{$repo}/tags?page_size=10");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $responseAll = curl_exec($ch);
        $httpCodeAll = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $latestTag = 'v1.10';
        if ($httpCodeAll === 200 && $responseAll) {
            $dataAll = json_decode($responseAll, true);
            if (!empty($dataAll['results'])) {
                foreach ($dataAll['results'] as $t) {
                    $name = $t['name'] ?? '';
                    if (preg_match('/^v[0-9]+\.[0-9]+$/', $name)) {
                        if (version_compare(substr($name, 1), substr($latestTag, 1), '>')) {
                            $latestTag = $name;
                        }
                    }
                }
            }
        }
        $currentTag = 'v1.10'; 
        $updateAvailable = ($latestTag !== $currentTag);
        $remoteDigest = $latestTag;
        $localDigest = $currentTag;
    }
    
    return [
        'socket_writable' => $isSocketWritable,
        'local_value' => $localDigest,
        'remote_value' => $remoteDigest,
        'last_updated' => $lastUpdated,
        'update_available' => $updateAvailable,
        'compare_method' => $method
    ];
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
        $previousCommit = trim(shell_exec('export HOME=/var/www && cd ' . escapeshellarg($repoPath) . ' && git rev-parse HEAD 2>/dev/null') ?? '');
        $cmd = 'export HOME=/var/www && cd ' . escapeshellarg($repoPath) . ' && bash ' . escapeshellarg($repoPath . '/scripts/update.sh') . ' 2>&1';
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
            $newCommit = trim(shell_exec('export HOME=/var/www && cd ' . escapeshellarg($repoPath) . ' && git rev-parse HEAD 2>/dev/null') ?? '');
            appendRestorePoint([
                'timestamp' => gmdate('c'),
                'previous_commit' => $previousCommit,
                'backup_path' => $backupPath,
                'new_commit' => $newCommit,
            ]);
            
            // Invalidate the cached update state so the UI reflects the new state immediately
            $statePath = __DIR__ . '/storage/update_state.json';
            if (file_exists($statePath)) {
                unlink($statePath);
            }
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
            
            if ($code === 0) {
                // Invalidate the cached update state so the UI reflects the new state immediately
                $statePath = __DIR__ . '/storage/update_state.json';
                if (file_exists($statePath)) {
                    unlink($statePath);
                }
            }
        } else {
            $statusMessage = 'No valid backup selected to restore.';
            $statusType = 'error';
        }
    } elseif ($statusType !== 'error' && $action === 'update_docker') {
        $cmd = 'bash ' . escapeshellarg($repoPath . '/scripts/docker_update.sh') . ' 2>&1';
        exec($cmd, $out, $code);
        $statusMessage = $code === 0 ? 'Docker self-update command successfully spawned. The container will pull the latest image and restart shortly.' : 'Docker update command failed: ' . implode("\n", $out);
        $statusType = $code === 0 ? 'success' : 'error';
    }
    releaseOpsLock($lockHandle);
}

include 'header.php';
$versionStr = getFormattedVersion($repoPath, 'HEAD');
$branchName = trim(shell_exec('export HOME=/var/www && cd ' . escapeshellarg($repoPath) . ' && git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? '');
$updateState = readUpdateStateFile();
$restorePoints = readRestorePoints();
$latestRestorePoint = $restorePoints[0] ?? null;
$updateAvailable = !empty($updateState['update_available']);
$behindCount = isset($updateState['behind_count']) ? (int) $updateState['behind_count'] : null;
$checkedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;

// Load Docker Hub updates status
$dockerHubStatus = getDockerHubUpdateStatus();
?>
<main class="container mx-auto px-4 py-8">
  <div class="max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold text-white mb-8 flex items-center gap-2">
      <i class="fas fa-sync-alt text-cyan-500"></i> Update Center
    </h1>

    <?php if ($statusMessage !== ''): ?>
      <div class="mb-6 p-4 rounded-lg <?= $statusType === 'success' ? 'bg-green-500/20 text-green-200 border border-green-500/30' : 'bg-red-500/20 text-red-200 border border-red-500/30' ?>">
        <?= htmlspecialchars($statusMessage) ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      
      <!-- CARD 1: Git Source Code Updates -->
      <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 shadow-xl flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
              <i class="fab fa-git-alt text-orange-500 text-2xl"></i> Git Repository Update
            </h2>
            <?php if ($updateAvailable): ?>
              <span class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 text-xs font-semibold">Update available</span>
            <?php else: ?>
              <span class="px-2.5 py-1 rounded-full bg-green-500/20 text-green-300 border border-green-500/40 text-xs font-semibold">Up to date</span>
            <?php endif; ?>
          </div>

          <div class="space-y-3 mb-6 text-slate-300 text-sm">
            <p><span class="text-slate-400">Current version/commit:</span> <code class="bg-slate-950 px-2 py-0.5 rounded text-cyan-400"><?= htmlspecialchars($versionStr) ?></code></p>
            <p><span class="text-slate-400">Branch:</span> <code class="bg-slate-950 px-2 py-0.5 rounded"><?= htmlspecialchars($branchName ?: 'unknown') ?></code></p>
            <?php if ($behindCount !== null): ?>
              <p><span class="text-slate-400">Behind upstream by:</span> <span class="text-amber-400 font-bold"><?= $behindCount ?> commit(s)</span></p>
            <?php endif; ?>
            <p><span class="text-slate-400">Last checked:</span> <?= htmlspecialchars($checkedAt ?: 'Never') ?><?= $checkedAt ? ' (UTC)' : '' ?></p>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-700">
          <form method="post" class="mb-4">
            <input type="hidden" name="action" value="update">
            <button type="submit" class="w-full px-4 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-semibold flex items-center justify-center gap-2 transition-colors">
              <i class="fas fa-cloud-download-alt"></i> Update from Git
            </button>
          </form>

          <!-- Restore form -->
          <form method="post" onsubmit="return confirm('Restore selected backup and restart services?');" class="flex gap-2">
            <input type="hidden" name="action" value="restore">
            <select name="backup_path" class="flex-grow px-3 py-2 rounded bg-slate-900 text-slate-200 border border-slate-700 text-sm focus:ring-2 focus:ring-cyan-500" <?= empty($restorePoints) ? 'disabled' : '' ?>>
              <?php foreach ($restorePoints as $point): ?>
                <option value="<?= htmlspecialchars((string) ($point['backup_path'] ?? '')) ?>"><?= htmlspecialchars((string) (($point['timestamp'] ?? '') . ' | ' . basename($point['backup_path'] ?? ''))) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" <?= empty($restorePoints) ? 'disabled' : '' ?> class="px-4 py-2 bg-yellow-600/80 hover:bg-yellow-700 disabled:opacity-50 text-white rounded-lg font-medium text-sm transition-colors flex items-center gap-1.5" title="Restore Selected Commit">
              <i class="fas fa-undo"></i> Restore
            </button>
          </form>
        </div>
      </div>

      <!-- CARD 2: Docker Hub Registry Updates -->
      <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 shadow-xl flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
              <i class="fab fa-docker text-blue-400 text-2xl"></i> Docker Hub Update
            </h2>
            <?php if ($dockerHubStatus['update_available']): ?>
              <span class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 text-xs font-semibold">Update available</span>
            <?php else: ?>
              <span class="px-2.5 py-1 rounded-full bg-green-500/20 text-green-300 border border-green-500/40 text-xs font-semibold">Up to date</span>
            <?php endif; ?>
          </div>

          <div class="space-y-3 mb-6 text-slate-300 text-sm">
            <p>
              <span class="text-slate-400">Current tag/digest:</span> 
              <code class="bg-slate-950 px-2 py-0.5 rounded text-blue-300 select-all" title="<?= htmlspecialchars($dockerHubStatus['local_value']) ?>">
                <?= htmlspecialchars(strlen($dockerHubStatus['local_value']) > 15 ? substr($dockerHubStatus['local_value'], 0, 15) . '...' : $dockerHubStatus['local_value']) ?>
              </code>
            </p>
            <p>
              <span class="text-slate-400">Latest Docker Hub:</span> 
              <code class="bg-slate-950 px-2 py-0.5 rounded text-blue-300 select-all" title="<?= htmlspecialchars($dockerHubStatus['remote_value']) ?>">
                <?= htmlspecialchars(strlen($dockerHubStatus['remote_value']) > 15 ? substr($dockerHubStatus['remote_value'], 0, 15) . '...' : $dockerHubStatus['remote_value']) ?>
              </code>
            </p>
            <?php if (!empty($dockerHubStatus['last_updated'])): ?>
              <p><span class="text-slate-400">Last updated:</span> <?= htmlspecialchars($dockerHubStatus['last_updated']) ?></p>
            <?php endif; ?>
            <p><span class="text-slate-400">Check type:</span> <code class="bg-slate-950 px-2 py-0.5 rounded capitalize"><?= $dockerHubStatus['compare_method'] ?> comparison</code></p>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-700">
          <?php if (!$dockerHubStatus['socket_writable']): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-200 text-xs rounded-lg p-3 mb-4">
              <i class="fas fa-exclamation-triangle text-amber-400 mr-1"></i>
              <strong>Docker socket not mounted!</strong> Automatic image updates and digest-based checks are unavailable. Run the container with:
              <code class="bg-slate-950 px-1 py-0.5 rounded block mt-1 font-mono text-[10px]">-v /var/run/docker.sock:/var/run/docker.sock</code>
            </div>
          <?php endif; ?>

          <form method="post" onsubmit="return confirm('This will recreate your running container with the latest Docker Hub image. The app will be offline for a few seconds. Proceed?');">
            <input type="hidden" name="action" value="update_docker">
            <button type="submit" <?= !$dockerHubStatus['socket_writable'] ? 'disabled' : '' ?> class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg font-semibold flex items-center justify-center gap-2 transition-colors">
              <i class="fab fa-docker"></i> Update from Docker Hub
            </button>
          </form>
        </div>
      </div>

    </div>

    <!-- Restore Points List -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 shadow-xl text-slate-300">
      <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
        <i class="fas fa-history text-slate-400"></i> Recent Restore Points
      </h2>
      <?php if ($latestRestorePoint): ?>
        <div class="space-y-2 text-sm bg-slate-900 p-4 rounded-lg border border-slate-700 font-mono">
          <p><span class="text-slate-500">Timestamp:</span> <?= htmlspecialchars((string) ($latestRestorePoint['timestamp'] ?? 'n/a')) ?></p>
          <p><span class="text-slate-500">Prev Commit:</span> <?= htmlspecialchars((string) ($latestRestorePoint['previous_commit'] ?? 'n/a')) ?></p>
          <p><span class="text-slate-500">New Commit:</span> <?= htmlspecialchars((string) ($latestRestorePoint['new_commit'] ?? 'n/a')) ?></p>
          <p class="truncate"><span class="text-slate-500">Backup File:</span> <span class="text-slate-400"><?= htmlspecialchars((string) (($latestRestorePoint['backup_path'] ?? 'n/a'))) ?></span></p>
        </div>
      <?php else: ?>
        <p class="text-sm text-slate-400 italic">No restore points available yet.</p>
      <?php endif; ?>
    </div>

  </div>
</main>
<?php include 'footer.php'; ?>
