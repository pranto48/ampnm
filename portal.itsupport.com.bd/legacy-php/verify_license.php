<?php
header('Content-Type: text/plain'); // Change header to plain text for raw encrypted data

// Include the license service's database configuration
require_once __DIR__ . '/config.php';

// --- Encryption/Decryption Configuration ---
// NOTE: This key MUST be kept secret and MUST match the key used in the Docker app's license_manager.php
// For security, this should ideally be stored in a secure environment variable, but for this exercise, we define it here.
define('ENCRYPTION_KEY', 'ITSupportBD_SecureKey_2024');
define('CIPHER_METHOD', 'aes-256-cbc');

function encryptLicenseData(array $data) {
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt(json_encode($data), CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}
// --- End Encryption/Decryption Configuration ---


$input = json_decode(file_get_contents('php://input'), true);

// Log the received input for debugging
error_log("License verification received input: " . print_r($input, true));

$app_license_key = $input['app_license_key'] ?? null;
$user_id = $input['user_id'] ?? null;
$current_device_count = $input['current_device_count'] ?? 0;
$installation_id = $input['installation_id'] ?? null;

// Use empty() for a more robust check against null, empty strings, and 0
if (empty($app_license_key) || empty($user_id) || empty($installation_id)) {
    error_log("License verification failed: Missing app_license_key, user_id, or installation_id.");
    // Return an encrypted failure message
    echo encryptLicenseData([
        'success' => false,
        'message' => 'Missing application license key, user ID, or installation ID.',
        'actual_status' => 'invalid_request'
    ]);
    exit;
}

try {
    $pdo = getLicenseDbConnection();

    // 1. Fetch the license from MySQL
    $stmt = $pdo->prepare("SELECT * FROM `licenses` WHERE license_key = ?");
    $stmt->execute([$app_license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        echo encryptLicenseData([
            'success' => false,
            'message' => 'Invalid or expired application license key.',
            'actual_status' => 'not_found'
        ]);
        exit;
    }

    // 2. Check license status and expiry
    if ($license['status'] !== 'active' && $license['status'] !== 'free') {
        echo encryptLicenseData([
            'success' => false,
            'message' => 'License is ' . $license['status'] . '.',
            'actual_status' => $license['status']
        ]);
        exit;
    }

    if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
        // Automatically update status to 'expired' if past due
        $stmt = $pdo->prepare("UPDATE `licenses` SET status = 'expired', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        echo encryptLicenseData([
            'success' => false,
            'message' => 'License has expired.',
            'actual_status' => 'expired'
        ]);
        exit;
    }

    // 3. Enforce one-to-one binding using installation_id
    if (empty($license['bound_installation_id'])) {
        // License is not bound, bind it to this installation_id
        $stmt = $pdo->prepare("UPDATE `licenses` SET bound_installation_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$installation_id, $license['id']]);
        error_log("License '{$app_license_key}' bound to new installation ID: {$installation_id}");
    } elseif ($license['bound_installation_id'] !== $installation_id) {
        // License is bound to a different installation_id, deny access
        echo encryptLicenseData([
            'success' => false,
            'message' => 'License is already in use by another server.',
            'actual_status' => 'in_use'
        ]);
        exit;
    }

    // 4. Update current_devices count and last_active_at timestamp
    $stmt = $pdo->prepare("UPDATE `licenses` SET current_devices = ?, last_active_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$current_device_count, $license['id']]);

    // 5. Return encrypted success data
    echo encryptLicenseData([
        'success' => true,
        'message' => 'License is active.',
        'max_devices' => $license['max_devices'] ?? 1,
        'actual_status' => $license['status'],
        'expires_at' => $license['expires_at']
    ]);

} catch (Exception $e) {
    error_log("License verification error: " . $e->getMessage());
    echo encryptLicenseData([
        'success' => false,
        'message' => 'An internal error occurred during license verification.',
        'actual_status' => 'error'
    ]);
}
?>