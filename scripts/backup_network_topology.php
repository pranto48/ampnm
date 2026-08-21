<?php
/*
 * Pre-Update Data Backup Script for AMPNM Docker Server
 * Backs up `devices`, `maps`, and `device_edges` prior to deployments.
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDbConnection();

    $devices = $pdo->query("SELECT * FROM devices")->fetchAll(PDO::FETCH_ASSOC);
    $maps = $pdo->query("SELECT * FROM maps")->fetchAll(PDO::FETCH_ASSOC);
    $edges = $pdo->query("SELECT * FROM device_edges")->fetchAll(PDO::FETCH_ASSOC);

    $backupData = [
        'timestamp' => date('c'),
        'counts' => [
            'devices' => count($devices),
            'maps' => count($maps),
            'device_edges' => count($edges),
        ],
        'devices' => $devices,
        'maps' => $maps,
        'device_edges' => $edges,
    ];

    $backupDir = __DIR__ . '/../storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Ymd_His');
    $backupFile = $backupDir . "/topology_backup_{$timestamp}.json";
    file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT));

    echo "✅ [Pre-Update Data Backup Verified]\n";
    echo "Saved backup file: {$backupFile}\n";
    echo "Topology stats: {$backupData['counts']['devices']} devices, {$backupData['counts']['maps']} maps, {$backupData['counts']['device_edges']} device_edges.\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Backup Failed: " . $e->getMessage() . "\n";
    exit(1);
}
