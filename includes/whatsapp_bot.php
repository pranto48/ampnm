<?php
/**
 * WhatsApp Bot helper for AMPNM.
 * Handles sending alerts and processing incoming webhook queries.
 */

/**
 * Sends a WhatsApp message alert to a specific recipient phone number.
 *
 * @param string $recipient Recipient phone number (e.g. +8801712345678).
 * @param string $message The text message to send.
 * @param array|null $settings Optional WhatsApp settings (if null, fetched from DB).
 * @param string|null &$error Output parameter containing any error message.
 * @return bool True on success, false on failure.
 */
function whatsapp_send_alert($recipient, $message, $settings = null, &$error = null) {
    try {
        if (!function_exists('getDbConnection')) {
            require_once __DIR__ . '/../config.php';
        }

        // Fetch settings from database if not passed
        if (empty($settings)) {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT provider, api_url, token, phone_number, enabled FROM whatsapp_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings || empty($settings['token'])) {
                $error = "WhatsApp API credentials are not configured in settings.";
                return false;
            }
            if (isset($settings['enabled']) && !$settings['enabled']) {
                $error = "WhatsApp notifications are disabled in settings.";
                return false;
            }
        }

        $provider = $settings['provider'] ?? 'twilio';
        $token = $settings['token'] ?? '';
        $apiUrl = $settings['api_url'] ?? '';
        $senderPhone = $settings['phone_number'] ?? '';

        if (empty($token)) {
            $error = "WhatsApp API Key/Token is missing.";
            return false;
        }

        if ($provider === 'twilio') {
            // For Twilio, api_url stores the Twilio Account SID, and phone_number is the Twilio WhatsApp sender
            $accountSid = $apiUrl;
            if (empty($accountSid)) {
                $error = "Twilio Account SID is missing in API URL field.";
                return false;
            }

            $url = "https://api.twilio.com/2010-04-01/Accounts/" . $accountSid . "/Messages.json";
            
            // Format numbers for Twilio WhatsApp (must start with "whatsapp:")
            $to = $recipient;
            if (strpos($to, 'whatsapp:') !== 0) {
                $to = 'whatsapp:' . (strpos($to, '+') === 0 ? '' : '+') . $to;
            }
            $from = $senderPhone;
            if (strpos($from, 'whatsapp:') !== 0) {
                $from = 'whatsapp:' . (strpos($from, '+') === 0 ? '' : '+') . $from;
            }

            $payload = [
                'To' => $to,
                'From' => $from,
                'Body' => $message
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_USERPWD, $accountSid . ":" . $token);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                $error = "Twilio cURL error: " . $curlErr;
                return false;
            }

            $resDecoded = json_decode($response, true);
            if ($httpCode >= 400) {
                $error = $resDecoded['message'] ?? "Twilio HTTP error: " . $httpCode;
                return false;
            }

            return true;

        } elseif ($provider === 'ultramsg') {
            // For Ultramsg, api_url is the Instance ID or custom base URL, and token is the Instance Token.
            $instanceId = $apiUrl;
            
            // Normalize Ultramsg API endpoint URL
            if (strpos($instanceId, 'http') === 0) {
                $url = rtrim($instanceId, '/') . '/messages/chat';
            } else {
                $url = "https://api.ultramsg.com/" . $instanceId . "/messages/chat";
            }

            // Ultramsg expects just a raw number (e.g. +8801712345678 or 8801712345678)
            $to = preg_replace('/[^\+0-9]/', '', $recipient);

            $payload = [
                'token' => $token,
                'to' => $to,
                'body' => $message
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                $error = "Ultramsg cURL error: " . $curlErr;
                return false;
            }

            $resDecoded = json_decode($response, true);
            if ($httpCode >= 400 || isset($resDecoded['error'])) {
                $error = $resDecoded['error']['message'] ?? $resDecoded['error'] ?? "Ultramsg HTTP error: " . $httpCode;
                return false;
            }

            return true;
        }

        $error = "Unsupported WhatsApp provider: " . $provider;
        return false;

    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
        return false;
    }
}

/**
 * Handles incoming WhatsApp webhook requests from Twilio or Ultramsg.
 *
 * @param PDO $pdo DB connection.
 */
function handleWhatsappWebhook($pdo) {
    // 1. Read input payload (can be JSON for Ultramsg, or form-urlencoded POST for Twilio)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $rawInput = file_get_contents('php://input');

    $sender = '';
    $text = '';

    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode($rawInput, true);
        
        // Ultramsg format
        if (isset($input['data']['from'])) {
            $sender = $input['data']['from']; // e.g. 17477777777@c.us
            $text = $input['data']['body'] ?? '';
        }
    } else {
        // Twilio form-urlencoded POST format
        $sender = $_POST['From'] ?? ''; // e.g. whatsapp:+17477777777
        $text = $_POST['Body'] ?? '';
    }

    $sender = trim($sender);
    $text = trim($text);

    if (empty($sender) || empty($text)) {
        return;
    }

    // Clean phone number: remove non-digits
    // We strip "whatsapp:" and "@c.us" by simply keeping only digits.
    $cleanSender = preg_replace('/[^0-9]/', '', $sender);

    if (empty($cleanSender)) {
        return;
    }

    // Authenticate: search for a subscriber matching this phone number (ignoring + and spaces)
    $stmtUser = $pdo->prepare("
        SELECT DISTINCT user_id 
        FROM device_whatsapp_subscriptions 
        WHERE REPLACE(REPLACE(recipient_phone, '+', ''), ' ', '') = ? 
        LIMIT 1
    ");
    $stmtUser->execute([$cleanSender]);
    $userId = $stmtUser->fetchColumn();

    // Fetch User WhatsApp settings to send reply
    $stmtSettings = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE user_id = ? LIMIT 1");
    $stmtSettings->execute([$userId ? $userId : 1]); // Fallback to user ID 1 settings for unauthenticated welcome
    $userSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC);

    if (!$userSettings) {
        // Can't reply if we don't have configured settings at all
        return;
    }

    if (!$userId) {
        // Return welcome / registration help
        $reply = "*Welcome to AMPNM WhatsApp Monitor!*\n\n"
               . "Your phone number is detected as: *" . $cleanSender . "*\n\n"
               . "This number is not registered for notifications in AMPNM.\n"
               . "Please add this number under *Administration -> WhatsApp Notifications* in your dashboard to enable alerts and query commands.";
        whatsapp_send_alert($sender, $reply, $userSettings);
        return;
    }

    // Command Router
    if ($text === '/start') {
        $reply = "*Welcome to AMPNM WhatsApp Monitor!*\n\n"
               . "Your phone number is registered. You can query status using the following commands:\n"
               . "- `/status` or `/devices`: Get status of all devices\n"
               . "- `/device <name>`: Get detailed info about a specific device";
        whatsapp_send_alert($sender, $reply, $userSettings);
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
            whatsapp_send_alert($sender, "ℹ️ No devices configured in your dashboard.", $userSettings);
            return;
        }

        $reply = "*AMPNM Device Status Overview:*\n\n";
        foreach ($devices as $dev) {
            $emoji = '⚪';
            switch ($dev['status']) {
                case 'online': $emoji = '🟢'; break;
                case 'offline': $emoji = '🔴'; break;
                case 'warning': $emoji = '🟡'; break;
                case 'critical': $emoji = '🚨'; break;
            }
            
            // Re-use Telegram helper logic for duration calculation
            if (!function_exists('getDeviceStatusDuration')) {
                require_once __DIR__ . '/telegram_bot.php';
            }
            $duration = getDeviceStatusDuration($pdo, $dev['id'], $dev['status']);
            $reply .= "{$emoji} *" . $dev['name'] . "* (" . ($dev['ip'] ?? 'N/A') . ")\n"
                    . "   Status: _" . $dev['status'] . "_ (" . $duration . ")\n";
            if (!empty($dev['map_name'])) {
                $reply .= "   Map: " . $dev['map_name'] . "\n";
            }
            $reply .= "\n";
        }

        whatsapp_send_alert($sender, $reply, $userSettings);
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
            whatsapp_send_alert($sender, "❌ Device *" . $queryName . "* not found.", $userSettings);
            return;
        }

        $emoji = '⚪';
        switch ($dev['status']) {
            case 'online': $emoji = '🟢'; break;
            case 'offline': $emoji = '🔴'; break;
            case 'warning': $emoji = '🟡'; break;
            case 'critical': $emoji = '🚨'; break;
        }

        if (!function_exists('getDeviceStatusDuration')) {
            require_once __DIR__ . '/telegram_bot.php';
        }
        $duration = getDeviceStatusDuration($pdo, $dev['id'], $dev['status']);
        $latency = ($dev['last_avg_time'] !== null) ? $dev['last_avg_time'] . " ms" : "N/A";
        $ttl = ($dev['last_ttl'] !== null) ? $dev['last_ttl'] : "N/A";

        $reply = "📊 *Device Details:*\n\n"
               . "Name: *" . $dev['name'] . "*\n"
               . "IP: `" . ($dev['ip'] ?? 'N/A') . "`\n"
               . "Port: " . ($dev['check_port'] ?? 'N/A') . " (Method: " . $dev['monitor_method'] . ")\n"
               . "Status: {$emoji} *" . strtoupper($dev['status']) . "*\n"
               . "Duration: " . $duration . "\n"
               . "Last Seen: " . ($dev['last_seen'] ?? 'Never') . "\n"
               . "Ping Latency: " . $latency . "\n"
               . "TTL: " . $ttl . "\n";
        
        if (!empty($dev['map_name'])) {
            $reply .= "Map Location: " . $dev['map_name'] . "\n";
        }
        if (!empty($dev['description'])) {
            $reply .= "Description: " . $dev['description'] . "\n";
        }

        whatsapp_send_alert($sender, $reply, $userSettings);
        return;
    }

    // Default reply
    $help = "❓ *Available Commands:*\n\n"
          . "`/status` - List all monitored devices\n"
          . "`/device <name>` - View detailed status of a specific device\n"
          . "`/start` - Show welcome message";
    whatsapp_send_alert($sender, $help, $userSettings);
}
