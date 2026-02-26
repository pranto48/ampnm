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
        // Update last used timestamp
        $updateStmt = $pdo->prepare("UPDATE agent_tokens SET last_used_at = NOW() WHERE id = ?");
        $updateStmt->execute([$result['id']]);
        return $result;
    }
    return false;
}

/**
 * Find device by IP address
 */
function findDeviceByIp($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT id, name FROM devices WHERE ip = ? LIMIT 1");
    $stmt->execute([$ip]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Save host metrics to database
 */
function saveHostMetrics($pdo, $data, $deviceId = null) {
    $sql = "INSERT INTO host_metrics (
        device_id, host_name, host_ip, cpu_percent, memory_percent, 
        memory_total_gb, memory_free_gb, disk_percent, disk_total_gb, 
        disk_free_gb, network_in_mbps, network_out_mbps, gpu_percent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // Calculate disk percent if we have total and free
    $diskPercent = null;
    if (!empty($data['disk_total_gb']) && $data['disk_total_gb'] > 0) {
        $diskUsed = $data['disk_total_gb'] - ($data['disk_free_gb'] ?? 0);
        $diskPercent = round(($diskUsed / $data['disk_total_gb']) * 100, 2);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $deviceId,
        $data['host_name'] ?? 'Unknown',
        $data['host_ip'] ?? '0.0.0.0',
        $data['cpu_percent'] ?? null,
        $data['memory_percent'] ?? null,
        $data['memory_total_gb'] ?? null,
        $data['memory_free_gb'] ?? null,
        $diskPercent,
        $data['disk_total_gb'] ?? null,
        $data['disk_free_gb'] ?? null,
        $data['network_in_mbps'] ?? null,
        $data['network_out_mbps'] ?? null,
        $data['gpu_percent'] ?? null
    ]);
    
    return $pdo->lastInsertId();
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
        // Accept metrics from Windows agent
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        
        // Validate token
        $tokenInfo = validateAgentToken($pdo, $token);
        if (!$tokenInfo) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or missing agent token']);
            exit;
        }
        
        // Validate required fields
        if (empty($input['host_ip'])) {
            http_response_code(400);
            echo json_encode(['error' => 'host_ip is required']);
            exit;
        }
        
        // Try to find matching device
        $device = findDeviceByIp($pdo, $input['host_ip']);
        $deviceId = $device ? $device['id'] : null;
        
        // Save metrics
        $metricsId = saveHostMetrics($pdo, $input, $deviceId);
        
        // Check thresholds and send alerts if needed
        try {
            require_once __DIR__ . '/../../includes/host_alerts.php';
            $alertSystem = new HostAlertSystem($pdo);
            $alertSystem->checkAndAlert(
                $input['host_ip'],
                $input['host_name'] ?? $input['host_ip'],
                $input
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
            'device_matched' => $device ? $device['name'] : null
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
            $stmt = $pdo->prepare("SELECT * FROM host_metrics WHERE host_ip = ? ORDER BY created_at DESC LIMIT 1");
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
                SELECT id, cpu_percent, memory_percent, disk_percent, 
                       network_in_mbps, network_out_mbps, gpu_percent, created_at
                FROM host_metrics 
                WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$deviceId, $hours]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, cpu_percent, memory_percent, disk_percent, 
                       network_in_mbps, network_out_mbps, gpu_percent, created_at
                FROM host_metrics 
                WHERE host_ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$hostIp, $hours]);
        }
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($history);
        break;
        
    case 'get_all_hosts':
        // Get list of all monitored hosts with latest metrics, first registration time, and any per-host overrides
        $stmt = $pdo->query("
            SELECT hm.*, d.name as device_name, d.id as linked_device_id,
                   CASE WHEN hao.id IS NOT NULL AND hao.enabled = 1 THEN 1 ELSE 0 END as has_override,
                   hao.status_delay_seconds,
                   (SELECT MIN(created_at) FROM host_metrics WHERE host_ip = hm.host_ip) as first_seen_at
            FROM host_metrics hm
            LEFT JOIN devices d ON hm.device_id = d.id
            LEFT JOIN host_alert_overrides hao ON hm.host_ip = hao.host_ip
            WHERE hm.id IN (
                SELECT MAX(id) FROM host_metrics GROUP BY host_ip
            )
            ORDER BY hm.host_name
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
        
        $stmt = $pdo->prepare("INSERT INTO agent_tokens (token, name) VALUES (?, ?)");
        $stmt->execute([$token, $name]);
        
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
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
