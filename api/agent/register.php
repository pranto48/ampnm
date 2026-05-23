<?php
// Onboarding API for Windows Usage Agent
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$raw_payload = file_get_contents('php://input');
$payload = json_decode($raw_payload, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed JSON payload']);
    exit;
}

$enrollment_token = trim((string)($payload['enrollment_token'] ?? ''));
$agent_uuid = trim((string)($payload['agent_uuid'] ?? ''));
$hostname = trim((string)($payload['hostname'] ?? ''));
$os_name = trim((string)($payload['os_name'] ?? ''));
$os_version = trim((string)($payload['os_version'] ?? ''));
$architecture = trim((string)($payload['architecture'] ?? ''));
$app_version = trim((string)($payload['app_version'] ?? ''));
$server_address = trim((string)($payload['server_address'] ?? ''));

// Validate required fields
if (empty($enrollment_token) || empty($agent_uuid) || empty($hostname) || empty($os_name) || empty($os_version) || empty($architecture) || empty($app_version) || empty($server_address)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required registration parameters']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Hash token to query DB
    $token_hash = hash('sha256', $enrollment_token);
    
    $stmt = $pdo->prepare("SELECT id, is_active, expires_at FROM agent_enrollment_tokens WHERE token_hash = ? LIMIT 1");
    $stmt->execute([$token_hash]);
    $token_info = $stmt->fetch();
    
    if (!$token_info) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid enrollment token']);
        exit;
    }
    
    if (!$token_info['is_active']) {
        http_response_code(401);
        echo json_encode(['error' => 'Enrollment token is inactive']);
        exit;
    }
    
    if ($token_info['expires_at'] !== null && strtotime($token_info['expires_at']) < time()) {
        http_response_code(401);
        echo json_encode(['error' => 'Enrollment token has expired']);
        exit;
    }
    
    // Check consent settings from settings panel
    $collect_username = (getAppSetting('agent_collect_username') ?? '1') === '1';
    $collect_mac_address = (getAppSetting('agent_collect_mac_address') ?? '1') === '1';
    $collect_public_ip = (getAppSetting('agent_collect_public_ip') ?? '1') === '1';
    
    $username = $collect_username ? trim((string)($payload['username'] ?? '')) : null;
    $domain = trim((string)($payload['domain'] ?? ''));
    $local_ip = trim((string)($payload['local_ip'] ?? ''));
    $public_ip = $collect_public_ip ? ($_SERVER['REMOTE_ADDR'] ?? trim((string)($payload['public_ip'] ?? ''))) : null;
    $mac_address = $collect_mac_address ? trim((string)($payload['mac_address'] ?? '')) : null;
    
    $agent_name = trim((string)($payload['agent_name'] ?? ''));
    $device_name = trim((string)($payload['device_name'] ?? ''));
    $cpu_model = trim((string)($payload['cpu_model'] ?? ''));
    $cpu_cores = (int)($payload['cpu_cores'] ?? 1);
    $total_memory_mb = (int)($payload['total_memory_mb'] ?? 0);
    $total_disk_gb = (int)($payload['total_disk_gb'] ?? 0);
    
    $heartbeat_interval = (int)(getAppSetting('agent_heartbeat_interval_seconds') ?? 5);
    if ($heartbeat_interval < 1) $heartbeat_interval = 5;
    
    // Check if device already exists
    $stmt = $pdo->prepare("SELECT id FROM agent_devices WHERE agent_uuid = ? LIMIT 1");
    $stmt->execute([$agent_uuid]);
    $existing_device = $stmt->fetch();
    
    $now_str = date('Y-m-d H:i:s');
    
    if ($existing_device) {
        $device_id = $existing_device['id'];
        $stmt = $pdo->prepare("UPDATE agent_devices SET 
            hostname = ?, os_name = ?, os_version = ?, architecture = ?, username = ?, domain = ?, 
            local_ip = ?, public_ip = ?, mac_address = ?, cpu_model = ?, cpu_cores = ?, 
            total_memory_mb = ?, total_disk_gb = ?, app_version = ?, server_address = ?, 
            heartbeat_interval_seconds = ?, status = 'online', last_seen_at = ?, is_active = 1 
            WHERE id = ?");
        $stmt->execute([
            $hostname, $os_name, $os_version, $architecture, $username, $domain,
            $local_ip, $public_ip, $mac_address, $cpu_model, $cpu_cores,
            $total_memory_mb, $total_disk_gb, $app_version, $server_address,
            $heartbeat_interval, $now_str, $device_id
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO agent_devices (
            agent_uuid, agent_name, hostname, device_name, os_name, os_version, architecture, username, domain,
            local_ip, public_ip, mac_address, cpu_model, cpu_cores, total_memory_mb, total_disk_gb,
            app_version, server_address, heartbeat_interval_seconds, status, last_seen_at, registered_at, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', ?, ?, 1)");
        $stmt->execute([
            $agent_uuid, empty($agent_name) ? $hostname : $agent_name, $hostname, empty($device_name) ? null : $device_name,
            $os_name, $os_version, $architecture, $username, $domain,
            $local_ip, $public_ip, $mac_address, $cpu_model, $cpu_cores, $total_memory_mb, $total_disk_gb,
            $app_version, $server_address, $heartbeat_interval, $now_str, $now_str
        ]);
        $device_id = $pdo->lastInsertId();
    }
    
    // Generate secure random agent secret (64 characters hex = 32 bytes entropy)
    $agent_secret = bin2hex(random_bytes(32));
    $secret_hash = password_hash($agent_secret, PASSWORD_BCRYPT);
    
    // Revoke previous secrets
    $stmt = $pdo->prepare("UPDATE agent_device_secrets SET revoked_at = ? WHERE agent_device_id = ? AND revoked_at IS NULL");
    $stmt->execute([$now_str, $device_id]);
    
    // Insert new secret
    $stmt = $pdo->prepare("INSERT INTO agent_device_secrets (agent_device_id, secret_hash) VALUES (?, ?)");
    $stmt->execute([$device_id, $secret_hash]);
    
    // Update token last used
    $stmt = $pdo->prepare("UPDATE agent_enrollment_tokens SET last_used_at = ? WHERE id = ?");
    $stmt->execute([$now_str, $token_info['id']]);
    
    // Log registration event
    $stmt = $pdo->prepare("INSERT INTO agent_events (agent_device_id, event_type, severity, message, metadata_json) VALUES (?, 'onboarding', 'info', ?, ?)");
    $stmt->execute([
        $device_id,
        "Device registered successfully: hostname={$hostname}, OS={$os_name} {$os_version}",
        json_encode(['ip' => $public_ip, 'user' => $username, 'version' => $app_version])
    ]);
    
    echo json_encode([
        'success' => true,
        'agent_id' => (int)$device_id,
        'agent_secret' => $agent_secret, // Plaintext, displayed exactly once!
        'heartbeat_interval_seconds' => $heartbeat_interval
    ]);
    
} catch (Exception $e) {
    error_log("Error during agent registration: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error during registration']);
}
