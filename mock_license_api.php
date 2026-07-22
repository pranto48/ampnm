<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
header('Content-Type: text/plain');

// AES-256-CBC configuration matching the licensing portal key
define('ENCRYPTION_KEY', 'ITSupportBD_SecureKey_2024');
define('CIPHER_METHOD', 'aes-256-cbc');

function encryptLicenseData(array $data) {
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt(json_encode($data), CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// Receive request data (optional, we accept any key for the local mock)
$input = json_decode(file_get_contents('php://input'), true);
$license_key = $input['app_license_key'] ?? 'AMP256-LOCAL-MOCK';

// Return an encrypted active status response
echo encryptLicenseData([
    'success' => true,
    'message' => 'License is active (Local Development Bypass).',
    'max_devices' => 9999, // Unlimited nodes for local testing
    'actual_status' => 'active',
    'expires_at' => date('Y-m-d H:i:s', strtotime('+5 years')),
    'core_key' => 'ITSupportBD_CoreShield_2026'
]);
