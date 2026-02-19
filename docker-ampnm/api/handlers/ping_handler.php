<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$user_role = $_SESSION['user_role'] ?? 'viewer'; // Get current user's role

switch ($action) {
    case 'manual_ping':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can perform manual pings.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $input['host'] ?? '';
            $count = $input['count'] ?? 4; // Use count from input, default to 4
            if (empty($host)) {
                http_response_code(400);
                echo json_encode(['error' => 'Host is required']);
                exit;
            }
            $result = executePing($host, $count);
            savePingResult($pdo, $host, $result);
            echo json_encode($result);
        }
        break;

    case 'ping_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can ping devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip = $input['ip'] ?? '';
            if (empty($ip)) {
                http_response_code(400);
                echo json_encode(['error' => 'IP address is required']);
                exit;
            }
            $result = pingDevice($ip);
            echo json_encode($result);
        }
        break;

    case 'scan_network':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can scan the network.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $subnet = $input['subnet'] ?? ''; // e.g., '192.168.1.0/24'
            $devices = scanNetwork($subnet);
            echo json_encode(['devices' => $devices]);
        }
        break;

    case 'get_ping_history':
        // Allow viewers to see ping history
        $host = $_GET['host'] ?? '';
        $limit = $_GET['limit'] ?? 100;

        $sql = "SELECT host, avg_time, packet_loss, success, created_at FROM ping_results";
        $params = [];
        if ($host) {
            $sql .= " WHERE host = ?";
            $params[] = $host;
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reverse the array so the chart shows oldest to newest
        echo json_encode(array_reverse($history));
        break;
}