<?php
require_once 'includes/auth_check.php';
include 'header.php';

$canonicalRepoUrl = 'https://github.com/pranto48/ampnm.git';
$canonicalBranch = getenv('AMPNM_UPDATE_BRANCH') ?: 'main';
$targetUpstreamRef = 'origin/' . $canonicalBranch;
$allowedRepoBase = rtrim(getenv('AMPNM_ALLOWED_REPO_BASE') ?: '/var/www/html', '/\\');

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$autoDetection = (function (string $startPath): array {
    $normalize = static function (string $path): string {
        return rtrim($path, '/\\');
    };

    $attempts = [];
    $addAttempt = static function (string $path) use (&$attempts, $normalize): void {
        $normalized = $normalize($path);
        if (!in_array($normalized, $attempts, true)) {
            $attempts[] = $normalized;
        }
    };

    $envPath = getenv('AMPNM_REPO_PATH') ?: getenv('REPO_PATH');
    if ($envPath) {
        $addAttempt($envPath);
    }

    $current = realpath($startPath) ?: $startPath;
    $addAttempt($current);
    while ($current && $current !== dirname($current)) {
        $current = dirname($current);
        $addAttempt($current);
    }

    $parent = dirname(realpath($startPath) ?: $startPath);
    $addAttempt($parent . DIRECTORY_SEPARATOR . 'ampnm-project');
    $addAttempt('/var/www/html/ampnm-project');

    $detected = null;
    foreach ($attempts as $path) {
        $gitDir = $path . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir) || is_file($gitDir)) {
            $detected = $path;
            break;
        }
    }

    return [
        'path' => $detected,
        'attempts' => $attempts,
        'fallback' => $attempts[0] ?? realpath($startPath) ?: $startPath,
    ];
})(__DIR__);

$autoDetectedRepoPath = $autoDetection['path'];
$defaultRepoPath = $autoDetectedRepoPath ?? $autoDetection['fallback'];
$repoPath = isset($_POST['repo_path']) && trim($_POST['repo_path']) !== ''
    ? rtrim(trim($_POST['repo_path']), '/\\')
    : $defaultRepoPath;
$gitBinary = trim(shell_exec('which git 2>/dev/null'));
$gitAvailable = $gitBinary !== '';
$phpUser = safeTrim(shell_exec('whoami')) ?: 'www-data';
$containerHostname = safeTrim(shell_exec('hostname')) ?: 'docker';
$nodeVersion = safeTrim(shell_exec('node -v 2>/dev/null'));
$npmVersion = safeTrim(shell_exec('npm -v 2>/dev/null'));
$pnpmVersion = safeTrim(shell_exec('pnpm -v 2>/dev/null'));
$gitMarker = $repoPath . DIRECTORY_SEPARATOR . '.git';
$isGitRepo = $gitAvailable && (is_dir($gitMarker) || is_file($gitMarker));
$remoteUrl = '';
$originConfigured = false;
$remoteReachable = null;
$aheadCount = null;
$behindCount = null;
$workingTreeClean = null;
$updateAvailable = ($behindCount !== null && $behindCount > 0);

$action = $_POST['action'] ?? null;
$forceUpdate = isset($_POST['force_update']) && $_POST['force_update'] === '1';
$forceUpdateEnabled = filter_var(getenv('AMPNM_FORCE_UPDATE_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN);
$statusMessage = '';
$statusType = '';
$commandOutput = [];
$auditLogPath = rtrim(getenv('AMPNM_AUDIT_LOG_PATH') ?: '/var/log/ampnm', '/\\') . DIRECTORY_SEPARATOR . 'code-update-audit.log';
$latestAuditEntries = [];

$updateLockPath = '/tmp/ampnm-code-update.lock';
$updateLockHandle = null;
$updateStatePath = getenv('AMPNM_UPDATE_STATE_FILE') ?: (__DIR__ . '/storage/update_state.json');
$lastCheckedAt = null;
$scheduledUpdateAvailable = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $statusMessage = 'Request rejected: invalid or missing CSRF token.';
        $statusType = 'error';
        $action = null;
    } else {
        $repoValidation = resolveAllowedRepoPath($_POST['repo_path'] ?? $repoPath, $allowedRepoBase);
        if (!$repoValidation['ok']) {
            $statusMessage = 'Request rejected: ' . $repoValidation['error'];
            $statusType = 'error';
            $action = null;
        } else {
            $repoPath = $repoValidation['path'];
            $gitMarker = $repoPath . DIRECTORY_SEPARATOR . '.git';
            $isGitRepo = $gitAvailable && (is_dir($gitMarker) || is_file($gitMarker));
        }
    }
}

function acquireUpdateLock(string $lockPath, array &$commandOutput)
{
    $commandOutput['Lock'] = ($commandOutput['Lock'] ?? '') . "Lock file: {$lockPath}
";

    $handle = fopen($lockPath, 'c');
    if ($handle === false) {
        $commandOutput['Lock'] .= 'Failed to open lock file for update operations.
';
        return false;
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        $commandOutput['Lock'] .= 'Lock acquisition failed: another session currently holds the lock.
';
        fclose($handle);
        return false;
    }

    ftruncate($handle, 0);
    fwrite($handle, sprintf('pid=%s time=%s action=%s
', getmypid(), date('c'), $_POST['action'] ?? 'unknown'));
    fflush($handle);
    $commandOutput['Lock'] .= sprintf('Lock acquired by pid %s at %s.
', (string) getmypid(), date('c'));

    return $handle;
}

function releaseUpdateLock($handle, array &$commandOutput): void
{
    if (!is_resource($handle)) {
        return;
    }

    $commandOutput['Lock'] = ($commandOutput['Lock'] ?? '') . sprintf('Lock releasing by pid %s at %s.
', (string) getmypid(), date('c'));
    flock($handle, LOCK_UN);
    fclose($handle);
    $commandOutput['Lock'] .= 'Lock released.
';
}

function runGitCommand(string $repoPath, string $command): string
{
    $escapedPath = escapeshellarg($repoPath);
    $fullCommand = "cd {$escapedPath} && {$command} 2>&1";
    return shell_exec($fullCommand) ?? '';
}

function runShellCommand(string $command): string
{
    return shell_exec($command) ?? '';
}

function runShellCommandWithStatus(string $command): array
{
    $output = [];
    $exitCode = 1;
    exec($command . ' 2>&1', $output, $exitCode);
    return [
        'output' => implode("\n", $output),
        'exitCode' => $exitCode,
    ];
}

function getClientIpAddress(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim((string) ($parts[0] ?? 'unknown'));
            }
            return trim($value);
        }
    }
    return 'unknown';
}

function redactSensitiveOutput(string $output): string
{
    $redacted = preg_replace('/(authorization:\s*bearer\s+)[^\s]+/i', '$1[REDACTED]', $output);
    $redacted = preg_replace('/(token=)[^\s&]+/i', '$1[REDACTED]', (string) $redacted);
    $redacted = preg_replace('/(password=)[^\s&]+/i', '$1[REDACTED]', (string) $redacted);
    return (string) $redacted;
}

function appendAuditLog(string $logPath, array $entry): bool
{
    $directory = dirname($logPath);
    if (!is_dir($directory) && !mkdir($directory, 0750, true)) {
        return false;
    }
    if (!file_exists($logPath)) {
        touch($logPath);
        @chmod($logPath, 0640);
    }
    return file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}

function readRecentAuditLogs(string $logPath, int $limit = 10): array
{
    if (!is_file($logPath)) {
        return [];
    }
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || empty($lines)) {
        return [];
    }
    $rows = [];
    foreach (array_reverse($lines) as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
        if (count($rows) >= $limit) {
            break;
        }
    }
    return $rows;
}

function resolveAllowedRepoPath(?string $path, string $allowedBase): array
{
    $candidate = rtrim((string) ($path ?? ''), '/\\');
    if ($candidate === '') {
        return ['ok' => false, 'error' => 'Repository path is required.'];
    }

    $baseRealPath = realpath($allowedBase);
    if ($baseRealPath === false || !is_dir($baseRealPath)) {
        return ['ok' => false, 'error' => 'Configured allowed repository base path is invalid.'];
    }

    $resolvedPath = realpath($candidate);
    if ($resolvedPath === false) {
        $parentRealPath = realpath(dirname($candidate));
        if ($parentRealPath === false) {
            return ['ok' => false, 'error' => 'Repository path does not exist and parent directory could not be resolved.'];
        }
        $resolvedPath = rtrim($parentRealPath, '/\\') . DIRECTORY_SEPARATOR . basename($candidate);
    }

    $baseWithSeparator = rtrim($baseRealPath, '/\\') . DIRECTORY_SEPARATOR;
    $isAllowed = $resolvedPath === $baseRealPath || str_starts_with($resolvedPath, $baseWithSeparator);
    if (!$isAllowed) {
        return ['ok' => false, 'error' => 'Repository path is outside the allowed base path.'];
    }

    return ['ok' => true, 'path' => $resolvedPath];
}

function commandLooksSuccessful(string $output): bool
{
    $normalized = strtolower($output);
    return !str_contains($normalized, 'fatal:') && !str_contains($normalized, 'error:');
}

function safeTrim(?string $value): string
{
    $value = $value ?? '';
    $lines = explode("\n", $value);
    $lines = array_filter(array_map('trim', $lines));
    return implode("\n", $lines);
}

function ensureDirectory(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    return mkdir($path, 0755, true);
}

function isDirectoryEmpty(string $path): bool
{
    if (!is_dir($path)) {
        return true;
    }

    $files = scandir($path);
    if ($files === false) {
        return false;
    }

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            return false;
        }
    }

    return true;
}

function collectSyncMetrics(string $repoPath, string $upstreamRef): array
{
    $metrics = [
        'workingTreeClean' => null,
        'remoteReachable' => null,
        'aheadCount' => null,
        'behindCount' => null,
    ];

    $workingTree = runGitCommand($repoPath, 'git status --porcelain');
    $metrics['workingTreeClean'] = $workingTree === '';

    $lsRemoteOutput = runGitCommand($repoPath, 'git ls-remote --exit-code origin HEAD');
    $metrics['remoteReachable'] = $lsRemoteOutput !== '' && !str_starts_with($lsRemoteOutput, 'fatal:');

    if ($upstreamRef !== '') {
        $aheadBehind = safeTrim(runGitCommand($repoPath, 'git rev-list --left-right --count ' . escapeshellarg($upstreamRef . '...HEAD')));
        if ($aheadBehind !== '' && !str_starts_with($aheadBehind, 'fatal:')) {
            $parts = preg_split('/\s+/', trim($aheadBehind));
            if (count($parts) >= 2) {
                $metrics['behindCount'] = (int) $parts[0];
                $metrics['aheadCount'] = (int) $parts[1];
            }
        }
    }

    return $metrics;
}


function readUpdateState(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function writeUpdateState(string $path, array $state): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function performUpdateCheck(string $repoPath, string $upstreamRef, string $statePath, array &$commandOutput): array
{
    $fetchOutput = runGitCommand($repoPath, 'git fetch --all --prune');
    $commandOutput['Fetch'] = $fetchOutput;

    $metrics = collectSyncMetrics($repoPath, $upstreamRef);
    $behindCount = $metrics['behindCount'];
    $aheadCount = $metrics['aheadCount'];
    $updateAvailable = ($behindCount !== null && $behindCount > 0);

    $state = [
        'ok' => true,
        'checked_at' => gmdate('c'),
        'repo_path' => $repoPath,
        'upstream_ref' => $upstreamRef,
        'behind_count' => $behindCount,
        'ahead_count' => $aheadCount,
        'update_available' => $updateAvailable,
    ];

    writeUpdateState($statePath, $state);

    return $metrics;
}

$currentBranch = $isGitRepo ? safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD')) : '';
$localCommit = $isGitRepo ? safeTrim(runGitCommand($repoPath, 'git rev-parse HEAD')) : '';
$upstreamRef = $isGitRepo ? $targetUpstreamRef : '';
$remoteCommit = '';
$remoteUrl = $isGitRepo ? safeTrim(runGitCommand($repoPath, 'git config --get remote.origin.url')) : '';
$originConfigured = $remoteUrl !== '';
$updateState = readUpdateState($updateStatePath);
$lastCheckedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;
$scheduledUpdateAvailable = !empty($updateState['update_available']);

$isUpdateAction = ($action === 'check' || $action === 'update');

if ($isGitRepo) {
    if (!$originConfigured) {
        $escapedOrigin = escapeshellarg($canonicalRepoUrl);
        $addedOrigin = safeTrim(runGitCommand($repoPath, "git remote add origin {$escapedOrigin}"));
        $commandOutput['Remote'] = $addedOrigin !== '' ? $addedOrigin : 'origin set to canonical repository.';
        $remoteUrl = $canonicalRepoUrl;
        $originConfigured = true;
    }

    if (!$isUpdateAction) {
        $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
        if (str_starts_with($remoteCommit, 'fatal:')) {
            $remoteCommit = '';
        }

        $metrics = collectSyncMetrics($repoPath, $upstreamRef);
        $workingTreeClean = $metrics['workingTreeClean'];
        $remoteReachable = $metrics['remoteReachable'];
        $aheadCount = $metrics['aheadCount'];
        $behindCount = $metrics['behindCount'];
        $updateAvailable = ($behindCount !== null && $behindCount > 0);
        $lastCheckedAt = gmdate('c');
    }
}

if ($isUpdateAction && $isGitRepo) {
    $oldCommitForAudit = $localCommit;
    $restartResultForAudit = 'not_applicable';
    $updateLockHandle = acquireUpdateLock($updateLockPath, $commandOutput);
    if ($updateLockHandle === false) {
        $statusMessage = 'Update already in progress by another session.';
        $statusType = 'warning';
    } else {
        try {
            if ($action === 'check') {
    $metrics = performUpdateCheck($repoPath, $upstreamRef, $updateStatePath, $commandOutput);
    $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
    if ($currentBranch !== $canonicalBranch) {
        $commandOutput['Branch Check'] = sprintf('Warning: current branch is "%s", expected "%s". Sync metrics are computed against %s.', $currentBranch ?: 'unknown', $canonicalBranch, $targetUpstreamRef);
    }
    $statusOutput = runGitCommand($repoPath, 'git status -sb');
    $commandOutput['Status'] = $statusOutput;
    $statusMessage = 'Fetched latest metadata. Compare local and remote commits below.';
    $statusType = 'info';

    $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
    if (str_starts_with($remoteCommit, 'fatal:')) {
        $remoteCommit = '';
    }

    $metrics = collectSyncMetrics($repoPath, $upstreamRef);
$workingTreeClean = $metrics['workingTreeClean'];
    $remoteReachable = $metrics['remoteReachable'];
    $aheadCount = $metrics['aheadCount'];
    $behindCount = $metrics['behindCount'];
    $updateAvailable = ($behindCount !== null && $behindCount > 0);
    $lastCheckedAt = gmdate('c');
            }

            if ($action === 'update') {
    if ($workingTreeClean === false && (!$forceUpdateEnabled || !$forceUpdate)) {
        $statusMessage = 'Update blocked: working tree is dirty. Commit or stash changes first, then run update again.';
        $statusType = 'error';
        if ($forceUpdate && !$forceUpdateEnabled) {
            $commandOutput['Force Update Warning'] = 'WARNING: Force update requested but currently disabled by AMPNM_FORCE_UPDATE_ENABLED=0. Update stopped to protect local changes.';
        }
    } elseif (!$updateAvailable) {
        $statusMessage = 'No remote changes detected. Repository is already up to date.';
        $statusType = 'info';
    } else {
        if ($workingTreeClean === false && $forceUpdateEnabled && $forceUpdate) {
            $commandOutput['Force Update Warning'] = 'WARNING: Force update enabled while working tree has local changes. Pull may fail or require manual conflict resolution.';
        }

        $fetchOutput = runGitCommand($repoPath, 'git fetch --all --prune');
        $pullOutput = runGitCommand($repoPath, 'git pull --ff-only');
        $statusOutput = runGitCommand($repoPath, 'git status -sb');
        $commandOutput['Fetch'] = $fetchOutput;
        $commandOutput['Pull'] = $pullOutput;
        $commandOutput['Status'] = $statusOutput;

        $pullSucceeded = commandLooksSuccessful($pullOutput);

        if ($pullSucceeded) {
            $composeDirectory = __DIR__;
            $restartOutput = runShellCommand('cd ' . escapeshellarg($composeDirectory) . ' && (docker compose restart 2>&1 || docker-compose restart 2>&1)');
            $restartSucceeded = commandLooksSuccessful($restartOutput);

            $healthcheckUrl = trim((string) getenv('AMPNM_HEALTHCHECK_URL'));
            $healthTargets = ['http://127.0.0.1/'];
            if ($healthcheckUrl !== '') {
                $healthTargets[] = $healthcheckUrl;
            }

            $maxAttempts = 5;
            $sleepSeconds = 2;
            $healthLog = [];
            $healthPassed = false;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $healthLog[] = "Attempt {$attempt}/{$maxAttempts}";
                $attemptFailed = false;

                foreach ($healthTargets as $target) {
                    $probeResult = runShellCommandWithStatus('curl -fsS --max-time 10 ' . escapeshellarg($target));
                    $probeOutput = trim($probeResult['output']);
                    $probePassed = $probeResult['exitCode'] === 0;
                    $healthLog[] = sprintf('[%s] %s (exit=%d)', $probePassed ? 'PASS' : 'FAIL', $target, $probeResult['exitCode']);
                    $healthLog[] = $probeOutput !== '' ? $probeOutput : 'No output';

                    if (!$probePassed) {
                        $attemptFailed = true;
                    }
                }

                if (!$attemptFailed) {
                    $healthPassed = true;
                    $healthLog[] = 'Health check result: PASS';
                    break;
                }

                if ($attempt < $maxAttempts) {
                    $healthLog[] = "Health check result: FAIL (retrying in {$sleepSeconds}s)";
                    sleep($sleepSeconds);
                }
            }

            if (!$healthPassed) {
            $healthLog[] = 'Health check result: FAIL';
            }

            $commandOutput['Restart'] = $restartOutput;
            $commandOutput['Health Check'] = implode("\n", $healthLog);
            $restartResultForAudit = ($restartSucceeded && $healthPassed) ? 'success' : 'failed';

            if ($pullSucceeded && $restartSucceeded && $healthPassed) {
                $statusMessage = 'AmpNM updated from GitHub, containers restarted, and health checks passed.';
                $statusType = 'success';
            } else {
                $statusMessage = "Update completed with issues. Manual recovery steps:\n"
                    . "1) Inspect Command Logs (Pull/Restart/Health Check).\n"
                    . "2) Run docker compose ps and docker compose logs.\n"
                    . "3) Re-run docker compose restart.\n"
                    . "4) Verify endpoints manually via curl.\n"
                    . "5) Roll back or redeploy if service remains unhealthy.";
                $statusType = ($restartSucceeded || $healthPassed) ? 'warning' : 'error';
            }
        } else {
            $restartResultForAudit = 'skipped_pull_failed';
            $statusMessage = "Update failed before restart. Manual recovery steps:\n"
                . "1) Review Pull output for conflicts/errors.\n"
                . "2) Resolve git issues locally (stash/commit/reset as needed).\n"
                . "3) Re-run update after repository is clean.";
            $statusType = 'error';
        }
    }

    $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
    $localCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse HEAD'));
    $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
    if (str_starts_with($remoteCommit, 'fatal:')) {
        $remoteCommit = '';
    }

    $metrics = collectSyncMetrics($repoPath, $upstreamRef);
$workingTreeClean = $metrics['workingTreeClean'];
    $remoteReachable = $metrics['remoteReachable'];
    $aheadCount = $metrics['aheadCount'];
    $behindCount = $metrics['behindCount'];
    $updateAvailable = ($behindCount !== null && $behindCount > 0);
    $lastCheckedAt = gmdate('c');
            }

            $auditEntry = [
                'timestamp' => date('c'),
                'action' => $action,
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? ($_SESSION['user'] ?? 'unknown'),
                'client_ip' => getClientIpAddress(),
                'repo_path' => $repoPath,
                'old_commit' => $oldCommitForAudit ?: null,
                'new_commit' => $localCommit ?: null,
                'restart_result' => $action === 'check' ? 'not_applicable' : $restartResultForAudit,
                'status_type' => $statusType,
            ];
            appendAuditLog($auditLogPath, $auditEntry);
        } finally {
            releaseUpdateLock($updateLockHandle, $commandOutput);
        }
    }
}

if ($action === 'clone' && $gitAvailable && !$isGitRepo) {
    $oldCommitForAudit = null;
    $restartResultForAudit = 'not_applicable';
    $cloneTarget = $repoPath;
    $parentDir = dirname($cloneTarget);
    $commandOutput['Validation'] = '';

    if (!is_dir($parentDir) && !ensureDirectory($parentDir)) {
        $statusMessage = 'Failed to create parent directory for the repository path.';
        $statusType = 'error';
    } elseif (file_exists($cloneTarget) && !is_dir($cloneTarget)) {
        $statusMessage = 'Repository path points to a file. Please choose a directory.';
        $statusType = 'error';
    } elseif (!isDirectoryEmpty($cloneTarget) && !is_dir($cloneTarget . DIRECTORY_SEPARATOR . '.git')) {
        $statusMessage = 'Repository path is not empty. Please point to an empty directory or existing Git repo.';
        $statusType = 'error';
    } else {
        ensureDirectory($cloneTarget);
        $cloneCommand = sprintf('git clone --depth 1 %s %s', escapeshellarg($canonicalRepoUrl), escapeshellarg($cloneTarget));
        $cloneOutput = runShellCommand($cloneCommand);
        $commandOutput['Clone'] = $cloneOutput;
        $isGitRepo = is_dir($gitMarker) || is_file($gitMarker);

        if ($isGitRepo) {
            $statusMessage = 'Repository cloned successfully from GitHub.';
            $statusType = 'success';
            $remoteUrl = $canonicalRepoUrl;
            $originConfigured = true;
            $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
            $localCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse HEAD'));
            $upstreamRef = $targetUpstreamRef;
            $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
            if (str_starts_with($remoteCommit, 'fatal:')) {
                $remoteCommit = '';
            }

            $metrics = collectSyncMetrics($repoPath, $upstreamRef);
            $workingTreeClean = $metrics['workingTreeClean'];
            $remoteReachable = $metrics['remoteReachable'];
            $aheadCount = $metrics['aheadCount'];
            $behindCount = $metrics['behindCount'];
            $updateAvailable = ($behindCount !== null && $behindCount > 0);
        } else {
            $statusMessage = 'Clone failed. Review the output below for details.';
            $statusType = 'error';
        }

        $auditEntry = [
            'timestamp' => date('c'),
            'action' => 'clone',
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? ($_SESSION['user'] ?? 'unknown'),
            'client_ip' => getClientIpAddress(),
            'repo_path' => $repoPath,
            'old_commit' => $oldCommitForAudit,
            'new_commit' => $localCommit ?: null,
            'restart_result' => $restartResultForAudit,
            'status_type' => $statusType,
        ];
        appendAuditLog($auditLogPath, $auditEntry);
    }
}

foreach ($commandOutput as $title => $output) {
    $commandOutput[$title] = redactSensitiveOutput((string) $output);
}

$latestAuditEntries = readRecentAuditLogs($auditLogPath, 10);

?>
<main>
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <p class="text-sm text-slate-400 uppercase tracking-wide">Maintenance</p>
                <h1 class="text-3xl font-bold text-white">Docker Update Manager</h1>
                <p class="text-slate-400 mt-1">Sync the AMPNM Docker app with the latest code from <a href="https://github.com/pranto48/ampnm" class="text-cyan-400 hover:underline" target="_blank" rel="noopener noreferrer">github.com/pranto48/ampnm</a>.</p>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-400 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2">
                <i class="fas fa-shield-alt text-cyan-400"></i>
                <span class="text-slate-200">Admins only</span>
            </div>
        </div>

        <?php if (!$gitAvailable): ?>
            <div class="bg-red-500/10 border border-red-500/40 text-red-300 rounded-lg p-4 mb-6">
                <p class="font-semibold mb-1">Git not found on this container.</p>
                <p class="text-sm">Install Git in the Docker image to enable code syncing (e.g., <code>apt-get update && apt-get install -y git</code>).</p>
            </div>
        <?php elseif (!$isGitRepo): ?>
            <div class="bg-yellow-500/10 border border-yellow-500/40 text-yellow-200 rounded-lg p-4 mb-6">
                <p class="font-semibold mb-1">Repository not detected at <code><?php echo htmlspecialchars($repoPath); ?></code>.</p>
                <p class="text-sm">Make sure the Docker app files include the <code>.git</code> folder or adjust the path below. You can also set <code>AMPNM_REPO_PATH</code> in the container to point directly to the mounted repository (e.g., <code>/var/www/html/ampnm-project</code>) or clone the official repo into that path.</p>
                <?php if (empty($autoDetectedRepoPath) && !empty($autoDetection['attempts'])): ?>
                    <p class="text-xs text-yellow-100 mt-2">
                        Checked automatically: <code><?php echo htmlspecialchars(implode(', ', $autoDetection['attempts'])); ?></code>
                    </p>
                <?php elseif (!empty($autoDetectedRepoPath)): ?>
                    <p class="text-xs text-yellow-100 mt-2">Nearest <code>.git</code> found at <code><?php echo htmlspecialchars($autoDetectedRepoPath); ?></code>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <?php if ($scheduledUpdateAvailable): ?>
            <div class="mb-6 bg-amber-500/10 border border-amber-500/40 text-amber-100 rounded-lg p-4">
                <p class="font-semibold">Update available: scheduled check detected new commits upstream.</p>
                <p class="text-sm mt-1">Use <span class="font-semibold">🚀 Update AmpNM</span> to apply manually. Auto-pull is disabled by design.</p>
            </div>
        <?php endif; ?>

        <?php if ($statusMessage): ?>
            <div class="mb-6 <?php echo $statusType === 'success' ? 'bg-green-500/10 border-green-500/40 text-green-200' : ($statusType === 'error' ? 'bg-red-500/10 border-red-500/40 text-red-200' : 'bg-slate-700/50 border-slate-600 text-slate-200'); ?> border rounded-lg p-4">
                <p class="font-semibold flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    <span><?php echo htmlspecialchars($statusMessage); ?></span>
                </p>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Repository Status</h2>
                        <span class="px-3 py-1 rounded-full text-xs uppercase tracking-wide <?php echo $isGitRepo ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30'; ?>">
                            <?php echo $isGitRepo ? 'Connected' : 'Unavailable'; ?>
                        </span>
                    </div>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm text-slate-300">
                        <div>
                            <dt class="text-slate-400">Repository Path</dt>
                            <dd class="font-mono text-slate-100 mt-1 break-all"><?php echo htmlspecialchars($repoPath); ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Current Branch</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $currentBranch ?: 'Unknown'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Local Commit</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $localCommit ?: 'n/a'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Upstream Ref</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $upstreamRef ?: htmlspecialchars($targetUpstreamRef); ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Remote Commit</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $remoteCommit ?: 'Unknown'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Remote Origin</dt>
                            <dd class="font-mono text-slate-100 mt-1 break-all"><?php echo $remoteUrl ?: $canonicalRepoUrl; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Remote Reachability</dt>
                            <dd class="mt-1">
                                <?php if ($remoteReachable === true): ?>
                                    <span class="text-emerald-200">Reachable (ls-remote succeeded)</span>
                                <?php elseif ($remoteReachable === false): ?>
                                    <span class="text-amber-200">Not reachable yet</span>
                                <?php else: ?>
                                    <span class="text-slate-400">n/a</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Sync Target</dt>
                            <dd class="mt-1 font-mono">github.com/pranto48/ampnm.git (<?php echo htmlspecialchars($targetUpstreamRef); ?>)</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Ahead / Behind</dt>
                            <dd class="mt-1">
                                <?php if ($aheadCount !== null && $behindCount !== null): ?>
                                    <span class="text-emerald-200"><?php echo $aheadCount; ?> ahead</span>
                                    <span class="text-slate-500"> / </span>
                                    <span class="text-amber-200"><?php echo $behindCount; ?> behind</span>
                                <?php else: ?>
                                    <span class="text-slate-400">n/a</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Working Tree</dt>
                            <dd class="mt-1">
                                <?php if ($workingTreeClean === true): ?>
                                    <span class="text-emerald-200">Clean</span>
                                <?php elseif ($workingTreeClean === false): ?>
                                    <span class="text-amber-200">Uncommitted changes present</span>
                                <?php else: ?>
                                    <span class="text-slate-400">Unknown</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                    <?php if ($updateAvailable === true): ?>
                        <p class="mt-3 text-xs inline-flex items-center gap-2 px-2 py-1 rounded-full bg-emerald-500/20 text-cyan-200 border border-emerald-500/30">New update available</p>
                    <?php elseif ($behindCount === 0): ?>
                        <p class="mt-3 text-xs inline-flex items-center gap-2 px-2 py-1 rounded-full bg-slate-700 text-slate-200 border border-slate-600">Already up to date</p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-500 mt-3">If commits differ, use "↻ Check now" to compare against <code><?php echo htmlspecialchars($targetUpstreamRef); ?></code> or "🚀 Update AmpNM" to pull from that target and auto-restart this Docker app.</p>
                    <p class="text-xs text-slate-400 mt-2">Last checked at: <?php echo $lastCheckedAt ? htmlspecialchars($lastCheckedAt) . ' (UTC)' : 'Never'; ?></p>
                </div>

                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h2 class="text-xl font-semibold text-white mb-4">Actions</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="check">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <label class="block text-sm text-slate-400">Repository Path</label>
                            <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="/var/www/html/docker-ampnm">
                            <button type="submit" class="w-full px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg flex items-center justify-center gap-2<?php echo !$isGitRepo ? ' opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$isGitRepo ? 'disabled aria-disabled="true"' : ''; ?>>
                                <i class="fas fa-sync-alt"></i>
                                <span>↻ Check now</span>
                            </button>
                            <p class="text-xs text-slate-500">Fetches remote metadata so you can compare local commits against <code><?php echo htmlspecialchars($targetUpstreamRef); ?></code> without applying changes.</p>
                        </form>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <label class="block text-sm text-slate-400">Repository Path</label>
                            <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <label class="flex items-start gap-2 text-xs text-slate-300">
                                <input type="checkbox" name="force_update" value="1" class="mt-0.5" <?php echo $forceUpdate ? 'checked' : ''; ?> <?php echo !$forceUpdateEnabled ? 'disabled aria-disabled="true"' : ''; ?>>
                                <span><span class="font-semibold text-amber-200">Force update (advanced)</span> &mdash; continue even with local changes. This can fail pulls or require manual conflict resolution. Commit/stash first unless you intentionally accept that risk.</span>
                            </label>
                            <?php if (!$forceUpdateEnabled): ?>
                                <p class="text-xs text-amber-300">Force update is currently disabled for this rollout. Set <code>AMPNM_FORCE_UPDATE_ENABLED=1</code> to enable this override.</p>
                            <?php endif; ?>
                            <button type="submit" class="w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg flex items-center justify-center gap-2<?php echo (!$isGitRepo || !$updateAvailable) ? ' opacity-60 cursor-not-allowed' : ''; ?>" <?php echo (!$isGitRepo || !$updateAvailable) ? 'disabled aria-disabled="true"' : ''; ?>>
                                <i class="fas fa-cloud-download-alt"></i>
                                <span>🚀 Update AmpNM</span>
                            </button>
                            <p class="text-xs text-slate-500">Runs <code>git fetch --all</code> then <code>git pull --ff-only</code> from <code>https://github.com/pranto48/ampnm.git</code>, then automatically restarts this Docker app with <code>docker compose restart</code>.
                            Local uncommitted changes are risky during updates: they can block fast-forward pulls, create conflicts, or leave deployments partially updated. Commit or stash changes first unless you intentionally use force update.</p>
                            <?php if ($isGitRepo && !$updateAvailable): ?>
                                <p class="text-xs text-amber-200">No remote changes detected</p>
                            <?php endif; ?>
                        </form>
                        <?php if (!$isGitRepo && $gitAvailable): ?>
                            <form method="POST" class="space-y-3 md:col-span-2">
                                <input type="hidden" name="action" value="clone">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <label class="block text-sm text-slate-400">Clone into Path</label>
                                <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="/var/www/html/ampnm-project">
                                <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg flex items-center justify-center gap-2">
                                    <i class="fas fa-cloud-download-alt"></i>
                                    <span>Clone from GitHub</span>
                                </button>
                                <p class="text-xs text-slate-500">Creates (if needed) the directory and performs <code>git clone --depth 1</code> from <code><?php echo htmlspecialchars($canonicalRepoUrl); ?></code> so updates can be applied.</p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($commandOutput)): ?>
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-xl font-semibold text-white">Command Logs</h2>
                            <span class="text-xs text-slate-500">Most recent action</span>
                        </div>
                        <div class="space-y-4 text-sm">
                            <?php foreach ($commandOutput as $title => $output): ?>
                                <div>
                                    <p class="text-slate-400 mb-1 font-semibold flex items-center gap-2"><i class="fas fa-terminal text-cyan-400"></i><?php echo htmlspecialchars($title); ?></p>
                                    <pre class="bg-slate-900 border border-slate-700 rounded-lg p-3 text-slate-100 overflow-auto whitespace-pre-wrap text-xs"><?php echo htmlspecialchars($output ?: 'No output'); ?></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-semibold text-white">Recent Update Audit</h2>
                        <span class="text-xs text-slate-500">Last 10 actions</span>
                    </div>
                    <?php if (empty($latestAuditEntries)): ?>
                        <p class="text-sm text-slate-400">No audit records yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs text-left text-slate-300">
                                <thead class="text-slate-400 border-b border-slate-700">
                                    <tr>
                                        <th class="py-2 pr-4">Time</th><th class="py-2 pr-4">Action</th><th class="py-2 pr-4">User</th><th class="py-2 pr-4">IP</th><th class="py-2 pr-4">Commits</th><th class="py-2 pr-4">Restart</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestAuditEntries as $entry): ?>
                                        <tr class="border-b border-slate-700/60">
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) ($entry['timestamp'] ?? '')); ?></td>
                                            <td class="py-2 pr-4"><?php echo htmlspecialchars((string) ($entry['action'] ?? '')); ?></td>
                                            <td class="py-2 pr-4"><?php echo htmlspecialchars((string) (($entry['username'] ?? 'unknown') . ' #' . ($entry['user_id'] ?? 'n/a'))); ?></td>
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) ($entry['client_ip'] ?? 'unknown')); ?></td>
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) (($entry['old_commit'] ?? 'n/a') . ' → ' . ($entry['new_commit'] ?? 'n/a'))); ?></td>
                                            <td class="py-2 pr-4"><?php echo htmlspecialchars((string) ($entry['restart_result'] ?? 'n/a')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">How it works</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Targets the Docker app at <code>portal.itsupport.com.bd/docker-ampnm</code> (current container path: <code><?php echo htmlspecialchars($defaultRepoPath); ?></code>). We auto-detect the nearest <code>.git</code> above this folder and use it as the default path.</li>
                        <li>Checks out updates from the official repository: <code>https://github.com/pranto48/ampnm.git</code> on branch <code><?php echo htmlspecialchars($canonicalBranch); ?></code> (<code><?php echo htmlspecialchars($targetUpstreamRef); ?></code>).</li>
                        <li>Uses <span class="font-semibold">fetch</span> to compare and <span class="font-semibold">pull</span> to apply new versions without overwriting local, uncommitted changes.</li>
                    </ul>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">Tips</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Ensure the container has outbound internet access to reach GitHub.</li>
                        <li>If <span class="font-semibold">Remote Reachability</span> shows "Not reachable", verify DNS/proxy settings and that <code>github.com</code> is accessible from the container.</li>
                        <li>Commit or back up any local changes before pulling to avoid merge conflicts.</li>
                        <li>If you maintain a fork, change the repository path to your mounted code directory inside the container.</li>
                        <li>If the code was copied without <code>.git</code>, use "Clone from GitHub" to pull a fresh working copy into the desired path.</li>
                    </ul>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">Environment & tooling</h3>
                    <dl class="space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">Container hostname</dt>
                            <dd class="font-mono text-slate-100"><?php echo htmlspecialchars($containerHostname); ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">PHP user</dt>
                            <dd class="font-mono text-slate-100"><?php echo htmlspecialchars($phpUser); ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">Git binary</dt>
                            <dd class="font-mono text-slate-100"><?php echo $gitAvailable ? htmlspecialchars($gitBinary) : 'Not found'; ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">Node</dt>
                            <dd class="font-mono text-slate-100"><?php echo $nodeVersion !== '' ? htmlspecialchars($nodeVersion) : 'Not installed'; ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">npm</dt>
                            <dd class="font-mono text-slate-100"><?php echo $npmVersion !== '' ? htmlspecialchars($npmVersion) : 'Not installed'; ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">pnpm</dt>
                            <dd class="font-mono text-slate-100"><?php echo $pnpmVersion !== '' ? htmlspecialchars($pnpmVersion) : 'Not installed'; ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-slate-500 mt-3">Use these details when installing dependencies or troubleshooting why fetch/pull commands might fail in this container.</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">After updating</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Rebuild the frontend bundles from the repo root:<br><code class="block bg-slate-900 border border-slate-700 rounded mt-1 p-2">pnpm install<br>pnpm run build && pnpm run build:server</code></li>
                        <li>Restart the Docker services to pick up new assets:<br><code class="block bg-slate-900 border border-slate-700 rounded mt-1 p-2">docker compose down<br>docker compose up -d --build</code></li>
                        <li>Verify the portal at <code>https://portal.itsupport.com.bd</code> and the Docker app at <code>/docker-ampnm</code> both reflect the latest commit hashes above.</li>
                    </ul>
                    <p class="text-xs text-slate-500 mt-3">Run these commands inside the same container or host where the repository is mounted. Adjust the compose project name or paths if you keep the stack elsewhere.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
