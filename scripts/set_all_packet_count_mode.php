<?php
/**
 * Update all devices to Option 2: Packet Count Drop Mode
 * Availability & Timeout Timers:
 *   - ping_interval: 20
 *   - critical_offline_seconds: 20
 *   - offline_timeout_seconds: 30
 * Packet Count Drop Configuration:
 *   - monitoring_mode: 'packet_count'
 *   - critical_packet_count: 20
 *   - offline_packet_count: 30
 *   - consecutive_drops: 0
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDbConnection();

    // 1. Execute batch update
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET monitoring_mode = 'packet_count',
            ping_interval = 20,
            critical_offline_seconds = 20,
            offline_timeout_seconds = 30,
            critical_packet_count = 20,
            offline_packet_count = 30,
            consecutive_drops = 0
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();

    echo "========================================\n";
    echo "✅ [BATCH UPDATE EXECUTED SUCCESSFULLY]\n";
    echo "Devices modified/confirmed: {$affected}\n";
    echo "========================================\n\n";

    // 2. Query statistics across all devices
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_devices,
            SUM(CASE WHEN monitoring_mode = 'packet_count' THEN 1 ELSE 0 END) as packet_count_mode,
            SUM(CASE WHEN ping_interval = 20 THEN 1 ELSE 0 END) as ping_interval_20,
            SUM(CASE WHEN critical_offline_seconds = 20 THEN 1 ELSE 0 END) as crit_timeout_20,
            SUM(CASE WHEN offline_timeout_seconds = 30 THEN 1 ELSE 0 END) as off_timeout_30,
            SUM(CASE WHEN critical_packet_count = 20 THEN 1 ELSE 0 END) as crit_packet_20,
            SUM(CASE WHEN offline_packet_count = 30 THEN 1 ELSE 0 END) as off_packet_30,
            SUM(CASE WHEN consecutive_drops = 0 THEN 1 ELSE 0 END) as zero_drops
        FROM devices
    ")->fetch(PDO::FETCH_ASSOC);

    echo "📊 [DATABASE VERIFICATION TOTALS]\n";
    echo "Total Devices in DB        : " . $stats['total_devices'] . "\n";
    echo "In 'packet_count' Mode     : " . $stats['packet_count_mode'] . " / " . $stats['total_devices'] . "\n";
    echo "Ping Interval = 20s        : " . $stats['ping_interval_20'] . " / " . $stats['total_devices'] . "\n";
    echo "Critical Timeout = 20s     : " . $stats['crit_timeout_20'] . " / " . $stats['total_devices'] . "\n";
    echo "Offline Timeout = 30s      : " . $stats['off_timeout_30'] . " / " . $stats['total_devices'] . "\n";
    echo "Critical Packet Count = 20 : " . $stats['crit_packet_20'] . " / " . $stats['total_devices'] . "\n";
    echo "Offline Packet Count = 30  : " . $stats['off_packet_30'] . " / " . $stats['total_devices'] . "\n";
    echo "Consecutive Drops Reset    : " . $stats['zero_drops'] . " / " . $stats['total_devices'] . "\n\n";

    // 3. Print sample devices
    echo "📋 [SAMPLE DEVICES - VERIFIED CONFIGURATION]\n";
    $samples = $pdo->query("
        SELECT id, name, ip, monitoring_mode, ping_interval, critical_offline_seconds, offline_timeout_seconds, critical_packet_count, offline_packet_count, consecutive_drops 
        FROM devices 
        ORDER BY id ASC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $s) {
        printf(
            "ID: %-3d | Name: %-25s | IP: %-15s | Mode: %-12s | Ping: %2ds | CritTime: %2ds | OffTime: %2ds | CritDrop: %2d | OffDrop: %2d\n",
            $s['id'],
            substr($s['name'], 0, 25),
            $s['ip'] ?? 'N/A',
            $s['monitoring_mode'],
            $s['ping_interval'],
            $s['critical_offline_seconds'],
            $s['offline_timeout_seconds'],
            $s['critical_packet_count'],
            $s['offline_packet_count']
        );
    }

    echo "\n🎉 All devices are now successfully configured in Packet Count Drop Mode!\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Error during batch update: " . $e->getMessage() . "\n";
    exit(1);
}
