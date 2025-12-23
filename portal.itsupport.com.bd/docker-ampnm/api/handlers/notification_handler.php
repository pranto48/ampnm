<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];

switch ($action) {
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

    case 'get_all_devices_for_subscriptions':
        // Get all devices for the current user, including their map name
        $stmt = $pdo->prepare("SELECT d.id, d.name, d.ip, m.name as map_name FROM devices d LEFT JOIN maps m ON d.map_id = m.id WHERE d.user_id = ? ORDER BY d.name ASC");
        $stmt->execute([$current_user_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($devices);
        break;

    case 'get_device_subscriptions':
        $device_id = $_GET['device_id'] ?? null;
        if (!$device_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID is required.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, recipient_email, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical FROM device_email_subscriptions WHERE user_id = ? AND device_id = ? ORDER BY recipient_email ASC");
        $stmt->execute([$current_user_id, $device_id]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($subscriptions);
        break;

    case 'save_device_subscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null; // For updating existing subscription
            $device_id = $input['device_id'] ?? null;
            $recipient_email = $input['recipient_email'] ?? '';
            $notify_on_online = $input['notify_on_online'] ?? false;
            $notify_on_offline = $input['notify_on_offline'] ?? false;
            $notify_on_warning = $input['notify_on_warning'] ?? false;
            $notify_on_critical = $input['notify_on_critical'] ?? false;

            if (!$device_id || empty($recipient_email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Device ID and Recipient Email are required.']);
                exit;
            }

            if ($id) {
                // Update existing subscription
                $sql = "UPDATE device_email_subscriptions SET recipient_email = ?, notify_on_online = ?, notify_on_offline = ?, notify_on_warning = ?, notify_on_critical = ? WHERE id = ? AND user_id = ? AND device_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$recipient_email, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical, $id, $current_user_id, $device_id]);
                echo json_encode(['success' => true, 'message' => 'Subscription updated successfully.']);
            } else {
                // Create new subscription
                $sql = "INSERT INTO device_email_subscriptions (user_id, device_id, recipient_email, notify_on_online, notify_on_offline, notify_on_warning, notify_on_critical) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$current_user_id, $device_id, $recipient_email, $notify_on_online, $notify_on_offline, $notify_on_warning, $notify_on_critical]);
                echo json_encode(['success' => true, 'message' => 'Subscription created successfully.', 'id' => $pdo->lastInsertId()]);
            }
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
        
    case 'test_smtp':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $test_email = $input['email'] ?? '';
            
            if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'A valid email address is required.']);
                exit;
            }
            
            // Get SMTP settings for current user
            $stmt = $pdo->prepare("SELECT * FROM smtp_settings WHERE user_id = ?");
            $stmt->execute([$current_user_id]);
            $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$smtp) {
                http_response_code(400);
                echo json_encode(['error' => 'SMTP settings not configured. Please save your SMTP settings first.']);
                exit;
            }
            
            // Build test email
            $subject = "AMPNM Test Email - Configuration Verified";
            $time = date('Y-m-d H:i:s');
            
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #1e293b; border-radius: 12px; overflow: hidden; }
                    .header { background: #22c55e; color: white; padding: 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .content { padding: 24px; }
                    .metric { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #334155; }
                    .metric:last-child { border-bottom: none; }
                    .label { color: #94a3b8; }
                    .value { font-weight: bold; color: #fff; }
                    .footer { padding: 16px 24px; background: #0f172a; text-align: center; font-size: 12px; color: #64748b; }
                    .success { color: #22c55e; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>✅ SMTP Configuration Test</h1>
                    </div>
                    <div class='content'>
                        <p style='text-align: center; font-size: 18px; color: #22c55e; margin-bottom: 20px;'>
                            Your SMTP settings are working correctly!
                        </p>
                        <div class='metric'>
                            <span class='label'>SMTP Host</span>
                            <span class='value'>{$smtp['host']}</span>
                        </div>
                        <div class='metric'>
                            <span class='label'>Port</span>
                            <span class='value'>{$smtp['port']}</span>
                        </div>
                        <div class='metric'>
                            <span class='label'>Encryption</span>
                            <span class='value'>" . strtoupper($smtp['encryption']) . "</span>
                        </div>
                        <div class='metric'>
                            <span class='label'>From Email</span>
                            <span class='value'>{$smtp['from_email']}</span>
                        </div>
                        <div class='metric'>
                            <span class='label'>Test Sent At</span>
                            <span class='value'>{$time}</span>
                        </div>
                    </div>
                    <div class='footer'>
                        Sent by AMPNM Network Monitoring System
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Send email using PHP mail() or configured SMTP
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=utf-8',
                'From: ' . ($smtp['from_name'] ? "{$smtp['from_name']} <{$smtp['from_email']}>" : $smtp['from_email']),
                'Reply-To: ' . $smtp['from_email']
            ];
            
            $result = @mail($test_email, $subject, $body, implode("\r\n", $headers));
            
            if ($result) {
                error_log("Test email sent to {$test_email} from SMTP settings for user {$current_user_id}");
                echo json_encode(['success' => true, 'message' => "Test email sent successfully to {$test_email}"]);
            } else {
                $lastError = error_get_last();
                error_log("Failed to send test email to {$test_email}: " . ($lastError['message'] ?? 'Unknown error'));
                echo json_encode(['success' => false, 'error' => 'Failed to send test email. Check your server mail configuration.']);
            }
        }
        break;
}
?>