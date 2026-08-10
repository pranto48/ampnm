<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Scheduled Backup & Disaster Recovery Worker
 */

if (php_sapi_name() !== 'cli' && empty($_GET['key'])) {
    die("Access denied. CLI execution only.");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit_logger.php';

$backupDir = __DIR__ . '/../uploads/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Ymd_His');
$filename = "ampnm_backup_{$timestamp}.sql";
$filePath = "{$backupDir}/{$filename}";

try {
    // Export MySQL Database Tables
    $tables = [];
    $query = $pdo->query("SHOW TABLES");
    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $out = "-- AMPNM Database Backup Export\n-- Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $out .= $createRow['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $keys = array_map(function($k) { return "`{$k}`"; }, array_keys($row));
            $vals = array_map(function($v) use ($pdo) {
                if ($v === null) return "NULL";
                return $pdo->quote($v);
            }, array_values($row));

            $out .= "INSERT INTO `{$table}` (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $vals) . ");\n";
        }
        $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($filePath, $out);
    
    // Gzip compression
    if (function_exists('gzopen')) {
        $gzPath = "{$filePath}.gz";
        $fp = gzopen($gzPath, 'w9');
        gzwrite($fp, $out);
        gzclose($fp);
        unlink($filePath);
        $finalFile = basename($gzPath);
    } else {
        $finalFile = $filename;
    }

    log_audit($pdo, 'system_backup', 'system', null, "Created backup: {$finalFile}");

    if (php_sapi_name() === 'cli') {
        echo "[SUCCESS] Backup created successfully: {$finalFile}\n";
    } else {
        echo json_encode(['success' => true, 'file' => $finalFile, 'timestamp' => $timestamp]);
    }

} catch (Exception $e) {
    error_log("Backup failed: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "[ERROR] Backup failed: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
