<?php
/**
 * AMPNM Windows Agent Remote Command Result Ingest Endpoint
 * Copyright (c) IT Support BD. All rights reserved.
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Authenticate Agent
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

if ($agent_id === null || $agent_secret === null) {
    $agent_id = isset($_SERVER['HTTP_X_AGENT_ID']) ? (int)$_SERVER['HTTP_X_AGENT_ID'] : null;
    $agent_secret = $_SERVER['HTTP_X_AGENT_SECRET'] ?? null;
}

if (!$agent_id || !$agent_secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Missing agent credentials']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Verify secret
    $stmt = $pdo->prepare("SELECT secret_hash FROM agent_device_secrets WHERE agent_device_id = ? AND revoked_at IS NULL");
    $stmt->execute([$agent_id]);
    $secrets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

    $raw_payload = file_get_contents('php://input');
    $payload = json_decode($raw_payload, true);

    if (!is_array($payload) || empty($payload['command_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request: Missing command_id']);
        exit;
    }

    $commandId = trim($payload['command_id']);
    $exitCode = isset($payload['exit_code']) ? (int)$payload['exit_code'] : 0;
    $output = trim($payload['output'] ?? '');
    $status = ($exitCode === 0) ? 'completed' : 'failed';

    $upd = $pdo->prepare("UPDATE agent_command_queue 
        SET status = ?, output = ?, exit_code = ?, executed_at = NOW() 
        WHERE id = ? AND agent_device_id = ?");
    $upd->execute([$status, $output, $exitCode, $commandId, $agent_id]);

    echo json_encode(['success' => true, 'message' => 'Command result recorded']);

} catch (Exception $e) {
    error_log("Error recording agent command result: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
