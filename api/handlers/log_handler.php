<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// This file is included by api.php and assumes $pdo, $action and $input are available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer';

function logPeriodBounds(string $periodScope): array {
    $scope = in_array($periodScope, ['day', 'month', 'year'], true) ? $periodScope : 'day';
    if ($scope === 'year') return ['INTERVAL 365 DAY', '%Y-%m'];
    if ($scope === 'month') return ['INTERVAL 30 DAY', '%Y-%m-%d'];
    return ['INTERVAL 1 DAY', '%Y-%m-%d %H:00:00'];
}

function computeNextRunAt(array $schedule): ?string {
    $type = $schedule['schedule_type'] ?? 'daily';
    $time = $schedule['schedule_time'] ?? '00:15:00';
    $now = new DateTime('now');
    $next = new DateTime('now');
    [$h, $m, $s] = array_map('intval', explode(':', $time));
    $next->setTime($h, $m, $s);

    if ($type === 'weekly') {
        $dow = (int)($schedule['day_of_week'] ?? 1); // 1..7, monday first
        $currentDow = (int)$next->format('N');
        $delta = $dow - $currentDow;
        if ($delta < 0 || ($delta === 0 && $next <= $now)) $delta += 7;
        $next->modify("+{$delta} day");
    } elseif ($type === 'monthly') {
        $dom = max(1, min(28, (int)($schedule['day_of_month'] ?? 1)));
        $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $dom);
        if ($next <= $now) $next->modify('first day of next month')->setDate((int)$next->format('Y'), (int)$next->format('m'), $dom);
    } else {
        if ($next <= $now) $next->modify('+1 day');
    }
    return $next->format('Y-m-d H:i:s');
}

function buildLogBackupCsv(PDO $pdo, int $userId, string $periodScope): array {
    [$interval] = logPeriodBounds($periodScope);
    $stmt = $pdo->prepare("
        SELECT d.name AS device_name, d.ip, l.status, l.details, l.created_at
        FROM device_status_logs l
        JOIN devices d ON d.id = l.device_id
        WHERE d.user_id = ? AND l.created_at >= NOW() - $interval
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tmpFile = tempnam(sys_get_temp_dir(), 'ampnm_logs_');
    $csvFile = $tmpFile . '.csv';
    rename($tmpFile, $csvFile);
    $fp = fopen($csvFile, 'w');
    fputcsv($fp, ['device_name', 'ip', 'status', 'details', 'created_at']);
    foreach ($rows as $r) {
        fputcsv($fp, [$r['device_name'], $r['ip'], $r['status'], $r['details'], $r['created_at']]);
    }
    fclose($fp);

    return [$csvFile, basename($csvFile), filesize($csvFile) ?: 0, count($rows)];
}

function deliverLogBackup(array $schedule, string $csvFilePath, string $csvName, int $rowCount): array {
    $targetType = $schedule['target_type'];
    $cfg = json_decode($schedule['target_config'] ?? '{}', true);
    if (!is_array($cfg)) $cfg = [];

    // ── FTP ──────────────────────────────────────────────────────────────────
    if ($targetType === 'ftp') {
        $host       = trim((string)($cfg['host'] ?? ''));
        $username   = trim((string)($cfg['username'] ?? ''));
        $password   = (string)($cfg['password'] ?? '');
        $remotePath = trim((string)($cfg['remote_path'] ?? '/'));
        $port       = (int)($cfg['port'] ?? 21);
        if ($host === '' || $username === '') return [false, 'FTP host/username are required'];
        $conn = @ftp_connect($host, $port, 15);
        if (!$conn) return [false, 'FTP connection failed to ' . $host . ':' . $port];
        if (!@ftp_login($conn, $username, $password)) {
            ftp_close($conn);
            return [false, 'FTP authentication failed for user ' . $username];
        }
        @ftp_pasv($conn, true);
        $remoteFile = rtrim($remotePath, '/') . '/' . $csvName;
        $ok = @ftp_put($conn, $remoteFile, $csvFilePath, FTP_BINARY);
        ftp_close($conn);
        return [$ok, $ok ? null : 'FTP upload failed — check remote path permissions'];
    }

    // ── NAS (container-mounted path) ─────────────────────────────────────────
    if ($targetType === 'nas' || $targetType === 'smb') {
        $mountPath = rtrim((string)($cfg['mount_path'] ?? ''), '/');
        if ($mountPath === '') return [false, 'NAS destination path is required'];
        if (!is_dir($mountPath)) {
            @mkdir($mountPath, 0777, true);
        }
        if (!is_dir($mountPath) || !is_writable($mountPath)) {
            return [false, 'NAS path "' . $mountPath . '" is not writable. Check your Docker volume bind-mount.'];
        }
        $dest = $mountPath . '/' . $csvName;
        $copied = @copy($csvFilePath, $dest);
        if ($copied) @chmod($dest, 0664);
        return [$copied, $copied ? null : 'Failed to copy log backup to NAS path: ' . $mountPath];
    }

    // ── Email ─────────────────────────────────────────────────────────────────
    if ($targetType === 'email') {
        require_once __DIR__ . '/../../includes/smtp_mailer.php';
        $to = trim((string)($cfg['recipient_email'] ?? ''));
        if ($to === '') return [false, 'Email recipient is required'];
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM smtp_settings WHERE user_id = ? LIMIT 1");

        $stmt->execute([$schedule['user_id']]);
        $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$smtp) return [false, 'SMTP settings not configured'];
        $subject = "Log backup ({$schedule['period_scope']})";
        $preview = @file_get_contents($csvFilePath);
        $body = "Backup generated: {$csvName}\nRows: {$rowCount}\n\nCSV preview:\n" . substr((string)$preview, 0, 3500);
        $smtpErr = null;
        $sent = smtp_send_mail($smtp, $to, $subject, $body, $smtpErr);
        return [$sent, $sent ? null : ($smtpErr ?: 'Email send failed')];
    }

    return [false, 'Unsupported target type'];
}

if ($action === 'get_status_logs') {
    $map_id = $_GET['map_id'] ?? null;
    $device_id = $_GET['device_id'] ?? null;
    $period = $_GET['period'] ?? '24h';

    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required.']);
        exit;
    }

    switch ($period) {
        case 'live': $interval = 'INTERVAL 1 HOUR'; $dateFormat = '%Y-%m-%d %H:%i:00'; break;
        case '7d': $interval = 'INTERVAL 7 DAY'; $dateFormat = '%Y-%m-%d'; break;
        case '30d': $interval = 'INTERVAL 30 DAY'; $dateFormat = '%Y-%m-%d'; break;
        case '24h':
        default: $interval = 'INTERVAL 24 HOUR'; $dateFormat = '%Y-%m-%d %H:00:00'; break;
    }

    $sql = "
        SELECT DATE_FORMAT(l.created_at, ?) as time_group,
               SUM(CASE WHEN l.status = 'warning' THEN 1 ELSE 0 END) as warning_count,
               SUM(CASE WHEN l.status = 'critical' THEN 1 ELSE 0 END) as critical_count,
               SUM(CASE WHEN l.status = 'offline' THEN 1 ELSE 0 END) as offline_count
        FROM device_status_logs l
        JOIN devices d ON l.device_id = d.id
        WHERE d.map_id = ? AND l.created_at >= NOW() - $interval
    ";
    $params = [$dateFormat, $map_id];
    if ($user_role !== 'viewer') { $sql .= " AND d.user_id = ?"; $params[] = $current_user_id; }
    if ($device_id) { $sql .= " AND l.device_id = ?"; $params[] = $device_id; }
    $sql .= " GROUP BY time_group ORDER BY time_group ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    return;
}

if ($action === 'get_downtime_summary') {
    $map_id = (int)($_GET['map_id'] ?? 0);
    $device_id = (int)($_GET['device_id'] ?? 0);
    $scope = $_GET['scope'] ?? 'day';
    if ($map_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Map ID is required.']); exit; }
    [$interval, $groupFormat] = logPeriodBounds($scope);

    $sql = "
        SELECT d.id AS device_id, d.name AS device_name,
               DATE_FORMAT(l.created_at, ?) AS bucket,
               SUM(CASE WHEN l.status='offline' THEN 1 ELSE 0 END) AS offline_events
        FROM device_status_logs l
        JOIN devices d ON d.id = l.device_id
        WHERE d.map_id = ? AND l.created_at >= NOW() - $interval
    ";
    $params = [$groupFormat, $map_id];
    if ($user_role !== 'viewer') { $sql .= " AND d.user_id = ?"; $params[] = $current_user_id; }
    if ($device_id > 0) { $sql .= " AND d.id = ?"; $params[] = $device_id; }
    $sql .= " GROUP BY d.id, d.name, bucket ORDER BY bucket DESC, d.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    return;
}

if ($action === 'get_offline_logs') {
    $map_id = (int)($_GET['map_id'] ?? 0);
    $device_id = (int)($_GET['device_id'] ?? 0);
    $scope = $_GET['scope'] ?? 'day';
    if ($map_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Map ID is required.']); exit; }
    [$interval] = logPeriodBounds($scope);
    $sql = "
        SELECT l.id, d.name AS device_name, d.ip, l.status, l.details, l.created_at
        FROM device_status_logs l
        JOIN devices d ON d.id = l.device_id
        WHERE d.map_id = ? AND l.status='offline' AND l.created_at >= NOW() - $interval
    ";
    $params = [$map_id];
    if ($user_role !== 'viewer') { $sql .= " AND d.user_id = ?"; $params[] = $current_user_id; }
    if ($device_id > 0) { $sql .= " AND d.id = ?"; $params[] = $device_id; }
    $sql .= " ORDER BY l.created_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    return;
}

if ($action === 'get_log_backup_schedules') {
    $stmt = $pdo->prepare("SELECT * FROM log_backup_schedules WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    return;
}

if ($action === 'save_log_backup_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $name = trim((string)($input['name'] ?? ''));
    $targetType = in_array($input['target_type'] ?? '', ['ftp', 'nas', 'smb', 'email'], true) ? $input['target_type'] : '';
    $scheduleType = in_array($input['schedule_type'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $input['schedule_type'] : 'daily';
    $periodScope = in_array($input['period_scope'] ?? '', ['day', 'month', 'year'], true) ? $input['period_scope'] : 'day';
    $scheduleTime = trim((string)($input['schedule_time'] ?? '00:15:00'));
    $dayOfWeek = isset($input['day_of_week']) ? (int)$input['day_of_week'] : null;
    $dayOfMonth = isset($input['day_of_month']) ? (int)$input['day_of_month'] : null;
    $enabled = !empty($input['enabled']) ? 1 : 0;
    $targetConfig = json_encode($input['target_config'] ?? new stdClass(), JSON_UNESCAPED_SLASHES);
    if ($name === '' || $targetType === '') { http_response_code(400); echo json_encode(['error' => 'Name and target type are required']); exit; }
    $nextRun = computeNextRunAt([
        'schedule_type' => $scheduleType,
        'schedule_time' => $scheduleTime,
        'day_of_week' => $dayOfWeek,
        'day_of_month' => $dayOfMonth
    ]);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE log_backup_schedules
            SET name=?, target_type=?, target_config=?, period_scope=?, schedule_type=?, schedule_time=?, day_of_week=?, day_of_month=?, enabled=?, next_run_at=?
            WHERE id=? AND user_id=?");
        $stmt->execute([$name, $targetType, $targetConfig, $periodScope, $scheduleType, $scheduleTime, $dayOfWeek, $dayOfMonth, $enabled, $nextRun, $id, $current_user_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO log_backup_schedules (user_id, name, target_type, target_config, period_scope, schedule_type, schedule_time, day_of_week, day_of_month, enabled, next_run_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_user_id, $name, $targetType, $targetConfig, $periodScope, $scheduleType, $scheduleTime, $dayOfWeek, $dayOfMonth, $enabled, $nextRun]);
        $id = (int)$pdo->lastInsertId();
    }
    echo json_encode(['success' => true, 'id' => $id]);
    return;
}

if ($action === 'delete_log_backup_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Schedule ID is required']); exit; }
    $stmt = $pdo->prepare("DELETE FROM log_backup_schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $current_user_id]);
    echo json_encode(['success' => true]);
    return;
}

if ($action === 'run_log_backup_now' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Schedule ID is required']); exit; }
    $stmt = $pdo->prepare("SELECT * FROM log_backup_schedules WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$id, $current_user_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$schedule) { http_response_code(404); echo json_encode(['error' => 'Schedule not found']); exit; }

    [$csvPath, $csvName, $csvSize, $rowCount] = buildLogBackupCsv($pdo, (int)$current_user_id, $schedule['period_scope']);
    [$ok, $err] = deliverLogBackup($schedule, $csvPath, $csvName, $rowCount);

    $runStmt = $pdo->prepare("INSERT INTO log_backup_runs (schedule_id, user_id, status, target_type, period_scope, file_name, file_size_bytes, error_message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $runStmt->execute([
        $id, $current_user_id, $ok ? 'success' : 'failed', $schedule['target_type'], $schedule['period_scope'], $csvName, $csvSize, $err
    ]);

    $updateSchedule = $pdo->prepare("UPDATE log_backup_schedules SET last_run_at = NOW(), next_run_at = ? WHERE id = ? AND user_id = ?");
    $updateSchedule->execute([computeNextRunAt($schedule), $id, $current_user_id]);

    @unlink($csvPath);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => $err ?: 'Backup failed']); exit; }
    echo json_encode(['success' => true, 'message' => "Backup completed ({$rowCount} rows)."]);
    return;
}

if ($action === 'run_due_log_backups' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    $dueStmt = $pdo->prepare("SELECT * FROM log_backup_schedules WHERE user_id = ? AND enabled = 1 AND next_run_at IS NOT NULL AND next_run_at <= NOW()");
    $dueStmt->execute([$current_user_id]);
    $schedules = $dueStmt->fetchAll(PDO::FETCH_ASSOC);
    $ran = 0;
    foreach ($schedules as $schedule) {
        [$csvPath, $csvName, $csvSize, $rowCount] = buildLogBackupCsv($pdo, (int)$current_user_id, $schedule['period_scope']);
        [$ok, $err] = deliverLogBackup($schedule, $csvPath, $csvName, $rowCount);
        $runStmt = $pdo->prepare("INSERT INTO log_backup_runs (schedule_id, user_id, status, target_type, period_scope, file_name, file_size_bytes, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $runStmt->execute([
            $schedule['id'], $current_user_id, $ok ? 'success' : 'failed', $schedule['target_type'], $schedule['period_scope'], $csvName, $csvSize, $err
        ]);
        $updateSchedule = $pdo->prepare("UPDATE log_backup_schedules SET last_run_at = NOW(), next_run_at = ? WHERE id = ? AND user_id = ?");
        $updateSchedule->execute([computeNextRunAt($schedule), $schedule['id'], $current_user_id]);
        @unlink($csvPath);
        $ran++;
    }
    echo json_encode(['success' => true, 'ran' => $ran]);
    return;
}
