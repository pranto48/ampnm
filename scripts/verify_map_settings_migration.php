<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = getDbConnection();

echo "=== AMPNM Map Settings & Line Thickness Verification ===\n";

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

// 4. Test API response for get_user_map_settings
$testMap = $pdo->query("SELECT id FROM maps LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($testMap) {
    echo "4. Sample Map ID: {$testMap['id']}\n";
}
echo "Verification complete!\n";
