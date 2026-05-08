<?php
require_once 'includes/auth_check.php';
require_once 'includes/update_state.php';
include 'header.php';

$canonicalRepoUrl = getenv('AMPNM_UPDATE_REPO_URL') ?: 'https://github.com/pranto48/ampnm.git';
$canonicalBranch = getenv('AMPNM_UPDATE_BRANCH') ?: 'main';
$targetUpstreamRef = 'origin/' . $canonicalBranch;
$allowedRepoBase = rtrim(getenv('AMPNM_ALLOWED_REPO_BASE') ?: '/var/www/html', '/\\');

$csrfToken = ensureCsrfTokenInSession();

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
$auditLogPath = rtrim(getenv('AMPNM_AUDIT_LOG_PATH') ?: '/var/log/ampnm', '/\\') . DIRECTORY_SEPARATOR . 'code-update-audit.jsonl';
$latestAuditEntries = [];
$auditStorageMode = 'file';
$updateScriptResult = [];
$backupBasePath = rtrim(getenv('BACKUP_BASE') ?: '/var/www/html/docker-ampnm/data/code_backups', '/\\');
$recentBackups = [];
$rollbackBackupPath = '';
$rollbackLogFilePath = '';
$showRollbackFailureBanner = false;

$updateLockPath = '/tmp/ampnm-code-update.lock';
$updateLockHandle = null;
$updateStatePath = getUpdateStatePath();
$lastCheckedAt = null;
$scheduledUpdateAvailable = false;
$auditPdo = function_exists('getDbConnection') ? getDbConnection() : null;
$repoPathValidation = resolveAllowedRepoPath($repoPath, $allowedRepoBase);
if ($repoPathValidation['ok']) {
    $repoPath = $repoPathValidation['path'];
} else {
    $statusMessage = 'Repository path warning: ' . $repoPathValidation['error'];
    $statusType = 'error';
    $isGitRepo = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!isValidCsrfToken($postedToken)) {
        $statusMessage = 'Request rejected: invalid or missing CSRF token.';
        $statusType = 'error';
        $action = null;
    } else {
        $repoValidation = resolveAllowedRepoPath($_POST['repo_path'] ?? $repoPath, $allowedRepoBase);
        if (!$repoValidation['ok']) {
            $statusMessage = 'Request rejected: ' . $repoValidation['error'];
            $statusType = 'error';
            $action = null;
            $isGitRepo = false;
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

function runUpdateScript(string $repoPath, string $upstreamRef, bool $forceUpdate): array
{
    $scriptPath = realpath(__DIR__ . '/scripts/update.sh') ?: (__DIR__ . '/scripts/update.sh');
    $resultFile = '/tmp/ampnm_update_result.env';
    $envOverrides = [
        'AMPNM_REPO_PATH=' . $repoPath,
        'AMPNM_UPSTREAM_REF=' . $upstreamRef,
        'AMPNM_FORCE_UPDATE=' . ($forceUpdate ? '1' : '0'),
        'AMPNM_RESULT_FILE=' . $resultFile,
    ];
    $escapedEnv = implode(' ', array_map('escapeshellarg', $envOverrides));
    $command = 'env ' . $escapedEnv . ' bash ' . escapeshellarg($scriptPath) . ' 2>&1';

    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);

    $parsedResult = [];
    if (is_file($resultFile)) {
        $lines = file($resultFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $parsedResult[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    return [
        'output' => implode("\n", $output),
        'exitCode' => $exitCode,
        'result' => $parsedResult,
    ];
}

function runRestoreScript(string $repoPath, string $backupPath): array
{
    $scriptPath = realpath(__DIR__ . '/scripts/restore_backup.sh') ?: (__DIR__ . '/scripts/restore_backup.sh');
    $resultFile = '/tmp/ampnm_restore_result.env';
    $envOverrides = [
        'HOST_APP_DIR=' . $repoPath,
        'BACKUP_PATH=' . $backupPath,
        'RESULT_ENV_FILE=' . $resultFile,
    ];
    $escapedEnv = implode(' ', array_map('escapeshellarg', $envOverrides));
    $command = 'env ' . $escapedEnv . ' bash ' . escapeshellarg($scriptPath) . ' 2>&1';

    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);

    $parsedResult = [];
    if (is_file($resultFile)) {
        $lines = file($resultFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $parsedResult[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    return [
        'output' => implode("\n", $output),
        'exitCode' => $exitCode,
        'result' => $parsedResult,
    ];
}

function runDirectUpdateScript(string $targetDir): array
{
    $scriptPath = realpath(__DIR__ . '/scripts/direct_update.sh') ?: (__DIR__ . '/scripts/direct_update.sh');
    $resultFile = '/tmp/ampnm_direct_update_result.env';
    $envOverrides = [
        'TARGET_DIR=' . $targetDir,
        'RESULT_ENV_FILE=' . $resultFile,
    ];
    $escapedEnv = implode(' ', array_map('escapeshellarg', $envOverrides));
    $command = 'env ' . $escapedEnv . ' bash ' . escapeshellarg($scriptPath) . ' 2>&1';
    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);
    $parsedResult = [];
    if (is_file($resultFile)) {
        $lines = file($resultFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $parsedResult[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
    return ['output' => implode("\n", $output), 'exitCode' => $exitCode, 'result' => $parsedResult];
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


function ensureAuditTable(PDO $pdo): bool
{
    static $ensured = false;
    if ($ensured) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS code_update_audit_logs (
"
        . "id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
"
        . "timestamp_utc DATETIME NOT NULL,
"
        . "action VARCHAR(16) NOT NULL,
"
        . "user_id BIGINT NULL,
"
        . "username VARCHAR(255) NULL,
"
        . "client_ip VARCHAR(64) NULL,
"
        . "repo_path VARCHAR(1024) NOT NULL,
"
        . "old_commit VARCHAR(64) NULL,
"
        . "new_commit VARCHAR(64) NULL,
"
        . "session_id VARCHAR(128) NULL,
"
        . "action_type VARCHAR(32) NULL,
"
        . "source_commit VARCHAR(64) NULL,
"
        . "target_commit VARCHAR(64) NULL,
"
        . "backup_path VARCHAR(1024) NULL,
"
        . "update_log_file VARCHAR(1024) NULL,
"
        . "restart_result VARCHAR(64) NULL,
"
        . "status_type VARCHAR(32) NULL,
"
        . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
"
        . "INDEX idx_code_update_audit_logs_timestamp (timestamp_utc),
"
        . "INDEX idx_code_update_audit_logs_action (action)
"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $ensured = $pdo->exec($sql) !== false;
    if (!$ensured) {
        return false;
    }

    $requiredColumns = [
        'session_id' => 'ALTER TABLE code_update_audit_logs ADD COLUMN session_id VARCHAR(128) NULL AFTER username',
        'action_type' => 'ALTER TABLE code_update_audit_logs ADD COLUMN action_type VARCHAR(32) NULL AFTER session_id',
        'source_commit' => 'ALTER TABLE code_update_audit_logs ADD COLUMN source_commit VARCHAR(64) NULL AFTER new_commit',
        'target_commit' => 'ALTER TABLE code_update_audit_logs ADD COLUMN target_commit VARCHAR(64) NULL AFTER source_commit',
        'backup_path' => 'ALTER TABLE code_update_audit_logs ADD COLUMN backup_path VARCHAR(1024) NULL AFTER target_commit',
        'update_log_file' => 'ALTER TABLE code_update_audit_logs ADD COLUMN update_log_file VARCHAR(1024) NULL AFTER backup_path',
    ];

    foreach ($requiredColumns as $column => $alterSql) {
        $checkStmt = $pdo->query("SHOW COLUMNS FROM code_update_audit_logs LIKE " . $pdo->quote($column));
        if ($checkStmt === false || $checkStmt->fetch(PDO::FETCH_ASSOC) === false) {
            $pdo->exec($alterSql);
        }
    }

    return true;
}

function appendAuditLogDb(PDO $pdo, array $entry): bool
{
    if (!ensureAuditTable($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO code_update_audit_logs (timestamp_utc, action, user_id, username, session_id, action_type, client_ip, repo_path, old_commit, new_commit, source_commit, target_commit, backup_path, update_log_file, restart_result, status_type)
         VALUES (:timestamp_utc, :action, :user_id, :username, :session_id, :action_type, :client_ip, :repo_path, :old_commit, :new_commit, :source_commit, :target_commit, :backup_path, :update_log_file, :restart_result, :status_type)'
    );

    $timestampUtc = isset($entry['timestamp']) ? gmdate('Y-m-d H:i:s', strtotime((string) $entry['timestamp'])) : gmdate('Y-m-d H:i:s');

    return $stmt->execute([
        ':timestamp_utc' => $timestampUtc,
        ':action' => $entry['action'] ?? null,
        ':user_id' => $entry['user_id'] ?? null,
        ':username' => $entry['username'] ?? null,
        ':session_id' => $entry['session_id'] ?? null,
        ':action_type' => $entry['action_type'] ?? null,
        ':client_ip' => $entry['client_ip'] ?? null,
        ':repo_path' => $entry['repo_path'] ?? null,
        ':old_commit' => $entry['old_commit'] ?? null,
        ':new_commit' => $entry['new_commit'] ?? null,
        ':source_commit' => $entry['source_commit'] ?? null,
        ':target_commit' => $entry['target_commit'] ?? null,
        ':backup_path' => $entry['backup_path'] ?? null,
        ':update_log_file' => $entry['update_log_file'] ?? null,
        ':restart_result' => $entry['restart_result'] ?? null,
        ':status_type' => $entry['status_type'] ?? null,
    ]);
}

function readRecentAuditLogsDb(PDO $pdo, int $limit = 10): array
{
    if (!ensureAuditTable($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query('SELECT timestamp_utc, action, user_id, username, session_id, action_type, client_ip, repo_path, old_commit, new_commit, source_commit, target_commit, backup_path, update_log_file, restart_result, status_type FROM code_update_audit_logs ORDER BY id DESC LIMIT ' . $limit);
    if ($stmt === false) {
        return [];
    }

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['timestamp'] = isset($row['timestamp_utc']) ? gmdate('c', strtotime((string) $row['timestamp_utc'] . ' UTC')) : '';
        unset($row['timestamp_utc']);
        $rows[] = $row;
    }

    return $rows;
}

function persistAuditEntry(array $entry, ?PDO $pdo, string $logPath, string &$storageMode): bool
{
    if ($pdo instanceof PDO) {
        try {
            if (appendAuditLogDb($pdo, $entry)) {
                $storageMode = 'database';
                return true;
            }
        } catch (Throwable $e) {
            // Fallback to append-only file when DB logging is unavailable.
        }
    }

    $storageMode = 'file';
    return appendAuditLog($logPath, $entry);
}

function readLatestAuditEntries(?PDO $pdo, string $logPath, int $limit, string &$storageMode): array
{
    if ($pdo instanceof PDO) {
        try {
            $rows = readRecentAuditLogsDb($pdo, $limit);
            if (!empty($rows)) {
                $storageMode = 'database';
                return $rows;
            }
        } catch (Throwable $e) {
            // Continue to file fallback.
        }
    }

    $storageMode = 'file';
    return readRecentAuditLogs($logPath, $limit);
}

function listRecentBackupFolders(string $backupBasePath, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $rows = [];

    if (!is_dir($backupBasePath)) {
        return $rows;
    }

    $entries = scandir($backupBasePath, SCANDIR_SORT_DESCENDING);
    if ($entries === false) {
        return $rows;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = rtrim($backupBasePath, '/\\') . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($fullPath)) {
            continue;
        }

        $commitFile = $fullPath . DIRECTORY_SEPARATOR . 'git_commit.txt';
        $legacyCommitFile = $fullPath . DIRECTORY_SEPARATOR . 'previous_commit.txt';
        $commitHash = null;
        if (is_file($commitFile)) {
            $commitHash = safeTrim((string) file_get_contents($commitFile));
        } elseif (is_file($legacyCommitFile)) {
            $commitHash = safeTrim((string) file_get_contents($legacyCommitFile));
        }

        $rows[] = [
            'timestamp_folder' => $entry,
            'path' => $fullPath,
            'git_commit' => $commitHash !== '' ? $commitHash : null,
        ];

        if (count($rows) >= $limit) {
            break;
        }
    }

    return $rows;
}



function buildBackupStatusInfo(string $backupPath): array
{
    $normalizedPath = rtrim($backupPath, '/\\');
    if ($normalizedPath === '' || !is_dir($normalizedPath)) {
        return ['is_valid' => false, 'reason' => 'Backup directory is missing.'];
    }

    $codePath = $normalizedPath . DIRECTORY_SEPARATOR . 'code';
    if (!is_dir($codePath)) {
        return ['is_valid' => false, 'reason' => 'Missing code/ directory in backup.'];
    }

    return ['is_valid' => true, 'reason' => 'Ready'];
}

function enrichBackupsWithAuditMetadata(array $backups, array $auditEntries): array
{
    $lookup = [];
    foreach ($auditEntries as $entry) {
        $backupPath = safeTrim((string) ($entry['backup_path'] ?? ''));
        if ($backupPath === '') {
            continue;
        }

        if (!isset($lookup[$backupPath])) {
            $lookup[$backupPath] = [
                'previous_commit' => safeTrim((string) ($entry['source_commit'] ?? $entry['old_commit'] ?? '')),
                'updated_commit' => safeTrim((string) ($entry['target_commit'] ?? $entry['new_commit'] ?? '')),
            ];
        }
    }

    foreach ($backups as $index => $backup) {
        $path = (string) ($backup['path'] ?? '');
        $status = buildBackupStatusInfo($path);
        $audit = $lookup[$path] ?? null;
        $backups[$index]['previous_commit'] = $audit['previous_commit'] ?? ((string) ($backup['git_commit'] ?? ''));
        $backups[$index]['updated_commit'] = $audit['updated_commit'] ?? '';
        $backups[$index]['is_valid'] = $status['is_valid'];
        $backups[$index]['invalid_reason'] = $status['is_valid'] ? '' : $status['reason'];
    }

    return $backups;
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
$updateState = readUpdateStateFile($updateStatePath);
$lastCheckedAt = isset($updateState['checked_at']) ? (string) $updateState['checked_at'] : null;
$scheduledUpdateAvailable = !empty($updateState['update_available']);

$isUpdateAction = ($action === 'check' || $action === 'update' || $action === 'rollback');

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

        $scriptRun = runUpdateScript($repoPath, $upstreamRef, $forceUpdateEnabled && $forceUpdate);
        $updateScriptResult = $scriptRun['result'];
        $commandOutput['Update Script'] = "=== Step: launch update script ===\n"
            . ($scriptRun['output'] !== '' ? $scriptRun['output'] : 'No output')
            . "\n=== Step: parse result file ===\n"
            . (!empty($updateScriptResult) ? json_encode($updateScriptResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No parsed result values');

        $scriptStatus = strtolower((string) ($updateScriptResult['STATUS'] ?? ''));
        $scriptSucceeded = $scriptRun['exitCode'] === 0 && in_array($scriptStatus, ['success', 'ok', 'passed'], true);
        $restartResultForAudit = $scriptSucceeded ? 'success' : 'failed';
        if ($scriptSucceeded) {
            $statusMessage = 'AmpNM updated via update script successfully.';
            $statusType = 'success';
        } else {
            $statusMessage = 'Update script completed with errors. Review Command Logs for details.';
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

            if ($action === 'rollback') {
    $requestedBackupPath = isset($_POST['backup_path']) ? trim((string) $_POST['backup_path']) : '';
    $resolvedBackupPath = realpath($requestedBackupPath);
    $backupBaseReal = realpath($backupBasePath);

    if ($requestedBackupPath === '' || $resolvedBackupPath === false || !is_dir($resolvedBackupPath)) {
        $statusMessage = 'Rollback failed: backup path is missing or invalid.';
        $statusType = 'error';
    } elseif ($backupBaseReal === false || !str_starts_with($resolvedBackupPath, rtrim($backupBaseReal, '/\\') . DIRECTORY_SEPARATOR)) {
        $statusMessage = 'Rollback rejected: backup path is outside the allowed backup directory.';
        $statusType = 'error';
    } else {
        $restoreRun = runRestoreScript($repoPath, $resolvedBackupPath);
        $updateScriptResult = $restoreRun['result'];
        $commandOutput['Rollback Script'] = "=== Step: launch rollback script ===\n"
            . ($restoreRun['output'] !== '' ? $restoreRun['output'] : 'No output')
            . "\n=== Step: parse result file ===\n"
            . (!empty($updateScriptResult) ? json_encode($updateScriptResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No parsed result values');

        $scriptStatus = strtolower((string) ($updateScriptResult['STATUS'] ?? ''));
        $scriptSucceeded = $restoreRun['exitCode'] === 0 && in_array($scriptStatus, ['success', 'ok', 'passed'], true);
        $restartResultForAudit = $scriptSucceeded ? 'success' : 'failed';
        if ($scriptSucceeded) {
            $restoredCommit = safeTrim((string) ($updateScriptResult['RESTORED_COMMIT'] ?? ''));
            $statusMessage = 'Rollback completed successfully and services restarted.' . ($restoredCommit !== '' ? ' Restored commit: ' . $restoredCommit . '.' : '');
            $statusType = 'success';
        } else {
            $statusMessage = 'Rollback script reported errors. Review Command Logs for details.';
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
                'action_type' => $action,
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? ($_SESSION['user'] ?? 'unknown'),
                'session_id' => session_id() ?: null,
                'client_ip' => getClientIpAddress(),
                'repo_path' => $repoPath,
                'old_commit' => $oldCommitForAudit ?: null,
                'new_commit' => $localCommit ?: null,
                'source_commit' => $oldCommitForAudit ?: null,
                'target_commit' => $remoteCommit ?: null,
                'backup_path' => ($action === 'update' || $action === 'rollback') ? ((string) ($updateScriptResult['BACKUP_PATH'] ?? $updateScriptResult['backup_path'] ?? ($_POST['backup_path'] ?? ''))) : null,
                'update_log_file' => ($action === 'update' || $action === 'rollback') ? ((string) ($updateScriptResult['LOG_FILE_PATH'] ?? $updateScriptResult['LOG_FILE'] ?? $updateScriptResult['log_file_path'] ?? '')) : null,
                'restart_result' => $action === 'check' ? 'not_applicable' : $restartResultForAudit,
                'status_type' => $statusType,
            ];
            persistAuditEntry($auditEntry, $auditPdo, $auditLogPath, $auditStorageMode);
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
        persistAuditEntry($auditEntry, $auditPdo, $auditLogPath, $auditStorageMode);
    }
}

if ($action === 'direct_update') {
    $scriptRun = runDirectUpdateScript($repoPath);
    $updateScriptResult = $scriptRun['result'];
    $commandOutput['Direct Update Script'] = ($scriptRun['output'] !== '' ? $scriptRun['output'] : 'No output');
    $scriptStatus = strtolower((string) ($updateScriptResult['STATUS'] ?? ''));
    if ($scriptRun['exitCode'] === 0 && in_array($scriptStatus, ['success', 'ok', 'passed'], true)) {
        $statusMessage = 'Direct Docker folder update completed successfully from GitHub.';
        $statusType = 'success';
    } else {
        $statusMessage = 'Direct update failed. Review command logs.';
        $statusType = 'error';
    }
}

foreach ($commandOutput as $title => $output) {
    $commandOutput[$title] = redactSensitiveOutput((string) $output);
}

$latestAuditEntries = readLatestAuditEntries($auditPdo, $auditLogPath, 10, $auditStorageMode);
$recentBackups = enrichBackupsWithAuditMetadata(listRecentBackupFolders($backupBasePath, 5), $latestAuditEntries);
$rollbackBackupPath = (string) ($updateScriptResult['BACKUP_PATH'] ?? $updateScriptResult['backup_path'] ?? ($recentBackups[0]['path'] ?? 'n/a'));
$rollbackLogFilePath = (string) ($updateScriptResult['LOG_FILE_PATH'] ?? $updateScriptResult['LOG_FILE'] ?? $updateScriptResult['log_file_path'] ?? 'n/a');
$showRollbackFailureBanner = $action === 'update' && $statusType === 'error';

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


        <?php if ($showRollbackFailureBanner): ?>
            <div class="mb-6 bg-red-600/15 border border-red-500/60 text-red-100 rounded-lg p-4">
                <p class="font-semibold mb-2">Update failed — rollback may be required immediately.</p>
                <p class="text-sm">Last backup path: <code><?php echo htmlspecialchars($rollbackBackupPath); ?></code></p>
                <p class="text-sm">Update log file: <code><?php echo htmlspecialchars($rollbackLogFilePath); ?></code></p>
                <ol class="list-decimal list-inside text-sm mt-2 space-y-1">
                    <li>Stop app services: <code>docker compose down</code></li>
                    <li>Restore backup into source path (example): <code>rsync -a --delete <?php echo htmlspecialchars($rollbackBackupPath); ?>/code/ <?php echo htmlspecialchars($repoPath); ?>/</code></li>
                    <li>Restart services and validate health endpoints/UI.</li>
                </ol>
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
                            <dd class="mt-1 font-mono"><?php echo htmlspecialchars($canonicalRepoUrl); ?> (<?php echo htmlspecialchars($targetUpstreamRef); ?>)</dd>
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
                                <input type="hidden" name="action" value="direct_update">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <label class="block text-sm text-slate-400">Docker App Path</label>
                                <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                <button type="submit" class="w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg flex items-center justify-center gap-2">
                                    <i class="fas fa-download"></i>
                                    <span>⬇ Direct Update docker-ampnm</span>
                                </button>
                                <p class="text-xs text-slate-500">Downloads latest <code>main</code> zip from GitHub, updates only the <code>docker-ampnm/</code> folder into this path, keeps local <code>data/storage/logs</code>, and restarts services.</p>
                            </form>
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

                <?php if (!empty($updateScriptResult)): ?>
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                        <h2 class="text-xl font-semibold text-white mb-4">Update Script Result</h2>
                        <dl class="grid md:grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-400">Status</dt><dd class="font-mono text-slate-100"><?php echo htmlspecialchars((string) ($updateScriptResult['STATUS'] ?? $updateScriptResult['status'] ?? 'unknown')); ?></dd></div>
                            <div><dt class="text-slate-400">New Commit Hash</dt><dd class="font-mono text-slate-100 break-all"><?php echo htmlspecialchars((string) ($updateScriptResult['NEW_COMMIT_HASH'] ?? $updateScriptResult['new_commit_hash'] ?? 'n/a')); ?></dd></div>
                            <div><dt class="text-slate-400">Backup Path</dt><dd class="font-mono text-slate-100 break-all"><?php echo htmlspecialchars((string) ($updateScriptResult['BACKUP_PATH'] ?? $updateScriptResult['backup_path'] ?? 'n/a')); ?></dd></div>
                            <div><dt class="text-slate-400">Log File Path</dt><dd class="font-mono text-slate-100 break-all"><?php echo htmlspecialchars((string) ($updateScriptResult['LOG_FILE_PATH'] ?? $updateScriptResult['log_file_path'] ?? 'n/a')); ?></dd></div>
                            <div><dt class="text-slate-400">Timestamp</dt><dd class="font-mono text-slate-100"><?php echo htmlspecialchars((string) ($updateScriptResult['TIMESTAMP'] ?? $updateScriptResult['timestamp'] ?? 'n/a')); ?></dd></div>
                        </dl>
                    </div>
                <?php endif; ?>

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

                <?php if (($_SESSION['user_role'] ?? 'viewer') === 'admin'): ?>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-semibold text-white">Recent update events</h2>
                        <span class="text-xs text-slate-500">Last 10 actions (<?php echo htmlspecialchars($auditStorageMode); ?>)</span>
                    </div>
                    <?php if (empty($latestAuditEntries)): ?>
                        <p class="text-sm text-slate-400">No audit records yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs text-left text-slate-300">
                                <thead class="text-slate-400 border-b border-slate-700">
                                    <tr>
                                        <th class="py-2 pr-4">Time</th><th class="py-2 pr-4">Action</th><th class="py-2 pr-4">User / Session</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Source → Target</th><th class="py-2 pr-4">Backup / Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestAuditEntries as $entry): ?>
                                        <tr class="border-b border-slate-700/60">
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) ($entry['timestamp'] ?? '')); ?></td>
                                            <td class="py-2 pr-4"><?php echo htmlspecialchars((string) ($entry['action'] ?? '')); ?></td>
                                            <td class="py-2 pr-4"><?php echo htmlspecialchars((string) (($entry['username'] ?? 'unknown') . ' #' . ($entry['user_id'] ?? 'n/a'))); ?><div class="font-mono text-[10px] text-slate-400"><?php echo htmlspecialchars((string) ($entry['session_id'] ?? 'n/a')); ?></div></td>
                                            <td class="py-2 pr-4"><?php echo htmlspecialchars((string) ($entry['status_type'] ?? 'unknown')); ?></td>
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) (($entry['source_commit'] ?? $entry['old_commit'] ?? 'n/a') . ' → ' . ($entry['target_commit'] ?? $entry['new_commit'] ?? 'n/a'))); ?></td>
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) ($entry['backup_path'] ?? 'n/a')); ?><div class="text-[10px] text-slate-400"><?php echo htmlspecialchars((string) ($entry['update_log_file'] ?? 'n/a')); ?></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">How it works</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Targets the Docker app at <code>portal.itsupport.com.bd/docker-ampnm</code> (current container path: <code><?php echo htmlspecialchars($defaultRepoPath); ?></code>). We auto-detect the nearest <code>.git</code> above this folder and use it as the default path.</li>
                        <li>Checks out updates from the configured repository: <code><?php echo htmlspecialchars($canonicalRepoUrl); ?></code> on branch <code><?php echo htmlspecialchars($canonicalBranch); ?></code> (<code><?php echo htmlspecialchars($targetUpstreamRef); ?></code>).</li>
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
                    <h3 class="text-lg font-semibold text-white mb-3">Recent backups</h3>
                    <p class="text-xs text-slate-400 mb-3">Base directory: <code><?php echo htmlspecialchars($backupBasePath); ?></code></p>
                    <?php if (empty($recentBackups)): ?>
                        <p class="text-sm text-slate-400">No backup folders found yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-slate-300">
                                <thead class="text-xs uppercase text-slate-400 border-b border-slate-700">
                                    <tr>
                                        <th class="py-2 pr-4 text-left">Backup timestamp</th>
                                        <th class="py-2 pr-4 text-left">Previous commit</th>
                                        <th class="py-2 pr-4 text-left">Updated commit</th>
                                        <th class="py-2 pr-4 text-left">Backup path</th>
                                        <th class="py-2 pr-4 text-left">Status</th>
                                        <th class="py-2 text-left">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBackups as $index => $backup): ?>
                                        <tr class="border-b border-slate-700/60 <?php echo $index === 0 ? 'bg-emerald-500/10 ring-1 ring-emerald-400/40' : ''; ?>">
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) $backup['timestamp_folder']); ?><?php if ($index === 0): ?><div class="text-[10px] text-emerald-300">Newest</div><?php endif; ?></td>
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) ($backup['previous_commit'] !== '' ? $backup['previous_commit'] : 'n/a')); ?></td>
                                            <td class="py-2 pr-4 font-mono"><?php echo htmlspecialchars((string) ($backup['updated_commit'] !== '' ? $backup['updated_commit'] : 'n/a')); ?></td>
                                            <td class="py-2 pr-4 font-mono break-all"><?php echo htmlspecialchars((string) $backup['path']); ?></td>
                                            <td class="py-2 pr-4">
                                                <?php if (!empty($backup['is_valid'])): ?>
                                                    <span class="text-emerald-300">Valid</span>
                                                <?php else: ?>
                                                    <span class="text-red-300">Invalid</span>
                                                    <div class="text-[10px] text-red-200"><?php echo htmlspecialchars((string) ($backup['invalid_reason'] ?? 'Unavailable')); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2">
                                                <form method="POST" onsubmit="return confirm('Restore this backup and restart services?');">
                                                    <input type="hidden" name="action" value="rollback">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>">
                                                    <input type="hidden" name="backup_path" value="<?php echo htmlspecialchars((string) $backup['path']); ?>">
                                                    <button type="submit" <?php echo empty($backup['is_valid']) ? 'disabled title="' . htmlspecialchars((string) ($backup['invalid_reason'] ?? 'Unavailable')) . '"' : ''; ?> class="px-3 py-2 bg-amber-600 hover:bg-amber-500 disabled:bg-slate-600 disabled:text-slate-300 text-white rounded text-xs flex items-center justify-center gap-2">
                                                        <i class="fas fa-history"></i>
                                                        <span>Restore this version</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">How to rollback</h3>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-slate-300">
                        <li>Stop services: <code>docker compose down</code> (or <code>docker compose stop</code>).</li>
                        <li>Restore the chosen backup directory's <code>code/</code> into the repo path with <code>rsync -a --delete &lt;backup_path&gt;/code/ &lt;repo_path&gt;/</code>.</li>
                        <li>Restart services: <code>docker compose up -d --build</code> (or <code>docker compose restart</code>).</li>
                        <li>Validate health: open app UI, run smoke tests, and check container logs for errors.</li>
                    </ol>
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
