<?php
/*
 * Database Migration Script: Add critical_offline_seconds and offline_timeout_seconds to devices
 * Part of AMPNM Network Monitoring System
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDbConnection();
    echo "→ Checking devices table schema...\n";

    $dbname = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // Check critical_offline_seconds
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'critical_offline_seconds'");
    $stmt->execute([$dbname]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "  + Adding 'critical_offline_seconds' column to devices table...\n";
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `critical_offline_seconds` INT(11) NOT NULL DEFAULT 20 AFTER `ping_interval`");
        echo "  ✓ Column 'critical_offline_seconds' added.\n";
    } else {
        echo "  ✓ Column 'critical_offline_seconds' already exists.\n";
    }

    // Check offline_timeout_seconds
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'devices' AND COLUMN_NAME = 'offline_timeout_seconds'");
    $stmt->execute([$dbname]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "  + Adding 'offline_timeout_seconds' column to devices table...\n";
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `offline_timeout_seconds` INT(11) NOT NULL DEFAULT 30 AFTER `critical_offline_seconds`");
        echo "  ✓ Column 'offline_timeout_seconds' added.\n";
    } else {
        echo "  ✓ Column 'offline_timeout_seconds' already exists.\n";
    }

    // Update existing records with default thresholds
    echo "→ Updating device defaults across all devices...\n";
    $updated = $pdo->exec("
        UPDATE `devices`
        SET critical_offline_seconds = 20
        WHERE critical_offline_seconds IS NULL OR critical_offline_seconds = 0
    ");
    echo "  ✓ Set critical_offline_seconds = 20 on {$updated} devices.\n";

    $updatedOff = $pdo->exec("
        UPDATE `devices`
        SET offline_timeout_seconds = 30
        WHERE offline_timeout_seconds IS NULL OR offline_timeout_seconds = 0
    ");
    echo "  ✓ Set offline_timeout_seconds = 30 on {$updatedOff} devices.\n";

    $updatedPing = $pdo->exec("
        UPDATE `devices`
        SET ping_interval = 20
        WHERE ping_interval IS NULL OR ping_interval = 0
    ");
    echo "  ✓ Set ping_interval = 20 on {$updatedPing} devices.\n";

    // Clean up stale first_failed_at for online devices
    $cleanedFails = $pdo->exec("
        UPDATE `devices`
        SET first_failed_at = NULL
        WHERE status = 'online' AND first_failed_at IS NOT NULL
    ");
    echo "  ✓ Cleared stale first_failed_at on {$cleanedFails} online devices.\n";

    echo "✅ Migration completed successfully!\n";
    exit(0);

} catch (Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
