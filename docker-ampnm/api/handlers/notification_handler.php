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
        $stmt = $pdo->prepare("SELECT host, port, username, password, encryption, from_email, from_name FROM smtp_settings WHERE user_id = ?");
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

            if (empty($host) || empty($port) || empty($username) || empty($from_email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Host, Port, Username, and From Email are required.']);
                exit;
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
                $sql = "UPDATE smtp_settings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name, $current_user_id]);
            } else {
                $sql = "INSERT INTO smtp_settings (user_id, host, port, username, password, encryption, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$current_user_id, $host, $port, $username, $password, $encryption, $from_email, $from_name]);
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

        $stmt = $pdo->prepare("SELECT host, port, username, password, encryption, from_email, from_name FROM smtp_settings WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$smtpSettings) {
            http_response_code(400);
            echo json_encode(['error' => 'SMTP settings are not configured yet.']);
            exit;
        }

        $subject = 'AMPNM SMTP Test Email';
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
}
?>
