<?php
/**
 * Script to update all devices on 192.168.9.9 ampnm server:
 * Ping Interval: 10s
 * Warn Latency: 400ms
 * Warn Packet Loss: 50%
 * Critical Latency: 500ms
 * Critical Packet Loss: 100%
 */
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDbConnection();

    // 1. Check total devices before update
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM devices");
    $totalDevices = (int)$stmtCount->fetchColumn();

    $stmtMonitorable = $pdo->query("SELECT COUNT(*) FROM devices WHERE type NOT IN ('box', 'text')");
    $monitorableCount = (int)$stmtMonitorable->fetchColumn();

    // 2. Perform atomic update on all monitorable network devices
    $stmtUpdate = $pdo->prepare("
        UPDATE devices 
        SET ping_interval = 10,
            warning_latency_threshold = 400,
            warning_packetloss_threshold = 50,
            critical_latency_threshold = 500,
            critical_packetloss_threshold = 100
        WHERE type NOT IN ('box', 'text')
    ");
    $stmtUpdate->execute();
    $affected = $stmtUpdate->rowCount();

    echo "✅ [Device Thresholds & Ping Interval Updated Successfully]\n";
    echo "Total devices in database: {$totalDevices}\n";
    echo "Monitorable network devices: {$monitorableCount}\n";
    echo "Rows updated: {$affected}\n";
    echo "Config applied: Ping Interval = 10s | Warn Latency = 400ms | Warn Packet Loss = 50% | Critical Latency = 500ms | Critical Packet Loss = 100%\n";

    // 3. Verify
    $stmtVerify = $pdo->query("
        SELECT COUNT(*) 
        FROM devices 
        WHERE type NOT IN ('box', 'text')
          AND ping_interval = 10 
          AND warning_latency_threshold = 400 
          AND warning_packetloss_threshold = 50 
          AND critical_latency_threshold = 500 
          AND critical_packetloss_threshold = 100
    ");
    $verifiedCount = (int)$stmtVerify->fetchColumn();
    echo "Verification: {$verifiedCount} of {$monitorableCount} network devices now have 10s ping interval and thresholds (400ms / 50% / 500ms / 100%).\n";

} catch (Throwable $e) {
    echo "❌ Error updating device thresholds: " . $e->getMessage() . "\n";
    exit(1);
}
