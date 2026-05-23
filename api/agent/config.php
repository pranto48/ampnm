<?php
// Config API for Windows Usage Agent
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $heartbeat_interval = (int)(getAppSetting('agent_heartbeat_interval_seconds') ?? 5);
    if ($heartbeat_interval < 1) $heartbeat_interval = 5;

    $collect_username = (getAppSetting('agent_collect_username') ?? '1') === '1';
    $collect_mac_address = (getAppSetting('agent_collect_mac_address') ?? '1') === '1';
    $collect_public_ip = (getAppSetting('agent_collect_public_ip') ?? '1') === '1';

    $warn_threshold_cpu = (float)(getAppSetting('agent_warn_threshold_cpu') ?? 90.0);
    $warn_threshold_mem = (float)(getAppSetting('agent_warn_threshold_mem') ?? 90.0);
    $warn_threshold_disk = (float)(getAppSetting('agent_warn_threshold_disk') ?? 90.0);

    echo json_encode([
        'success' => true,
        'heartbeat_interval_seconds' => $heartbeat_interval,
        'collect_username' => $collect_username,
        'collect_mac_address' => $collect_mac_address,
        'collect_public_ip' => $collect_public_ip,
        'warn_threshold_cpu' => $warn_threshold_cpu,
        'warn_threshold_mem' => $warn_threshold_mem,
        'warn_threshold_disk' => $warn_threshold_disk,
    ]);
} catch (Exception $e) {
    error_log("Error fetching agent configuration: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error fetching configuration']);
}
