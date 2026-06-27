<?php
// Include bootstrap to ensure DB connection is available
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config.php';

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

// Retrieve the core decryption key from the database (saved upon successful license verification)
$enc_key = getAppSetting('core_key');
$raw_key = '';

if (!empty($enc_key)) {
    $raw_key = decryptSensitiveValue($enc_key);
}

if (empty($raw_key)) {
    // If not activated yet, only allow setup pages to run
    if ($current_page !== 'license_setup.php' && $current_page !== 'license_expired.php') {
        header('Location: license_setup.php');
        exit;
    }
    return;
}

$key_buf = hash('sha256', $raw_key, true);
$decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_buf, OPENSSL_RAW_DATA, $iv);

if ($decrypted === false) {
    // Key is wrong or modified
    error_log("LICENSE_SECURITY: Failed to decrypt auth_check.enc. Invalid key or tampered file.");
    if ($current_page !== 'license_setup.php' && $current_page !== 'license_expired.php') {
        header('Location: license_setup.php');
        exit;
    }
    return;
}

// Execute decrypted core logic in memory
eval($decrypted);

