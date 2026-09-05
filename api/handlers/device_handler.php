<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer'; // Get current user's role
$current_group_user_ids = $GLOBALS['current_group_user_ids'] ?? [$current_user_id];
$groupIdsStr = implode(',', array_map('intval', $current_group_user_ids));
require_once __DIR__ . '/../../includes/smtp_mailer.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

function sendEmailNotification($pdo, $device, $oldStatus, $newStatus, $details) {
    if (!in_array($newStatus, ['online', 'offline', 'warning', 'critical'], true)) {
        return;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        error_log('Email notification skipped: invalid session user');
        return;
    }

    $stmtSmtp = $pdo->prepare("SELECT * FROM smtp_settings WHERE user_id = ?");
    $stmtSmtp->execute([$userId]);
    $smtpSettings = $stmtSmtp->fetch(PDO::FETCH_ASSOC);

    if (!$smtpSettings) {
        error_log("Email notification skipped: no SMTP settings for user {$userId}.");
        return;
    }

    $sqlSubscriptions = "SELECT recipient_email FROM device_email_subscriptions WHERE user_id = ? AND device_id = ?";
    $paramsSubscriptions = [$userId, $device['id']];

    if ($newStatus === 'online') {
        $sqlSubscriptions .= " AND notify_on_online = TRUE";
    } elseif ($newStatus === 'offline') {
        $sqlSubscriptions .= " AND notify_on_offline = TRUE";
    } elseif ($newStatus === 'warning') {
        $sqlSubscriptions .= " AND notify_on_warning = TRUE";
    } elseif ($newStatus === 'critical') {
        $sqlSubscriptions .= " AND notify_on_critical = TRUE";
    }

    $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
    $stmtSubscriptions->execute($paramsSubscriptions);
    $recipients = $stmtSubscriptions->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        error_log("Email notification skipped: no active subscriptions for device '{$device['name']}' status '{$newStatus}'.");
        return;
    }

    $subject = sprintf('Alert: %s is %s', $device['name'], strtoupper($newStatus));
    $body = "Device: {$device['name']}\n"
        . "IP: " . ($device['ip'] ?? 'N/A') . "\n"
        . "Previous Status: {$oldStatus}\n"
        . "Current Status: {$newStatus}\n"
        . "Details: {$details}\n"
        . "Time (UTC): " . gmdate('Y-m-d H:i:s') . "\n";

    foreach ($recipients as $recipient) {
        $smtpError = null;
        $sent = smtp_send_mail($smtpSettings, $recipient, $subject, $body, $smtpError);
        if (!$sent) {
            error_log("Email send failed for {$recipient} (device {$device['name']}, status {$newStatus}): " . ($smtpError ?? 'Unknown SMTP error'));
        }
    }
}

function sendSMSNotification($pdo, $device, $oldStatus, $newStatus, $details) {
    if (!in_array($newStatus, ['online', 'offline', 'warning', 'critical'], true)) {
        return;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        error_log('SMS notification skipped: invalid session user');
        return;
    }

    // Load SMS settings to verify if enabled and cooldown is respected
    $stmt = $pdo->prepare("SELECT * FROM sms_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $smsSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no settings in DB, try to fallback to environment variables
    $enabled = $smsSettings ? (bool)$smsSettings['enabled'] : (getenv('SMS_ALERTS_ENABLED') !== '0');
    $cooldownMinutes = (int)($smsSettings ? $smsSettings['cooldown_minutes'] : (getenv('SMS_COOLDOWN_MINUTES') ?: 30));

    if (!$enabled) {
        return;
    }

    // Cooldown verification to avoid spamming SMS alerts
    if ($cooldownMinutes > 0) {
        // Find if there is a status log for this device and status that was logged within the cooldown period
        $stmtCooldown = $pdo->prepare("
            SELECT COUNT(*) 
            FROM device_status_logs 
            WHERE device_id = ? AND status = ? AND created_at >= NOW() - INTERVAL ? MINUTE
        ");
        $stmtCooldown->execute([$device['id'], $newStatus, $cooldownMinutes]);
        // Note: logStatusChange is called before sendSMSNotification. So there will be exactly 1 log (the current one).
        // If count > 1, it means there was another status log for this device and status within the cooldown period.
        if ((int)$stmtCooldown->fetchColumn() > 1) {
            error_log("SMS notification skipped: Cooldown active for device '{$device['name']}' status '{$newStatus}'.");
            return;
        }
    }

    // Fetch active SMS subscriptions for this device
    $sqlSubscriptions = "SELECT recipient_phone FROM device_sms_subscriptions WHERE user_id = ? AND device_id = ?";
    $paramsSubscriptions = [$userId, $device['id']];

    if ($newStatus === 'online') {
        $sqlSubscriptions .= " AND notify_on_online = TRUE";
    } elseif ($newStatus === 'offline') {
        $sqlSubscriptions .= " AND notify_on_offline = TRUE";
    } elseif ($newStatus === 'warning') {
        $sqlSubscriptions .= " AND notify_on_warning = TRUE";
    } elseif ($newStatus === 'critical') {
        $sqlSubscriptions .= " AND notify_on_critical = TRUE";
    }

    $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
    $stmtSubscriptions->execute($paramsSubscriptions);
    $recipients = $stmtSubscriptions->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        return;
    }

    // Format a concise SMS body (SMS length constraints apply)
    $smsBody = sprintf("ALERT: %s is %s. %s", $device['name'], strtoupper($newStatus), $details);

    require_once __DIR__ . '/../../includes/sms_sender.php';
    foreach ($recipients as $recipient) {
        $smsError = null;
        $sent = sms_send_alert($recipient, $smsBody, $smsError);
        if (!$sent) {
            error_log("SMS send failed for {$recipient} (device {$device['name']}, status {$newStatus}): " . ($smsError ?? 'Unknown error'));
        }
    }
}

function sendTelegramNotification($pdo, $device, $oldStatus, $newStatus, $details) {
    if (!in_array($newStatus, ['online', 'offline', 'warning', 'critical'], true)) {
        return;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        error_log('Telegram notification skipped: invalid session user');
        return;
    }

    $stmt = $pdo->prepare("SELECT bot_token, enabled FROM telegram_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings || empty($settings['bot_token']) || !$settings['enabled']) {
        return;
    }

    $sqlSubscriptions = "SELECT chat_id FROM device_telegram_subscriptions WHERE user_id = ? AND device_id = ?";
    $paramsSubscriptions = [$userId, $device['id']];

    if ($newStatus === 'online') {
        $sqlSubscriptions .= " AND notify_on_online = TRUE";
    } elseif ($newStatus === 'offline') {
        $sqlSubscriptions .= " AND notify_on_offline = TRUE";
    } elseif ($newStatus === 'warning') {
        $sqlSubscriptions .= " AND notify_on_warning = TRUE";
    } elseif ($newStatus === 'critical') {
        $sqlSubscriptions .= " AND notify_on_critical = TRUE";
    }

    $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
    $stmtSubscriptions->execute($paramsSubscriptions);
    $recipients = $stmtSubscriptions->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        return;
    }

    $emoji = '⚪';
    switch ($newStatus) {
        case 'online': $emoji = '🟢'; break;
        case 'offline': $emoji = '🔴'; break;
        case 'warning': $emoji = '🟡'; break;
        case 'critical': $emoji = '🚨'; break;
    }

    $body = sprintf("⚠️ <b>ALERT: %s is %s</b>\n\n"
        . "Device: %s\n"
        . "IP: %s\n"
        . "Previous Status: %s\n"
        . "Current Status: %s %s\n"
        . "Details: %s\n"
        . "Time (UTC): %s",
        htmlspecialchars($device['name']),
        strtoupper($newStatus),
        htmlspecialchars($device['name']),
        htmlspecialchars($device['ip'] ?? 'N/A'),
        htmlspecialchars($oldStatus),
        $emoji,
        htmlspecialchars($newStatus),
        htmlspecialchars($details),
        gmdate('Y-m-d H:i:s')
    );

    require_once __DIR__ . '/../../includes/telegram_bot.php';
    foreach ($recipients as $recipient) {
        $err = null;
        $sent = telegram_send_alert($recipient, $body, $settings['bot_token'], $err);
        if (!$sent) {
            error_log("Telegram send failed for chat {$recipient} (device {$device['name']}): " . ($err ?? 'Unknown error'));
        }
    }
}

function sendWhatsappNotification($pdo, $device, $oldStatus, $newStatus, $details) {
    if (!in_array($newStatus, ['online', 'offline', 'warning', 'critical'], true)) {
        return;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        error_log('WhatsApp notification skipped: invalid session user');
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings || empty($settings['token']) || !$settings['enabled']) {
        return;
    }

    $cooldownMinutes = (int)($settings['cooldown_minutes'] ?? 30);
    if ($cooldownMinutes > 0) {
        $stmtCooldown = $pdo->prepare("
            SELECT COUNT(*) 
            FROM device_status_logs 
            WHERE device_id = ? AND status = ? AND created_at >= NOW() - INTERVAL ? MINUTE
        ");
        $stmtCooldown->execute([$device['id'], $newStatus, $cooldownMinutes]);
        if ((int)$stmtCooldown->fetchColumn() > 1) {
            error_log("WhatsApp notification skipped: Cooldown active for device '{$device['name']}' status '{$newStatus}'.");
            return;
        }
    }

    $sqlSubscriptions = "SELECT recipient_phone FROM device_whatsapp_subscriptions WHERE user_id = ? AND device_id = ?";
    $paramsSubscriptions = [$userId, $device['id']];

    if ($newStatus === 'online') {
        $sqlSubscriptions .= " AND notify_on_online = TRUE";
    } elseif ($newStatus === 'offline') {
        $sqlSubscriptions .= " AND notify_on_offline = TRUE";
    } elseif ($newStatus === 'warning') {
        $sqlSubscriptions .= " AND notify_on_warning = TRUE";
    } elseif ($newStatus === 'critical') {
        $sqlSubscriptions .= " AND notify_on_critical = TRUE";
    }

    $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
    $stmtSubscriptions->execute($paramsSubscriptions);
    $recipients = $stmtSubscriptions->fetchAll(PDO::FETCH_COLUMN);

    if (empty($recipients)) {
        return;
    }

    $emoji = '⚪';
    switch ($newStatus) {
        case 'online': $emoji = '🟢'; break;
        case 'offline': $emoji = '🔴'; break;
        case 'warning': $emoji = '🟡'; break;
        case 'critical': $emoji = '🚨'; break;
    }

    $body = sprintf("*ALERT: %s is %s*\n\n"
        . "Device: %s\n"
        . "IP: %s\n"
        . "Status: %s %s\n"
        . "Details: %s",
        $device['name'],
        strtoupper($newStatus),
        $device['name'],
        $device['ip'] ?? 'N/A',
        $emoji,
        $newStatus,
        $details
    );

    require_once __DIR__ . '/../../includes/whatsapp_bot.php';
    foreach ($recipients as $recipient) {
        $err = null;
        $sent = whatsapp_send_alert($recipient, $body, $settings, $err);
        if (!$sent) {
            error_log("WhatsApp send failed for phone {$recipient} (device {$device['name']}): " . ($err ?? 'Unknown error'));
        }
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

function getFreshAgentMetrics(PDO $pdo, int $deviceId, int $freshnessSeconds = 180): ?array {
    static $hasDeviceIdColumn = null;
    if ($hasDeviceIdColumn === null) {
        $columns = $pdo->query("SHOW COLUMNS FROM host_metrics")->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasDeviceIdColumn = in_array('device_id', $columns, true);
    }
    if (!$hasDeviceIdColumn) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(last_seen, created_at) AS seen_at,
            COALESCE(cpu_percent, cpu_usage) AS cpu_percent,
            COALESCE(memory_percent, memory_usage) AS memory_percent,
            COALESCE(disk_percent, disk_usage) AS disk_percent,
            COALESCE(gpu_percent, gpu_usage) AS gpu_percent
        FROM host_metrics
        WHERE device_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$deviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['seen_at'])) {
        return null;
    }

    $seenTs = strtotime((string)$row['seen_at']);
    if (!$seenTs) {
        return null;
    }

    $isFresh = (time() - $seenTs) <= $freshnessSeconds;
    $row['is_fresh'] = $isFresh;
    return $row;
}

function checkDevicesInParallel(PDO $pdo, array $devices, string $userRole, int $currentUserId): array {
    $results = [];
    $pingDevices = [];
    $pingIps = [];

    // First pass: identify agent telemetry and port-monitored devices
    foreach ($devices as $device) {
        $deviceId = isset($device['id']) ? (int)$device['id'] : 0;
        $monitorMethod = $device['monitor_method'] ?? 'ping';
        $hasPort = !empty($device['check_port']) && is_numeric($device['check_port']);

        // 1. Agent Telemetry Check
        if ($deviceId > 0) {
            $agentMetrics = getFreshAgentMetrics($pdo, $deviceId);
            if ($agentMetrics && !empty($agentMetrics['is_fresh'])) {
                $last_seen = date('Y-m-d H:i:s');
                $details = sprintf(
                    'Agent telemetry active (CPU %s%%, RAM %s%%, Disk %s%%, GPU %s%%).',
                    round((float)($agentMetrics['cpu_percent'] ?? 0), 1),
                    round((float)($agentMetrics['memory_percent'] ?? 0), 1),
                    round((float)($agentMetrics['disk_percent'] ?? 0), 1),
                    round((float)($agentMetrics['gpu_percent'] ?? 0), 1)
                );
                $results[$device['id']] = [
                    'status' => 'online',
                    'last_seen' => $last_seen,
                    'last_avg_time' => null,
                    'last_ttl' => null,
                    'check_output' => 'Agent active',
                    'details' => $details
                ];
                continue;
            }
        }

        // 2. No IP check
        if (empty($device['ip'])) {
            $results[$device['id']] = [
                'status' => 'unknown',
                'last_seen' => $device['last_seen'],
                'last_avg_time' => null,
                'last_ttl' => null,
                'check_output' => 'Device has no IP configured.',
                'details' => 'Device has no IP configured.'
            ];
            continue;
        }

        // 3. Port check
        if ($monitorMethod === 'port' && $hasPort) {
            $portCheckResult = checkPortStatus($device['ip'], $device['check_port']);
            $details = $portCheckResult['success'] ? "Port {$device['check_port']} is open." : "Port {$device['check_port']} is closed.";
            $results[$device['id']] = [
                'status' => $portCheckResult['success'] ? 'online' : 'offline',
                'last_seen' => $portCheckResult['success'] ? date('Y-m-d H:i:s') : $device['last_seen'],
                'last_avg_time' => $portCheckResult['time'],
                'last_ttl' => null,
                'check_output' => $portCheckResult['output'] ?? '',
                'details' => $details
            ];
            continue;
        }

        // Fallback or explicit Ping check
        $pingDevices[] = $device;
        $pingIps[] = $device['ip'];
    }

    // Second pass: perform parallel pinging
    if (count($pingIps) > 0) {
        $pingIps = array_unique($pingIps);
        $pingResults = pingMultiple($pingIps, 2, 1); // 2 packets, 1s timeout for accuracy and speed

        foreach ($pingDevices as $device) {
            $ip = $device['ip'];
            $pingResult = $pingResults[$ip] ?? [
                'success' => false,
                'output' => 'Parallel ping failed',
                'avg_time' => 0,
                'packet_loss' => 100,
                'ttl' => null
            ];

            // Save individual result
            savePingResult($pdo, $ip, $pingResult);

            $parsedResult = [
                'packet_loss' => $pingResult['packet_loss'],
                'avg_time' => $pingResult['avg_time'],
                'ttl' => $pingResult['ttl']
            ];

            $details = '';
            $status = getStatusFromPingResult($device, $pingResult, $parsedResult, $details);

            $results[$device['id']] = [
                'status' => $status,
                'last_seen' => $status !== 'offline' ? date('Y-m-d H:i:s') : $device['last_seen'],
                'last_avg_time' => $parsedResult['avg_time'] ?? null,
                'last_ttl' => $parsedResult['ttl'] ?? null,
                'check_output' => $pingResult['output'],
                'details' => $details
            ];
        }
    }

    // Third pass: perform update, log, notifications
    $status_changes = 0;
    $updated_list = [];

    foreach ($devices as $device) {
        if (!isset($results[$device['id']])) {
            continue;
        }
        $eval = $results[$device['id']];
        $old_status = $device['status'];
        $new_status = $eval['status'];
        $last_seen = $eval['last_seen'];
        $last_avg_time = $eval['last_avg_time'];
        $last_ttl = $eval['last_ttl'];
        $details = $eval['details'];

        if ($old_status !== $new_status) {
            logStatusChange($pdo, $device['id'], $old_status, $new_status, $details);
            sendEmailNotification($pdo, $device, $old_status, $new_status, $details);
            sendSMSNotification($pdo, $device, $old_status, $new_status, $details);
            sendTelegramNotification($pdo, $device, $old_status, $new_status, $details);
            sendWhatsappNotification($pdo, $device, $old_status, $new_status, $details);
            $status_changes++;
        }

        // Update database
        $updateSql = "UPDATE devices SET status = ?, last_seen = ?, last_avg_time = ?, last_ttl = ? WHERE id = ?";
        $updateParams = [$new_status, $last_seen, $last_avg_time, $last_ttl, $device['id']];

        if ($userRole !== 'viewer') {
            $updateSql .= " AND user_id = ?";
            $updateParams[] = $currentUserId;
        }

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($updateParams);

        $updated_list[] = [
            'id' => $device['id'],
            'name' => $device['name'],
            'old_status' => $old_status,
            'status' => $new_status,
            'last_seen' => $last_seen,
            'last_avg_time' => $last_avg_time,
            'last_ttl' => $last_ttl,
            'last_ping_output' => $eval['check_output']
        ];
    }

    return [
        'checked_count' => count($devices),
        'status_changes' => $status_changes,
        'updated_devices' => $updated_list
    ];
}

function evaluateDeviceCheck($pdo, $device, &$details, &$last_avg_time, &$last_ttl, &$check_output = '') {
    $monitorMethod = $device['monitor_method'] ?? 'ping';
    $hasPort = !empty($device['check_port']) && is_numeric($device['check_port']);
    $last_seen = $device['last_seen'];
    $deviceId = isset($device['id']) ? (int)$device['id'] : 0;

    if ($deviceId > 0) {
        $agentMetrics = getFreshAgentMetrics($pdo, $deviceId);
        if ($agentMetrics && !empty($agentMetrics['is_fresh'])) {
            $last_seen = date('Y-m-d H:i:s');
            $details = sprintf(
                'Agent telemetry active (CPU %s%%, RAM %s%%, Disk %s%%, GPU %s%%).',
                round((float)($agentMetrics['cpu_percent'] ?? 0), 1),
                round((float)($agentMetrics['memory_percent'] ?? 0), 1),
                round((float)($agentMetrics['disk_percent'] ?? 0), 1),
                round((float)($agentMetrics['gpu_percent'] ?? 0), 1)
            );
            return ['status' => 'online', 'last_seen' => $last_seen];
        }
    }

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
                    user_id, name, ip, check_port, monitor_method, type, subchoice, description,
                    ping_interval, icon_size, name_text_size, name_text_color, name_text_bold, name_text_italic, icon_url, router_api_username, router_api_password, router_api_port,
                    warning_latency_threshold, warning_packetloss_threshold,
                    critical_latency_threshold, critical_packetloss_threshold,
                    show_live_ping, port_config, map_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"; // map_id is NULL

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
                        $device['subchoice'] ?? 0,
                        $device['description'] ?? null,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['name_text_color'] ?? '#ffffff',
                        $device['name_text_bold'] ?? 0,
                        $device['name_text_italic'] ?? 0,
                        $device['icon_url'] ?? null,
                        $device['router_api_username'] ?? null,
                        $device['router_api_password'] ?? null,
                        $device['router_api_port'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0,
                        $device['port_config'] ?? null
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
            
            $res = checkDevicesInParallel($pdo, $devices, $user_role, (int)$current_user_id);
            
            echo json_encode([
                'success' => true, 
                'message' => "Checked {$res['checked_count']} devices.",
                'checked_count' => $res['checked_count'],
                'status_changes' => $res['status_changes']
            ]);
        }
        break;

    case 'ping_all_devices':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            $sql = "SELECT * FROM devices WHERE enabled = TRUE AND map_id = ? AND ip IS NOT NULL AND ip != '' AND type != 'box'";
            $params = [$map_id];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $res = checkDevicesInParallel($pdo, $devices, $user_role, (int)$current_user_id);
            
            echo json_encode([
                'success' => ($res['checked_count'] > 0), 
                'message' => ($res['checked_count'] > 0) ? "Checked {$res['checked_count']} devices." : "No pingable devices found on this map.",
                'checked_count' => $res['checked_count'],
                'updated_devices' => $res['updated_devices']
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
            sendSMSNotification($pdo, $device, $old_status, $status, $details); // Trigger SMS notification
            sendTelegramNotification($pdo, $device, $old_status, $status, $details); // Trigger Telegram
            sendWhatsappNotification($pdo, $device, $old_status, $status, $details); // Trigger WhatsApp
            
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
                d.id, d.name, d.ip, d.check_port, d.monitor_method, d.type, d.subchoice, d.description, d.enabled, d.x, d.y, d.map_id,
                d.ping_interval, d.icon_size, d.name_text_size, d.name_text_color, d.name_text_bold, d.name_text_italic, d.icon_url,
                d.router_api_username, d.router_api_password, d.router_api_port,
                d.warning_latency_threshold, d.warning_packetloss_threshold,
                d.critical_latency_threshold, d.critical_packetloss_threshold,
                d.last_avg_time, d.last_ttl, d.show_live_ping, d.status, d.last_seen,
                d.port_config,
                m.name as map_name,
                p.output as last_ping_output,
                hm.cpu_usage, hm.memory_usage, hm.disk_usage, hm.network_in, hm.network_out, hm.last_seen AS agent_last_seen
            FROM 
                devices d
            LEFT JOIN 
                maps m ON d.map_id = m.id
            LEFT JOIN 
                ping_results p ON (
                    d.ip IS NOT NULL AND d.ip != '' AND p.id = (
                        SELECT id 
                        FROM ping_results 
                        WHERE host = d.ip 
                        ORDER BY created_at DESC 
                        LIMIT 1
                    )
                )
            LEFT JOIN 
                host_metrics hm ON (
                    (d.ip IS NOT NULL AND d.ip != '' AND hm.ip_address = d.ip) OR 
                    (d.name IS NOT NULL AND d.name != '' AND hm.hostname = d.name)
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
            // Filter by user group user IDs
            $sql .= " AND d.user_id IN ($groupIdsStr)";
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

            // Check duplicate name (skip for text label nodes)
            if (($input['type'] ?? '') !== 'text') {
                $stmtDupName = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE LOWER(name) = LOWER(?) AND user_id IN ($groupIdsStr)");
                $stmtDupName->execute([$input['name']]);
                if ((int)$stmtDupName->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'A device with this name already exists in your group.']);
                    exit;
                }
            }

            // Check duplicate IP
            if (!empty($input['ip'])) {
                $stmtDupIp = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE ip = ? AND user_id IN ($groupIdsStr)");
                $stmtDupIp->execute([$input['ip']]);
                if ((int)$stmtDupIp->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'A device with this IP address already exists in your group.']);
                    exit;
                }
            }

            $sql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, subchoice, description, map_id, x, y, ping_interval, icon_size, name_text_size, name_text_color, name_text_bold, name_text_italic, icon_url, router_api_username, router_api_password, router_api_port, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping, port_config) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $portConfigValue = isset($input['port_config']) ? (is_string($input['port_config']) ? $input['port_config'] : json_encode($input['port_config'])) : null;
            $stmt->execute([
                $current_user_id, $input['name'], $input['ip'] ?? null, $input['check_port'] ?? null, $input['monitor_method'] ?? 'ping', $input['type'], $input['subchoice'] ?? 0, $input['description'] ?? null, $input['map_id'] ?? null,
                $input['x'] ?? null, $input['y'] ?? null,
                $input['ping_interval'] ?? null, $input['icon_size'] ?? 50, $input['name_text_size'] ?? 14, $input['name_text_color'] ?? '#ffffff', $input['name_text_bold'] ?? 0, $input['name_text_italic'] ?? 0, $input['icon_url'] ?? null,
                $input['router_api_username'] ?? null, $input['router_api_password'] ?? null, $input['router_api_port'] ?? null,
                $input['warning_latency_threshold'] ?? null, $input['warning_packetloss_threshold'] ?? null,
                $input['critical_latency_threshold'] ?? null, $input['critical_packetloss_threshold'] ?? null,
                ($input['show_live_ping'] ?? false) ? 1 : 0,
                $portConfigValue
            ]);
            $lastId = $pdo->lastInsertId();
            log_audit($pdo, 'create_device', 'device', $lastId, "Created device '{$input['name']}' (type: {$input['type']})");
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id IN ($groupIdsStr)");
            $stmt->execute([$lastId]);
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

            // Fetch current device to compare unchanged name and ip
            $stmtCurrent = $pdo->prepare("SELECT name, ip, type FROM devices WHERE id = ?");
            $stmtCurrent->execute([$id]);
            $currentDev = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

            // Check duplicates if name is updated and actually changed
            if (array_key_exists('name', $updates)) {
                $newName = trim((string)$updates['name']);
                $origName = trim((string)($currentDev['name'] ?? ''));
                $devType = $updates['type'] ?? ($currentDev['type'] ?? '');
                if ($devType !== 'text' && $devType !== 'box' && strcasecmp($newName, $origName) !== 0) {
                    $stmtDupName = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE LOWER(name) = LOWER(?) AND id != ? AND user_id IN ($groupIdsStr)");
                    $stmtDupName->execute([$newName, $id]);
                    if ((int)$stmtDupName->fetchColumn() > 0) {
                        http_response_code(400);
                        echo json_encode(['error' => 'A device with this name already exists in your group.']);
                        exit;
                    }
                }
            }
            if (array_key_exists('ip', $updates) && !empty($updates['ip'])) {
                $newIp = trim((string)$updates['ip']);
                $origIp = trim((string)($currentDev['ip'] ?? ''));
                if (strcasecmp($newIp, $origIp) !== 0) {
                    $stmtDupIp = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE ip = ? AND id != ? AND user_id IN ($groupIdsStr)");
                    $stmtDupIp->execute([$newIp, $id]);
                    if ((int)$stmtDupIp->fetchColumn() > 0) {
                        http_response_code(400);
                        echo json_encode(['error' => 'A device with this IP address already exists in your group.']);
                        exit;
                    }
                }
            }

            // Detect schema capability (fresh/old DB might miss devices.subchoice)
            $hasSubchoice = false;
            try {
                $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
                if ($dbName) {
                    $stmtCol = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                    $stmtCol->execute([$dbName, 'devices', 'subchoice']);
                    $hasSubchoice = ((int)$stmtCol->fetchColumn()) > 0;
                }
            } catch (Throwable $e) {
                $hasSubchoice = false;
            }

            if (!$hasSubchoice && array_key_exists('subchoice', $updates)) {
                http_response_code(400);
                echo json_encode([
                    'error' => "Database schema missing required column devices.subchoice. Fix by running: ALTER TABLE devices ADD COLUMN subchoice TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER type; (or run docker-ampnm/FIX_SUBCHOICE_COMPLETE.sql)"
                ]);
                exit;
            }

            $allowed_fields = ['name', 'ip', 'check_port', 'monitor_method', 'type', 'subchoice', 'description', 'x', 'y', 'map_id', 'target_map_id', 'is_rack', 'rack_units', 'rack_position', 'ping_interval', 'icon_size', 'name_text_size', 'name_text_color', 'name_text_bold', 'name_text_italic', 'icon_url', 'router_api_username', 'router_api_password', 'router_api_port', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold', 'show_live_ping', 'status', 'last_seen', 'last_avg_time', 'last_ttl', 'port_config', 'snmp_enabled', 'snmp_version', 'snmp_community', 'snmp_port', 'snmp_v3_user', 'snmp_v3_auth_proto', 'snmp_v3_auth_pass', 'snmp_v3_priv_proto', 'snmp_v3_priv_pass', 'snmp_v3_sec_level'];
            if (!$hasSubchoice) {
                $allowed_fields = array_values(array_diff($allowed_fields, ['subchoice']));
            }
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
            
            if ($user_role === 'admin') {
                $updateSql = "UPDATE devices SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $updateParams = $params;
                $updateParams[] = $id;

                $stmt = $pdo->prepare($updateSql); 
                $stmt->execute($updateParams);

                // Re-fetch the device to return the updated data
                $fetchSql = "SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ?";
                $fetchParams = [$id];
                $stmt = $pdo->prepare($fetchSql); 
                $stmt->execute($fetchParams);
                $device = $stmt->fetch(PDO::FETCH_ASSOC); 
                echo json_encode($device);
            } else {
                $updateSql = "UPDATE devices SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id IN ($groupIdsStr)";
                $updateParams = $params;
                $updateParams[] = $id;

                $stmt = $pdo->prepare($updateSql); 
                $stmt->execute($updateParams);

                // Re-fetch the device to return the updated data
                $fetchSql = "SELECT d.*, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.id = ? AND d.user_id IN ($groupIdsStr)";
                $fetchParams = [$id];
                $stmt = $pdo->prepare($fetchSql); 
                $stmt->execute($fetchParams);
                $device = $stmt->fetch(PDO::FETCH_ASSOC); 
                echo json_encode($device);
            }
        }
        break;

    case 'update_device_status_by_ip': // NEW ACTION
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip_address = $input['ip_address'] ?? null;
            $status = $input['status'] ?? null;

            if (empty($ip_address) || empty($status)) {
                http_response_code(400);
                echo json_encode(['error' => 'IP address and status are required.']);
                exit;
            }

            // Select device within the user group
            $sql = "SELECT * FROM devices WHERE ip = ? AND user_id IN ($groupIdsStr)";
            $params = [$ip_address];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$device) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found for the given IP in your group.']);
                exit;
            }
            
            $old_status = $device['status'];
            $last_seen = ($status === 'online') ? date('Y-m-d H:i:s') : $device['last_seen'];
            $details = "Status updated by auto-ping to {$status}.";

            logStatusChange($pdo, $device['id'], $old_status, $status, $details);
            sendEmailNotification($pdo, $device, $old_status, $status, $details);
            sendSMSNotification($pdo, $device, $old_status, $status, $details);
            sendTelegramNotification($pdo, $device, $old_status, $status, $details);
            sendWhatsappNotification($pdo, $device, $old_status, $status, $details);

            $updateSql = "UPDATE devices SET status = ?, last_seen = ?, updated_at = CURRENT_TIMESTAMP WHERE ip = ? AND user_id IN ($groupIdsStr)";
            $updateParams = [$status, $last_seen, $ip_address];
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

            // Re-fetch the updated device to return
            $fetchSql = "SELECT * FROM devices WHERE ip = ? AND user_id IN ($groupIdsStr)";
            $fetchParams = [$ip_address];
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
            log_audit($pdo, 'delete_device', 'device', $id, "Deleted device #{$id}");
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ? AND user_id IN ($groupIdsStr)"); $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        }
        break;

    case 'bulk_delete_devices':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can delete devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $input['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) { http_response_code(400); echo json_encode(['error' => 'No device IDs provided']); exit; }
            log_audit($pdo, 'bulk_delete_devices', 'device', null, "Bulk deleted devices: " . implode(',', $ids));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id IN ($in) AND user_id IN ($groupIdsStr)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => 'Devices deleted successfully']);
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

            $insertSql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, subchoice, port_config, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, router_api_username, router_api_password, router_api_port, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $current_user_id,
                $newName,
                $device['ip'],
                $device['check_port'],
                $device['monitor_method'] ?? 'ping',
                $device['type'],
                $device['subchoice'] ?? 0,
                $device['port_config'] ?? null,
                $device['description'],
                $device['map_id'],
                $device['x'],
                $device['y'],
                $device['ping_interval'],
                $device['icon_size'],
                $device['name_text_size'],
                $device['icon_url'],
                $device['router_api_username'] ?? null,
                $device['router_api_password'] ?? null,
                $device['router_api_port'] ?? null,
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
                    user_id, name, ip, check_port, monitor_method, type, subchoice, description, map_id, x, y,
                    ping_interval, icon_size, name_text_size, name_text_color, name_text_bold, name_text_italic, icon_url, 
                    router_api_username, router_api_password, router_api_port,
                    warning_latency_threshold, warning_packetloss_threshold,
                    critical_latency_threshold, critical_packetloss_threshold,
                    show_live_ping, port_config
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_device = $pdo->prepare($sql_device);

                foreach ($devices_data as $device) {
                    $stmt_device->execute([
                        $current_user_id,
                        ($device['name'] ?? 'Imported Device'),
                        $device['ip'] ?? null,
                        $device['check_port'] ?? null,
                        $device['monitor_method'] ?? 'ping',
                        $device['type'] ?? 'other',
                        $device['subchoice'] ?? 0,
                        $device['description'] ?? null,
                        $map_id,
                        $device['position_x'] ?? $device['x'] ?? null,
                        $device['position_y'] ?? $device['y'] ?? null,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['name_text_color'] ?? '#ffffff',
                        $device['name_text_bold'] ?? 0,
                        $device['name_text_italic'] ?? 0,
                        $device['icon_url'] ?? null,
                        $device['router_api_username'] ?? null,
                        $device['router_api_password'] ?? null,
                        $device['router_api_port'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0,
                        $device['port_config'] ?? null
                    ]);
                    $new_id = $pdo->lastInsertId();
                    $device_id_map[$device['id']] = $new_id;
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
                            $edge['connection_type'] ?? 'cat6'
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

    case 'test_snmp':
        require_once __DIR__ . '/../../includes/snmp_monitor.php';
        $devId = isset($input['device_id']) ? (int)$input['device_id'] : (isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0);
        $config = [];

        if ($devId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
            $stmt->execute([$devId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found']);
                exit;
            }
            $config = $device;
        } else {
            $config = [
                'ip' => $input['ip'] ?? $_GET['ip'] ?? '',
                'snmp_port' => $input['snmp_port'] ?? $_GET['snmp_port'] ?? 161,
                'snmp_version' => $input['snmp_version'] ?? $_GET['snmp_version'] ?? 'v2c',
                'snmp_community' => $input['snmp_community'] ?? $_GET['snmp_community'] ?? 'public',
                'snmp_v3_user' => $input['snmp_v3_user'] ?? null,
                'snmp_v3_auth_proto' => $input['snmp_v3_auth_proto'] ?? 'SHA',
                'snmp_v3_auth_pass' => $input['snmp_v3_auth_pass'] ?? null,
                'snmp_v3_priv_proto' => $input['snmp_v3_priv_proto'] ?? 'AES',
                'snmp_v3_priv_pass' => $input['snmp_v3_priv_pass'] ?? null,
                'snmp_v3_sec_level' => $input['snmp_v3_sec_level'] ?? 'authPriv',
            ];
        }

        if (empty($config['ip'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Device IP address is required for SNMP test.']);
            exit;
        }

        $snmp = new SNMPMonitor($config);
        $overview = $snmp->getSystemOverview();

        if (empty($overview['online'])) {
            echo json_encode([
                'success' => false,
                'message' => $overview['error'] ?? 'SNMP connection failed or timed out. Check IP, Port (161), and Community string.',
                'raw_config' => ['ip' => $config['ip'], 'port' => $config['snmp_port'], 'version' => $config['snmp_version']]
            ]);
            exit;
        }

        $interfaces = $snmp->getInterfaces();

        echo json_encode([
            'success' => true,
            'message' => 'SNMP connection successful!',
            'system' => $overview,
            'interfaces_count' => count($interfaces),
            'interfaces' => $interfaces
        ]);
        break;

    case 'poll_snmp':
        require_once __DIR__ . '/../../includes/snmp_monitor.php';
        $devId = isset($input['device_id']) ? (int)$input['device_id'] : (isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0);
        if ($devId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$devId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            http_response_code(404);
            echo json_encode(['error' => 'Device not found']);
            exit;
        }

        $snmp = new SNMPMonitor($device);
        $overview = $snmp->getSystemOverview();
        if (empty($overview['online'])) {
            echo json_encode(['success' => false, 'error' => $overview['error'] ?? 'Host unreachable']);
            exit;
        }

        $interfaces = $snmp->getInterfaces();
        $now = date('Y-m-d H:i:s');
        $nowTs = time();

        // Get prior interface readings to calculate delta bitrate
        $stmtOld = $pdo->prepare("SELECT if_index, if_in_octets, if_out_octets, UNIX_TIMESTAMP(last_poll_time) as old_time FROM device_snmp_interfaces WHERE device_id = ?");
        $stmtOld->execute([$devId]);
        $oldRows = [];
        while ($r = $stmtOld->fetch(PDO::FETCH_ASSOC)) {
            $oldRows[(int)$r['if_index']] = $r;
        }

        $upsertStmt = $pdo->prepare("INSERT INTO device_snmp_interfaces 
            (device_id, if_index, if_descr, if_alias, if_type, if_speed, if_mac, if_admin_status, if_oper_status, if_in_octets, if_out_octets, if_in_errors, if_out_errors, in_rate_bps, out_rate_bps, last_poll_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                if_descr = VALUES(if_descr),
                if_alias = VALUES(if_alias),
                if_type = VALUES(if_type),
                if_speed = VALUES(if_speed),
                if_mac = VALUES(if_mac),
                if_admin_status = VALUES(if_admin_status),
                if_oper_status = VALUES(if_oper_status),
                if_in_octets = VALUES(if_in_octets),
                if_out_octets = VALUES(if_out_octets),
                if_in_errors = VALUES(if_in_errors),
                if_out_errors = VALUES(if_out_errors),
                in_rate_bps = VALUES(in_rate_bps),
                out_rate_bps = VALUES(out_rate_bps),
                last_poll_time = VALUES(last_poll_time)");

        $histStmt = $pdo->prepare("INSERT INTO device_snmp_history 
            (device_id, if_index, in_rate_bps, out_rate_bps, cpu_usage, temperature, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $savedInterfaces = [];

        foreach ($interfaces as $if) {
            $idx = (int)$if['if_index'];
            $inRate = 0;
            $outRate = 0;

            if (isset($oldRows[$idx])) {
                $old = $oldRows[$idx];
                $deltaTime = $nowTs - (int)($old['old_time'] ?? 0);
                if ($deltaTime > 0 && $deltaTime < 3600) {
                    $deltaIn = (float)$if['in_octets'] - (float)$old['if_in_octets'];
                    $deltaOut = (float)$if['out_octets'] - (float)$old['if_out_octets'];
                    if ($deltaIn >= 0) $inRate = (int)(($deltaIn * 8) / $deltaTime);
                    if ($deltaOut >= 0) $outRate = (int)(($deltaOut * 8) / $deltaTime);
                }
            }

            $upsertStmt->execute([
                $devId,
                $idx,
                $if['if_descr'],
                $if['if_alias'],
                $if['if_type'],
                $if['if_speed'],
                $if['if_mac'],
                $if['if_admin_status'],
                $if['if_oper_status'],
                $if['in_octets'],
                $if['out_octets'],
                $if['in_errors'],
                $if['out_errors'],
                $inRate,
                $outRate,
                $now
            ]);

            // Save history snapshot if active or periodic
            $histStmt->execute([
                $devId,
                $idx,
                $inRate,
                $outRate,
                $overview['cpu_percent'],
                $overview['temperature'],
                $now
            ]);

            $if['in_rate_bps'] = $inRate;
            $if['out_rate_bps'] = $outRate;
            $if['in_rate_formatted'] = SNMPMonitor::formatBitrate($inRate);
            $if['out_rate_formatted'] = SNMPMonitor::formatBitrate($outRate);
            $savedInterfaces[] = $if;
        }

        // Update device system info
        $updDev = $pdo->prepare("UPDATE devices SET snmp_last_poll = ?, snmp_sys_descr = ?, snmp_sys_uptime = ? WHERE id = ?");
        $updDev->execute([$now, substr((string)$overview['sys_descr'], 0, 500), (string)$overview['sys_uptime'], $devId]);

        echo json_encode([
            'success' => true,
            'system' => $overview,
            'interfaces' => $savedInterfaces,
            'last_poll' => $now
        ]);
        break;

    case 'get_snmp_interfaces':
        $devId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
        if ($devId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM device_snmp_interfaces WHERE device_id = ? ORDER BY if_index ASC");
        $stmt->execute([$devId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../../includes/snmp_monitor.php';
        foreach ($rows as &$r) {
            $r['in_rate_formatted'] = SNMPMonitor::formatBitrate((float)$r['in_rate_bps']);
            $r['out_rate_formatted'] = SNMPMonitor::formatBitrate((float)$r['out_rate_bps']);
            $speed = (float)$r['if_speed'];
            $maxRate = max((float)$r['in_rate_bps'], (float)$r['out_rate_bps']);
            $r['utilization_percent'] = ($speed > 0) ? round(($maxRate / $speed) * 100, 1) : 0;
        }

        echo json_encode(['success' => true, 'interfaces' => $rows]);
        break;

    case 'get_snmp_history':
        $devId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
        $ifIndex = isset($_GET['if_index']) ? (int)$_GET['if_index'] : 0;
        $limit = isset($_GET['limit']) ? min(300, max(10, (int)$_GET['limit'])) : 60;

        if ($devId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID required']);
            exit;
        }

        if ($ifIndex > 0) {
            $stmt = $pdo->prepare("SELECT * FROM device_snmp_history WHERE device_id = ? AND if_index = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $devId, PDO::PARAM_INT);
            $stmt->bindValue(2, $ifIndex, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM device_snmp_history WHERE device_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $devId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        echo json_encode(['success' => true, 'history' => $history]);
        break;

    case 'queue_agent_command':
        $token = trim($input['agent_token'] ?? $input['agent_token_id'] ?? $_POST['agent_token'] ?? '');
        $cmdType = trim($input['command_type'] ?? $_POST['command_type'] ?? 'system_info');
        $payload = trim($input['command_payload'] ?? $_POST['command_payload'] ?? '');

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Agent token required']);
            exit;
        }

        $allowedCommands = ['system_info', 'flush_dns', 'ping', 'traceroute', 'process_list', 'service_restart', 'powershell_script', 'bash_script', 'custom_script'];
        if (!in_array($cmdType, $allowedCommands, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported command type: ' . htmlspecialchars($cmdType)]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO agent_commands (agent_token_id, user_id, command_type, command_payload, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$token, $current_user_id, $cmdType, $payload]);
        $cmdId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Command queued for agent execution',
            'command_id' => (int)$cmdId,
            'command_type' => $cmdType
        ]);
        break;

    case 'get_agent_command':
        $cmdId = (int)($input['command_id'] ?? $_GET['command_id'] ?? 0);
        if ($cmdId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Command ID required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM agent_commands WHERE id = ?");
        $stmt->execute([$cmdId]);
        $cmd = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cmd) {
            http_response_code(404);
            echo json_encode(['error' => 'Command not found']);
            exit;
        }

        echo json_encode(['success' => true, 'command' => $cmd]);
        break;

    case 'list_agent_commands':
        $token = trim($input['agent_token'] ?? $input['agent_token_id'] ?? $_GET['agent_token'] ?? '');
        $limit = min(50, max(5, (int)($_GET['limit'] ?? 15)));

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Agent token required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM agent_commands WHERE agent_token_id = ? ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, $token, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'commands' => $commands]);
        break;

    case 'agent_poll_commands':
        // Authenticate agent
        $rawToken = $_SESSION['agent_token'] ?? $_SERVER['HTTP_X_AGENT_TOKEN'] ?? $_GET['agent_token'] ?? $_POST['agent_token'] ?? '';
        if (empty($rawToken)) {
            http_response_code(401);
            echo json_encode(['error' => 'Agent authentication token required']);
            exit;
        }

        // Fetch oldest pending command for this agent token
        $stmt = $pdo->prepare("SELECT * FROM agent_commands WHERE agent_token_id = ? AND status = 'pending' ORDER BY id ASC LIMIT 1");
        $stmt->execute([$rawToken]);
        $cmd = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cmd) {
            echo json_encode(['has_command' => false]);
            exit;
        }

        // Lock command to 'running'
        $lockStmt = $pdo->prepare("UPDATE agent_commands SET status = 'running', executed_at = NOW() WHERE id = ? AND status = 'pending'");
        $lockStmt->execute([$cmd['id']]);

        echo json_encode([
            'has_command' => true,
            'command' => [
                'id' => (int)$cmd['id'],
                'type' => $cmd['command_type'],
                'payload' => $cmd['command_payload']
            ]
        ]);
        break;

    case 'agent_report_command_result':
        $rawToken = $_SESSION['agent_token'] ?? $_SERVER['HTTP_X_AGENT_TOKEN'] ?? $input['agent_token'] ?? '';
        $cmdId = (int)($input['command_id'] ?? $_POST['command_id'] ?? 0);
        $status = in_array($input['status'] ?? '', ['completed', 'failed', 'cancelled'], true) ? $input['status'] : 'completed';
        $output = $input['result_output'] ?? $_POST['result_output'] ?? '';
        $exitCode = isset($input['exit_code']) ? (int)$input['exit_code'] : 0;
        $execMs = isset($input['execution_time_ms']) ? (int)$input['execution_time_ms'] : 0;

        if ($cmdId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Command ID required']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE agent_commands SET 
            status = ?, 
            result_output = ?, 
            exit_code = ?, 
            execution_time_ms = ?, 
            completed_at = NOW() 
            WHERE id = ?");
        $stmt->execute([$status, $output, $exitCode, $execMs, $cmdId]);

        echo json_encode(['success' => true, 'message' => 'Command execution result recorded']);
        break;

    case 'create_ssl_monitor':
        require_once __DIR__ . '/../../includes/ssl_checker.php';
        $domain = trim($input['domain'] ?? $_POST['domain'] ?? '');
        $port = (int)($input['port'] ?? $_POST['port'] ?? 443);
        if ($port <= 0 || $port > 65535) $port = 443;

        if (empty($domain)) {
            http_response_code(400);
            echo json_encode(['error' => 'Domain is required']);
            exit;
        }

        // Check if already monitored
        $stmtCheck = $pdo->prepare("SELECT id FROM domain_ssl_monitors WHERE user_id = ? AND domain = ? AND port = ?");
        $stmtCheck->execute([$current_user_id, $domain, $port]);
        if ($stmtCheck->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'This domain and port is already monitored']);
            exit;
        }

        // Perform initial certificate check
        $res = SSLChecker::checkCertificate($domain, $port);

        $stmt = $pdo->prepare("INSERT INTO domain_ssl_monitors (user_id, domain, port, common_name, issuer, valid_from, valid_to, days_remaining, status, last_checked_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $current_user_id,
            $domain,
            $port,
            $res['common_name'] ?? null,
            $res['issuer'] ?? null,
            $res['valid_from'] ?? null,
            $res['valid_to'] ?? null,
            $res['days_remaining'] ?? null,
            $res['status'] ?? 'pending'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'SSL monitor added successfully',
            'id' => (int)$pdo->lastInsertId(),
            'certificate' => $res
        ]);
        break;

    case 'check_ssl_monitor':
        require_once __DIR__ . '/../../includes/ssl_checker.php';
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Monitor ID required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM domain_ssl_monitors WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $current_user_id]);
        $mon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mon) {
            http_response_code(404);
            echo json_encode(['error' => 'SSL monitor not found']);
            exit;
        }

        $res = SSLChecker::checkCertificate($mon['domain'], (int)$mon['port']);

        $stmtUp = $pdo->prepare("UPDATE domain_ssl_monitors SET common_name = ?, issuer = ?, valid_from = ?, valid_to = ?, days_remaining = ?, status = ?, last_checked_at = NOW() WHERE id = ?");
        $stmtUp->execute([
            $res['common_name'] ?? null,
            $res['issuer'] ?? null,
            $res['valid_from'] ?? null,
            $res['valid_to'] ?? null,
            $res['days_remaining'] ?? null,
            $res['status'] ?? 'pending',
            $id
        ]);

        echo json_encode(['success' => true, 'certificate' => $res]);
        break;

    case 'check_all_ssl_monitors':
        require_once __DIR__ . '/../../includes/ssl_checker.php';
        $stmt = $pdo->prepare("SELECT id, domain, port FROM domain_ssl_monitors WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtUp = $pdo->prepare("UPDATE domain_ssl_monitors SET common_name = ?, issuer = ?, valid_from = ?, valid_to = ?, days_remaining = ?, status = ?, last_checked_at = NOW() WHERE id = ?");

        foreach ($monitors as $m) {
            $res = SSLChecker::checkCertificate($m['domain'], (int)$m['port']);
            $stmtUp->execute([
                $res['common_name'] ?? null,
                $res['issuer'] ?? null,
                $res['valid_from'] ?? null,
                $res['valid_to'] ?? null,
                $res['days_remaining'] ?? null,
                $res['status'] ?? 'pending',
                $m['id']
            ]);
        }

        echo json_encode(['success' => true, 'count' => count($monitors)]);
        break;

    case 'delete_ssl_monitor':
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Monitor ID required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM domain_ssl_monitors WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $current_user_id]);

        echo json_encode(['success' => true, 'message' => 'SSL monitor deleted']);
        break;

    case 'get_ssl_monitors':
        $stmt = $pdo->prepare("SELECT * FROM domain_ssl_monitors WHERE user_id = ? ORDER BY days_remaining ASC, id DESC");
        $stmt->execute([$current_user_id]);
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'monitors' => $monitors]);
        break;

    // --- Escalation & Webhook Handlers ---
    case 'get_escalation_settings':
        $webhooks = $pdo->query("SELECT * FROM webhook_endpoints ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
        $rules = $pdo->query("SELECT * FROM alert_escalation_rules ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'webhooks' => $webhooks,
            'rules' => $rules
        ]);
        break;

    case 'save_escalation_settings':
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin privileges required']);
            exit;
        }

        $webhooks = $input['webhooks'] ?? [];
        $rules = $input['rules'] ?? [];

        // Save Webhooks
        $pdo->exec("DELETE FROM webhook_endpoints");
        $stmtW = $pdo->prepare("INSERT INTO webhook_endpoints (id, name, type, url, routing_key, is_enabled) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($webhooks as $w) {
            if (!empty($w['url']) || !empty($w['routing_key'])) {
                $wid = !empty($w['id']) ? $w['id'] : generateUuid();
                $stmtW->execute([
                    $wid,
                    $w['name'] ?? ucfirst($w['type'] ?? 'Webhook'),
                    $w['type'] ?? 'custom',
                    $w['url'] ?? '',
                    $w['routing_key'] ?? '',
                    !empty($w['is_enabled']) ? 1 : 0
                ]);
            }
        }

        // Save Escalation Rules
        $pdo->exec("DELETE FROM alert_escalation_rules");
        $stmtR = $pdo->prepare("INSERT INTO alert_escalation_rules (id, level, delay_minutes, channels, recipients, is_enabled) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($rules as $r) {
            $rid = !empty($r['id']) ? $r['id'] : generateUuid();
            $channelsJson = is_array($r['channels']) ? json_encode($r['channels']) : (string)$r['channels'];
            $stmtR->execute([
                $rid,
                (int)($r['level'] ?? 1),
                (int)($r['delay_minutes'] ?? 0),
                $channelsJson,
                $r['recipients'] ?? '',
                !empty($r['is_enabled']) ? 1 : 0
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Escalation & webhook settings saved successfully!']);
        break;

    case 'test_webhook_endpoint':
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin privileges required']);
            exit;
        }
        require_once __DIR__ . '/../../includes/webhook_dispatcher.php';
        $type = $input['type'] ?? $_POST['type'] ?? 'custom';
        $url = $input['url'] ?? $_POST['url'] ?? '';
        $routingKey = $input['routing_key'] ?? $_POST['routing_key'] ?? '';

        $testRes = AMPNM_WebhookDispatcher::testChannel($type, $url, $routingKey);
        echo json_encode($testRes);
        break;

    // --- Config Backup Vault Handlers ---
    case 'backup_device_config':
        if ($user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin privileges required']);
            exit;
        }
        require_once __DIR__ . '/../../includes/config_backup_engine.php';
        $deviceId = $input['device_id'] ?? $_POST['device_id'] ?? '';
        $credentials = [
            'username' => $input['username'] ?? $_POST['username'] ?? '',
            'password' => $input['password'] ?? $_POST['password'] ?? '',
            'port' => (int)($input['port'] ?? $_POST['port'] ?? 22)
        ];
        $res = AMPNM_ConfigBackupEngine::executeBackup($deviceId, $credentials);
        echo json_encode($res);
        break;

    case 'get_device_config_history':
        require_once __DIR__ . '/../../includes/config_backup_engine.php';
        $deviceId = $input['device_id'] ?? $_GET['device_id'] ?? '';
        $history = AMPNM_ConfigBackupEngine::getHistory($deviceId);
        echo json_encode(['success' => true, 'history' => $history]);
        break;

    case 'get_device_config_content':
        require_once __DIR__ . '/../../includes/config_backup_engine.php';
        $backupId = $input['backup_id'] ?? $_GET['backup_id'] ?? '';
        $content = AMPNM_ConfigBackupEngine::getBackupContent($backupId);
        if ($content !== null) {
            echo json_encode(['success' => true, 'content' => $content]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Backup content not found']);
        }
        break;

    case 'compare_device_configs':
        require_once __DIR__ . '/../../includes/config_backup_engine.php';
        $b1 = $input['backup_id_1'] ?? $_POST['backup_id_1'] ?? '';
        $b2 = $input['backup_id_2'] ?? $_POST['backup_id_2'] ?? '';
        $res = AMPNM_ConfigBackupEngine::compareConfigs($b1, $b2);
        echo json_encode($res);
        break;

    // --- IPAM (IP Address Management) Handlers ---
    case 'get_ipam_subnets':
        $subnets = $pdo->query("SELECT s.*, 
            COUNT(i.id) AS total_ips_tracked,
            SUM(CASE WHEN i.status IN ('allocated', 'gateway', 'dhcp', 'reserved') THEN 1 ELSE 0 END) AS used_ips
            FROM ipam_subnets s
            LEFT JOIN ipam_ip_addresses i ON s.id = i.subnet_id
            GROUP BY s.id
            ORDER BY s.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'subnets' => $subnets]);
        break;

    case 'create_ipam_subnet':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $cidr = trim($input['cidr'] ?? $_POST['cidr'] ?? '');
        $gateway = trim($input['gateway_ip'] ?? $_POST['gateway_ip'] ?? '');
        $vlan = (int)($input['vlan_id'] ?? $_POST['vlan_id'] ?? 0);
        $desc = trim($input['description'] ?? $_POST['description'] ?? '');

        if (empty($name) || empty($cidr)) {
            http_response_code(400);
            echo json_encode(['error' => 'Subnet Name and CIDR are required']);
            exit;
        }

        $id = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO ipam_subnets (id, name, cidr, gateway_ip, vlan_id, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $name, $cidr, $gateway ?: null, $vlan ?: null, $desc]);

        // Auto-seed gateway if provided
        if (!empty($gateway)) {
            $stmtGw = $pdo->prepare("INSERT INTO ipam_ip_addresses (id, subnet_id, ip_address, status, hostname, notes) VALUES (?, ?, ?, 'gateway', 'Default Gateway', 'Subnet Gateway IP') ON DUPLICATE KEY UPDATE status = 'gateway'");
            $stmtGw->execute([generateUuid(), $id, $gateway]);
        }

        echo json_encode(['success' => true, 'message' => 'Subnet created successfully!', 'id' => $id]);
        break;

    case 'delete_ipam_subnet':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM ipam_ip_addresses WHERE subnet_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ipam_subnets WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Subnet deleted']);
        break;

    case 'get_ipam_subnet_ips':
        $subnetId = $input['subnet_id'] ?? $_GET['subnet_id'] ?? '';
        if (empty($subnetId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Subnet ID required']);
            exit;
        }

        $stmtSub = $pdo->prepare("SELECT * FROM ipam_subnets WHERE id = ?");
        $stmtSub->execute([$subnetId]);
        $subnet = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if (!$subnet) {
            http_response_code(404);
            echo json_encode(['error' => 'Subnet not found']);
            exit;
        }

        $stmtIps = $pdo->prepare("SELECT i.*, d.name AS linked_device_name, d.status AS linked_device_status 
            FROM ipam_ip_addresses i 
            LEFT JOIN devices d ON i.device_id = d.id 
            WHERE i.subnet_id = ? 
            ORDER BY INET_ATON(i.ip_address) ASC");
        $stmtIps->execute([$subnetId]);
        $ips = $stmtIps->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'subnet' => $subnet, 'ips' => $ips]);
        break;

    case 'assign_ipam_ip':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $subnetId = $input['subnet_id'] ?? $_POST['subnet_id'] ?? '';
        $ip = trim($input['ip_address'] ?? $_POST['ip_address'] ?? '');
        $status = $input['status'] ?? $_POST['status'] ?? 'allocated';
        $hostname = trim($input['hostname'] ?? $_POST['hostname'] ?? '');
        $mac = trim($input['mac_address'] ?? $_POST['mac_address'] ?? '');
        $deviceId = $input['device_id'] ?? $_POST['device_id'] ?? null;
        $notes = trim($input['notes'] ?? $_POST['notes'] ?? '');

        if (empty($subnetId) || empty($ip)) {
            http_response_code(400);
            echo json_encode(['error' => 'Subnet ID and IP address required']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO ipam_ip_addresses (id, subnet_id, ip_address, status, hostname, mac_address, device_id, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = VALUES(status), hostname = VALUES(hostname), mac_address = VALUES(mac_address), device_id = VALUES(device_id), notes = VALUES(notes)");
        
        $stmt->execute([generateUuid(), $subnetId, $ip, $status, $hostname, $mac, $deviceId ?: null, $notes]);
        echo json_encode(['success' => true, 'message' => "IP {$ip} updated successfully!"]);
        break;

    case 'scan_ipam_subnet':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        require_once __DIR__ . '/../../includes/advanced_scanner.php';
        $subnetId = $input['subnet_id'] ?? $_POST['subnet_id'] ?? '';
        $stmtSub = $pdo->prepare("SELECT * FROM ipam_subnets WHERE id = ?");
        $stmtSub->execute([$subnetId]);
        $subnet = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if (!$subnet) {
            http_response_code(404);
            echo json_encode(['error' => 'Subnet not found']);
            exit;
        }

        $scanResult = AMPNM_AdvancedScanner::scanSubnet($subnet['cidr']);
        $stmtUpsert = $pdo->prepare("INSERT INTO ipam_ip_addresses (id, subnet_id, ip_address, status, hostname, mac_address, notes) 
            VALUES (?, ?, ?, 'allocated', ?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = IF(status = 'gateway', 'gateway', 'allocated'), hostname = IF(VALUES(hostname) != '', VALUES(hostname), hostname), mac_address = IF(VALUES(mac_address) != '', VALUES(mac_address), mac_address)");

        $syncedCount = 0;
        foreach ($scanResult['live_hosts'] as $host) {
            $notes = "Auto-discovered via IPAM sweep ({$host['type']})";
            $stmtUpsert->execute([
                generateUuid(),
                $subnetId,
                $host['ip'],
                $host['hostname'] ?? '',
                $host['mac'] ?? '',
                $notes
            ]);
            $syncedCount++;
        }

        echo json_encode(['success' => true, 'message' => "Scan complete. Discovered and mapped {$syncedCount} active IP(s).", 'live_hosts' => $scanResult['live_hosts']]);
        break;

    // --- Data Center 42U Rack Elevation Handlers ---
    case 'get_rack_cabinets':
        $cabinets = $pdo->query("SELECT c.*, 
            COUNT(r.id) AS mounted_device_count,
            COALESCE(SUM(r.unit_height), 0) AS used_units,
            COALESCE(SUM(r.power_watts), 0) AS total_power_draw
            FROM rack_cabinets c
            LEFT JOIN rack_devices r ON c.id = r.rack_id
            GROUP BY c.id
            ORDER BY c.name ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'cabinets' => $cabinets]);
        break;

    case 'create_rack_cabinet':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $location = trim($input['location'] ?? $_POST['location'] ?? 'Main DC');
        $room = trim($input['room'] ?? $_POST['room'] ?? 'Server Room');
        $units = (int)($input['total_units'] ?? $_POST['total_units'] ?? 42);
        $power = (int)($input['power_budget_watts'] ?? $_POST['power_budget_watts'] ?? 5000);
        $notes = trim($input['notes'] ?? $_POST['notes'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Cabinet Name is required']);
            exit;
        }

        $id = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO rack_cabinets (id, name, location, room, total_units, power_budget_watts, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $name, $location, $room, $units, $power, $notes]);

        echo json_encode(['success' => true, 'message' => 'Rack Cabinet created!', 'id' => $id]);
        break;

    case 'delete_rack_cabinet':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM rack_devices WHERE rack_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM rack_cabinets WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Rack Cabinet deleted']);
        break;

    case 'get_rack_devices':
        $rackId = $input['rack_id'] ?? $_GET['rack_id'] ?? '';
        if (empty($rackId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rack ID required']);
            exit;
        }

        $stmtCabinet = $pdo->prepare("SELECT * FROM rack_cabinets WHERE id = ?");
        $stmtCabinet->execute([$rackId]);
        $cabinet = $stmtCabinet->fetch(PDO::FETCH_ASSOC);

        if (!$cabinet) {
            http_response_code(404);
            echo json_encode(['error' => 'Rack cabinet not found']);
            exit;
        }

        $stmtDevices = $pdo->prepare("SELECT r.*, d.name AS device_name, d.ip AS device_ip, d.status AS device_status, d.type AS device_model 
            FROM rack_devices r
            LEFT JOIN devices d ON r.device_id = d.id
            WHERE r.rack_id = ?
            ORDER BY r.start_unit DESC");
        $stmtDevices->execute([$rackId]);
        $mounted = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'cabinet' => $cabinet, 'mounted_devices' => $mounted]);
        break;

    case 'mount_rack_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $rackId = $input['rack_id'] ?? $_POST['rack_id'] ?? '';
        $slot = (int)($input['start_unit'] ?? $_POST['start_unit'] ?? 1);
        $height = (int)($input['unit_height'] ?? $_POST['unit_height'] ?? 1);
        $label = trim($input['label'] ?? $_POST['label'] ?? 'Server Unit');
        $cat = $input['category'] ?? $_POST['category'] ?? 'server';
        $power = (int)($input['power_watts'] ?? $_POST['power_watts'] ?? 150);
        $deviceId = $input['device_id'] ?? $_POST['device_id'] ?? null;

        if (empty($rackId) || empty($label)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rack ID and Label required']);
            exit;
        }

        $id = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO rack_devices (id, rack_id, device_id, start_unit, unit_height, label, category, power_watts) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $rackId, $deviceId ?: null, $slot, $height, $label, $cat, $power]);

        echo json_encode(['success' => true, 'message' => "Mounted {$label} at U{$slot}!", 'id' => $id]);
        break;

    case 'unmount_rack_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM rack_devices WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Device unmounted from rack.']);
        break;

    // --- Status Page & Incident Management Handlers ---
    case 'get_status_page_admin':
        $settings = $pdo->query("SELECT * FROM status_page_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $components = $pdo->query("SELECT c.*, d.name AS device_name, d.ip AS device_ip 
            FROM status_page_components c 
            LEFT JOIN devices d ON c.device_id = d.id 
            ORDER BY c.group_name ASC, c.display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
        $incidents = $pdo->query("SELECT * FROM status_page_incidents ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($incidents as &$inc) {
            $stmtUp = $pdo->prepare("SELECT * FROM status_page_incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $stmtUp->execute([$inc['id']]);
            $inc['updates'] = $stmtUp->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'components' => $components,
            'incidents' => $incidents
        ]);
        break;

    case 'save_status_page_settings':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $title = trim($input['title'] ?? $_POST['title'] ?? 'AMPNM System Status');
        $company = trim($input['company_name'] ?? $_POST['company_name'] ?? 'AMPNM Network');
        $logo = trim($input['logo_url'] ?? $_POST['logo_url'] ?? '');
        $msg = trim($input['header_message'] ?? $_POST['header_message'] ?? '');
        $isPublic = !empty($input['is_public_enabled']) || !empty($_POST['is_public_enabled']) ? 1 : 0;

        $chk = $pdo->query("SELECT id FROM status_page_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($chk) {
            $stmt = $pdo->prepare("UPDATE status_page_settings SET title = ?, company_name = ?, logo_url = ?, header_message = ?, is_public_enabled = ? WHERE id = ?");
            $stmt->execute([$title, $company, $logo, $msg, $isPublic, $chk['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO status_page_settings (id, title, company_name, logo_url, header_message, is_public_enabled) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([generateUuid(), $title, $company, $logo, $msg, $isPublic]);
        }

        echo json_encode(['success' => true, 'message' => 'Status page settings saved successfully!']);
        break;

    case 'save_status_component':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $group = trim($input['group_name'] ?? $_POST['group_name'] ?? 'Core Services');
        $devId = $input['device_id'] ?? $_POST['device_id'] ?? null;
        $status = $input['status'] ?? $_POST['status'] ?? 'operational';
        $order = (int)($input['display_order'] ?? $_POST['display_order'] ?? 0);

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Component Name required']);
            exit;
        }

        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE status_page_components SET name = ?, group_name = ?, device_id = ?, status = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $group, $devId ?: null, $status, $order, $id]);
        } else {
            $id = generateUuid();
            $stmt = $pdo->prepare("INSERT INTO status_page_components (id, name, group_name, device_id, status, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $name, $group, $devId ?: null, $status, $order]);
        }

        echo json_encode(['success' => true, 'message' => 'Component saved!', 'id' => $id]);
        break;

    case 'delete_status_component':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM status_page_components WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Component deleted']);
        break;

    case 'create_status_incident':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $title = trim($input['title'] ?? $_POST['title'] ?? '');
        $status = $input['status'] ?? $_POST['status'] ?? 'investigating';
        $impact = $input['impact'] ?? $_POST['impact'] ?? 'minor';
        $initialMessage = trim($input['message'] ?? $_POST['message'] ?? 'We are currently investigating the issue.');

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'Incident Title required']);
            exit;
        }

        $incId = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO status_page_incidents (id, title, status, impact) VALUES (?, ?, ?, ?)");
        $stmt->execute([$incId, $title, $status, $impact]);

        // Insert initial timeline update
        $stmtUp = $pdo->prepare("INSERT INTO status_page_incident_updates (id, incident_id, status_state, message) VALUES (?, ?, ?, ?)");
        $stmtUp->execute([generateUuid(), $incId, $status, $initialMessage]);

        echo json_encode(['success' => true, 'message' => 'Incident published!', 'id' => $incId]);
        break;

    case 'update_status_incident':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $incId = $input['incident_id'] ?? $_POST['incident_id'] ?? '';
        $status = $input['status'] ?? $_POST['status'] ?? 'investigating';
        $message = trim($input['message'] ?? $_POST['message'] ?? '');

        if (empty($incId) || empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Incident ID and update message required']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE status_page_incidents SET status = ?, resolved_at = IF(? = 'resolved', NOW(), resolved_at) WHERE id = ?");
        $stmt->execute([$status, $status, $incId]);

        $stmtUp = $pdo->prepare("INSERT INTO status_page_incident_updates (id, incident_id, status_state, message) VALUES (?, ?, ?, ?)");
        $stmtUp->execute([generateUuid(), $incId, $status, $message]);

        echo json_encode(['success' => true, 'message' => 'Incident update posted!']);
        break;

    // --- Planned Maintenance Windows Handlers ---
    case 'get_maintenance_windows':
        $windows = $pdo->query("SELECT m.*, d.name AS target_device_name, d.ip AS target_device_ip 
            FROM maintenance_windows m 
            LEFT JOIN devices d ON m.target_type = 'device' AND m.target_id = d.id 
            ORDER BY m.start_time DESC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'windows' => $windows]);
        break;

    case 'create_maintenance_window':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $title = trim($input['title'] ?? $_POST['title'] ?? '');
        $targetType = $input['target_type'] ?? $_POST['target_type'] ?? 'device';
        $targetId = $input['target_id'] ?? $_POST['target_id'] ?? null;
        $startTime = $input['start_time'] ?? $_POST['start_time'] ?? '';
        $endTime = $input['end_time'] ?? $_POST['end_time'] ?? '';
        $suppressAlerts = !empty($input['suppress_alerts']) || !empty($_POST['suppress_alerts']) ? 1 : 0;
        $notes = trim($input['notes'] ?? $_POST['notes'] ?? '');

        if (empty($title) || empty($startTime) || empty($endTime)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title, Start Time, and End Time are required']);
            exit;
        }

        $id = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO maintenance_windows (id, title, target_type, target_id, start_time, end_time, suppress_alerts, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $title, $targetType, $targetId ?: null, $startTime, $endTime, $suppressAlerts, $notes]);

        echo json_encode(['success' => true, 'message' => 'Maintenance window scheduled!', 'id' => $id]);
        break;

    case 'delete_maintenance_window':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM maintenance_windows WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Maintenance window deleted']);
        break;

    case 'check_device_maintenance_status':
        $devId = $input['device_id'] ?? $_GET['device_id'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM maintenance_windows WHERE (target_type = 'all' OR (target_type = 'device' AND target_id = ?)) AND NOW() BETWEEN start_time AND end_time LIMIT 1");
        $stmt->execute([$devId]);
        $activeWindow = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'is_in_maintenance' => $activeWindow ? true : false,
            'active_window' => $activeWindow
        ]);
        break;

    // --- SLA Compliance & Executive Reporting Handlers ---
    case 'get_sla_report_data':
        $days = (int)($input['days'] ?? $_GET['days'] ?? 30);
        if ($days <= 0 || $days > 365) $days = 30;

        $targetSla = (float)($input['target_sla'] ?? $_GET['target_sla'] ?? 99.90);
        $totalWindowMinutes = $days * 24 * 60;

        // Fetch all monitored devices
        $devices = $pdo->query("SELECT id, name, ip, type, status, map_id FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch status logs / downtime summary in the given window
        $stmtLogs = $pdo->prepare("SELECT device_id, 
            COUNT(*) AS outage_count,
            COALESCE(SUM(duration_seconds), 0) AS total_down_seconds
            FROM status_logs 
            WHERE status = 'offline' AND created_at >= NOW() - INTERVAL ? DAY
            GROUP BY device_id");
        $stmtLogs->execute([$days]);
        $downSummary = $stmtLogs->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP);

        $report = [];
        $totalSystemUptimeMinutes = 0;
        $totalSystemPossibleMinutes = count($devices) * $totalWindowMinutes;
        $breachCount = 0;

        foreach ($devices as $d) {
            $devId = $d['id'];
            
            // Get outage stats
            $stmtDevLog = $pdo->prepare("SELECT 
                COUNT(*) AS outages, 
                COALESCE(SUM(duration_seconds), 0) AS down_sec 
                FROM status_logs 
                WHERE device_id = ? AND status = 'offline' AND created_at >= NOW() - INTERVAL ? DAY");
            $stmtDevLog->execute([$devId, $days]);
            $stats = $stmtDevLog->fetch(PDO::FETCH_ASSOC);

            $outages = (int)($stats['outages'] ?? 0);
            $downMinutes = round(($stats['down_sec'] ?? 0) / 60, 1);
            $uptimeMinutes = max(0, $totalWindowMinutes - $downMinutes);
            
            $slaPercent = $totalWindowMinutes > 0 
                ? round(($uptimeMinutes / $totalWindowMinutes) * 100, 3) 
                : 100.0;

            // MTTR (Mean Time to Repair in minutes)
            $mttr = $outages > 0 ? round($downMinutes / $outages, 1) : 0;
            // MTBF (Mean Time Between Failures in hours)
            $mtbf = $outages > 0 ? round(($totalWindowMinutes - $downMinutes) / (60 * $outages), 1) : round($totalWindowMinutes / 60, 1);

            $isCompliant = $slaPercent >= $targetSla;
            if (!$isCompliant) $breachCount++;

            $totalSystemUptimeMinutes += $uptimeMinutes;

            $report[] = [
                'device_id' => $devId,
                'name' => $d['name'],
                'ip' => $d['ip'],
                'type' => $d['type'],
                'status' => $d['status'],
                'outage_count' => $outages,
                'downtime_minutes' => $downMinutes,
                'uptime_minutes' => $uptimeMinutes,
                'sla_percent' => $slaPercent,
                'mttr_minutes' => $mttr,
                'mtbf_hours' => $mtbf,
                'is_compliant' => $isCompliant
            ];
        }

        $overallSla = $totalSystemPossibleMinutes > 0 
            ? round(($totalSystemUptimeMinutes / $totalSystemPossibleMinutes) * 100, 3) 
            : 100.0;

        echo json_encode([
            'success' => true,
            'window_days' => $days,
            'target_sla' => $targetSla,
            'overall_sla_percent' => $overallSla,
            'total_devices' => count($devices),
            'breach_count' => $breachCount,
            'device_reports' => $report
        ]);
        break;

    case 'get_sla_profiles':
        $profiles = $pdo->query("SELECT * FROM sla_profiles ORDER BY target_sla_percent DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'profiles' => $profiles]);
        break;

    case 'save_sla_profile':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $name = trim($input['name'] ?? $_POST['name'] ?? 'Custom SLA');
        $target = (float)($input['target_sla_percent'] ?? $_POST['target_sla_percent'] ?? 99.90);
        $bizOnly = !empty($input['business_hours_only']) ? 1 : 0;
        $notes = trim($input['notes'] ?? $_POST['notes'] ?? '');

        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE sla_profiles SET name = ?, target_sla_percent = ?, business_hours_only = ?, notes = ? WHERE id = ?");
            $stmt->execute([$name, $target, $bizOnly, $notes, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO sla_profiles (id, name, target_sla_percent, business_hours_only, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([generateUuid(), $name, $target, $bizOnly, $notes]);
        }

        echo json_encode(['success' => true, 'message' => 'SLA Profile saved!']);
        break;

    // --- Topology Path Tracer Handlers ---
    case 'trace_topology_path':
        $mapId = $input['map_id'] ?? $_GET['map_id'] ?? '';
        $sourceId = $input['source_id'] ?? $_GET['source_id'] ?? '';
        $targetId = $input['target_id'] ?? $_GET['target_id'] ?? '';

        if (empty($mapId) || empty($sourceId) || empty($targetId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Map ID, Source ID, and Target ID are required']);
            exit;
        }

        // Fetch all edges for the map
        $stmtEdges = $pdo->prepare("SELECT id, source_id, target_id, connection_type FROM device_edges WHERE map_id = ?");
        $stmtEdges->execute([$mapId]);
        $edges = $stmtEdges->fetchAll(PDO::FETCH_ASSOC);

        // Build adjacency graph
        $adj = [];
        $edgeMap = [];
        foreach ($edges as $e) {
            $u = $e['source_id'];
            $v = $e['target_id'];
            if (!isset($adj[$u])) $adj[$u] = [];
            if (!isset($adj[$v])) $adj[$v] = [];
            $adj[$u][] = $v;
            $adj[$v][] = $u;
            $edgeMap["{$u}_{$v}"] = $e['id'];
            $edgeMap["{$v}_{$u}"] = $e['id'];
        }

        // Breadth-First Search (Shortest Path)
        $queue = [[$sourceId]];
        $visited = [$sourceId => true];
        $shortestPath = null;

        while (!empty($queue)) {
            $path = array_shift($queue);
            $last = end($path);

            if ($last === $targetId) {
                $shortestPath = $path;
                break;
            }

            foreach ($adj[$last] ?? [] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $newPath = $path;
                    $newPath[] = $neighbor;
                    $queue[] = $newPath;
                }
            }
        }

        if (!$shortestPath) {
            echo json_encode(['success' => false, 'message' => 'No active connection path found between selected devices.']);
            exit;
        }

        // Fetch device details and build hop list
        $pathEdgeIds = [];
        for ($i = 0; $i < count($shortestPath) - 1; $i++) {
            $k = "{$shortestPath[$i]}_{$shortestPath[$i+1]}";
            if (isset($edgeMap[$k])) $pathEdgeIds[] = $edgeMap[$k];
        }

        $inClause = implode(',', array_fill(0, count($shortestPath), '?'));
        $stmtDevs = $pdo->prepare("SELECT id, name, ip, status, type, last_avg_time FROM devices WHERE id IN ($inClause)");
        $stmtDevs->execute($shortestPath);
        $devsLookup = [];
        foreach ($stmtDevs->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $devsLookup[$row['id']] = $row;
        }

        $hops = [];
        $cumulativeLatency = 0;
        foreach ($shortestPath as $nodeId) {
            $node = $devsLookup[$nodeId] ?? ['id' => $nodeId, 'name' => 'Unknown Node', 'ip' => '', 'status' => 'unknown'];
            $lat = (float)($node['last_avg_time'] ?? 1.5);
            $cumulativeLatency += $lat;
            $hops[] = [
                'id' => $nodeId,
                'name' => $node['name'] ?? 'Node',
                'ip' => $node['ip'] ?? '',
                'status' => $node['status'] ?? 'unknown',
                'type' => $node['type'] ?? 'device',
                'hop_latency_ms' => $lat
            ];
        }

        echo json_encode([
            'success' => true,
            'path_node_ids' => $shortestPath,
            'path_edge_ids' => $pathEdgeIds,
            'hop_count' => count($shortestPath) - 1,
            'cumulative_latency_ms' => round($cumulativeLatency, 2),
            'hops' => $hops
        ]);
        break;

    // --- AIOps: AI Root Cause Analysis (RCA) ---
    case 'get_rca_analysis':
        require_once __DIR__ . '/../../includes/ai_rca_engine.php';
        $analysis = AMPNM_AiRcaEngine::analyzeOutages($pdo);
        echo json_encode([
            'success' => true,
            'analysis' => $analysis
        ]);
        break;

    // --- AIOps: Predictive Capacity & Anomaly Forecast ---
    case 'get_predictive_forecasts':
        $hosts = $pdo->query("SELECT hm.*, d.name AS linked_device_name, d.status AS linked_device_status 
            FROM host_metrics hm
            LEFT JOIN devices d ON hm.ip_address = d.ip OR hm.hostname = d.name
            ORDER BY hm.disk_usage DESC")->fetchAll(PDO::FETCH_ASSOC);

        $forecasts = [];
        foreach ($hosts as $h) {
            $diskPct = (float)($h['disk_usage'] ?? 0);
            $cpuPct = (float)($h['cpu_usage'] ?? 0);
            $memPct = (float)($h['memory_usage'] ?? 0);
            $diskTotalGb = round(((float)($h['disk_total'] ?? 0)) / (1024 * 1024 * 1024), 1);
            $usedGb = round($diskTotalGb * ($diskPct / 100), 1);
            $freeGb = max(0, round($diskTotalGb - $usedGb, 1));

            // Estimate daily growth rate (default 0.35 GB / day)
            $growthRateGbPerDay = max(0.1, round($diskTotalGb * 0.005, 2));
            $daysUntilFull = $growthRateGbPerDay > 0 ? floor($freeGb / $growthRateGbPerDay) : 999;
            
            // Risk level assessment
            $risk = 'low';
            if ($diskPct >= 90 || $daysUntilFull <= 7) $risk = 'critical';
            else if ($diskPct >= 80 || $daysUntilFull <= 30) $risk = 'warning';

            $forecasts[] = [
                'hostname' => $h['hostname'],
                'ip_address' => $h['ip_address'],
                'os' => $h['os'],
                'current_disk_pct' => $diskPct,
                'current_cpu_pct' => $cpuPct,
                'current_mem_pct' => $memPct,
                'disk_total_gb' => $diskTotalGb,
                'disk_free_gb' => $freeGb,
                'est_growth_gb_day' => $growthRateGbPerDay,
                'days_until_disk_full' => $daysUntilFull,
                'risk_level' => $risk,
                'anomaly_detected' => ($cpuPct > 85 || $memPct > 90) ? true : false,
                'recommendation' => $risk === 'critical' 
                    ? "Immediate Action: Disk exhaustion predicted in {$daysUntilFull} days. Purge logs or expand volume." 
                    : ($risk === 'warning' ? "Capacity Warning: Disk utilization exceeds 80%. Review growth trend." : "Normal capacity headroom.")
            ];
        }

        echo json_encode([
            'success' => true,
            'total_analyzed' => count($hosts),
            'forecasts' => $forecasts
        ]);
        break;

    // --- AIOps: Autonomous Auto-Remediation Runbooks ---
    case 'get_remediation_rules':
        $rules = $pdo->query("SELECT r.*, d.name AS target_device_name, d.ip AS target_device_ip 
            FROM auto_remediation_rules r 
            LEFT JOIN devices d ON r.target_device_id = d.id 
            ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        $logs = $pdo->query("SELECT l.*, r.name AS rule_name, d.name AS device_name 
            FROM auto_remediation_logs l 
            LEFT JOIN auto_remediation_rules r ON l.rule_id = r.id 
            LEFT JOIN devices d ON l.device_id = d.id 
            ORDER BY l.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'rules' => $rules,
            'logs' => $logs
        ]);
        break;

    case 'save_remediation_rule':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $cond = $input['trigger_condition'] ?? $_POST['trigger_condition'] ?? 'service_down';
        $targetDev = $input['target_device_id'] ?? $_POST['target_device_id'] ?? null;
        $actType = $input['action_type'] ?? $_POST['action_type'] ?? 'agent_service_restart';
        $payload = trim($input['action_payload'] ?? $_POST['action_payload'] ?? '');
        $retries = (int)($input['max_retries'] ?? $_POST['max_retries'] ?? 3);
        $cooldown = (int)($input['cooldown_minutes'] ?? $_POST['cooldown_minutes'] ?? 10);
        $enabled = !empty($input['is_enabled']) ? 1 : 0;

        if (empty($name) || empty($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rule Name and Action Payload are required']);
            exit;
        }

        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE auto_remediation_rules SET name = ?, trigger_condition = ?, target_device_id = ?, action_type = ?, action_payload = ?, max_retries = ?, cooldown_minutes = ?, is_enabled = ? WHERE id = ?");
            $stmt->execute([$name, $cond, $targetDev ?: null, $actType, $payload, $retries, $cooldown, $enabled, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO auto_remediation_rules (id, name, trigger_condition, target_device_id, action_type, action_payload, max_retries, cooldown_minutes, is_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([generateUuid(), $name, $cond, $targetDev ?: null, $actType, $payload, $retries, $cooldown, $enabled]);
        }

        echo json_encode(['success' => true, 'message' => 'Remediation rule saved!']);
        break;

    case 'delete_remediation_rule':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM auto_remediation_rules WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Rule deleted']);
        break;

    case 'trigger_remediation_manual':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        require_once __DIR__ . '/../../includes/auto_remediation_engine.php';
        $ruleId = $input['rule_id'] ?? $_POST['rule_id'] ?? '';
        $res = AMPNM_AutoRemediationEngine::executeRule($pdo, $ruleId);
        echo json_encode($res);
        break;

    // --- Synthetic End-User Performance Monitoring Handlers ---
    case 'get_synthetic_monitors':
        $monitors = $pdo->query("SELECT * FROM synthetic_monitors ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $recentRuns = $pdo->query("SELECT r.*, m.name AS monitor_name, m.type AS monitor_type 
            FROM synthetic_monitor_runs r 
            LEFT JOIN synthetic_monitors m ON r.monitor_id = m.id 
            ORDER BY r.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'monitors' => $monitors,
            'recent_runs' => $recentRuns
        ]);
        break;

    case 'create_synthetic_monitor':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $type = $input['type'] ?? $_POST['type'] ?? 'http_api';
        $target = trim($input['target_url'] ?? $_POST['target_url'] ?? '');
        $port = !empty($input['port']) ? (int)$input['port'] : null;
        $method = strtoupper($input['http_method'] ?? $_POST['http_method'] ?? 'GET');
        $headers = trim($input['headers'] ?? $_POST['headers'] ?? '');
        $payload = trim($input['body_payload'] ?? $_POST['body_payload'] ?? '');
        $expCode = (int)($input['expected_status_code'] ?? $_POST['expected_status_code'] ?? 200);
        $bodyAssert = trim($input['body_assertion'] ?? $_POST['body_assertion'] ?? '');
        $timeout = (int)($input['timeout_seconds'] ?? $_POST['timeout_seconds'] ?? 10);
        $interval = (int)($input['check_interval_seconds'] ?? $_POST['check_interval_seconds'] ?? 60);

        if (empty($name) || empty($target)) {
            http_response_code(400);
            echo json_encode(['error' => 'Monitor Name and Target URL/Host are required']);
            exit;
        }

        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE synthetic_monitors SET name = ?, type = ?, target_url = ?, port = ?, http_method = ?, headers = ?, body_payload = ?, expected_status_code = ?, body_assertion = ?, timeout_seconds = ?, check_interval_seconds = ? WHERE id = ?");
            $stmt->execute([$name, $type, $target, $port, $method, $headers, $payload, $expCode, $bodyAssert, $timeout, $interval, $id]);
        } else {
            $newId = generateUuid();
            $stmt = $pdo->prepare("INSERT INTO synthetic_monitors (id, name, type, target_url, port, http_method, headers, body_payload, expected_status_code, body_assertion, timeout_seconds, check_interval_seconds) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newId, $name, $type, $target, $port, $method, $headers, $payload, $expCode, $bodyAssert, $timeout, $interval]);
            $id = $newId;
        }

        // Run immediate initial test check
        require_once __DIR__ . '/../../includes/synthetic_checker.php';
        AMPNM_SyntheticChecker::runMonitor($pdo, $id);

        echo json_encode(['success' => true, 'message' => 'Synthetic monitor saved and initial check completed!']);
        break;

    case 'delete_synthetic_monitor':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM synthetic_monitors WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM synthetic_monitor_runs WHERE monitor_id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Monitor deleted']);
        break;

    case 'test_synthetic_monitor_live':
        require_once __DIR__ . '/../../includes/synthetic_checker.php';
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $res = AMPNM_SyntheticChecker::runMonitor($pdo, $id);
        echo json_encode($res);
        break;

    case 'run_all_synthetic_monitors':
        require_once __DIR__ . '/../../includes/synthetic_checker.php';
        $mons = $pdo->query("SELECT id FROM synthetic_monitors WHERE is_enabled = 1")->fetchAll(PDO::FETCH_COLUMN);
        $executed = 0;
        foreach ($mons as $mId) {
            AMPNM_SyntheticChecker::runMonitor($pdo, $mId);
            $executed++;
        }
        echo json_encode(['success' => true, 'executed_count' => $executed]);
        break;

    // --- Configuration Compliance & Golden Standard Handlers ---
    case 'run_compliance_audit':
        require_once __DIR__ . '/../../includes/compliance_engine.php';
        $compEngine = new ComplianceEngine($pdo);
        $devices = $pdo->query("SELECT id FROM devices")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $results = [];
        foreach ($devices as $devId) {
            $results[] = $compEngine->auditDevice($devId);
        }
        echo json_encode(['success' => true, 'audited_count' => count($results), 'results' => $results]);
        break;

    case 'get_compliance_overview':
        require_once __DIR__ . '/../../includes/compliance_engine.php';
        $compEngine = new ComplianceEngine($pdo);
        echo json_encode(['success' => true, 'overview' => $compEngine->getGlobalComplianceOverview()]);
        break;

    case 'get_compliance_rules':
        require_once __DIR__ . '/../../includes/compliance_engine.php';
        $compEngine = new ComplianceEngine($pdo);
        echo json_encode(['success' => true, 'rules' => $compEngine->getRules($_GET['vendor'] ?? null)]);
        break;

    // --- VoIP & IP SLA Quality Probe Handlers ---
    case 'run_voip_probe':
        require_once __DIR__ . '/../../includes/voip_probe_engine.php';
        $voipEngine = new VoipProbeEngine($pdo);
        $probeId = $input['probe_id'] ?? $_GET['probe_id'] ?? '';
        echo json_encode($voipEngine->runProbe($probeId));
        break;

    case 'run_all_voip_probes':
        require_once __DIR__ . '/../../includes/voip_probe_engine.php';
        $voipEngine = new VoipProbeEngine($pdo);
        $probes = $pdo->query("SELECT id FROM voip_sla_probes WHERE is_enabled = 1")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $probed = 0;
        foreach ($probes as $pId) {
            $voipEngine->runProbe($pId);
            $probed++;
        }
        echo json_encode(['success' => true, 'probed_count' => $probed]);
        break;

    case 'get_voip_probe_history':
        require_once __DIR__ . '/../../includes/voip_probe_engine.php';
        $voipEngine = new VoipProbeEngine($pdo);
        $probeId = $input['probe_id'] ?? $_GET['probe_id'] ?? '';
        echo json_encode(['success' => true, 'history' => $voipEngine->getProbeHistory($probeId, 25)]);
        break;

    case 'create_voip_probe':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $name = trim($input['name'] ?? '');
        $host = trim($input['target_host'] ?? '');
        $codec = trim($input['codec_simulated'] ?? 'G.711_uLaw');
        $minMos = (float)($input['min_mos_threshold'] ?? 3.8);

        if (empty($name) || empty($host)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and Target Host are required']);
            exit;
        }

        $probeId = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO voip_sla_probes (id, name, target_host, codec_simulated, min_mos_threshold) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$probeId, $name, $host, $codec, $minMos]);

        require_once __DIR__ . '/../../includes/voip_probe_engine.php';
        $voipEngine = new VoipProbeEngine($pdo);
        $voipEngine->runProbe($probeId);

        echo json_encode(['success' => true, 'probe_id' => $probeId]);
        break;

    case 'delete_voip_probe':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $probeId = $input['probe_id'] ?? $_GET['probe_id'] ?? '';
        $pdo->prepare("DELETE FROM voip_sla_probes WHERE id = ?")->execute([$probeId]);
        $pdo->prepare("DELETE FROM voip_sla_metrics WHERE probe_id = ?")->execute([$probeId]);
        echo json_encode(['success' => true]);
        break;

    // --- Planned Maintenance Window Handlers ---
    case 'create_maintenance_window':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        require_once __DIR__ . '/../../includes/maintenance_engine.php';
        $maintEngine = new MaintenanceEngine($pdo);
        $id = $maintEngine->scheduleWindow($input);
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'delete_maintenance_window':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $pdo->prepare("DELETE FROM maintenance_windows WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM maintenance_device_assignments WHERE maintenance_id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // --- Windows Agent Live Command & Remote Services Handlers ---
    case 'dispatch_agent_command':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $agentId = (int)($input['agent_device_id'] ?? $_POST['agent_device_id'] ?? 0);
        $cmdType = $input['command_type'] ?? $_POST['command_type'] ?? 'powershell';
        $cmdText = trim($input['command_text'] ?? $_POST['command_text'] ?? '');
        $dispatchedBy = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'admin';

        if ($agentId <= 0 || empty($cmdText)) {
            http_response_code(400);
            echo json_encode(['error' => 'Agent Device ID and Command Text are required']);
            exit;
        }

        $cmdId = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO agent_command_queue 
            (id, agent_device_id, command_type, command_text, status, dispatched_by, created_at)
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
        $stmt->execute([$cmdId, $agentId, $cmdType, $cmdText, $dispatchedBy]);

        echo json_encode(['success' => true, 'command_id' => $cmdId, 'status' => 'pending']);
        break;

    case 'get_agent_command_status':
        $cmdId = $input['command_id'] ?? $_GET['command_id'] ?? '';
        if (empty($cmdId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Command ID required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM agent_command_queue WHERE id = ? LIMIT 1");
        $stmt->execute([$cmdId]);
        $cmd = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cmd) {
            http_response_code(404);
            echo json_encode(['error' => 'Command not found']);
            exit;
        }
        echo json_encode(['success' => true, 'command' => $cmd]);
        break;

    case 'get_agent_services':
        $agentId = (int)($input['agent_device_id'] ?? $_GET['agent_device_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM agent_device_services WHERE agent_device_id = ? ORDER BY status ASC, service_name ASC");
        $stmt->execute([$agentId]);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'services' => $services]);
        break;

    case 'restart_agent_service':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Admin required']); exit; }
        $agentId = (int)($input['agent_device_id'] ?? $_POST['agent_device_id'] ?? 0);
        $serviceName = trim($input['service_name'] ?? $_POST['service_name'] ?? '');
        if ($agentId <= 0 || empty($serviceName)) {
            http_response_code(400);
            echo json_encode(['error' => 'Agent ID and Service Name required']);
            exit;
        }

        $cmdText = "Restart-Service -Name '{$serviceName}' -Force -PassThru | Select-Object Name, Status";
        $cmdId = generateUuid();
        $stmt = $pdo->prepare("INSERT INTO agent_command_queue 
            (id, agent_device_id, command_type, command_text, status, dispatched_by, created_at)
            VALUES (?, ?, 'service_control', ?, 'pending', ?, NOW())");
        $stmt->execute([$cmdId, $agentId, $cmdText, $_SESSION['username'] ?? 'admin']);

        echo json_encode(['success' => true, 'command_id' => $cmdId, 'message' => "Service restart queued for '{$serviceName}'"]);
        break;

    case 'get_agent_drives':
        $agentId = (int)($input['agent_device_id'] ?? $_GET['agent_device_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM agent_device_drives WHERE agent_device_id = ? ORDER BY drive_letter ASC");
        $stmt->execute([$agentId]);
        $drives = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'drives' => $drives]);
        break;

    case 'get_agent_software':
        $agentId = (int)($input['agent_device_id'] ?? $_GET['agent_device_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM agent_software_inventory WHERE agent_device_id = ? ORDER BY app_name ASC");
        $stmt->execute([$agentId]);
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'software' => $apps]);
        break;

    case 'get_agent_security_health':
        $agentId = (int)($input['agent_device_id'] ?? $_GET['agent_device_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM agent_security_health WHERE agent_device_id = ? LIMIT 1");
        $stmt->execute([$agentId]);
        $sec = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        echo json_encode(['success' => true, 'security' => $sec]);
        break;
}


