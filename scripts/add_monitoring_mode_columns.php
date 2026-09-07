<?php
/*
 * Database Migration Script: Add monitoring_mode and packet count columns to devices
 * Part of AMPNM Network Monitoring System
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDbConnection();
    echo "→ Checking devices table schema for monitoring_mode columns...\n";

    $dbname = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // 1. Check monitoring_mode column
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'monitoring_mode'");
    $stmt->execute([$dbname]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "  + Adding 'monitoring_mode' column to devices table...\n";
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `monitoring_mode` VARCHAR(30) NOT NULL DEFAULT 'time_threshold' AFTER `offline_timeout_seconds`");
        echo "  ✓ Column 'monitoring_mode' added.\n";
    } else {
        echo "  ✓ Column 'monitoring_mode' already exists.\n";
    }

    // 2. Check critical_packet_count column
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'critical_packet_count'");
    $stmt->execute([$dbname]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "  + Adding 'critical_packet_count' column to devices table...\n";
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `critical_packet_count` INT(11) NOT NULL DEFAULT 20 AFTER `monitoring_mode`");
        echo "  ✓ Column 'critical_packet_count' added.\n";
    } else {
        echo "  ✓ Column 'critical_packet_count' already exists.\n";
    }

    // 3. Check offline_packet_count column
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'offline_packet_count'");
    $stmt->execute([$dbname]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "  + Adding 'offline_packet_count' column to devices table...\n";
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `offline_packet_count` INT(11) NOT NULL DEFAULT 30 AFTER `critical_packet_count`");
        echo "  ✓ Column 'offline_packet_count' added.\n";
    } else {
        echo "  ✓ Column 'offline_packet_count' already exists.\n";
    }

    // 4. Check consecutive_drops column
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'consecutive_drops'");
    $stmt->execute([$dbname]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "  + Adding 'consecutive_drops' column to devices table...\n";
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `consecutive_drops` INT(11) NOT NULL DEFAULT 0 AFTER `offline_packet_count`");
        echo "  ✓ Column 'consecutive_drops' added.\n";
    } else {
        echo "  ✓ Column 'consecutive_drops' already exists.\n";
    }

    // Set defaults on existing records
    echo "→ Updating defaults across all existing devices...\n";
    $updatedMode = $pdo->exec("
        UPDATE `devices`
        SET monitoring_mode = 'time_threshold'
        WHERE monitoring_mode IS NULL OR monitoring_mode = ''
    ");
    echo "  ✓ Verified monitoring_mode on {$updatedMode} devices.\n";

    $updatedCrit = $pdo->exec("
        UPDATE `devices`
        SET critical_packet_count = 20
        WHERE critical_packet_count IS NULL OR critical_packet_count = 0
    ");
    echo "  ✓ Set critical_packet_count = 20 on {$updatedCrit} devices.\n";

    $updatedOff = $pdo->exec("
        UPDATE `devices`
        SET offline_packet_count = 30
        WHERE offline_packet_count IS NULL OR offline_packet_count = 0
    ");
    echo "  ✓ Set offline_packet_count = 30 on {$updatedOff} devices.\n";

    $updatedDrops = $pdo->exec("
        UPDATE `devices`
        SET consecutive_drops = 0
        WHERE consecutive_drops IS NULL
    ");
    echo "  ✓ Initialized consecutive_drops on {$updatedDrops} devices.\n";

    echo "✅ Migration completed successfully!\n";
    exit(0);

} catch (Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
