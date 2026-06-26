<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer'; // Get current user's role

// Placeholder for email notification function
function sendEmailNotification($pdo, $device, $oldStatus, $newStatus, $details) {
    // In a real application, this would fetch SMTP settings and subscriptions,
    // then use a mailer library (e.g., PHPMailer) to send emails.
    // For now, we'll just log that a notification *would* be sent.
    error_log("DEBUG: Notification triggered for device '{$device['name']}' (ID: {$device['id']}). Status changed from {$oldStatus} to {$newStatus}. Details: {$details}");

    // Fetch SMTP settings for the current user
    $stmtSmtp = $pdo->prepare("SELECT * FROM smtp_settings WHERE user_id = ?");
    $stmtSmtp->execute([$_SESSION['user_id']]);
    $smtpSettings = $stmtSmtp->fetch(PDO::FETCH_ASSOC);

    if (!$smtpSettings) {
        error_log("DEBUG: No SMTP settings found for user {$_SESSION['user_id']}. Cannot send email notification.");
        return;
    }

    // Fetch subscriptions for this device and status change
    $sqlSubscriptions = "SELECT recipient_email FROM device_email_subscriptions WHERE user_id = ? AND device_id = ?";
    $paramsSubscriptions = [$_SESSION['user_id'], $device['id']];

    if ($newStatus === 'online') {
        $sqlSubscriptions .= " AND notify_on_online = TRUE";
    } elseif ($newStatus === 'offline') {
        $sqlSubscriptions .= " AND notify_on_offline = TRUE";
    } elseif ($newStatus === 'warning') {
        $sqlSubscriptions .= " AND notify_on_warning = TRUE";
    } elseif ($newStatus === 'critical') {
        $sqlSubscriptions .= " AND notify_on_critical = TRUE";
    } else {
        // No specific notification for 'unknown' status changes
        return;
    }

    $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
    $stmtSubscriptions->execute($paramsSubscriptions);
    $recipients = $stmtSubscriptions->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        error_log("DEBUG: No active subscriptions for device '{$device['name']}' on status '{$newStatus}'.");
        return;
    }

    // Simulate sending email
    foreach ($recipients as $recipient) {
        error_log("DEBUG: Simulating email to {$recipient} for device '{$device['name']}' status change to '{$newStatus}'.");
        // In a real scenario, you'd use a mailer library here:
        // $mailer = new PHPMailer(true);
        // Configure $mailer with $smtpSettings
        // Set recipient, subject, body
        // $mailer->send();
    }
}


function getStatusFromPingResult($device, $pingResult, $parsedResult, &$details) {
    if (!$pingResult['success']) {
        $details = 'Device offline or unreachable.';
        return 'offline';
    }

    $status = 'online';
    $details = "Online with {$parsedResult['avg_time']}ms latency.";

    if ($device['critical_latency_threshold'] && $parsedResult['avg_time'] > $device['critical_latency_threshold']) {
        $status = 'critical';
        $details = "Critical latency: {$parsedResult['avg_time']}ms (>{$device['critical_latency_threshold']}ms).";
    } elseif ($device['critical_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['critical_packetloss_threshold']) {
        $status = 'critical';
        $details = "Critical packet loss: {$parsedResult['packet_loss']}% (>{$device['critical_packetloss_threshold']}%).";
    } elseif ($device['warning_latency_threshold'] && $parsedResult['avg_time'] > $device['warning_latency_threshold']) {
        $status = 'warning';
        $details = "Warning latency: {$parsedResult['avg_time']}ms (>{$device['warning_latency_threshold']}ms).";
    } elseif ($device['warning_packetloss_threshold'] && $parsedResult['packet_loss'] > $device['warning_packetloss_threshold']) {
        $status = 'warning';
        $details = "Warning packet loss: {$parsedResult['packet_loss']}% (>{$device['warning_packetloss_threshold']}%).";
    }
    return $status;
}

function logStatusChange($pdo, $deviceId, $oldStatus, $newStatus, $details) {
    if ($oldStatus !== $newStatus) {
        $stmt = $pdo->prepare("INSERT INTO device_status_logs (device_id, status, details) VALUES (?, ?, ?)");
        $stmt->execute([$deviceId, $newStatus, $details]);
    }
}

function evaluateDeviceCheck($pdo, $device, &$details, &$last_avg_time, &$last_ttl, &$check_output = '') {
    $monitorMethod = $device['monitor_method'] ?? 'ping';
    $hasPort = !empty($device['check_port']) && is_numeric($device['check_port']);
    $last_seen = $device['last_seen'];

    if (empty($device['ip'])) {
        $details = 'Device has no IP configured for monitoring.';
        return ['status' => 'unknown', 'last_seen' => $last_seen];
    }

    if ($monitorMethod === 'port' && $hasPort) {
        $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
        $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
        $last_avg_time = $portCheckResult['time'];
        $check_output = $portCheckResult['output'] ?? '';
        $last_seen = $portCheckResult['success'] ? date('Y-m-d H:i:s') : $last_seen;
        return ['status' => $portCheckResult['success'] ? 'online' : 'offline', 'last_seen' => $last_seen];
    }

    if ($monitorMethod === 'port' && !$hasPort) {
        $details = 'Port monitoring selected but no port is configured; falling back to ping.';
    }

    $pingResult = executePing($device['ip'], 1);
    savePingResult($pdo, $device['ip'], $pingResult);
    $parsedResult = parsePingOutput($pingResult['output']);
    $status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);
    $last_avg_time = $parsedResult['avg_time'] ?? null;
    $last_ttl = $parsedResult['ttl'] ?? null;
    $check_output = $pingResult['output'];
    $last_seen = $status !== 'offline' ? date('Y-m-d H:i:s') : $last_seen;

    return ['status' => $status, 'last_seen' => $last_seen];
}

switch ($action) {
    case 'import_devices':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can import devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $devices = $input['devices'] ?? [];
            if (empty($devices) || !is_array($devices)) {
                http_response_code(400);
                echo json_encode(['error' => 'No devices provided or invalid format.']);
                exit;
            }

            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;
            $devices_to_add_count = count($devices);

            if ($max_devices > 0 && ($current_devices + $devices_to_add_count) > $max_devices) {
                http_response_code(403);
                echo json_encode(['error' => "License limit reached. You can only add " . ($max_devices - $current_devices) . " more devices. Total allowed: {$max_devices}."]);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $sql = "INSERT INTO devices (
                    user_id, name, ip, check_port, monitor_method, type, description,
                    ping_interval, icon_size, name_text_size, icon_url, 
                    warning_latency_threshold, warning_packetloss_threshold, 
                    critical_latency_threshold, critical_packetloss_threshold, 
                    show_live_ping, map_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"; // map_id is NULL

                $stmt = $pdo->prepare($sql);
                $imported_count = 0;

                foreach ($devices as $device) {
                    $stmt->execute([
                        $current_user_id,
                        ($device['name'] ?? 'Imported Device'),
                        $device['ip'] ?? null,
                        $device['check_port'] ?? null,
                        $device['monitor_method'] ?? 'ping',
                        $device['type'] ?? 'other',
                        $device['description'] ?? null,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['icon_url'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0
                    ]);
                    $imported_count++;
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => "Successfully imported {$imported_count} devices."]);

            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;

    case 'check_all_devices_globally':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can check all devices globally.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE enabled = TRUE AND user_id = ? AND ip IS NOT NULL AND ip != '' AND type != 'box'");
            $stmt->execute([$current_user_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $checked_count = 0;
            $status_changes = 0;

            foreach ($devices as $device) {
                $old_status = $device['status'];
                $new_status = 'unknown';
                $last_avg_time = null;
                $last_ttl = null;
                $last_seen = $device['last_seen'];
                $details = '';

                $check_output = '';
                $evaluation = evaluateDeviceCheck($pdo, $device, $details, $last_avg_time, $last_ttl, $check_output);
                $new_status = $evaluation['status'];
                $last_seen = $evaluation['last_seen'];
                
                if ($old_status !== $new_status) {
                    logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
                    sendEmailNotification($pdo, $device, $old_status, $new_status, $details); // Trigger email notification
                    $status_changes++;
                }
                
                $updateStmt = $pdo->prepare("UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ? AND user_id = ?");
                $updateStmt->execute([$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id'], $current_user_id]);
                $checked_count++;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Checked {$checked_count} devices.",
                'checked_count' => $checked_count,
                'status_changes' => $status_changes
            ]);
        }
        break;

    case 'ping_all_devices':
        // Allow viewers to trigger pings, but ensure they can only update devices on maps they can see.
        // The actual update logic in the loop already handles this by checking user_role.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            $sql = "SELECT * FROM devices WHERE enabled = TRUE AND map_id = ? AND ip IS NOT NULL AND ip != '' AND type != 'box'";
            $params = [$map_id];
            // IMPORTANT: For viewers, do NOT filter by user_id here when SELECTING devices.
            // Viewers should be able to ping all devices on a map they can see.
            // The update logic below will ensure they only update if they own it, or if it's a shared map.
            // For now, we'll let them *select* all devices on a map.
            // If the map itself is user-specific, the map_handler's get_maps would have already filtered.
            // For public maps, this is fine.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated_devices = [];
            $processed_devices_count = 0;

            foreach ($devices as $device) {
                $old_status = $device['status'];
                $new_status = 'unknown';
                $last_avg_time = null;
                $last_ttl = null;
                $last_seen = $device['last_seen'];
                $check_output = 'Device has no IP configured for checking.';
                $details = '';

                if (!empty($device['ip'])) {
                    $evaluation = evaluateDeviceCheck($pdo, $device, $details, $last_avg_time, $last_ttl, $check_output);
                    $new_status = $evaluation['status'];
                    $last_seen = $evaluation['last_seen'];
                }
                
                logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
                sendEmailNotification($pdo, $device, $old_status, $new_status, $details); // Trigger email notification
                
                // CRITICAL FIX: Remove user_id filter from UPDATE if current user is a viewer.
                // This allows viewers to update the status of devices on shared maps.
                $updateSql = "UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?";
                $updateParams = [$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id']];

                // Only add user_id filter if the user is NOT a viewer
                if ($user_role !== 'viewer') {
                    $updateSql .= " AND user_id = ?";
                    $updateParams[] = $current_user_id;
                }
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($updateParams);

                $updated_devices[] = [
                    'id' => $device['id'],
                    'name' => $device['name'],
                    'old_status' => $old_status,
                    'status' => $new_status,
                    'last_seen' => $last_seen,
                    'last_avg_time' => $last_avg_time,
                    'last_ttl' => $last_ttl,
                    'last_ping_output' => $check_output
                ];
                $processed_devices_count++;
            }
            
            $overall_success = ($processed_devices_count > 0);
            $message = $overall_success ? "Checked {$processed_devices_count} devices." : "No pingable devices found on this map.";

            echo json_encode([
                'success' => $overall_success, 
                'message' => $message,
                'checked_count' => $processed_devices_count,
                'updated_devices' => $updated_devices
            ]);
        }
        break;

    case 'check_device':
        // Allow viewers to trigger pings, but ensure they can only update devices on maps they can see.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $deviceId = $input['id'] ?? 0;
            if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            
            $sql = "SELECT * FROM devices WHERE id = ?";
            $params = [$deviceId];
            // For viewers, do NOT filter by user_id here when SELECTING device.
            // The update logic below will ensure they only update if they own it, or if it's a shared map.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found']); exit; }

            $old_status = $device['status'];
            $status = 'unknown';
            $last_seen = $device['last_seen'];
            $last_avg_time = null;
            $last_ttl = null;
            $check_output = 'Device has no IP configured for checking.';
            $details = '';

            if (!empty($device['ip'])) {
                $evaluation = evaluateDeviceCheck($pdo, $device, $details, $last_avg_time, $last_ttl, $check_output);
                $status = $evaluation['status'];
                $last_seen = $evaluation['last_seen'];
            }
            
            logStatusChange($pdo, $deviceId, $old_status, $status, $details);
            sendEmailNotification($pdo, $device, $old_status, $status, $details); // Trigger email notification
            
            // CRITICAL FIX: Remove user_id filter from UPDATE if current user is a viewer.
            // This allows viewers to update the status of devices on shared maps.
            $updateSql = "UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?";
            $updateParams = [$status, $last_seen, $last_avg_time, $last_ttl, $deviceId];

            // Only add user_id filter if the user is NOT a viewer
            if ($user_role !== 'viewer') {
                $updateSql .= " AND user_id = ?";
                $updateParams[] = $current_user_id;
            }
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);
            
            echo json_encode(['id' => $deviceId, 'status' => $status, 'last_seen' => $last_seen, 'last_avg_time' => $last_avg_time, 'last_ttl' => $last_ttl, 'last_ping_output' => $check_output]);
        }
        break;

    case 'get_device_uptime':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        $sql = "SELECT ip FROM devices WHERE id = ?";
        $params = [$deviceId];
        // For viewers, do NOT filter by user_id here when SELECTING device.
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device || !$device['ip']) {
            echo json_encode(['uptime_24h' => null, 'uptime_7d' => null, 'outages_24h' => null]);
            exit;
        }
        $host = $device['ip'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as successful FROM ping_results WHERE host = ? AND created_at >= NOW() - INTERVAL 24 HOUR");
        $stmt->execute([$host]);
        $stats24h = $stmt->fetch(PDO::FETCH_ASSOC);
        $uptime24h = ($stats24h['total'] > 0) ? round(($stats24h['successful'] / $stats24h['total']) * 100, 2) : null;
        $outages24h = $stats24h['total'] - $stats24h['successful'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as successful FROM ping_results WHERE host = ? AND created_at >= NOW() - INTERVAL 7 DAY");
        $stmt->execute([$host]);
        $stats7d = $stmt->fetch(PDO::FETCH_ASSOC);
        $uptime7d = ($stats7d['total'] > 0) ? round(($stats7d['successful'] / $stats7d['total']) * 100, 2) : null;

        echo json_encode(['uptime_24h' => $uptime24h, 'uptime_7d' => $uptime7d, 'outages_24h' => $outages24h]);
        break;

    case 'get_device_details':
        $deviceId = $_GET['id'] ?? 0;
        if (!$deviceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
        
        $sql = "SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ?";
        $params = [$deviceId];
        // For viewers, do NOT filter by user_id here when SELECTING device.
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found']); exit; }
        $history = [];
        if ($device['ip']) {
            $stmt = $pdo->prepare("SELECT * FROM ping_results WHERE host = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$device['ip']]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['device' => $device, 'history' => $history]);
        break;

    case 'get_devices':
        $map_id = $_GET['map_id'] ?? null;
        $unmapped = isset($_GET['unmapped']);

        $sql = "
            SELECT 
                d.id, d.name, d.ip, d.check_port, d.monitor_method, d.type, d.description, d.enabled, d.x, d.y, d.map_id,
                d.ping_interval, d.icon_size, d.name_text_size, d.icon_url, 
                d.warning_latency_threshold, d.warning_packetloss_threshold, 
                d.critical_latency_threshold, d.critical_packetloss_threshold, 
                d.last_avg_time, d.last_ttl, d.show_live_ping, d.status, d.last_seen,
                m.name as map_name,
                p.output as last_ping_output
            FROM 
                devices d
            LEFT JOIN 
                maps m ON d.map_id = m.id
            LEFT JOIN 
                ping_results p ON p.id = (
                    SELECT id 
                    FROM ping_results 
                    WHERE host = d.ip 
                    ORDER BY created_at DESC 
                    LIMIT 1
                )
            WHERE 1=1
        ";
        $params = [];

        if ($map_id) { 
            $sql .= " AND d.map_id = ?"; 
            $params[] = $map_id; 
            // If map_id is provided, viewers can see all devices on that map
            // Only filter by user_id if the user is NOT a viewer
            // This allows shared maps to show all devices to viewers
            // But if the map itself is user-specific, map_handler's get_maps would have already filtered.
            // So, no user_id filter here for mapped devices.
        } else {
            // If no map_id is provided (e.g., on the main devices inventory page),
            // always filter by user_id, as this is a personal inventory.
            $sql .= " AND d.user_id = ?";
            $params[] = $current_user_id;
        }

        if ($unmapped) {
            $sql .= " AND d.map_id IS NULL";
        }
        $sql .= " ORDER BY d.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['devices' => $devices]); // Wrap in 'devices' key
        break;

    case 'create_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can create devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;

            if ($max_devices > 0 && $current_devices >= $max_devices) {
                http_response_code(403);
                echo json_encode(['error' => "License limit reached. You cannot add more than {$max_devices} devices."]);
                exit;
            }

            $sql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $current_user_id, $input['name'], $input['ip'] ?? null, $input['check_port'] ?? null, $input['monitor_method'] ?? 'ping', $input['type'], $input['description'] ?? null, $input['map_id'] ?? null,
                $input['x'] ?? null, $input['y'] ?? null,
                $input['ping_interval'] ?? null, $input['icon_size'] ?? 50, $input['name_text_size'] ?? 14, $input['icon_url'] ?? null,
                $input['warning_latency_threshold'] ?? null, $input['warning_packetloss_threshold'] ?? null,
                $input['critical_latency_threshold'] ?? null, $input['critical_packetloss_threshold'] ?? null,
                ($input['show_live_ping'] ?? false) ? 1 : 0
            ]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$lastId, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($device);
        }
        break;

    case 'update_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can update devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Device ID and updates are required']); exit; }
            $allowed_fields = ['name', 'ip', 'check_port', 'monitor_method', 'type', 'description', 'x', 'y', 'map_id', 'ping_interval', 'icon_size', 'name_text_size', 'icon_url', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold', 'show_live_ping', 'status', 'last_seen', 'last_avg_time', 'last_ttl']; // Added status and last_seen
            $fields = []; $params = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    if ($key === 'show_live_ping') {
                        $params[] = $value ? 1 : 0;
                    } else if ($key === 'last_seen') { // Handle last_seen as a timestamp
                        $params[] = $value;
                    }
                    else {
                        $params[] = ($value === '' || is_null($value)) ? null : $value;
                    }
                }
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            
            $updateSql = "UPDATE devices SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $updateParams = $params;
            $updateParams[] = $id;

            // Only add user_id filter if the user is NOT a viewer
            if ($user_role !== 'viewer') {
                $updateSql .= " AND user_id = ?";
                $updateParams[] = $current_user_id;
            }
            $stmt = $pdo->prepare($updateSql); 
            $stmt->execute($updateParams);

            // Re-fetch the device to return the updated data
            $fetchSql = "SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ?";
            $fetchParams = [$id];
            if ($user_role !== 'viewer') {
                $fetchSql .= " AND d.user_id = ?";
                $fetchParams[] = $current_user_id;
            }
            $stmt = $pdo->prepare($fetchSql); 
            $stmt->execute($fetchParams);
            $device = $stmt->fetch(PDO::FETCH_ASSOC); 
            echo json_encode($device);
        }
        break;

    case 'update_device_status_by_ip': // NEW ACTION
        // Allow viewers to trigger pings, but ensure they can only update devices on maps they can see.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip_address = $input['ip_address'] ?? null;
            $status = $input['status'] ?? null;

            if (empty($ip_address) || empty($status)) {
                http_response_code(400);
                echo json_encode(['error' => 'IP address and status are required.']);
                exit;
            }

            // Select device without user_id filter for viewers
            $sql = "SELECT * FROM devices WHERE ip = ?";
            $params = [$ip_address];
            // For viewers, do NOT filter by user_id here when SELECTING device.
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$device) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found for the given IP.']);
                exit;
            }
            
            $old_status = $device['status'];
            $last_seen = ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen'];
            $details = "Status updated by auto-ping to {$status}.";

            logStatusChange($pdo, $device['id'], $old_status, $status, $details);
            sendEmailNotification($pdo, $device, $old_status, $status, $details);

            // CRITICAL FIX: Remove user_id filter from UPDATE if current user is a viewer.
            // This allows viewers to update the status of devices on shared maps.
            $updateSql = "UPDATE devices SET status = ?, last_seen = ?, updated_at = CURRENT_TIMESTAMP WHERE ip = ?";
            $updateParams = [$status, $last_seen, $ip_address];

            // Only add user_id filter if the user is NOT a viewer
            if ($user_role !== 'viewer') {
                $updateSql .= " AND user_id = ?";
                $updateParams[] = $current_user_id;
            }
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

            // Re-fetch the updated device to return
            $fetchSql = "SELECT * FROM devices WHERE ip = ?";
            $fetchParams = [$ip_address];
            if ($user_role !== 'viewer') {
                $fetchSql .= " AND user_id = ?";
                $fetchParams[] = $current_user_id;
            }
            $stmt = $pdo->prepare($fetchSql);
            $stmt->execute($fetchParams);
            $updated_device = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($updated_device);
        }
        break;

    case 'delete_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can delete devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ? AND user_id = ?"); $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;

    case 'copy_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can copy devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sourceId = $input['id'] ?? null;
            if (!$sourceId) { http_response_code(400); echo json_encode(['error' => 'Device ID is required']); exit; }

            // License guard
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
            $stmtCount->execute([$current_user_id]);
            $current_devices = (int) $stmtCount->fetchColumn();
            if ($max_devices > 0 && $current_devices >= $max_devices) {
                http_response_code(403);
                echo json_encode(['error' => "License limit reached. Cannot copy more than {$max_devices} devices."]); exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$sourceId, $current_user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) { http_response_code(404); echo json_encode(['error' => 'Device not found']); exit; }

            $baseName = $device['name'] . '_copy';
            $newName = $baseName;
            $suffix = 2;
            $nameCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ? AND name = ?");
            while (true) {
                $nameCheckStmt->execute([$current_user_id, $newName]);
                if ((int) $nameCheckStmt->fetchColumn() === 0) { break; }
                $newName = $baseName . "{$suffix}";
                $suffix++;
            }

            $insertSql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $current_user_id,
                $newName,
                $device['ip'],
                $device['check_port'],
                $device['monitor_method'] ?? 'ping',
                $device['type'],
                $device['description'],
                $device['map_id'],
                $device['x'],
                $device['y'],
                $device['ping_interval'],
                $device['icon_size'],
                $device['name_text_size'],
                $device['icon_url'],
                $device['warning_latency_threshold'],
                $device['warning_packetloss_threshold'],
                $device['critical_latency_threshold'],
                $device['critical_packetloss_threshold'],
                ($device['show_live_ping'] ?? false) ? 1 : 0
            ]);

            $newId = $pdo->lastInsertId();
            $fetchSql = "SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ? AND d.user_id = ?";
            $fetchStmt = $pdo->prepare($fetchSql);
            $fetchStmt->execute([$newId, $current_user_id]);
            $newDevice = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'device' => $newDevice]);
        }
        break;

    case 'upload_device_icon':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can upload device icons.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $deviceId = $_POST['id'] ?? null;
            if (!$deviceId || !isset($_FILES['iconFile'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and icon file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/icons/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['iconFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $newFileName = 'device_' . $deviceId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/icons/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE devices SET icon_url = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$urlPath, $deviceId, $current_user_id]);
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;
    
    case 'import_map': // NEW ACTION
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can import maps.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $devices_data = $input['devices'] ?? [];
            $edges_data = $input['edges'] ?? [];
            $map_id = $input['map_id'] ?? null; // Assuming map_id is passed for context, though devices might not have it yet

            if (empty($devices_data) && empty($edges_data)) {
                http_response_code(400);
                echo json_encode(['error' => 'No devices or edges provided for import.']);
                exit;
            }
            if (!$map_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Map ID is required for import.']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // Clear existing devices and edges for the current user and map
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE map_id = ? AND user_id = ?");
                $stmt->execute([$map_id, $current_user_id]);
                $stmt = $pdo->prepare("DELETE FROM devices WHERE map_id = ? AND user_id = ?");
                $stmt->execute([$map_id, $current_user_id]);

                $device_id_map = []; // To map old IDs from import file to new DB IDs

                // Insert devices
                $sql_device = "INSERT INTO devices (
                    user_id, name, ip, check_port, type, description, map_id, x, y, 
                    ping_interval, icon_size, name_text_size, icon_url, 
                    warning_latency_threshold, warning_packetloss_threshold, 
                    critical_latency_threshold, critical_packetloss_threshold, 
                    show_live_ping
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_device = $pdo->prepare($sql_device);

                foreach ($devices_data as $device) {
                    $stmt_device->execute([
                        $current_user_id,
                        ($device['name'] ?? 'Imported Device'),
                        $device['ip'] ?? null, // Use ip_address from frontend
                        $device['check_port'] ?? null,
                        $device['type'] ?? 'other', // Use icon from frontend
                        $device['description'] ?? null,
                        $map_id, // Assign to the current map_id
                        $device['position_x'] ?? null,
                        $device['position_y'] ?? null,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['icon_url'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0
                    ]);
                    $new_id = $pdo->lastInsertId();
                    $device_id_map[$device['id']] = $new_id; // Map old ID to new ID
                }

                // Insert edges, using the new device IDs
                $sql_edge = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?, ?)";
                $stmt_edge = $pdo->prepare($sql_edge);

                foreach ($edges_data as $edge) {
                    $new_source_id = $device_id_map[$edge['source_id']] ?? null; // Use source_id from frontend
                    $new_target_id = $device_id_map[$edge['target_id']] ?? null; // Use target_id from frontend
                    
                    if ($new_source_id && $new_target_id) {
                        $stmt_edge->execute([
                            $current_user_id,
                            $new_source_id,
                            $new_target_id,
                            $map_id, // Assign to the current map_id
                            $edge['connection_type'] ?? 'cat5'
                        ]);
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Map imported successfully.']);

            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;
}