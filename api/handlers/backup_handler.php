<?php
// Prevent unauthorized access
if (!defined('DB_SERVER')) {
    die("Direct access forbidden.");
}

require_once __DIR__ . '/../../includes/functions.php';

// Helper to compute next run time
function computeNextBackupRunAt(array $schedule): string {
    $type = $schedule['schedule_type'];
    $time = $schedule['schedule_time'] ?: '00:15:00';
    $dayOfWeek = isset($schedule['day_of_week']) ? (int)$schedule['day_of_week'] : null;
    $dayOfMonth = isset($schedule['day_of_month']) ? (int)$schedule['day_of_month'] : null;

    $now = new DateTime();
    $next = new DateTime($now->format('Y-m-d') . ' ' . $time);

    if ($next <= $now) {
        $next->modify('+1 day');
    }

    if ($type === 'weekly' && $dayOfWeek !== null) {
        // day_of_week: 1 (Mon) to 7 (Sun)
        $currentDow = (int)$now->format('N');
        $diff = $dayOfWeek - $currentDow;
        if ($diff < 0 || ($diff === 0 && new DateTime($now->format('Y-m-d') . ' ' . $time) <= $now)) {
            $diff += 7;
        }
        $next = new DateTime();
        $next->modify("+{$diff} days");
        $next = new DateTime($next->format('Y-m-d') . ' ' . $time);
    } elseif ($type === 'monthly' && $dayOfMonth !== null) {
        $currentDom = (int)$now->format('j');
        if ($dayOfMonth < $currentDom || ($dayOfMonth === $currentDom && new DateTime($now->format('Y-m-d') . ' ' . $time) <= $now)) {
            $next->modify('first day of next month');
            $next = new DateTime($next->format('Y-m-d') . ' ' . $time);
        }
        // Set specific day
        $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $dayOfMonth);
    }

    return $next->format('Y-m-d H:i:s');
}

// Function to generate the full system backup
function runSystemBackup(PDO $pdo, int $userId, array $schedule = null): array {
    $tempDir = '/tmp/ampnm_backup_' . uniqid();
    if (!mkdir($tempDir, 0755, true)) {
        return [false, 'Failed to create temp working directory', '', 0];
    }

    $dbFile = $tempDir . '/database_backup.sql';
    
    // Build mysqldump command
    $host = DB_SERVER;
    $user = DB_USERNAME;
    $pass = DB_PASSWORD;
    $name = DB_NAME;
    
    $passArg = $pass !== '' ? '-p' . escapeshellarg($pass) : '';
    $dumpCmd = "mysqldump -h " . escapeshellarg($host) . " -u " . escapeshellarg($user) . " {$passArg} " . escapeshellarg($name) . " > " . escapeshellarg($dbFile) . " 2>&1";
    
    $output = [];
    $ret = 0;
    exec($dumpCmd, $output, $ret);
    
    if ($ret !== 0) {
        @array_map('unlink', glob("$tempDir/*"));
        @rmdir($tempDir);
        return [false, 'Database dump failed: ' . implode("\n", $output), '', 0];
    }

    // Now copy the uploads folder to temp directory
    $uploadsSrc = __DIR__ . '/../../uploads';
    $uploadsDest = $tempDir . '/uploads';
    
    if (is_dir($uploadsSrc)) {
        exec("cp -r " . escapeshellarg($uploadsSrc) . " " . escapeshellarg($uploadsDest) . " 2>&1", $copyOutput, $copyRet);
        if ($copyRet !== 0) {
            @array_map('unlink', glob("$tempDir/*"));
            @rmdir($tempDir);
            return [false, 'Failed to copy uploads directory: ' . implode("\n", $copyOutput), '', 0];
        }
    }

    // Create a zip or tar.gz archive
    $archiveName = 'ampnm_system_backup_' . date('Ymd_His') . '.tar.gz';
    $archivePath = '/tmp/' . $archiveName;
    
    $tarCmd = "tar -czf " . escapeshellarg($archivePath) . " -C " . escapeshellarg($tempDir) . " . 2>&1";
    exec($tarCmd, $tarOutput, $tarRet);

    // Cleanup temp files
    exec("rm -rf " . escapeshellarg($tempDir));

    if ($tarRet !== 0) {
        @unlink($archivePath);
        return [false, 'Compression failed: ' . implode("\n", $tarOutput), '', 0];
    }

    $fileSize = filesize($archivePath) ?: 0;

    // Local target path
    $localDest = __DIR__ . '/../../uploads/backups/' . $archiveName;
    @mkdir(dirname($localDest), 0777, true);
    @chmod(dirname($localDest), 0777);
    @chown(dirname($localDest), 'www-data');

    // Copy to localDest first so it can always be downloaded locally
    if (!@copy($archivePath, $localDest)) {
        @unlink($archivePath);
        return [false, 'Failed to save local backup file', '', 0];
    }
    @chmod($localDest, 0666);
    @chown($localDest, 'www-data');

    // Deliver target if specified
    if ($schedule) {
        $ok = false;
        $err = '';
        $targetType = $schedule['target_type'];
        $cfg = json_decode($schedule['target_config'] ?? '{}', true) ?: [];

        if ($targetType === 'ftp') {
            $ftpHost = trim((string)($cfg['host'] ?? ''));
            $ftpUser = trim((string)($cfg['username'] ?? ''));
            $ftpPass = (string)($cfg['password'] ?? '');
            $ftpPort = (int)($cfg['port'] ?? 21);
            $ftpPath = trim((string)($cfg['remote_path'] ?? '/'));

            if ($ftpHost === '' || $ftpUser === '') {
                $err = 'FTP host or username missing';
            } else {
                $conn = @ftp_connect($ftpHost, $ftpPort, 15);
                if (!$conn) {
                    $err = 'FTP connection failed';
                } else {
                    if (!@ftp_login($conn, $ftpUser, $ftpPass)) {
                        $err = 'FTP authentication failed';
                        ftp_close($conn);
                    } else {
                        @ftp_pasv($conn, true);
                        $remoteFile = rtrim($ftpPath, '/') . '/' . $archiveName;
                        $uploaded = @ftp_put($conn, $remoteFile, $localDest, FTP_BINARY);
                        ftp_close($conn);
                        if ($uploaded) {
                            $ok = true;
                        } else {
                            $err = 'FTP upload failed';
                        }
                    }
                }
            }
        } elseif ($targetType === 'nas') {
            // Copy to NAS mount path
            $nasPath = rtrim((string)($cfg['mount_path'] ?? ''), '/');
            if ($nasPath === '') {
                $err = 'NAS mount path is required';
            } else {
                if (!is_dir($nasPath)) {
                    @mkdir($nasPath, 0777, true);
                    @chmod($nasPath, 0777);
                    @chown($nasPath, 'www-data');
                }
                if (!is_writable($nasPath)) {
                    $err = 'NAS mount path is not writable';
                } else {
                    $dest = $nasPath . '/' . $archiveName;
                    if (@copy($localDest, $dest)) {
                        @chmod($dest, 0666);
                        @chown($dest, 'www-data');
                        $ok = true;
                    } else {
                        $err = 'Failed to copy archive to NAS path';
                    }
                }
            }
        }

        @unlink($archivePath); // Clean up the original tmp file

        if (!$ok) {
            // Transfer failed, but we still have the local backup in uploads/backups
            return [false, $err ?: 'Transfer failed', $archiveName, $fileSize];
        }

        return [true, null, $archiveName, $fileSize];
    } else {
        @unlink($archivePath); // Clean up the original tmp file
        return [true, null, $archiveName, $fileSize];
    }
}

// Router for System Backup actions
if ($action === 'get_system_backup_schedules') {
    $stmt = $pdo->prepare("SELECT * FROM system_backup_schedules WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    return;
}

if ($action === 'save_system_backup_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $name = trim((string)($input['name'] ?? ''));
    $targetType = in_array($input['target_type'] ?? '', ['ftp', 'nas'], true) ? $input['target_type'] : '';
    $scheduleType = in_array($input['schedule_type'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $input['schedule_type'] : 'daily';
    $scheduleTime = trim((string)($input['schedule_time'] ?? '00:15:00'));
    $dayOfWeek = isset($input['day_of_week']) && $input['day_of_week'] !== '' ? (int)$input['day_of_week'] : null;
    $dayOfMonth = isset($input['day_of_month']) && $input['day_of_month'] !== '' ? (int)$input['day_of_month'] : null;
    $enabled = !empty($input['enabled']) ? 1 : 0;
    $targetConfig = json_encode($input['target_config'] ?? new stdClass(), JSON_UNESCAPED_SLASHES);

    if ($name === '' || $targetType === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Name and target type are required']);
        exit;
    }

    $scheduleData = [
        'schedule_type' => $scheduleType,
        'schedule_time' => $scheduleTime,
        'day_of_week' => $dayOfWeek,
        'day_of_month' => $dayOfMonth
    ];
    $nextRun = computeNextBackupRunAt($scheduleData);

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE system_backup_schedules
            SET name=?, target_type=?, target_config=?, schedule_type=?, schedule_time=?, day_of_week=?, day_of_month=?, enabled=?, next_run_at=?
            WHERE id=? AND user_id=?");
        $stmt->execute([$name, $targetType, $targetConfig, $scheduleType, $scheduleTime, $dayOfWeek, $dayOfMonth, $enabled, $nextRun, $id, $current_user_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO system_backup_schedules (user_id, name, target_type, target_config, schedule_type, schedule_time, day_of_week, day_of_month, enabled, next_run_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_user_id, $name, $targetType, $targetConfig, $scheduleType, $scheduleTime, $dayOfWeek, $dayOfMonth, $enabled, $nextRun]);
        $id = (int)$pdo->lastInsertId();
    }
    echo json_encode(['success' => true, 'id' => $id]);
    return;
}

if ($action === 'delete_system_backup_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Schedule ID is required']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM system_backup_schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $current_user_id]);
    echo json_encode(['success' => true]);
    return;
}

if ($action === 'get_system_backup_runs') {
    $stmt = $pdo->prepare("SELECT * FROM system_backup_runs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$current_user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    return;
}

if ($action === 'delete_system_backup_run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Run ID is required']);
        exit;
    }
    
    // Get file name to delete it locally if it exists
    $stmt = $pdo->prepare("SELECT file_name FROM system_backup_runs WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$id, $current_user_id]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($run && !empty($run['file_name'])) {
        $localFile = __DIR__ . '/../../uploads/backups/' . $run['file_name'];
        if (file_exists($localFile)) {
            @unlink($localFile);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM system_backup_runs WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $current_user_id]);
    echo json_encode(['success' => true]);
    return;
}

if ($action === 'run_system_backup_now' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    $schedule = null;
    $targetType = 'nas'; // Fallback
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM system_backup_schedules WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$id, $current_user_id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) {
            http_response_code(404);
            echo json_encode(['error' => 'Schedule not found']);
            exit;
        }
        $targetType = $schedule['target_type'];
    }

    [$ok, $err, $archiveName, $fileSize] = runSystemBackup($pdo, (int)$current_user_id, $schedule);

    // Save run record
    $runStmt = $pdo->prepare("INSERT INTO system_backup_runs (schedule_id, user_id, status, target_type, file_name, file_size_bytes, error_message)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $runStmt->execute([
        $id > 0 ? $id : null, 
        $current_user_id, 
        $ok ? 'success' : 'failed', 
        $targetType, 
        $archiveName, 
        $fileSize, 
        $err
    ]);

    if ($id > 0 && $schedule) {
        $updateSchedule = $pdo->prepare("UPDATE system_backup_schedules SET last_run_at = NOW(), next_run_at = ? WHERE id = ? AND user_id = ?");
        $updateSchedule->execute([computeNextBackupRunAt($schedule), $id, $current_user_id]);
    }

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => $err ?: 'Backup execution failed']);
        exit;
    }

    echo json_encode(['success' => true, 'file_name' => $archiveName, 'file_size' => $fileSize]);
    return;
}

if ($action === 'run_due_system_backups' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    
    $dueStmt = $pdo->prepare("SELECT * FROM system_backup_schedules WHERE enabled = 1 AND next_run_at IS NOT NULL AND next_run_at <= NOW()");
    $dueStmt->execute();
    $schedules = $dueStmt->fetchAll(PDO::FETCH_ASSOC);
    $ran = 0;
    
    foreach ($schedules as $schedule) {
        [$ok, $err, $archiveName, $fileSize] = runSystemBackup($pdo, (int)$schedule['user_id'], $schedule);
        
        $runStmt = $pdo->prepare("INSERT INTO system_backup_runs (schedule_id, user_id, status, target_type, file_name, file_size_bytes, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $runStmt->execute([
            $schedule['id'], 
            $schedule['user_id'], 
            $ok ? 'success' : 'failed', 
            $schedule['target_type'], 
            $archiveName, 
            $fileSize, 
            $err
        ]);

        $updateSchedule = $pdo->prepare("UPDATE system_backup_schedules SET last_run_at = NOW(), next_run_at = ? WHERE id = ? AND user_id = ?");
        $updateSchedule->execute([computeNextBackupRunAt($schedule), $schedule['id'], $schedule['user_id']]);
        $ran++;
    }
    echo json_encode(['success' => true, 'ran' => $ran]);
    return;
}
