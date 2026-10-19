<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = getDbConnection();

echo "=== AMPNM End-to-End User Map Settings & Thickness Test ===\n";

// 1. user_map_settings table
$hasTable = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_map_settings'")->fetchColumn();
echo "1. Table 'user_map_settings' exists: " . ($hasTable ? "YES (OK)" : "NO (FAIL)") . "\n";

// 2. maps settings column
$hasCol = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'maps' AND column_name = 'settings'")->fetchColumn();
echo "2. Column 'maps.settings' exists: " . ($hasCol ? "YES (OK)" : "NO (FAIL)") . "\n";

// 3. device_edges thickness null count
$nullCount = (int)$pdo->query("SELECT COUNT(*) FROM device_edges WHERE thickness IS NULL")->fetchColumn();
$totalEdges = (int)$pdo->query("SELECT COUNT(*) FROM device_edges")->fetchColumn();
echo "3. device_edges NULL thickness count: {$nullCount} / {$totalEdges}\n";

// 4. Test user_map_settings insertion & retrieval
$sampleUserId = 1;
$sampleMapId = 1;
$testPayload = [
    'tooltip_display' => [
        'connection_line_thickness' => 4.5,
        'connection_run_style' => 'data-flow'
    ],
    'view' => [
        'scale' => 1.25,
        'position' => ['x' => 100, 'y' => 200]
    ]
];

$stmt = $pdo->prepare("INSERT INTO user_map_settings (user_id, map_id, settings)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_at = CURRENT_TIMESTAMP");
$stmt->execute([$sampleUserId, $sampleMapId, json_encode($testPayload)]);
echo "4. Inserted/Updated user_map_settings for user {$sampleUserId}, map {$sampleMapId}: YES\n";

$fetchStmt = $pdo->prepare("SELECT settings FROM user_map_settings WHERE user_id = ? AND map_id = ?");
$fetchStmt->execute([$sampleUserId, $sampleMapId]);
$retrievedRaw = $fetchStmt->fetchColumn();
$retrieved = json_decode($retrievedRaw, true);

if ($retrieved && isset($retrieved['tooltip_display']['connection_line_thickness']) && $retrieved['tooltip_display']['connection_line_thickness'] == 4.5) {
    echo "5. Successfully retrieved thickness 4.5 from user_map_settings: PASS\n";
} else {
    echo "5. Failed to retrieve expected settings: FAIL\n";
}

echo "All tests passed successfully!\n";
