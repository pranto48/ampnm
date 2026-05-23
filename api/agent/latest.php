<?php
// API endpoint to fetch the latest metrics and status of a monitored device
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Check admin/user session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$agent_uuid = isset($_GET['agent_uuid']) ? trim((string)$_GET['agent_uuid']) : '';

if ($agent_id <= 0 && empty($agent_uuid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing agent_id or agent_uuid parameter']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    if ($agent_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM agent_devices WHERE id = ? LIMIT 1");
        $stmt->execute([$agent_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM agent_devices WHERE agent_uuid = ? LIMIT 1");
        $stmt->execute([$agent_uuid]);
    }
    
    $device = $stmt->fetch();
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Agent device not found']);
        exit;
    }
    
    $device_id = $device['id'];
    
    // Fetch latest heartbeat
    $stmt = $pdo->prepare("SELECT * FROM agent_heartbeats WHERE agent_device_id = ? ORDER BY collected_at DESC LIMIT 1");
    $stmt->execute([$device_id]);
    $latest_heartbeat = $stmt->fetch() ?: null;
    
    // Fetch recent events (last 10)
    $stmt = $pdo->prepare("SELECT * FROM agent_events WHERE agent_device_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$device_id]);
    $recent_events = $stmt->fetchAll() ?: [];
    
    // Format JSON
    echo json_encode([
        'success' => true,
        'device' => $device,
        'latest_heartbeat' => $latest_heartbeat,
        'recent_events' => $recent_events
    ]);
} catch (Exception $e) {
    error_log("Error in api/agent/latest.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
