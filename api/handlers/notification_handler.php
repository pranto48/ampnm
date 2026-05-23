<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
require_once __DIR__ . '/../../includes/smtp_mailer.php';

switch ($action) {
    case 'get_device_subscriptions':
        $device_id = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
        if ($device_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID is required.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, recipient_email, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical FROM device_email_subscriptions WHERE user_id = ? AND device_id = ? ORDER BY recipient_email ASC");
        $stmt->execute([$current_user_id, $device_id]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($subscriptions);
        break;

    case 'get_smtp_settings':
        $stmt = $pdo->prepare("SELECT host, port, username, password, encryption, from_email, from_name, bind_ip, reply_to_email, subject_prefix, connection_timeout_seconds, max_emails_per_hour, allow_invalid_certs FROM smtp_settings WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        // Mask password for security, or don't send it at all if not needed by frontend
        if ($settings && isset($settings['password'])) {
            $settings['password'] = '********'; // Mask password
        }
        echo json_encode($settings ?: []);
        break;

    case 'save_smtp_settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $input['host'] ?? '';
            $port = $input['port'] ?? '';
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? ''; // This might be masked, handle carefully
            $encryption = $input['encryption'] ?? 'tls';
            $from_email = $input['from_email'] ?? '';
            $from_name = $input['from_name'] ?? null;
            $bind_ip = trim((string)($input['bind_ip'] ?? ''));
            $reply_to_email = trim((string)($input['reply_to_email'] ?? ''));
            $subject_prefix = trim((string)($input['subject_prefix'] ?? '[AMPNM]'));
            $connection_timeout_seconds = (int)($input['connection_timeout_seconds'] ?? 20);
            $max_emails_per_hour = (int)($input['max_emails_per_hour'] ?? 240);
            $allow_invalid_certs = !empty($input['allow_invalid_certs']) ? 1 : 0;

            if (empty($host) || empty($port) || empty($username) || empty($from_email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Host, Port, Username, and From Email are required.']);
                exit;
            }
            if ($reply_to_email !== '' && !filter_var($reply_to_email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Reply-To email is invalid.']);
                exit;
            }
            if ($bind_ip !== '' && !filter_var($bind_ip, FILTER_VALIDATE_IP)) {
                http_response_code(400);
                echo json_encode(['error' => 'Mail outbound IP must be a valid IP address.']);
                exit;
            }
            if ($connection_timeout_seconds < 5 || $connection_timeout_seconds > 120) {
                http_response_code(400);
                echo json_encode(['error' => 'Connection timeout must be between 5 and 120 seconds.']);
                exit;
            }
            if ($max_emails_per_hour < 1 || $max_emails_per_hour > 10000) {
                http_response_code(400);
                echo json_encode(['error' => 'Max emails per hour must be between 1 and 10000.']);
                exit;
            }
            $reply_to_email = $reply_to_email === '' ? null : $reply_to_email;
            $bind_ip = $bind_ip === '' ? null : $bind_ip;
            if ($subject_prefix === '') {
                $subject_prefix = '[AMPNM]';
            }

            // Check if settings already exist for this user
            $stmt = $pdo->prepare("SELECT id, password FROM smtp_settings WHERE user_id = ?");
            $stmt->execute([$current_user_id]);
            $existingSettings = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSettings) {
                // If password is '********', it means it wasn't changed, so keep the old one
                if ($password === '********') {
                    $password = $existingSettings['password'];
                }
                $sql = "UPDATE smtp_settings
                        SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ?, bind_ip = ?, reply_to_email = ?, subject_prefix = ?, connection_timeout_seconds = ?, max_emails_per_hour = ?, allow_invalid_certs = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name, $bind_ip, $reply_to_email, $subject_prefix, $connection_timeout_seconds, $max_emails_per_hour, $allow_invalid_certs, $current_user_id]);
            } else {
                $sql = "INSERT INTO smtp_settings (user_id, host, port, username, password, encryption, from_email, from_name, bind_ip, reply_to_email, subject_prefix, connection_timeout_seconds, max_emails_per_hour, allow_invalid_certs)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$current_user_id, $host, $port, $username, $password, $encryption, $from_email, $from_name, $bind_ip, $reply_to_email, $subject_prefix, $connection_timeout_seconds, $max_emails_per_hour, $allow_invalid_certs]);
            }
            echo json_encode(['success' => true, 'message' => 'SMTP settings saved successfully.']);
        }
        break;

    case 'send_test_email':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            exit;
        }

        $recipient_email = trim((string)($input['recipient_email'] ?? ''));
        if ($recipient_email === '' || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'A valid recipient email is required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT host, port, username, password, encryption, from_email, from_name, bind_ip, reply_to_email, subject_prefix, connection_timeout_seconds, max_emails_per_hour, allow_invalid_certs FROM smtp_settings WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$smtpSettings) {
            http_response_code(400);
            echo json_encode(['error' => 'SMTP settings are not configured yet.']);
            exit;
        }

        $subjectPrefix = trim((string)($smtpSettings['subject_prefix'] ?? '[AMPNM]'));
        $subject = ($subjectPrefix !== '' ? $subjectPrefix . ' ' : '') . 'SMTP Test Email';
        $body = "This is a test email from AMPNM.\n\nSent at: " . gmdate('Y-m-d H:i:s') . " UTC";
        $smtpError = null;
        $sent = smtp_send_mail($smtpSettings, $recipient_email, $subject, $body, $smtpError);

        if (!$sent) {
            http_response_code(500);
            echo json_encode(['error' => 'Test email failed to send.', 'details' => $smtpError ?? 'Unknown SMTP error']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => "Test email sent to {$recipient_email}."]);
        break;

    case 'get_all_devices_for_subscriptions':
        // Get all devices for the current user, including their map name
        $stmt = $pdo->prepare("SELECT d.id, d.name, d.ip, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.user_id = ? ORDER BY d.name ASC");
        $stmt->execute([$current_user_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'save_device_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : null; // For updating existing subscription
            $device_id = isset($input['device_id']) ? (int)$input['device_id'] : 0;
            $recipient_email = trim((string)($input['recipient_email'] ?? ''));
            $notify_on_online = !empty($input['notify_on_online']) ? 1 : 0;
            $notify_on_offline = !empty($input['notify_on_offline']) ? 1 : 0;
            $notify_on_warning = !empty($input['notify_on_warning']) ? 1 : 0;
            $notify_on_critical = !empty($input['notify_on_critical']) ? 1 : 0;

            if ($device_id <= 0 || $recipient_email === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and Recipient Email are required.']);
                exit;
            }

            if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Recipient email is invalid.']);
                exit;
            }

            if (!$notify_on_online && !$notify_on_offline && !$notify_on_warning && !$notify_on_critical) {
                http_response_code(400);
                echo json_encode(['error' => 'Enable at least one notification type.']);
                exit;
            }

            $deviceStmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ? LIMIT 1");
            $deviceStmt->execute([$device_id, $current_user_id]);
            if (!$deviceStmt->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found.']);
                exit;
            }

            if ($id) {
                $sql = "UPDATE device_email_subscriptions
                        SET recipient_email = ?, notify_on_online = ?, notify_on_offline = ?, notify_on_warning = ?, notify_on_critical = ?
                        WHERE id = ? AND user_id = ? AND device_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$recipient_email, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical, $id, $current_user_id, $device_id]);
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Subscription not found for update.']);
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Subscription updated successfully.']);
                break;
            }

            $sql = "INSERT INTO device_email_subscriptions (user_id, device_id, recipient_email, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        notify_on_online = VALUES(notify_on_online),
                        notify_on_offline = VALUES(notify_on_offline),
                        notify_on_warning = VALUES(notify_on_warning),
                        notify_on_critical = VALUES(notify_on_critical),
                        created_at = created_at";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_user_id, $device_id, $recipient_email, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical]);

            $fetchStmt = $pdo->prepare("SELECT id FROM device_email_subscriptions WHERE user_id = ? AND device_id = ? AND recipient_email = ? LIMIT 1");
            $fetchStmt->execute([$current_user_id, $device_id, $recipient_email]);
            $subscriptionId = $fetchStmt->fetchColumn();
            echo json_encode([
                'success' => true,
                'message' => 'Subscription saved successfully.',
                'id' => $subscriptionId ? (int)$subscriptionId : null
            ]);
        }
        break;

    case 'delete_device_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Subscription ID is required.']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM device_email_subscriptions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true, 'message' => 'Subscription deleted successfully.']);
        }
        break;

    case 'get_sms_settings':
        $stmt = $pdo->prepare("SELECT username, api_key, sender_id, enabled, cooldown_minutes FROM sms_settings WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        // Mask API key for security
        if ($settings && isset($settings['api_key'])) {
            $settings['api_key'] = '********'; // Mask key
        }
        
        // If not found in database, check environment variables as fallbacks
        if (!$settings) {
            $settings = [
                'username' => getenv('ALPHA_SMS_USERNAME') ?: '',
                'api_key' => getenv('ALPHA_SMS_API_KEY') ? '********' : '',
                'sender_id' => getenv('ALPHA_SMS_SENDER_ID') ?: '',
                'enabled' => getenv('SMS_ALERTS_ENABLED') !== '0' ? 1 : 0,
                'cooldown_minutes' => getenv('SMS_COOLDOWN_MINUTES') !== false ? (int)getenv('SMS_COOLDOWN_MINUTES') : 30
            ];
        }
        echo json_encode($settings);
        break;

    case 'save_sms_settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string)($input['username'] ?? ''));
            $api_key = trim((string)($input['api_key'] ?? ''));
            $sender_id = trim((string)($input['sender_id'] ?? ''));
            $enabled = !empty($input['enabled']) ? 1 : 0;
            $cooldown_minutes = isset($input['cooldown_minutes']) ? (int)$input['cooldown_minutes'] : 30;

            if (empty($username) || empty($api_key)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username and API Key/Hash are required.']);
                exit;
            }

            // Check if settings already exist
            $stmt = $pdo->prepare("SELECT id, api_key FROM sms_settings WHERE user_id = ?");
            $stmt->execute([$current_user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($api_key === '********') {
                    $api_key = $existing['api_key'];
                }
                $stmt = $pdo->prepare("UPDATE sms_settings SET username = ?, api_key = ?, sender_id = ?, enabled = ?, cooldown_minutes = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt->execute([$username, $api_key, $sender_id, $enabled, $cooldown_minutes, $current_user_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sms_settings (user_id, username, api_key, sender_id, enabled, cooldown_minutes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user_id, $username, $api_key, $sender_id, $enabled, $cooldown_minutes]);
            }
            echo json_encode(['success' => true, 'message' => 'SMS settings saved successfully.']);
        }
        break;

    case 'send_test_sms':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            exit;
        }

        $recipient_phone = trim((string)($input['recipient_phone'] ?? ''));
        if ($recipient_phone === '') {
            http_response_code(400);
            echo json_encode(['error' => 'A recipient phone number is required.']);
            exit;
        }

        require_once __DIR__ . '/../../includes/sms_sender.php';
        
        $body = "Test SMS from AMPNM monitor. Sent at: " . date('Y-m-d H:i:s');
        $smsError = null;
        $sent = sms_send_alert($recipient_phone, $body, $smsError);

        if (!$sent) {
            http_response_code(500);
            echo json_encode(['error' => 'Test SMS failed to send.', 'details' => $smsError ?? 'Unknown error']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => "Test SMS sent to {$recipient_phone}."]);
        break;

    case 'get_device_sms_subscriptions':
        $device_id = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
        if ($device_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID is required.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, recipient_phone, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical FROM device_sms_subscriptions WHERE user_id = ? AND device_id = ? ORDER BY recipient_phone ASC");
        $stmt->execute([$current_user_id, $device_id]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($subscriptions);
        break;

    case 'save_device_sms_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : null;
            $device_id = isset($input['device_id']) ? (int)$input['device_id'] : 0;
            $recipient_phone = trim((string)($input['recipient_phone'] ?? ''));
            $notify_on_online = !empty($input['notify_on_online']) ? 1 : 0;
            $notify_on_offline = !empty($input['notify_on_offline']) ? 1 : 0;
            $notify_on_warning = !empty($input['notify_on_warning']) ? 1 : 0;
            $notify_on_critical = !empty($input['notify_on_critical']) ? 1 : 0;

            if ($device_id <= 0 || $recipient_phone === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and Recipient Phone are required.']);
                exit;
            }

            if (!$notify_on_online && !$notify_on_offline && !$notify_on_warning && !$notify_on_critical) {
                http_response_code(400);
                echo json_encode(['error' => 'Enable at least one notification type.']);
                exit;
            }

            $deviceStmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ? LIMIT 1");
            $deviceStmt->execute([$device_id, $current_user_id]);
            if (!$deviceStmt->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found.']);
                exit;
            }

            if ($id) {
                $sql = "UPDATE device_sms_subscriptions
                        SET recipient_phone = ?, notify_on_online = ?, notify_on_offline = ?, notify_on_warning = ?, notify_on_critical = ?
                        WHERE id = ? AND user_id = ? AND device_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$recipient_phone, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical, $id, $current_user_id, $device_id]);
                echo json_encode(['success' => true, 'message' => 'SMS subscription updated successfully.']);
                break;
            }

            $sql = "INSERT INTO device_sms_subscriptions (user_id, device_id, recipient_phone, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        notify_on_online = VALUES(notify_on_online),
                        notify_on_offline = VALUES(notify_on_offline),
                        notify_on_warning = VALUES(notify_on_warning),
                        notify_on_critical = VALUES(notify_on_critical),
                        created_at = created_at";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_user_id, $device_id, $recipient_phone, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical]);

            echo json_encode(['success' => true, 'message' => 'SMS subscription saved successfully.']);
        }
        break;

    case 'delete_device_sms_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Subscription ID is required.']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM device_sms_subscriptions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true, 'message' => 'SMS subscription deleted successfully.']);
        }
        break;
}
?>
