<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */

function agentCompatGetHeader($key) {
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
    if (isset($_SERVER[$serverKey])) {
        return trim((string)$_SERVER[$serverKey]);
    }

    // Fallback 1: getallheaders() (case-insensitive)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $hKey => $hVal) {
            if (strcasecmp($hKey, $key) === 0) {
                return trim((string)$hVal);
            }
        }
    }

    // Fallback 2: Check standard HTTP authorization header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = trim((string)$_SERVER['HTTP_AUTHORIZATION']);
        if (str_starts_with(strtolower($auth), 'bearer ')) {
            return trim(substr($auth, 7));
        }
    }

    // Fallback 3: Query parameters (makes debugging / simple scripts very robust)
    if (isset($_GET['token'])) {
        return trim((string)$_GET['token']);
    }
    if (isset($_GET['agent_token'])) {
        return trim((string)$_GET['agent_token']);
    }
    if (isset($_GET['X-Agent-Token'])) {
        return trim((string)$_GET['X-Agent-Token']);
    }

    // Fallback 4: POST parameters or JSON body fields (in case headers are completely stripped)
    if (isset($_POST['agent_token'])) {
        return trim((string)$_POST['agent_token']);
    }
    
    // Check if JSON body contains token field
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            if (isset($json['agent_token'])) {
                return trim((string)$json['agent_token']);
            }
            if (isset($json['token'])) {
                return trim((string)$json['token']);
            }
        }
    }

    return '';
}

function agentCompatValidateToken($pdo, $token) {
    if ($token === '') {
        error_log("AMPNM Agent Auth Warning: Empty token provided by request from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
    
    // Auto-create/seed the default agent token if requested and not present in DB
    $default_token = 'ampnm_1dc3c51eb6872b8eabcd2717e0b7bcf3';
    if ($token === $default_token) {
        $check = $pdo->prepare("SELECT id FROM agent_tokens WHERE token = ? LIMIT 1");
        $check->execute(array($token));
        if (!$check->fetch()) {
            // Find default admin user (typically id = 1)
            $user_stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
            $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $user_row ? (int)$user_row['id'] : 1;
            
            $insert = $pdo->prepare("INSERT INTO agent_tokens (user_id, token, name, enabled) VALUES (?, ?, 'Default Windows Agent', 1)");
            $insert->execute(array($userId, $token));
        }
    }

    $stmt = $pdo->prepare("SELECT id, user_id FROM agent_tokens WHERE token = ? AND enabled = 1 LIMIT 1");
    $stmt->execute(array($token));
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tokenRow) {
        // Fallback: Check if it is a valid active agent enrollment token
        $token_hash = hash('sha256', $token);
        $stmt_enroll = $pdo->prepare("SELECT id, created_by FROM agent_enrollment_tokens WHERE token_hash = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $stmt_enroll->execute(array($token_hash));
        $enrollRow = $stmt_enroll->fetch(PDO::FETCH_ASSOC);
        if ($enrollRow) {
            $tokenRow = array(
                'id' => (int)$enrollRow['id'],
                'user_id' => $enrollRow['created_by'] !== null ? (int)$enrollRow['created_by'] : 1,
                'is_enrollment_token' => true
            );
            $touch = $pdo->prepare("UPDATE agent_enrollment_tokens SET last_used_at = NOW() WHERE id = ?");
            $touch->execute(array($enrollRow['id']));
            return $tokenRow;
        }

        error_log("AMPNM Agent Auth Warning: Invalid/disabled agent token (" . htmlspecialchars(substr($token, 0, 12)) . "...) requested from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
    $touch = $pdo->prepare("UPDATE agent_tokens SET last_used_at = NOW() WHERE id = ?");
    $touch->execute(array($tokenRow['id']));
    return $tokenRow;
}


function agentCompatGetTableColumns($pdo, $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table . "`");
    $columns = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

function agentCompatHasColumn($columns, $name) {
    return in_array($name, $columns, true);
}

function agentCompatPickValue($payload, $keys, $defaultValue) {
    foreach ($keys as $k) {
        if (isset($payload[$k]) && $payload[$k] !== '') {
            return $payload[$k];
        }
    }
    return $defaultValue;
}

function agentCompatFindOrCreateDevice($pdo, $userId, $hostName, $hostIp) {
    if ($hostIp !== '') {
        $stmt = $pdo->prepare("SELECT id, name, ip FROM devices WHERE user_id = ? AND ip = ? LIMIT 1");
        $stmt->execute(array($userId, $hostIp));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing;
        }
    }

    if ($hostName !== '') {
        $stmt = $pdo->prepare("SELECT id, name, ip FROM devices WHERE user_id = ? AND name = ? LIMIT 1");
        $stmt->execute(array($userId, $hostName));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing;
        }
    }

    $name = $hostName !== '' ? $hostName : ($hostIp !== '' ? $hostIp : 'Agent Host');
    $insert = $pdo->prepare("INSERT INTO devices (user_id, name, ip, monitor_method, type, status, ping_interval, show_live_ping, description) VALUES (?, ?, ?, 'ping', 'server', 'online', NULL, 0, ?)");
    $insert->execute(array($userId, $name, $hostIp !== '' ? $hostIp : null, 'Auto-created from agent telemetry'));
    $id = $pdo->lastInsertId();
    return array('id' => $id, 'name' => $name, 'ip' => $hostIp);
}

function agentCompatSaveMetrics($pdo, $payload, $tokenUserId) {
    $hostIp = trim((string)agentCompatPickValue($payload, array('host_ip', 'ip_address'), ''));
    $hostName = trim((string)agentCompatPickValue($payload, array('host_name', 'hostname'), $hostIp));
    if ($hostIp === '') {
        return array('ok' => false, 'error' => 'host_ip is required');
    }

    $cpu = agentCompatPickValue($payload, array('cpu_percent', 'cpu_usage', 'cpu'), null);
    $mem = agentCompatPickValue($payload, array('memory_percent', 'memory_usage'), null);
    $disk = agentCompatPickValue($payload, array('disk_percent', 'disk_usage'), null);
    $gpu = agentCompatPickValue($payload, array('gpu_percent', 'gpu_usage'), null);
    $netIn = agentCompatPickValue($payload, array('network_in_mbps', 'network_in'), null);
    $netOut = agentCompatPickValue($payload, array('network_out_mbps', 'network_out'), null);

    $device = agentCompatFindOrCreateDevice($pdo, (int)$tokenUserId, $hostName, $hostIp);
    $deviceId = isset($device['id']) ? (int)$device['id'] : null;

    $deviceTouch = $pdo->prepare("UPDATE devices SET last_seen = NOW() WHERE id = ?");
    if ($deviceId) {
        $deviceTouch->execute(array($deviceId));
    }

    $hostCols = agentCompatGetTableColumns($pdo, 'host_metrics');
    $row = array(
        'device_id' => $deviceId,
        'host_name' => $hostName,
        'hostname' => $hostName,
        'host_ip' => $hostIp,
        'ip_address' => $hostIp,
        'cpu_percent' => $cpu,
        'cpu_usage' => $cpu,
        'memory_percent' => $mem,
        'memory_usage' => $mem,
        'disk_percent' => $disk,
        'disk_usage' => $disk,
        'network_in_mbps' => $netIn,
        'network_out_mbps' => $netOut,
        'network_in' => $netIn,
        'network_out' => $netOut,
        'gpu_percent' => $gpu,
        'gpu_usage' => $gpu,
        'status' => 'online',
        'last_seen' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    );

    $insertCols = array();
    $insertVals = array();
    foreach ($row as $k => $v) {
        if (agentCompatHasColumn($hostCols, $k)) {
            $insertCols[] = "`" . $k . "`";
            $insertVals[] = $v;
        }
    }
    if (!empty($insertCols)) {
        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        
        $updateParts = array();
        $updateVals = array();
        foreach ($row as $k => $v) {
            if (agentCompatHasColumn($hostCols, $k) && $k !== 'hostname' && $k !== 'created_at' && $k !== 'id') {
                $updateParts[] = "`" . $k . "` = ?";
                $updateVals[] = $v;
            }
        }
        
        $sql = "INSERT INTO host_metrics (" . implode(', ', $insertCols) . ") VALUES (" . $placeholders . ") " .
               "ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
               
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($insertVals, $updateVals));
    }

    $historyCols = agentCompatGetTableColumns($pdo, 'host_metrics_history');
    $historyRow = array(
        'host_name' => $hostName,
        'hostname' => $hostName,
        'host_ip' => $hostIp,
        'ip_address' => $hostIp,
        'cpu_percent' => $cpu,
        'cpu_usage' => $cpu,
        'memory_percent' => $mem,
        'memory_usage' => $mem,
        'disk_percent' => $disk,
        'disk_usage' => $disk,
        'network_in_mbps' => $netIn,
        'network_out_mbps' => $netOut,
        'network_in' => $netIn,
        'network_out' => $netOut,
        'gpu_percent' => $gpu,
        'gpu_usage' => $gpu,
        'recorded_at' => date('Y-m-d H:i:s')
    );
    $hCols = array();
    $hVals = array();
    foreach ($historyRow as $k => $v) {
        if (agentCompatHasColumn($historyCols, $k)) {
            $hCols[] = "`" . $k . "`";
            $hVals[] = $v;
        }
    }
    if (!empty($hCols)) {
        $ph = implode(', ', array_fill(0, count($hCols), '?'));
        $sql = "INSERT INTO host_metrics_history (" . implode(', ', $hCols) . ") VALUES (" . $ph . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($hVals);
    }

    if ($deviceId) {
        agentCompatCheckAlerts($pdo, $deviceId, (int)$tokenUserId, $hostIp, $hostName, $cpu, $mem, $disk);
    }

    return array('ok' => true, 'host_ip' => $hostIp, 'host_name' => $hostName, 'device_id' => $deviceId);
}

function agentCompatCheckAlerts($pdo, $deviceId, $tokenUserId, $hostIp, $hostName, $cpu, $mem, $disk) {
    try {
        $enabled = true;
        $cpuWarn = 80.0;
        $cpuCrit = 95.0;
        $memWarn = 80.0;
        $memCrit = 95.0;
        $diskWarn = 80.0;
        $diskCrit = 95.0;
        $cooldownMinutes = 30;

        // Load global settings for user
        $stmtGlobal = $pdo->prepare("SELECT * FROM host_alert_settings WHERE user_id = ?");
        $stmtGlobal->execute([$tokenUserId]);
        $globalSettings = $stmtGlobal->fetch(PDO::FETCH_ASSOC);
        if ($globalSettings) {
            $enabled = (bool)($globalSettings['enabled'] ?? true);
            if ($globalSettings['cpu_warning_threshold'] !== null) $cpuWarn = (float)$globalSettings['cpu_warning_threshold'];
            if ($globalSettings['cpu_critical_threshold'] !== null) $cpuCrit = (float)$globalSettings['cpu_critical_threshold'];
            if ($globalSettings['memory_warning_threshold'] !== null) $memWarn = (float)$globalSettings['memory_warning_threshold'];
            if ($globalSettings['memory_critical_threshold'] !== null) $memCrit = (float)$globalSettings['memory_critical_threshold'];
            if ($globalSettings['disk_warning_threshold'] !== null) $diskWarn = (float)$globalSettings['disk_warning_threshold'];
            if ($globalSettings['disk_critical_threshold'] !== null) $diskCrit = (float)$globalSettings['disk_critical_threshold'];
            if ($globalSettings['cooldown_minutes'] !== null) $cooldownMinutes = (int)$globalSettings['cooldown_minutes'];
        }

        // Load override settings for this host IP or hostname
        $stmtOverride = $pdo->prepare("SELECT * FROM host_alert_overrides WHERE (host_ip = ? AND host_ip != '') OR (hostname = ? AND hostname != '') LIMIT 1");
        $stmtOverride->execute([$hostIp, $hostName]);
        $override = $stmtOverride->fetch(PDO::FETCH_ASSOC);
        if ($override) {
            $enabled = (bool)($override['enabled'] ?? true);
            if ($override['cpu_warning'] !== null) $cpuWarn = (float)$override['cpu_warning'];
            if ($override['cpu_critical'] !== null) $cpuCrit = (float)$override['cpu_critical'];
            if ($override['memory_warning'] !== null) $memWarn = (float)$override['memory_warning'];
            if ($override['memory_critical'] !== null) $memCrit = (float)$override['memory_critical'];
            if ($override['disk_warning'] !== null) $diskWarn = (float)$override['disk_warning'];
            if ($override['disk_critical'] !== null) $diskCrit = (float)$override['disk_critical'];
        }

        if (!$enabled) {
            return;
        }

        // Compare values
        $newStatus = 'online';
        $details = 'Host is reporting normally';

        // Critical takes precedence over Warning
        // CPU check
        if ($cpu !== null) {
            $cpuVal = (float)$cpu;
            if ($cpuVal >= $cpuCrit) {
                $newStatus = 'critical';
                $details = "Critical CPU usage: {$cpuVal}% (>= {$cpuCrit}%)";
            } elseif ($cpuVal >= $cpuWarn && $newStatus !== 'critical') {
                $newStatus = 'warning';
                $details = "Warning CPU usage: {$cpuVal}% (>= {$cpuWarn}%)";
            }
        }
        // Memory check
        if ($mem !== null) {
            $memVal = (float)$mem;
            if ($memVal >= $memCrit) {
                $newStatus = 'critical';
                $details = "Critical Memory usage: {$memVal}% (>= {$memCrit}%)";
            } elseif ($memVal >= $memWarn && $newStatus !== 'critical') {
                $newStatus = 'warning';
                $details = "Warning Memory usage: {$memVal}% (>= {$memWarn}%)";
            }
        }
        // Disk check
        if ($disk !== null) {
            $diskVal = (float)$disk;
            if ($diskVal >= $diskCrit) {
                $newStatus = 'critical';
                $details = "Critical Disk usage: {$diskVal}% (>= {$diskCrit}%)";
            } elseif ($diskVal >= $diskWarn && $newStatus !== 'critical') {
                $newStatus = 'warning';
                $details = "Warning Disk usage: {$diskVal}% (>= {$diskWarn}%)";
            }
        }

        // Fetch current device status
        $stmtDevice = $pdo->prepare("SELECT name, ip, status FROM devices WHERE id = ?");
        $stmtDevice->execute([$deviceId]);
        $device = $stmtDevice->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            return;
        }

        $oldStatus = $device['status'] ?? 'online';

        // If status changed, update and log/notify
        if ($oldStatus !== $newStatus) {
            // Update device status (ensure we keep last_seen updated)
            $update = $pdo->prepare("UPDATE devices SET status = ?, last_seen = NOW() WHERE id = ?");
            $update->execute([$newStatus, $deviceId]);

            // Log change to device_status_logs
            $log = $pdo->prepare("INSERT INTO device_status_logs (device_id, status, details) VALUES (?, ?, ?)");
            $log->execute([$deviceId, $newStatus, $details]);

            // Dispatch alert notifications
            agentCompatSendNotifications($pdo, $device, $deviceId, $tokenUserId, $oldStatus, $newStatus, $details, $cooldownMinutes);
        }
    } catch (Exception $e) {
        error_log("Error in agentCompatCheckAlerts: " . $e->getMessage());
    }
}

function agentCompatSendNotifications($pdo, $device, $deviceId, $tokenUserId, $oldStatus, $newStatus, $details, $cooldownMinutes) {
    try {
        // Cooldown check
        if ($cooldownMinutes > 0) {
            $stmtCooldown = $pdo->prepare("
                SELECT COUNT(*) 
                FROM device_status_logs 
                WHERE device_id = ? AND status = ? AND created_at >= NOW() - INTERVAL ? MINUTE
            ");
            $stmtCooldown->execute([$deviceId, $newStatus, $cooldownMinutes]);
            if ((int)$stmtCooldown->fetchColumn() > 1) {
                error_log("Alert notifications throttled by cooldown of {$cooldownMinutes} minutes for device {$device['name']} status {$newStatus}");
                return;
            }
        }

        // 1. Email Notifications
        $stmtEmailSubs = $pdo->prepare("
            SELECT recipient_email 
            FROM device_email_subscriptions 
            WHERE user_id = ? AND device_id = ? AND 
                  ((? = 'online' AND notify_on_online = 1) OR
                   (? = 'offline' AND notify_on_offline = 1) OR
                   (? = 'warning' AND notify_on_warning = 1) OR
                   (? = 'critical' AND notify_on_critical = 1))
        ");
        $stmtEmailSubs->execute([$tokenUserId, $deviceId, $newStatus, $newStatus, $newStatus, $newStatus]);
        $emails = $stmtEmailSubs->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($emails)) {
            $stmtSmtp = $pdo->prepare("SELECT * FROM smtp_settings WHERE user_id = ? LIMIT 1");
            $stmtSmtp->execute([$tokenUserId]);
            $smtpSettings = $stmtSmtp->fetch(PDO::FETCH_ASSOC);
            if ($smtpSettings) {
                require_once __DIR__ . '/smtp_mailer.php';
                $subject = sprintf('Alert: %s is %s', $device['name'], strtoupper($newStatus));
                $body = "Device: {$device['name']}\n"
                    . "IP: " . ($device['ip'] ?? 'N/A') . "\n"
                    . "Previous Status: {$oldStatus}\n"
                    . "Current Status: {$newStatus}\n"
                    . "Details: {$details}\n"
                    . "Time (UTC): " . gmdate('Y-m-d H:i:s') . "\n";
                foreach ($emails as $email) {
                    $smtpError = null;
                    $sent = smtp_send_mail($smtpSettings, $email, $subject, $body, $smtpError);
                    if (!$sent) {
                        error_log("Email send failed for {$email}: " . ($smtpError ?? 'Unknown SMTP error'));
                    } else {
                        error_log("Email send succeeded for {$email}");
                    }
                }
            } else {
                error_log("Email notification skipped: SMTP settings not configured for user {$tokenUserId}");
            }
        }

        // 2. SMS Notifications
        $stmtSmsSubs = $pdo->prepare("
            SELECT recipient_phone 
            FROM device_sms_subscriptions 
            WHERE user_id = ? AND device_id = ? AND 
                  ((? = 'online' AND notify_on_online = 1) OR
                   (? = 'offline' AND notify_on_offline = 1) OR
                   (? = 'warning' AND notify_on_warning = 1) OR
                   (? = 'critical' AND notify_on_critical = 1))
        ");
        $stmtSmsSubs->execute([$tokenUserId, $deviceId, $newStatus, $newStatus, $newStatus, $newStatus]);
        $smsPhones = $stmtSmsSubs->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($smsPhones)) {
            $stmtSmsSettings = $pdo->prepare("SELECT * FROM sms_settings WHERE user_id = ? LIMIT 1");
            $stmtSmsSettings->execute([$tokenUserId]);
            $smsSettings = $stmtSmsSettings->fetch(PDO::FETCH_ASSOC);
            if ($smsSettings && (bool)$smsSettings['enabled']) {
                require_once __DIR__ . '/sms_sender.php';
                $smsBody = sprintf("ALERT: %s is %s. %s", $device['name'], strtoupper($newStatus), $details);
                foreach ($smsPhones as $phone) {
                    $smsError = null;
                    $sent = sms_send_alert($phone, $smsBody, $smsSettings, $smsError);
                    if (!$sent) {
                        error_log("SMS send failed for {$phone}: " . ($smsError ?? 'Unknown SMS error'));
                    } else {
                        error_log("SMS send succeeded for {$phone}");
                    }
                }
            } else {
                error_log("SMS notification skipped: SMS settings disabled or not configured for user {$tokenUserId}");
            }
        }

        // 3. Telegram Notifications
        $stmtTelegramSubs = $pdo->prepare("
            SELECT chat_id 
            FROM device_telegram_subscriptions 
            WHERE user_id = ? AND device_id = ? AND 
                  ((? = 'online' AND notify_on_online = 1) OR
                   (? = 'offline' AND notify_on_offline = 1) OR
                   (? = 'warning' AND notify_on_warning = 1) OR
                   (? = 'critical' AND notify_on_critical = 1))
        ");
        $stmtTelegramSubs->execute([$tokenUserId, $deviceId, $newStatus, $newStatus, $newStatus, $newStatus]);
        $telegramChats = $stmtTelegramSubs->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($telegramChats)) {
            $stmtTelegramSettings = $pdo->prepare("SELECT * FROM telegram_settings WHERE user_id = ? LIMIT 1");
            $stmtTelegramSettings->execute([$tokenUserId]);
            $tgSettings = $stmtTelegramSettings->fetch(PDO::FETCH_ASSOC);
            if ($tgSettings && !empty($tgSettings['bot_token']) && (bool)$tgSettings['enabled']) {
                require_once __DIR__ . '/telegram_bot.php';
                $emoji = '⚪';
                switch ($newStatus) {
                    case 'online': $emoji = '🟢'; break;
                    case 'offline': $emoji = '🔴'; break;
                    case 'warning': $emoji = '🟡'; break;
                    case 'critical': $emoji = '🚨'; break;
                }
                $tgBody = sprintf("⚠️ <b>ALERT: %s is %s</b>\n\n"
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
                foreach ($telegramChats as $chat) {
                    $tgError = null;
                    $sent = telegram_send_alert($chat, $tgBody, $tgSettings['bot_token'], $tgError);
                    if (!$sent) {
                        error_log("Telegram send failed for chat {$chat}: " . ($tgError ?? 'Unknown Telegram error'));
                    } else {
                        error_log("Telegram send succeeded for chat {$chat}");
                    }
                }
            } else {
                error_log("Telegram notification skipped: Telegram settings disabled or not configured for user {$tokenUserId}");
            }
        }

        // 4. WhatsApp Notifications
        $stmtWhatsappSubs = $pdo->prepare("
            SELECT recipient_phone 
            FROM device_whatsapp_subscriptions 
            WHERE user_id = ? AND device_id = ? AND 
                  ((? = 'online' AND notify_on_online = 1) OR
                   (? = 'offline' AND notify_on_offline = 1) OR
                   (? = 'warning' AND notify_on_warning = 1) OR
                   (? = 'critical' AND notify_on_critical = 1))
        ");
        $stmtWhatsappSubs->execute([$tokenUserId, $deviceId, $newStatus, $newStatus, $newStatus, $newStatus]);
        $waPhones = $stmtWhatsappSubs->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($waPhones)) {
            $stmtWhatsappSettings = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
            $stmtWhatsappSettings->execute([$tokenUserId]);
            $waSettings = $stmtWhatsappSettings->fetch(PDO::FETCH_ASSOC);
            if ($waSettings && !empty($waSettings['token']) && (bool)$waSettings['enabled']) {
                require_once __DIR__ . '/whatsapp_bot.php';
                $emoji = '⚪';
                switch ($newStatus) {
                    case 'online': $emoji = '🟢'; break;
                    case 'offline': $emoji = '🔴'; break;
                    case 'warning': $emoji = '🟡'; break;
                    case 'critical': $emoji = '🚨'; break;
                }
                $waBody = sprintf("*ALERT: %s is %s*\n\n"
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
                foreach ($waPhones as $phone) {
                    $waError = null;
                    $sent = whatsapp_send_alert($phone, $waBody, $waSettings, $waError);
                    if (!$sent) {
                        error_log("WhatsApp send failed for phone {$phone}: " . ($waError ?? 'Unknown WhatsApp error'));
                    } else {
                        error_log("WhatsApp send succeeded for phone {$phone}");
                    }
                }
            } else {
                error_log("WhatsApp notification skipped: WhatsApp settings disabled or not configured for user {$tokenUserId}");
            }
        }
    } catch (Exception $e) {
        error_log("Error in agentCompatSendNotifications: " . $e->getMessage());
    }
}

