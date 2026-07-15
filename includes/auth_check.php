<?php
// Include bootstrap to ensure DB connection is available
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../license_guard.php';
require_once __DIR__ . '/license_manager.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Load the encrypted core logic
$enc_file = __DIR__ . '/auth_check.enc';
if (!file_exists($enc_file)) {
    die("Core system file missing. Please reinstall the application.");
}

$encrypted_data = file_get_contents($enc_file);
$data = base64_decode($encrypted_data);
if ($data === false || strlen($data) < 16) {
    die("System integrity compromised. Execution blocked.");
}

$iv = substr($data, 0, 16);
$ciphertext = substr($data, 16);

// Retrieve the core decryption key from the database
$enc_key = getAppSetting('core_key');
$raw_key = '';
if (!empty($enc_key)) {
    $raw_key = decryptSensitiveValue($enc_key);
}

// If core_key is missing AND we have a license key already configured,
// attempt a forced re-verification to repopulate the core_key.
// This prevents the redirect loop on fresh container restarts.
if (empty($raw_key) && !empty(getAppLicenseKey())) {
    verifyLicenseWithPortal(true); // Force re-verify to get core_key from portal
    $enc_key = getAppSetting('core_key');
    if (!empty($enc_key)) {
        $raw_key = decryptSensitiveValue($enc_key);
    }
}

if (empty($raw_key)) {
    // No license key configured at all - redirect to setup (but only from non-setup pages)
    if ($current_page !== 'license_setup.php' && $current_page !== 'license_expired.php') {
        header('Location: license_setup.php');
        exit;
    }
    return;
}

$key_buf = hash('sha256', $raw_key, true);
$decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_buf, OPENSSL_RAW_DATA, $iv);

if ($decrypted === false) {
    // Key mismatch or tampered file - fall back to executing core logic directly
    error_log("LICENSE_SECURITY: Failed to decrypt auth_check.enc. Falling back to inline logic.");
    // Fallback: run core auth logic inline without encryption
    require_once __DIR__ . '/license_manager.php';

    if (!isset($_SESSION['user_id'])) {
        if ($current_page !== 'login.php') {
            header('Location: login.php');
            exit;
        }
        return;
    }

    if ($current_page !== 'license_setup.php') {
        enforceLicenseValidation();
    }

    $license_status_code = $_SESSION['license_status_code'] ?? 'unknown';
    if (in_array($license_status_code, ['disabled', 'offline_expired']) && $current_page !== 'license_expired.php') {
        header('Location: license_expired.php');
        exit;
    }
    return;
}

// Execute decrypted core logic in memory
eval($decrypted);

if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $current_user_group = $_SESSION['user_group'] ?? 'default_group';
    $pdo = getDbConnection();
    
    // Retrieve all users in the same user group
    $stmtGroup = $pdo->prepare("SELECT id FROM users WHERE user_group = ?");
    $stmtGroup->execute([$current_user_group]);
    $current_group_user_ids = $stmtGroup->fetchAll(PDO::FETCH_COLUMN) ?: [$current_user_id];
}
