<?php
// API endpoint for agent logging (POST) and admin log viewing (GET)
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // 1. Agent Authenticate (similar to heartbeat)
    // Accept both Bearer token and X-Agent-Secret header (new Rust agent format)
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $agent_id = null;
    $agent_secret = null;

    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
        $parts = explode(':', $token, 2);
        if (count($parts) === 2) {
            $agent_id = (int)$parts[0];
            $agent_secret = $parts[1];
        }
    }

    // X-Agent-Secret header (new Tauri agent format)
    if ($agent_id === null || $agent_secret === null) {
        $agent_id = isset($_SERVER['HTTP_X_AGENT_ID']) ? (int)$_SERVER['HTTP_X_AGENT_ID'] : null;
        $agent_secret = $_SERVER['HTTP_X_AGENT_SECRET'] ?? null;
    }

    // Also try to read agent_id from JSON body (Tauri agent sends it there)
    $raw_payload_pre = file_get_contents('php://input');
    $payload_pre = json_decode($raw_payload_pre, true);
    if ($agent_id === null && isset($payload_pre['agent_id'])) {
        $agent_id = (int)$payload_pre['agent_id'];
    }

    if (!$agent_id || !$agent_secret) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Missing agent credentials']);
        exit;
    }


    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("SELECT id, is_active FROM agent_devices WHERE id = ? LIMIT 1");
        $stmt->execute([$agent_id]);
        $device = $stmt->fetch();
        
        if (!$device) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Unknown agent']);
            exit;
        }
        
        if (!$device['is_active']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Agent is deactivated']);
            exit;
        }
        
        // Verify secret
        $stmt = $pdo->prepare("SELECT secret_hash FROM agent_device_secrets WHERE agent_device_id = ? AND revoked_at IS NULL");
        $stmt->execute([$agent_id]);
        $secrets = $stmt->fetchAll();
        
        $authenticated = false;
        foreach ($secrets as $sec) {
            if (password_verify($agent_secret, $sec['secret_hash'])) {
                $authenticated = true;
                break;
            }
        }
        
        if (!$authenticated) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Invalid agent secret']);
            exit;
        }
        
        // Parse logs payload — use already-read body if available
        $raw_payload = isset($raw_payload_pre) ? $raw_payload_pre : file_get_contents('php://input');
        $payload = isset($payload_pre) ? $payload_pre : json_decode($raw_payload, true);
        
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Malformed JSON payload']);
            exit;
        }
        
        // Accept both 'logs' (old format) and 'entries' (new Tauri Rust format)
        $logs = $payload['entries'] ?? $payload['logs'] ?? [];
        if (!is_array($logs)) {
            $logs = [$payload];
        }
        
        $stmt_event = $pdo->prepare("INSERT INTO agent_events (agent_device_id, event_type, severity, message, metadata_json) VALUES (?, ?, ?, ?, ?)");
        $count = 0;
        
        foreach ($logs as $log) {
            // Support both old format and new LogEntry format from Rust
            $event_type = trim((string)($log['event_type'] ?? $log['channel'] ?? 'windows_event'));
            $severity    = trim((string)($log['severity']   ?? $log['level']   ?? 'info'));
            $message     = trim((string)($log['message']    ?? ''));
            
            // Build metadata from extra LogEntry fields
            $meta = [];
            if (isset($log['event_id']))  $meta['event_id']  = $log['event_id'];
            if (isset($log['source']))    $meta['source']    = $log['source'];
            if (isset($log['timestamp'])) $meta['timestamp'] = $log['timestamp'];
            if (isset($log['metadata_json'])) $meta = array_merge($meta, (array)$log['metadata_json']);
            $metadata = !empty($meta) ? json_encode($meta) : null;
            
            if (!empty($message)) {
                $stmt_event->execute([$agent_id, $event_type, $severity, $message, $metadata]);
                $count++;
            }
        }
        
        echo json_encode(['success' => true, 'inserted_logs' => $count]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error in api/agent/logs.php POST: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
} elseif ($method === 'GET') {
    // 2. Admin UI Logs Querying
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
    $severity = isset($_GET['severity']) ? trim((string)$_GET['severity']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($limit < 1) $limit = 1;
    if ($limit > 500) $limit = 500;
    
    try {
        $pdo = getDbConnection();
        
        $query = "SELECT ae.*, ad.hostname, ad.agent_name FROM agent_events ae 
                  JOIN agent_devices ad ON ae.agent_device_id = ad.id";
        $params = [];
        $conditions = [];
        
        if ($agent_id > 0) {
            $conditions[] = "ae.agent_device_id = ?";
            $params[] = $agent_id;
        }
        
        if (!empty($severity)) {
            $conditions[] = "ae.severity = ?";
            $params[] = $severity;
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY ae.created_at DESC LIMIT " . $limit;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $events = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'events' => $events
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error in api/agent/logs.php GET: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
