<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// REST-style endpoint for Windows/Linux monitoring agents
// - POST   /api/agent/metrics
// - GET    /api/agent/metrics/health
// - GET    /api/agent/metrics/recent?limit=50
// - GET    /api/agent/metrics/<HOSTNAME>/latest
// - GET    /api/agent/metrics/device-by-ip?host_ip=1.2.3.4&host_name=HOST

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/agent_metrics_compat.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$endpointBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$suffix = '';
if ($endpointBase !== '' && str_starts_with($requestPath, $endpointBase)) {
    $suffix = trim(substr($requestPath, strlen($endpointBase)), '/');
}

try {
    if ($method === 'POST' && ($suffix === '' || $suffix === 'ingest')) {
        $pdo = getDbConnection();
        $token = agentCompatGetHeader('X-Agent-Token');
        $tokenInfo = agentCompatValidateToken($pdo, $token);
        if (!$tokenInfo) {
            http_response_code(401);
            echo json_encode(array('error' => 'Invalid or missing agent token'));
            exit;
        }
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $saved = agentCompatSaveMetrics($pdo, $payload, (int)$tokenInfo['user_id']);
        if (empty($saved['ok'])) {
            http_response_code(400);
            echo json_encode(array('error' => $saved['error']));
            exit;
        }
        echo json_encode(array('success' => true, 'host_ip' => $saved['host_ip'], 'host_name' => $saved['host_name'], 'device_id' => $saved['device_id']));
        exit;
    }

    if ($method === 'GET' && $suffix === 'device-by-ip') {
        $pdo = getDbConnection();
        $token = agentCompatGetHeader('X-Agent-Token');
        $tokenInfo = agentCompatValidateToken($pdo, $token);
        if (!$tokenInfo) {
            http_response_code(401);
            echo json_encode(array('error' => 'Invalid or missing agent token'));
            exit;
        }
        $hostIp = isset($_GET['host_ip']) ? trim((string)$_GET['host_ip']) : '';
        $hostName = isset($_GET['host_name']) ? trim((string)$_GET['host_name']) : '';
        if ($hostIp === '' && $hostName === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'host_ip or host_name is required'));
            exit;
        }
        $device = agentCompatFindOrCreateDevice($pdo, (int)$tokenInfo['user_id'], $hostName, $hostIp);
        echo json_encode(array('success' => true, 'device_found' => !empty($device), 'device' => $device));
        exit;
    }

    $pdo = getDbConnection();

    if ($method === 'GET' && $suffix === 'health') {
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();
        echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
        exit;
    }

    if ($method === 'GET' && $suffix === 'recent') {
        $limit = (int)($_GET['limit'] ?? 50);
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $stmt = $pdo->prepare('SELECT hm.*, hm.hostname AS host_name, hm.ip_address AS host_ip, hm.cpu_usage AS cpu_percent, hm.memory_usage AS memory_percent, hm.disk_usage AS disk_percent, hm.gpu_usage AS gpu_percent FROM host_metrics hm ORDER BY hm.created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^([^/]+)/latest$#', $suffix, $m)) {
        $hostName = urldecode($m[1]);
        $stmt = $pdo->prepare('SELECT hm.*, hm.hostname AS host_name, hm.ip_address AS host_ip, hm.cpu_usage AS cpu_percent, hm.memory_usage AS memory_percent, hm.disk_usage AS disk_percent, hm.gpu_usage AS gpu_percent FROM host_metrics hm WHERE hm.hostname = ? ORDER BY hm.created_at DESC LIMIT 1');
        $stmt->execute([$hostName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['error' => 'No metrics found']);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
} catch (Exception $e) {
    error_log('agent metrics endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
