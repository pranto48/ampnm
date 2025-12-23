<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer'; // Get current user's role

if ($action === 'get_dashboard_data') {
    $map_id = $_GET['map_id'] ?? null;
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required']);
        exit;
    }

    // Get detailed stats for each status for the SELECTED MAP
    // For viewers, do not filter by user_id here, show all devices on the map
    $sql_map_stats = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
        FROM devices WHERE map_id = ?
    ";
    $params_map_stats = [$map_id];

    if ($user_role !== 'viewer') {
        $sql_map_stats .= " AND user_id = ?";
        $params_map_stats[] = $current_user_id;
    }
    $stmt = $pdo->prepare($sql_map_stats);
    $stmt->execute($params_map_stats);
    $map_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure counts are integers, not null
    $map_stats['online'] = $map_stats['online'] ?? 0;
    $map_stats['warning'] = $map_stats['warning'] ?? 0;
    $map_stats['critical'] = $map_stats['critical'] ?? 0;
    $map_stats['offline'] = $map_stats['offline'] ?? 0;
    $map_stats['total'] = $map_stats['total'] ?? 0; // Ensure total is also set

    // Get GLOBAL total devices for the user
    // CRITICAL FIX: For viewers, count all devices in the system, not just those owned by them.
    $sql_global_total = "SELECT COUNT(*) as global_total FROM devices";
    $params_global_total = [];
    if ($user_role !== 'viewer') { // Only filter by user_id if not a viewer
        $sql_global_total .= " WHERE user_id = ?";
        $params_global_total[] = $current_user_id;
    }
    $stmt_global_total = $pdo->prepare($sql_global_total);
    $stmt_global_total->execute($params_global_total);
    $global_total_devices = $stmt_global_total->fetch(PDO::FETCH_ASSOC)['global_total'] ?? 0;


    // Get devices (this part is not directly used by dashboard.js for display, but kept for consistency)
    // For viewers, do not filter by user_id here, show all devices on the map
    $sql_devices = "SELECT name, ip, status FROM devices WHERE map_id = ?";
    $params_devices = [$map_id];
    if ($user_role !== 'viewer') {
        $sql_devices .= " AND user_id = ?";
        $params_devices[] = $current_user_id;
    }
    $sql_devices .= " ORDER BY name ASC LIMIT 10";
    $stmt = $pdo->prepare($sql_devices);
    $stmt->execute($params_devices);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent status logs for the map's devices
    // For viewers, do not filter by user_id here, show all devices on the map
    $sql_recent_activity = "
        SELECT 
            dsl.created_at, 
            dsl.status, 
            dsl.details, 
            d.name as device_name, 
            d.ip as device_ip
        FROM 
            device_status_logs dsl
        JOIN 
            devices d ON dsl.device_id = d.id
        WHERE 
            d.map_id = ?
    ";
    $params_recent_activity = [$map_id];
    if ($user_role !== 'viewer') {
        $sql_recent_activity .= " AND d.user_id = ?";
        $params_recent_activity[] = $current_user_id;
    }
    $sql_recent_activity .= " ORDER BY dsl.created_at DESC LIMIT 5";
    $stmt = $pdo->prepare($sql_recent_activity);
    $stmt->execute($params_recent_activity);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'map_stats' => $map_stats,
        'global_total_devices' => $global_total_devices,
        'devices' => $devices,
        'recent_activity' => $recent_activity
    ]);
}