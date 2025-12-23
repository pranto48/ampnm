<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
// It also assumes auth_check.php has run, so $_SESSION['user_id'] and $_SESSION['user_role'] are set.

// Ensure only admin can perform these actions
if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can manage licenses.']);
    exit;
}

switch ($action) {
    case 'get_current_license_info':
        // Return license information directly from the session
        echo json_encode([
            'success' => true,
            'license_status_code' => $_SESSION['license_status_code'] ?? 'unknown',
            'license_message' => $_SESSION['license_message'] ?? 'License status unknown.',
            'app_license_key' => getAppLicenseKey() ?? '',
            'installation_id' => getAppSetting('installation_id') ?? '',
            'max_devices' => $_SESSION['license_max_devices'] ?? 0,
            'current_devices' => $_SESSION['current_device_count'] ?? 0,
            'expires_at' => $_SESSION['license_expires_at'] ?? null,
            'grace_period_end' => $_SESSION['license_grace_period_end'] ?? null,
        ]);
        break;

    case 'update_app_license_key':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_license_key = trim($input['license_key'] ?? '');

            if (empty($new_license_key)) {
                http_response_code(400);
                echo json_encode(['error' => 'License key cannot be empty.']);
                exit;
            }

            if (setAppLicenseKey($new_license_key)) {
                // Force a re-verification with the portal immediately after updating the key
                unset($_SESSION['license_last_verified']);
                unset($_SESSION['license_last_verified_key']);
                verifyLicenseWithPortal(true);
                echo json_encode(['success' => true, 'message' => 'License key updated and re-verified.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save license key to database.']);
            }
        }
        break;

    case 'force_license_recheck':
        // Force a re-verification with the portal
        verifyLicenseWithPortal(true);
        echo json_encode(['success' => true, 'message' => 'License re-verification initiated.']);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid license API action.']);
        break;
}
?>