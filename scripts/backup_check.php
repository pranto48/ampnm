<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
 ?>
#!/usr/bin/env php
<?php
// Bootstrap the environment
require_once __DIR__ . '/../includes/functions.php';

// Enable error reporting for logs
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "[" . date('Y-m-d H:i:s') . "] Starting scheduled backup audit...\n";

try {
    $pdo = getDbConnection();

    // 1. Run due log backups
    $dueLogsStmt = $pdo->prepare("SELECT * FROM log_backup_schedules WHERE enabled = 1 AND next_run_at IS NOT NULL AND next_run_at <= NOW()");
    $dueLogsStmt->execute();
    $logSchedules = $dueLogsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logSchedules) > 0) {
        echo "Found " . count($logSchedules) . " due log backups. Processing...\n";
        require_once __DIR__ . '/../api/handlers/log_handler.php';
        
        foreach ($logSchedules as $schedule) {
            echo "Running log backup schedule: '{$schedule['name']}' (ID: {$schedule['id']})...\n";
            [$csvPath, $csvName, $csvSize, $rowCount] = buildLogBackupCsv($pdo, (int)$schedule['user_id'], $schedule['period_scope']);
            [$ok, $err] = deliverLogBackup($schedule, $csvPath, $csvName, $rowCount);
            
            $runStmt = $pdo->prepare("INSERT INTO log_backup_runs (schedule_id, user_id, status, target_type, period_scope, file_name, file_size_bytes, error_message)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $runStmt->execute([
                $schedule['id'], $schedule['user_id'], $ok ? 'success' : 'failed', $schedule['target_type'], $schedule['period_scope'], $csvName, $csvSize, $err
            ]);
            
            $updateSchedule = $pdo->prepare("UPDATE log_backup_schedules SET last_run_at = NOW(), next_run_at = ? WHERE id = ?");
            $updateSchedule->execute([computeNextRunAt($schedule), $schedule['id']]);
            @unlink($csvPath);
            echo "Finished log backup schedule: ID {$schedule['id']}. Status: " . ($ok ? 'success' : 'failed') . "\n";
        }
    }

    // 2. Run due system backups
    $dueSysStmt = $pdo->prepare("SELECT * FROM system_backup_schedules WHERE enabled = 1 AND next_run_at IS NOT NULL AND next_run_at <= NOW()");
    $dueSysStmt->execute();
    $sysSchedules = $dueSysStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sysSchedules) > 0) {
        echo "Found " . count($sysSchedules) . " due system backups. Processing...\n";
        require_once __DIR__ . '/../api/handlers/backup_handler.php';
        
        foreach ($sysSchedules as $schedule) {
            echo "Running system backup schedule: '{$schedule['name']}' (ID: {$schedule['id']})...\n";
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

            $updateSchedule = $pdo->prepare("UPDATE system_backup_schedules SET last_run_at = NOW(), next_run_at = ? WHERE id = ?");
            $updateSchedule->execute([computeNextBackupRunAt($schedule), $schedule['id']]);
            echo "Finished system backup schedule: ID {$schedule['id']}. Status: " . ($ok ? 'success' : 'failed') . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Backup audit completed.\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
