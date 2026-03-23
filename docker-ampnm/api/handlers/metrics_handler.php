<?php
/**
 * Metrics Handler - Receives metrics from Windows monitoring agents
 * Supports both authenticated (token) and IP-based device matching
 */

// This handler can be called directly for agent metrics (no session required)
$action = $_GET['action'] ?? '';
$pdo = getDbConnection();

/**
 * Validate agent token
 */
function validateAgentToken($pdo, $token) {
    if (empty($token)) return false;

    $stmt = $pdo->prepare("SELECT id, name FROM agent_tokens WHERE token = ? AND enabled = TRUE");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $updateStmt = $pdo->prepare("UPDATE agent_tokens SET last_used_at = NOW() WHERE id = ?");
        $updateStmt->execute([$result['id']]);
        return $result;
    }
    return false;
}

/**
 * Find device by IP address or hostname
 */
function findDeviceMatch($pdo, $hostname, $ip) {
    if (!empty($ip)) {
        $stmt = $pdo->prepare("SELECT id, name FROM devices WHERE ip = ? LIMIT 1");
        $stmt->execute([$ip]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($device) return $device;
    }

    if (!empty($hostname)) {
        $stmt = $pdo->prepare("SELECT id, name FROM devices WHERE name = ? LIMIT 1");
        $stmt->execute([$hostname]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($device) return $device;
    }

    return false;
}

function normalizePlatform($payload) {
    $platform = strtolower(trim((string)($payload['platform'] ?? $payload['agent_platform'] ?? '')));
    if ($platform === '') {
        $runtimeCollector = strtolower((string)($payload['agent_runtime']['collector'] ?? ''));
        $osVersion = strtolower((string)($payload['os_version'] ?? ''));
        if (str_contains($runtimeCollector, 'linux') || str_contains($osVersion, 'ubuntu') || str_contains($osVersion, 'debian') || str_contains($osVersion, 'rhel') || str_contains($osVersion, 'rocky') || str_contains($osVersion, 'alma') || str_contains($osVersion, 'fedora')) {
            return 'linux';
        }
        if (str_contains($osVersion, 'windows')) {
            return 'windows';
        }
    }
    return in_array($platform, ['windows', 'linux'], true) ? $platform : 'unknown';
}

function normalizeMetricsPayload($payload) {
    $hostname = trim((string)($payload['hostname'] ?? $payload['host_name'] ?? 'Unknown Host'));
    $ipAddress = trim((string)($payload['ip_address'] ?? $payload['host_ip'] ?? ''));
    $cpuUsage = $payload['cpu_usage'] ?? $payload['cpu_percent'] ?? $payload['cpu'] ?? null;
    $memoryUsage = $payload['memory_usage'] ?? $payload['memory_percent'] ?? null;
    $memoryTotal = $payload['memory_total'] ?? $payload['memory_total_gb'] ?? null;
    $diskUsage = $payload['disk_usage'] ?? $payload['disk_percent'] ?? null;
    $diskTotal = $payload['disk_total'] ?? $payload['disk_total_gb'] ?? null;
    $networkIn = $payload['network_in'] ?? null;
    $networkOut = $payload['network_out'] ?? null;
    $gpuUsage = $payload['gpu_usage'] ?? $payload['gpu_percent'] ?? null;
    $temperature = $payload['temperature_celsius'] ?? $payload['temperature_c'] ?? null;
    $loadAverage = $payload['load_average'] ?? $payload['load_1'] ?? null;
    $platform = normalizePlatform($payload);

    return [
        'hostname' => $hostname !== '' ? $hostname : 'Unknown Host',
        'ip_address' => $ipAddress !== '' ? $ipAddress : null,
        'os_version' => $payload['os_version'] ?? null,
        'cpu_usage' => is_numeric($cpuUsage) ? round((float)$cpuUsage, 2) : null,
        'memory_usage' => is_numeric($memoryUsage) ? round((float)$memoryUsage, 2) : null,
        'memory_total' => is_numeric($memoryTotal) ? round((float)$memoryTotal, 2) : null,
        'disk_usage' => is_numeric($diskUsage) ? round((float)$diskUsage, 2) : null,
        'disk_total' => is_numeric($diskTotal) ? round((float)$diskTotal, 2) : null,
        'gpu_usage' => is_numeric($gpuUsage) ? round((float)$gpuUsage, 2) : null,
        'network_in' => is_numeric($networkIn) ? (int)round((float)$networkIn) : null,
        'network_out' => is_numeric($networkOut) ? (int)round((float)$networkOut) : null,
        'uptime_seconds' => isset($payload['uptime_seconds']) && is_numeric($payload['uptime_seconds']) ? (int)$payload['uptime_seconds'] : null,
        'boot_time' => !empty($payload['boot_time']) ? date('Y-m-d H:i:s', strtotime((string)$payload['boot_time'])) : null,
        'status' => 'online',
        'platform' => $platform,
        'load_average' => is_numeric($loadAverage) ? round((float)$loadAverage, 2) : null,
        'temperature_celsius' => is_numeric($temperature) ? round((float)$temperature, 2) : null,
        'top_processes' => is_array($payload['top_processes'] ?? null) ? $payload['top_processes'] : (is_array($payload['processes'] ?? null) ? $payload['processes'] : []),
        'services' => is_array($payload['services'] ?? null) ? $payload['services'] : [],
    ];
}

/**
 * Save current host snapshot and matching history row
 */
function saveHostMetrics($pdo, $payload, $tokenInfo, $deviceId = null) {
    $sql = "INSERT INTO host_metrics (
        device_id, hostname, ip_address, os_version, cpu_usage, memory_usage,
        memory_total, disk_usage, disk_total, gpu_usage, network_in, network_out,
        uptime_seconds, boot_time, status, agent_token_id, platform, load_average, temperature_celsius
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        device_id = VALUES(device_id),
        ip_address = VALUES(ip_address),
        os_version = VALUES(os_version),
        cpu_usage = VALUES(cpu_usage),
        memory_usage = VALUES(memory_usage),
        memory_total = VALUES(memory_total),
        disk_usage = VALUES(disk_usage),
        disk_total = VALUES(disk_total),
        gpu_usage = VALUES(gpu_usage),
        network_in = VALUES(network_in),
        network_out = VALUES(network_out),
        uptime_seconds = VALUES(uptime_seconds),
        boot_time = VALUES(boot_time),
        status = VALUES(status),
        agent_token_id = VALUES(agent_token_id),
        platform = VALUES(platform),
        load_average = VALUES(load_average),
        temperature_celsius = VALUES(temperature_celsius),
        last_seen = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $deviceId,
        $payload['hostname'],
        $payload['ip_address'],
        $payload['os_version'],
        $payload['cpu_usage'],
        $payload['memory_usage'],
        $payload['memory_total'],
        $payload['disk_usage'],
        $payload['disk_total'],
        $payload['gpu_usage'],
        $payload['network_in'],
        $payload['network_out'],
        $payload['uptime_seconds'],
        $payload['boot_time'],
        $payload['status'],
        $tokenInfo['id'],
        $payload['platform'],
        $payload['load_average'],
        $payload['temperature_celsius'],
    ]);

    $historyStmt = $pdo->prepare("INSERT INTO host_metrics_history (
        hostname, cpu_usage, memory_usage, memory_total, disk_usage, disk_total, gpu_usage, network_in, network_out
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $historyStmt->execute([
        $payload['hostname'],
        $payload['cpu_usage'],
        $payload['memory_usage'],
        $payload['memory_total'],
        $payload['disk_usage'],
        $payload['disk_total'],
        $payload['gpu_usage'],
        $payload['network_in'],
        $payload['network_out'],
    ]);

    return $pdo->lastInsertId();
}

function saveHostProcesses($pdo, $hostname, $processes, $services) {
    if (empty($hostname)) return;

    $deleteStmt = $pdo->prepare("DELETE FROM host_processes WHERE hostname = ?");
    $deleteStmt->execute([$hostname]);

    $insertStmt = $pdo->prepare("INSERT INTO host_processes (hostname, process_name, process_type, pid, cpu_percent, memory_mb, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($processes as $process) {
        if (!is_array($process)) continue;
        $insertStmt->execute([
            $hostname,
            $process['name'] ?? $process['process_name'] ?? 'process',
            'process',
            isset($process['pid']) && is_numeric($process['pid']) ? (int)$process['pid'] : null,
            isset($process['cpu_percent']) && is_numeric($process['cpu_percent']) ? round((float)$process['cpu_percent'], 2) : null,
            isset($process['memory_mb']) && is_numeric($process['memory_mb']) ? round((float)$process['memory_mb'], 2) : null,
            $process['status'] ?? null,
        ]);
    }

    foreach ($services as $service) {
        if (!is_array($service)) continue;
        $insertStmt->execute([
            $hostname,
            $service['name'] ?? $service['service_name'] ?? 'service',
            'service',
            null,
            null,
            null,
            $service['status'] ?? $service['state'] ?? $service['sub_state'] ?? null,
        ]);
    }
}

/**
 * Clean up old metrics (keep last 7 days)
 */
function cleanupOldMetrics($pdo, $daysToKeep = 7) {
    $stmt = $pdo->prepare("DELETE FROM host_metrics WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$daysToKeep]);
    return $stmt->rowCount();
}

// Parse input early for all actions that need it
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Handle different actions
switch ($action) {
    case 'submit_metrics':
        // Accept metrics from Windows and Linux agents
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        
        // Validate token
        $tokenInfo = validateAgentToken($pdo, $token);
        if (!$tokenInfo) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or missing agent token']);
            exit;
        }
        
        $normalized = normalizeMetricsPayload($input);
        if (empty($normalized['hostname'])) {
            http_response_code(400);
            echo json_encode(['error' => 'hostname is required']);
            exit;
        }

        $device = findDeviceMatch($pdo, $normalized['hostname'], $normalized['ip_address']);
        $deviceId = $device ? $device['id'] : null;

        $metricsId = saveHostMetrics($pdo, $normalized, $tokenInfo, $deviceId);
        saveHostProcesses($pdo, $normalized['hostname'], $normalized['top_processes'], $normalized['services']);
        
        // Check thresholds and send alerts if needed
        try {
            require_once __DIR__ . '/../../includes/host_alerts.php';
            $alertSystem = new HostAlertSystem($pdo);
            $alertSystem->checkAndAlert(
                $normalized['ip_address'] ?? $normalized['hostname'],
                $normalized['hostname'],
                $normalized
            );
        } catch (Exception $e) {
            error_log("Host Alert Error: " . $e->getMessage());
        }
        
        // Cleanup old data occasionally (1 in 100 requests)
        if (rand(1, 100) === 1) {
            cleanupOldMetrics($pdo);
        }
        
        echo json_encode([
            'success' => true,
            'metrics_id' => $metricsId,
            'device_matched' => $device ? $device['name'] : null,
            'platform' => $normalized['platform'],
            'hostname' => $normalized['hostname'],
            'ip_address' => $normalized['ip_address']
        ]);
        break;
        
    case 'get_latest_metrics':
        // Get latest metrics for a specific device
        $deviceId = $_GET['device_id'] ?? null;
        $hostIp = $_GET['host_ip'] ?? null;
        
        if (!$deviceId && !$hostIp) {
            http_response_code(400);
            echo json_encode(['error' => 'device_id or host_ip required']);
            exit;
        }
        
        if ($deviceId) {
            $stmt = $pdo->prepare("SELECT * FROM host_metrics WHERE device_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$deviceId]);
        } else {
            $stmt = $pdo->prepare("SELECT hm.*, hm.hostname AS host_name, hm.ip_address AS host_ip, hm.cpu_usage AS cpu_percent, hm.memory_usage AS memory_percent, hm.disk_usage AS disk_percent, hm.gpu_usage AS gpu_percent, hm.created_at FROM host_metrics hm WHERE hm.ip_address = ? ORDER BY hm.created_at DESC LIMIT 1");
            $stmt->execute([$hostIp]);
        }
        
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($metrics ?: ['error' => 'No metrics found']);
        break;
        
    case 'get_metrics_history':
        // Get historical metrics for charts
        $deviceId = $_GET['device_id'] ?? null;
        $hostIp = $_GET['host_ip'] ?? null;
        $hours = min((int)($_GET['hours'] ?? 24), 168); // Max 7 days
        
        if (!$deviceId && !$hostIp) {
            http_response_code(400);
            echo json_encode(['error' => 'device_id or host_ip required']);
            exit;
        }
        
        if ($deviceId) {
            $stmt = $pdo->prepare("
                SELECT id, cpu_usage AS cpu_percent, memory_usage AS memory_percent, disk_usage AS disk_percent,
                       (network_in * 8 / 1000000) AS network_in_mbps, (network_out * 8 / 1000000) AS network_out_mbps, gpu_usage AS gpu_percent, recorded_at AS created_at
                FROM host_metrics_history
                WHERE hostname = (SELECT hostname FROM host_metrics WHERE device_id = ? LIMIT 1) AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY recorded_at ASC
            ");
            $stmt->execute([$deviceId, $hours]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, cpu_usage AS cpu_percent, memory_usage AS memory_percent, disk_usage AS disk_percent,
                       (network_in * 8 / 1000000) AS network_in_mbps, (network_out * 8 / 1000000) AS network_out_mbps, gpu_usage AS gpu_percent, recorded_at AS created_at
                FROM host_metrics_history
                WHERE hostname = (SELECT hostname FROM host_metrics WHERE ip_address = ? LIMIT 1) AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY recorded_at ASC
            ");
            $stmt->execute([$hostIp, $hours]);
        }
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($history);
        break;
        
    case 'get_all_hosts':
        // Get list of all monitored hosts with latest metrics, first registration time, and any per-host overrides
        $stmt = $pdo->query("
            SELECT hm.*, hm.hostname as host_name, hm.ip_address as host_ip,
                   hm.cpu_usage as cpu_percent, hm.memory_usage as memory_percent, hm.disk_usage as disk_percent, hm.gpu_usage as gpu_percent,
                   d.name as device_name, d.id as linked_device_id,
                   CASE WHEN hao.id IS NOT NULL AND hao.enabled = 1 THEN 1 ELSE 0 END as has_override,
                   hao.status_delay_seconds,
                   hm.first_seen as first_seen_at
            FROM host_metrics hm
            LEFT JOIN devices d ON hm.device_id = d.id
            LEFT JOIN host_alert_overrides hao ON hm.ip_address = hao.host_ip
            ORDER BY hm.hostname
        ");
        $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($hosts);
        break;
        
    case 'get_agent_tokens':
        // List all agent tokens (admin only)
        $stmt = $pdo->query("SELECT id, name, token, enabled, last_used_at, created_at FROM agent_tokens ORDER BY created_at DESC");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($tokens);
        break;
        
    case 'create_agent_token':
        // Create a new agent token
        $name = $input['name'] ?? 'Windows Agent ' . date('Y-m-d H:i');
        $token = bin2hex(random_bytes(32)); // 64 character hex token
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
        $stmt = $pdo->prepare("INSERT INTO agent_tokens (user_id, token, name) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $name]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'token' => $token,
            'name' => $name
        ]);
        break;
        
    case 'delete_agent_token':
        $tokenId = $input['id'] ?? null;
        if (!$tokenId) {
            http_response_code(400);
            echo json_encode(['error' => 'Token ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM agent_tokens WHERE id = ?");
        $stmt->execute([$tokenId]);
        
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        break;
        
    case 'toggle_agent_token':
        $tokenId = $input['id'] ?? null;
        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
        
        if (!$tokenId) {
            http_response_code(400);
            echo json_encode(['error' => 'Token ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE agent_tokens SET enabled = ? WHERE id = ?");
        $stmt->execute([$enabled, $tokenId]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'get_alert_settings':
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("SELECT * FROM host_alert_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($settings ?: [
            'cpu_warning_threshold' => 80,
            'cpu_critical_threshold' => 95,
            'memory_warning_threshold' => 80,
            'memory_critical_threshold' => 95,
            'disk_warning_threshold' => 80,
            'disk_critical_threshold' => 95,
            'enabled' => true,
            'cooldown_minutes' => 30
        ]);
        break;
        
    case 'save_alert_settings':
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("SELECT id FROM host_alert_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            $sql = "UPDATE host_alert_settings SET cpu_warning_threshold=?, cpu_critical_threshold=?, memory_warning_threshold=?, memory_critical_threshold=?, disk_warning_threshold=?, disk_critical_threshold=?, enabled=?, cooldown_minutes=? WHERE user_id=?";
            $pdo->prepare($sql)->execute([
                $input['cpu_warning_threshold'] ?? 80, $input['cpu_critical_threshold'] ?? 95,
                $input['memory_warning_threshold'] ?? 80, $input['memory_critical_threshold'] ?? 95,
                $input['disk_warning_threshold'] ?? 80, $input['disk_critical_threshold'] ?? 95,
                $input['enabled'] ?? true, $input['cooldown_minutes'] ?? 30, $userId
            ]);
        } else {
            $sql = "INSERT INTO host_alert_settings (user_id, cpu_warning_threshold, cpu_critical_threshold, memory_warning_threshold, memory_critical_threshold, disk_warning_threshold, disk_critical_threshold, enabled, cooldown_minutes) VALUES (?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([
                $userId, $input['cpu_warning_threshold'] ?? 80, $input['cpu_critical_threshold'] ?? 95,
                $input['memory_warning_threshold'] ?? 80, $input['memory_critical_threshold'] ?? 95,
                $input['disk_warning_threshold'] ?? 80, $input['disk_critical_threshold'] ?? 95,
                $input['enabled'] ?? true, $input['cooldown_minutes'] ?? 30
            ]);
        }
        echo json_encode(['success' => true]);
        break;
    
    case 'get_host_override':
        $hostIp = $_GET['host_ip'] ?? null;
        if (!$hostIp) {
            echo json_encode(['error' => 'host_ip required']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM host_alert_overrides WHERE host_ip = ?");
        $stmt->execute([$hostIp]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($override ?: []);
        break;
        
    case 'save_host_override':
        $hostIp = $input['host_ip'] ?? null;
        if (!$hostIp) {
            echo json_encode(['error' => 'host_ip required']);
            break;
        }
        
        // Check if override exists
        $stmt = $pdo->prepare("SELECT id FROM host_alert_overrides WHERE host_ip = ?");
        $stmt->execute([$hostIp]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $sql = "UPDATE host_alert_overrides SET 
                    host_name = ?, enabled = ?,
                    cpu_warning = ?, cpu_critical = ?,
                    memory_warning = ?, memory_critical = ?,
                    disk_warning = ?, disk_critical = ?,
                    gpu_warning = ?, gpu_critical = ?,
                    status_delay_seconds = ?,
                    updated_at = NOW()
                    WHERE host_ip = ?";
            $pdo->prepare($sql)->execute([
                $input['host_name'] ?? $hostIp,
                !empty($input['enabled']) ? 1 : 0,
                $input['cpu_warning'] ?? 80, $input['cpu_critical'] ?? 95,
                $input['memory_warning'] ?? 80, $input['memory_critical'] ?? 95,
                $input['disk_warning'] ?? 85, $input['disk_critical'] ?? 95,
                $input['gpu_warning'] ?? 80, $input['gpu_critical'] ?? 95,
                isset($input['status_delay_seconds']) ? (int)$input['status_delay_seconds'] : null,
                $hostIp
            ]);
        } else {
            $sql = "INSERT INTO host_alert_overrides 
                    (host_ip, host_name, enabled, cpu_warning, cpu_critical, memory_warning, memory_critical, disk_warning, disk_critical, gpu_warning, gpu_critical, status_delay_seconds)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $hostIp,
                $input['host_name'] ?? $hostIp,
                !empty($input['enabled']) ? 1 : 0,
                $input['cpu_warning'] ?? 80, $input['cpu_critical'] ?? 95,
                $input['memory_warning'] ?? 80, $input['memory_critical'] ?? 95,
                $input['disk_warning'] ?? 85, $input['disk_critical'] ?? 95,
                $input['gpu_warning'] ?? 80, $input['gpu_critical'] ?? 95,
                isset($input['status_delay_seconds']) ? (int)$input['status_delay_seconds'] : null
            ]);
        }
        echo json_encode(['success' => true]);
        break;
        
    case 'delete_host_override':
        $hostIp = $input['host_ip'] ?? null;
        if (!$hostIp) {
            echo json_encode(['error' => 'host_ip required']);
            break;
        }
        
        $stmt = $pdo->prepare("DELETE FROM host_alert_overrides WHERE host_ip = ?");
        $stmt->execute([$hostIp]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        break;
        
    case 'get_all_host_overrides':
        $stmt = $pdo->query("SELECT * FROM host_alert_overrides ORDER BY host_ip");
        $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($overrides);
        break;

    case 'export_host_overrides':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="host_alert_overrides.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'host_ip', 'host_name', 'enabled',
            'cpu_warning', 'cpu_critical',
            'memory_warning', 'memory_critical',
            'disk_warning', 'disk_critical',
            'gpu_warning', 'gpu_critical',
            'status_delay_seconds'
        ]);

        $stmt = $pdo->query("SELECT host_ip, host_name, enabled, cpu_warning, cpu_critical, memory_warning, memory_critical, disk_warning, disk_critical, gpu_warning, gpu_critical, status_delay_seconds FROM host_alert_overrides ORDER BY host_ip");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    case 'import_host_overrides':
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'CSV file upload failed']);
            break;
        }

        $filePath = $_FILES['file']['tmp_name'];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            echo json_encode(['error' => 'Unable to read uploaded CSV']);
            break;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            echo json_encode(['error' => 'Empty CSV file']);
            break;
        }

        $map = [];
        foreach ($header as $index => $name) {
            $normalized = strtolower(trim($name));
            $map[$normalized] = $index;
        }

        $requiredColumns = ['host_ip'];
        foreach ($requiredColumns as $col) {
            if (!isset($map[$col])) {
                fclose($handle);
                echo json_encode(['error' => "Missing required column: {$col}"]);
                break 2;
            }
        }

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $hostIp = trim($row[$map['host_ip']] ?? '');
            if ($hostIp === '') {
                continue;
            }

            $hostName = $map['host_name'] ?? null;
            $enabledCol = $map['enabled'] ?? null;

            $values = [
                'host_ip' => $hostIp,
                'host_name' => $hostName !== null ? trim($row[$hostName] ?? '') : $hostIp,
                'enabled' => $enabledCol !== null ? (int)in_array(strtolower(trim($row[$enabledCol] ?? '1')), ['1', 'true', 'yes', 'y']) : 1,
                'cpu_warning' => (int)($row[$map['cpu_warning']] ?? 80),
                'cpu_critical' => (int)($row[$map['cpu_critical']] ?? 95),
                'memory_warning' => (int)($row[$map['memory_warning']] ?? 80),
                'memory_critical' => (int)($row[$map['memory_critical']] ?? 95),
                'disk_warning' => (int)($row[$map['disk_warning']] ?? 85),
                'disk_critical' => (int)($row[$map['disk_critical']] ?? 95),
                'gpu_warning' => (int)($row[$map['gpu_warning']] ?? 80),
                'gpu_critical' => (int)($row[$map['gpu_critical']] ?? 95),
                'status_delay_seconds' => isset($map['status_delay_seconds']) && $row[$map['status_delay_seconds']] !== ''
                    ? (int)$row[$map['status_delay_seconds']]
                    : null,
            ];

            // Upsert similar to save_host_override
            $stmt = $pdo->prepare("SELECT id FROM host_alert_overrides WHERE host_ip = ?");
            $stmt->execute([$values['host_ip']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $sql = "UPDATE host_alert_overrides SET 
                        host_name = ?, enabled = ?,
                        cpu_warning = ?, cpu_critical = ?,
                        memory_warning = ?, memory_critical = ?,
                        disk_warning = ?, disk_critical = ?,
                        gpu_warning = ?, gpu_critical = ?,
                        status_delay_seconds = ?,
                        updated_at = NOW()
                        WHERE host_ip = ?";
                $pdo->prepare($sql)->execute([
                    $values['host_name'] ?: $values['host_ip'],
                    $values['enabled'],
                    $values['cpu_warning'], $values['cpu_critical'],
                    $values['memory_warning'], $values['memory_critical'],
                    $values['disk_warning'], $values['disk_critical'],
                    $values['gpu_warning'], $values['gpu_critical'],
                    $values['status_delay_seconds'],
                    $values['host_ip'],
                ]);
            } else {
                $sql = "INSERT INTO host_alert_overrides 
                        (host_ip, host_name, enabled, cpu_warning, cpu_critical, memory_warning, memory_critical, disk_warning, disk_critical, gpu_warning, gpu_critical, status_delay_seconds)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $values['host_ip'],
                    $values['host_name'] ?: $values['host_ip'],
                    $values['enabled'],
                    $values['cpu_warning'], $values['cpu_critical'],
                    $values['memory_warning'], $values['memory_critical'],
                    $values['disk_warning'], $values['disk_critical'],
                    $values['gpu_warning'], $values['gpu_critical'],
                    $values['status_delay_seconds'],
                ]);
            }

            $imported++;
        }

        fclose($handle);
        echo json_encode(['success' => true, 'imported' => $imported]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid metrics action']);
}
