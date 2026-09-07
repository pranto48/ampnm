<?php
/**
 * Migration & Setup Script: 20s Critical & 30s Offline Threshold
 * 
 * - Ensures `devices.first_failed_at` column exists
 * - Updates `maps.offline_delay_seconds` to 30
 * - Verifies schema and database consistency
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config.php';

$pdo = getDbConnection();
if (!$pdo) {
    echo "❌ ERROR: Unable to connect to database.\n";
    exit(1);
}

echo "→ Checking devices table schema for 'first_failed_at' column...\n";
$stmt = $pdo->query("SHOW COLUMNS FROM devices LIKE 'first_failed_at'");
$hasCol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hasCol) {
    echo "  + Adding 'first_failed_at' column to devices table...\n";
    $pdo->exec("ALTER TABLE devices ADD COLUMN first_failed_at TIMESTAMP NULL DEFAULT NULL AFTER last_seen");
    echo "  ✓ Column 'first_failed_at' added successfully.\n";
} else {
    echo "  ✓ Column 'first_failed_at' already exists.\n";
}

echo "→ Updating maps offline delay to 30 seconds...\n";
$stmtMap = $pdo->prepare("UPDATE maps SET offline_delay_seconds = 30 WHERE offline_delay_seconds != 30 OR offline_delay_seconds IS NULL");
$stmtMap->execute();
$mapsUpdated = $stmtMap->rowCount();
echo "  ✓ Maps updated: {$mapsUpdated}\n";

// Verification check
$verifyCol = $pdo->query("SHOW COLUMNS FROM devices LIKE 'first_failed_at'")->fetch(PDO::FETCH_ASSOC);
$totalDevices = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$totalMaps = $pdo->query("SELECT COUNT(*) FROM maps WHERE offline_delay_seconds = 30")->fetchColumn();

echo "========================================================\n";
echo "✅ [Migration Complete: 20s Critical & 30s Offline Threshold]\n";
echo "Devices schema status: " . ($verifyCol ? 'first_failed_at column present' : 'FAILED') . "\n";
echo "Total devices: {$totalDevices}\n";
echo "Maps with 30s offline delay: {$totalMaps}\n";
echo "========================================================\n";
