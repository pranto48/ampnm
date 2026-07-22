<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// Heartbeat API for Windows Usage Agent
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// 1. Authenticate the Agent
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

// Fallback headers
if ($agent_id === null || $agent_secret === null) {
    $agent_id = isset($_SERVER['HTTP_X_AGENT_ID']) ? (int)$_SERVER['HTTP_X_AGENT_ID'] : null;
    $agent_secret = $_SERVER['HTTP_X_AGENT_SECRET'] ?? null;
}

// Fallback query parameters
if ($agent_id === null || $agent_secret === null) {
    $agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
    $agent_secret = $_GET['agent_secret'] ?? null;
}

if (!$agent_id || !$agent_secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Missing agent credentials']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Find device
    $stmt = $pdo->prepare("SELECT id, is_active, hostname FROM agent_devices WHERE id = ? LIMIT 1");
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
        // Log auth failure
        $stmt = $pdo->prepare("INSERT INTO agent_events (agent_device_id, event_type, severity, message) VALUES (?, 'auth_failure', 'warning', ?)");
        $stmt->execute([$agent_id, "Failed heartbeat authentication attempt from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]);
        
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid agent secret']);
        exit;
    }
    
    // 2. Parse Telemetry Payload
    $raw_payload = file_get_contents('php://input');
    $payload = json_decode($raw_payload, true);
    
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Malformed JSON payload']);
        exit;
    }
    
    $cpu_usage_percent = (float)($payload['cpu_usage_percent'] ?? 0.0);
    $memory_used_mb = (int)($payload['memory_used_mb'] ?? 0);
    $memory_total_mb = (int)($payload['memory_total_mb'] ?? 0);
    $memory_usage_percent = $memory_total_mb > 0 ? round(($memory_used_mb / $memory_total_mb) * 100, 2) : 0.0;
    
    $disk_used_gb = (int)($payload['disk_used_gb'] ?? 0);
    $disk_total_gb = (int)($payload['disk_total_gb'] ?? 0);
    $disk_usage_percent = $disk_total_gb > 0 ? round(($disk_used_gb / $disk_total_gb) * 100, 2) : 0.0;
    
    $network_rx_bytes = (int)($payload['network_rx_bytes'] ?? 0);
    $network_tx_bytes = (int)($payload['network_tx_bytes'] ?? 0);
    $uptime_seconds = (int)($payload['uptime_seconds'] ?? 0);
    
    $battery_percent = isset($payload['battery_percent']) ? (int)$payload['battery_percent'] : null;
    $battery_status = trim((string)($payload['battery_status'] ?? 'unknown'));
    
    // Consent checking for privacy compliance
    $collect_username = (getAppSetting('agent_collect_username') ?? '1') === '1';
    $collect_public_ip = (getAppSetting('agent_collect_public_ip') ?? '1') === '1';
    
    $active_user = $collect_username ? trim((string)($payload['active_user'] ?? '')) : null;
    $current_ip = $collect_public_ip ? ($_SERVER['REMOTE_ADDR'] ?? trim((string)($payload['current_ip'] ?? ''))) : null;
    
    $process_count = (int)($payload['process_count'] ?? 0);
    $service_count = (int)($payload['service_count'] ?? 0);
    $agent_version = trim((string)($payload['agent_version'] ?? '1.0.0'));
    
    $collected_at = trim((string)($payload['collected_at'] ?? ''));
    if (empty($collected_at)) {
        $collected_at = date('Y-m-d H:i:s');
    } else {
        // Convert ISO 8601 or other formats to DB datetime format
        $timestamp = strtotime($collected_at);
        if ($timestamp !== false) {
            $collected_at = date('Y-m-d H:i:s', $timestamp);
        } else {
            $collected_at = date('Y-m-d H:i:s');
        }
    }
    
    // 3. Log Heartbeat in Database
    $stmt = $pdo->prepare("INSERT INTO agent_heartbeats (
        agent_device_id, cpu_usage_percent, memory_used_mb, memory_total_mb, memory_usage_percent,
        disk_used_gb, disk_total_gb, disk_usage_percent, network_rx_bytes, network_tx_bytes,
        uptime_seconds, battery_percent, battery_status, active_user, current_ip,
        process_count, service_count, agent_version, collected_at, raw_payload_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $agent_id, $cpu_usage_percent, $memory_used_mb, $memory_total_mb, $memory_usage_percent,
        $disk_used_gb, $disk_total_gb, $disk_usage_percent, $network_rx_bytes, $network_tx_bytes,
        $uptime_seconds, $battery_percent, $battery_status, $active_user, $current_ip,
        $process_count, $service_count, $agent_version, $collected_at, $raw_payload
    ]);
    
    // 4. Determine Dynamic Status Warning Thresholds
    $cpu_threshold = (float)(getAppSetting('agent_warn_threshold_cpu') ?? 90.0);
    $mem_threshold = (float)(getAppSetting('agent_warn_threshold_mem') ?? 90.0);
    $disk_threshold = (float)(getAppSetting('agent_warn_threshold_disk') ?? 90.0);
    
    $status = 'online';
    $warnings = [];
    
    if ($cpu_usage_percent >= $cpu_threshold) {
        $status = 'warning';
        $warnings[] = "High CPU: {$cpu_usage_percent}%";
    }
    if ($memory_usage_percent >= $mem_threshold) {
        $status = 'warning';
        $warnings[] = "High Memory: {$memory_usage_percent}%";
    }
    if ($disk_usage_percent >= $disk_threshold) {
        $status = 'warning';
        $warnings[] = "High Disk: {$disk_usage_percent}%";
    }
    
    // 5. Update agent_devices
    $now_str = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE agent_devices SET 
        status = ?, last_seen_at = ?, public_ip = ?, app_version = ?, 
        total_memory_mb = ?, total_disk_gb = ?
        WHERE id = ?");
    $stmt->execute([
        $status, $now_str, $current_ip, $agent_version,
        $memory_total_mb, $disk_total_gb, $agent_id
    ]);
    
    // 6. Ingest Client Events if provided
    if (isset($payload['events']) && is_array($payload['events'])) {
        $stmt_event = $pdo->prepare("INSERT INTO agent_events (agent_device_id, event_type, severity, message, metadata_json) VALUES (?, ?, ?, ?, ?)");
        foreach ($payload['events'] as $evt) {
            $evt_type = trim((string)($evt['event_type'] ?? 'client_event'));
            $severity = trim((string)($evt['severity'] ?? 'info'));
            $msg = trim((string)($evt['message'] ?? ''));
            $meta = isset($evt['metadata_json']) ? json_encode($evt['metadata_json']) : null;
            
            if (!empty($msg)) {
                $stmt_event->execute([$agent_id, $evt_type, $severity, $msg, $meta]);
            }
        }
    }
    
    // Also log auto-warnings to agent_events
    if (!empty($warnings)) {
        $stmt_warn = $pdo->prepare("INSERT INTO agent_events (agent_device_id, event_type, severity, message) VALUES (?, 'system_threshold', 'warning', ?)");
        $stmt_warn->execute([$agent_id, "Threshold warning: " . implode(', ', $warnings)]);
    }
    
    // Get server configuration for response
    $heartbeat_interval = (int)(getAppSetting('agent_heartbeat_interval_seconds') ?? 5);
    if ($heartbeat_interval < 1) $heartbeat_interval = 5;
    
    echo json_encode([
        'success' => true,
        'heartbeat_interval_seconds' => $heartbeat_interval
    ]);
    
} catch (Exception $e) {
    error_log("Error during agent heartbeat: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error during heartbeat processing']);
}
