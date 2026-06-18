<?php
/**
 * Sends an SMS alert using the Alpha SMS Gateway (alphasms.biz).
 * Checks the database settings table or fallback environment variables.
 *
 * @param string $recipient_phone Phone number of the recipient.
 * @param string $message The text message to send.
 * @param string|null &$smsError Output parameter containing any error message.
 * @return bool True on success, false on failure.
 */
function sms_send_alert($recipient_phone, $message, &$settingsOrError = null, &$smsError = null) {
    try {
        // Ensure database connection helper is loaded
        if (!function_exists('getDbConnection')) {
            require_once __DIR__ . '/../config.php';
        }

        $pdo = getDbConnection();
        
        $settings = null;
        $actualErrorRef = null;
        if (is_array($settingsOrError)) {
            $settings = $settingsOrError;
            $actualErrorRef = &$smsError;
        } else {
            $actualErrorRef = &$settingsOrError;
        }

        // Try to fetch settings from the database
        $username = null;
        $apiKey = null;
        $senderId = null;
        $enabled = true;

        if ($settings !== null) {
            $username = $settings['username'] ?? null;
            $apiKey = $settings['api_key'] ?? null;
            $senderId = $settings['sender_id'] ?? null;
            $enabled = isset($settings['enabled']) ? (bool)$settings['enabled'] : true;
            $dbSettings = $settings;
        } else {
            try {
                $stmt = $pdo->query("SELECT username, api_key, sender_id, enabled FROM sms_settings ORDER BY id ASC LIMIT 1");
                $dbSettings = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dbSettings) {
                    $username = $dbSettings['username'] ?? null;
                    $apiKey = $dbSettings['api_key'] ?? null;
                    $senderId = $dbSettings['sender_id'] ?? null;
                    $enabled = isset($dbSettings['enabled']) ? (bool)$dbSettings['enabled'] : true;
                }
            } catch (PDOException $e) {
                // Table might not exist yet if setup hasn't run, fallback silently to env
                error_log("sms_settings query failed (setup may not have run): " . $e->getMessage());
                $dbSettings = null;
            }
        }
        
        // Fallback to environment variables if not set in DB
        if (empty($username)) {
            $username = getenv('ALPHA_SMS_USERNAME') ?: '';
        }
        if (empty($apiKey)) {
            $apiKey = getenv('ALPHA_SMS_API_KEY') ?: '';
        }
        if (empty($senderId)) {
            $senderId = getenv('ALPHA_SMS_SENDER_ID') ?: '';
        }
        
        // SMS alerts enabled check (fall back to env variable)
        $envEnabled = getenv('SMS_ALERTS_ENABLED');
        $smsEnabled = ($envEnabled !== false && $envEnabled !== '') ? ($envEnabled !== '0') : true;
        if ($dbSettings) {
            $smsEnabled = $enabled;
        }

        if (!$smsEnabled) {
            $actualErrorRef = "SMS alerts are disabled in configuration.";
            return false;
        }

        if (empty($username) || empty($apiKey)) {
            $actualErrorRef = "Alpha SMS API credentials (username and API key/hash) are not configured.";
            return false;
        }

        // Clean phone number: remove non-digits
        $phone = preg_replace('/[^0-9]/', '', $recipient_phone);
        
        // Standardize BD mobile numbers to format like 8801XXXXXXXXX
        if (strlen($phone) === 11 && strpos($phone, '01') === 0) {
            $phone = '88' . $phone;
        }

        // Base URL for alphasms.biz API
        $url = "https://alphasms.biz/index.php";
        
        $params = [
            'app' => 'ws',
            'u'   => $username,
            'h'   => $apiKey,
            'op'  => 'pv',
            'to'  => $phone,
            'msg' => $message
        ];

        // Pass sender ID if present (using both parameters common in Alpha SMS APIs)
        if (!empty($senderId)) {
            $params['sig'] = $senderId;
            $params['senderid'] = $senderId;
        }

        // Initialize cURL GET request
        $queryUrl = $url . '?' . http_build_query($params);
        $ch = curl_init($queryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $actualErrorRef = "cURL error: " . $curlErr;
            return false;
        }

        if ($httpCode >= 400) {
            $actualErrorRef = "HTTP error code: " . $httpCode . ", Response: " . $response;
            return false;
        }

        // If it starts with "ERR" or "Error", it's a failure.
        $trimmedResponse = trim($response);
        if (empty($trimmedResponse) || stripos($trimmedResponse, 'ERR') !== false || stripos($trimmedResponse, 'ERROR') !== false) {
            $actualErrorRef = "Gateway response: " . $response;
            return false;
        }

        return true;
    } catch (Exception $e) {
        $actualErrorRef = "Exception: " . $e->getMessage();
        return false;
    }
}
