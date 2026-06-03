<?php
/**
 * Telegram Bot helper for AMPNM.
 * Handles sending alerts and processing incoming webhook queries.
 */

/**
 * Sends a Telegram message alert to a specific Chat ID.
 *
 * @param string $chat_id Telegram Chat ID of the recipient.
 * @param string $message The text message to send.
 * @param string|null $bot_token Optional Telegram Bot Token (if null, fetched from DB).
 * @param string|null &$error Output parameter containing any error message.
 * @return bool True on success, false on failure.
 */
function telegram_send_alert($chat_id, $message, $bot_token = null, &$error = null) {
    try {
        if (!function_exists('getDbConnection')) {
            require_once __DIR__ . '/../config.php';
        }

        // Fetch bot token from database if not passed
        if (empty($bot_token)) {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT bot_token, enabled FROM telegram_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings || empty($settings['bot_token'])) {
                $error = "Telegram Bot Token is not configured in settings.";
                return false;
            }
            if (isset($settings['enabled']) && !$settings['enabled']) {
                $error = "Telegram notifications are disabled in settings.";
                return false;
            }
            $bot_token = $settings['bot_token'];
        }

        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        $payload = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $error = "cURL error: " . $curlErr;
            return false;
        }

        $resDecoded = json_decode($response, true);
        if ($httpCode >= 400 || !($resDecoded['ok'] ?? false)) {
            $error = $resDecoded['description'] ?? "HTTP error code: " . $httpCode;
            return false;
        }

        return true;
    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
        return false;
    }
}

/**
 * Calculates duration in the current status by checking status transition logs.
 *
 * @param PDO $pdo
 * @param int $deviceId
 * @param string $currentStatus
 * @return string Human readable duration string (e.g. "2d 5h 12m").
 */
function getDeviceStatusDuration($pdo, $deviceId, $currentStatus) {
    // Find the latest status log for this device that does NOT match the current status
    $stmt = $pdo->prepare("
        SELECT created_at 
        FROM device_status_logs 
        WHERE device_id = ? AND status != ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$deviceId, $currentStatus]);
    $lastTransitionLog = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastTransitionLog) {
        $startTime = strtotime($lastTransitionLog['created_at']);
    } else {
        // Fallback: find the first log matching the current status
        $stmtFallback = $pdo->prepare("
            SELECT created_at 
            FROM device_status_logs 
            WHERE device_id = ? AND status = ? 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmtFallback->execute([$deviceId, $currentStatus]);
        $firstStatusLog = $stmtFallback->fetch(PDO::FETCH_ASSOC);
        if ($firstStatusLog) {
            $startTime = strtotime($firstStatusLog['created_at']);
        } else {
            // Fallback to the device's created_at time
            $stmtDev = $pdo->prepare("SELECT created_at FROM devices WHERE id = ?");
            $stmtDev->execute([$deviceId]);
            $dev = $stmtDev->fetch(PDO::FETCH_ASSOC);
            $startTime = $dev ? strtotime($dev['created_at']) : time();
        }
    }

    $diffSeconds = time() - $startTime;
    if ($diffSeconds < 0) $diffSeconds = 0;

    $days = floor($diffSeconds / 86400);
    $hours = floor(($diffSeconds % 86400) / 3600);
    $minutes = floor(($diffSeconds % 3600) / 60);
    $seconds = $diffSeconds % 60;

    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";
    if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";

    return implode(' ', $parts);
}

/**
 * Handles the Telegram incoming Webhook updates.
 *
 * @param PDO $pdo DB connection.
 */
function handleTelegramWebhook($pdo) {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if (empty($update) || !isset($update['message'])) {
        return;
    }

    $message = $update['message'];
    $chatId = $message['chat']['id'] ?? null;
    $text = trim($message['text'] ?? '');

    if (empty($chatId) || empty($text)) {
        return;
    }

    // Command Router
    if ($text === '/start') {
        $reply = "<b>Welcome to AMPNM Monitor Bot!</b>\n\n"
               . "Your Telegram Chat ID is: <code>" . htmlspecialchars($chatId) . "</code>\n\n"
               . "To receive notifications in this chat, copy this Chat ID and subscribe this chat to your devices under <b>Administration -> Telegram Notifications</b> in the AMPNM panel.";
        telegram_send_alert($chatId, $reply);
        return;
    }

    // Authenticate: search for a subscription matching this chat_id to locate the owner user_id
    $stmtUser = $pdo->prepare("SELECT DISTINCT user_id FROM device_telegram_subscriptions WHERE chat_id = ? LIMIT 1");
    $stmtUser->execute([$chatId]);
    $userId = $stmtUser->fetchColumn();

    if (!$userId) {
        $reply = "🚫 <b>Access Denied</b>\n\n"
               . "Your Telegram Chat ID: <code>" . htmlspecialchars($chatId) . "</code> is not registered in AMPNM yet.\n"
               . "Please register your Chat ID in the AMPNM settings to execute commands.";
        telegram_send_alert($chatId, $reply);
        return;
    }

    if ($text === '/status' || $text === '/devices') {
        // Fetch all devices for this user
        $stmtDevs = $pdo->prepare("
            SELECT d.id, d.name, d.ip, d.status, m.name as map_name 
            FROM devices d 
            LEFT JOIN maps m ON d.map_id = m.id 
            WHERE d.user_id = ? 
            ORDER BY d.status DESC, d.name ASC
        ");
        $stmtDevs->execute([$userId]);
        $devices = $stmtDevs->fetchAll(PDO::FETCH_ASSOC);

        if (empty($devices)) {
            telegram_send_alert($chatId, "ℹ️ No devices configured in your dashboard.");
            return;
        }

        $reply = "🖥️ <b>AMPNM Device Status Overview:</b>\n\n";
        foreach ($devices as $dev) {
            $emoji = '⚪';
            switch ($dev['status']) {
                case 'online': $emoji = '🟢'; break;
                case 'offline': $emoji = '🔴'; break;
                case 'warning': $emoji = '🟡'; break;
                case 'critical': $emoji = '🚨'; break;
            }
            
            $duration = getDeviceStatusDuration($pdo, $dev['id'], $dev['status']);
            $reply .= "{$emoji} <b>" . htmlspecialchars($dev['name']) . "</b> (" . htmlspecialchars($dev['ip'] ?? 'N/A') . ")\n"
                    . "   Status: <i>" . htmlspecialchars($dev['status']) . "</i> (" . $duration . ")\n";
            if (!empty($dev['map_name'])) {
                $reply .= "   Map: " . htmlspecialchars($dev['map_name']) . "\n";
            }
            $reply .= "\n";
        }

        telegram_send_alert($chatId, $reply);
        return;
    }

    if (preg_match('/^\/device\s+(.+)$/i', $text, $matches)) {
        $queryName = trim($matches[1]);
        
        // Find device by name or IP for this user
        $stmtDev = $pdo->prepare("
            SELECT d.*, m.name as map_name 
            FROM devices d 
            LEFT JOIN maps m ON d.map_id = m.id 
            WHERE d.user_id = ? AND (d.name LIKE ? OR d.ip = ?) 
            LIMIT 1
        ");
        $stmtDev->execute([$userId, '%' . $queryName . '%', $queryName]);
        $dev = $stmtDev->fetch(PDO::FETCH_ASSOC);

        if (!$dev) {
            telegram_send_alert($chatId, "❌ Device <b>" . htmlspecialchars($queryName) . "</b> not found.");
            return;
        }

        $emoji = '⚪';
        switch ($dev['status']) {
            case 'online': $emoji = '🟢'; break;
            case 'offline': $emoji = '🔴'; break;
            case 'warning': $emoji = '🟡'; break;
            case 'critical': $emoji = '🚨'; break;
        }

        $duration = getDeviceStatusDuration($pdo, $dev['id'], $dev['status']);
        $latency = ($dev['last_avg_time'] !== null) ? $dev['last_avg_time'] . " ms" : "N/A";
        $ttl = ($dev['last_ttl'] !== null) ? $dev['last_ttl'] : "N/A";

        $reply = "📊 <b>Device Details:</b>\n\n"
               . "Name: <b>" . htmlspecialchars($dev['name']) . "</b>\n"
               . "IP: <code>" . htmlspecialchars($dev['ip'] ?? 'N/A') . "</code>\n"
               . "Port: " . ($dev['check_port'] ?? 'N/A') . " (Method: " . htmlspecialchars($dev['monitor_method']) . ")\n"
               . "Status: {$emoji} <b>" . htmlspecialchars(strtoupper($dev['status'])) . "</b>\n"
               . "Duration: " . $duration . "\n"
               . "Last Seen: " . ($dev['last_seen'] ?? 'Never') . "\n"
               . "Ping Latency: " . $latency . "\n"
               . "TTL: " . $ttl . "\n";
        
        if (!empty($dev['map_name'])) {
            $reply .= "Map Location: " . htmlspecialchars($dev['map_name']) . "\n";
        }
        if (!empty($dev['description'])) {
            $reply .= "Description: " . htmlspecialchars($dev['description']) . "\n";
        }

        telegram_send_alert($chatId, $reply);
        return;
    }

    // Default reply
    $help = "❓ <b>Available Commands:</b>\n\n"
          . "/status - List all monitored devices\n"
          . "/device &lt;name&gt; - View detailed status of a specific device\n"
          . "/start - Show bot connection information";
    telegram_send_alert($chatId, $help);
}
