<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/license_manager.php';

// Try to decrypt the direct response from the licensing API.
$installation_id = getAppSetting('installation_id');
if (empty($installation_id)) {
    // Generate UUID if empty
    $installation_id = generateUuid();
    updateAppSetting('installation_id', $installation_id);
}

$post_data = [
    'app_license_key' => 'AMP256-A13BBD9F83764C23-9970370490183E96-804953DC04E31B87-CA4ED1D650D7BB5B',
    'user_id' => 'anonymous',
    'current_device_count' => 0,
    'installation_id' => $installation_id
];

echo "Installation ID: " . $post_data['installation_id'] . "\n";
echo "License Key: " . $post_data['app_license_key'] . "\n";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($post_data),
        'timeout' => 10,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$encrypted_response = @file_get_contents(LICENSE_API_URL, false, $context);
if ($encrypted_response === false) {
    echo "Error making request: " . print_r(error_get_last(), true) . "\n";
    exit(1);
}

echo "Encrypted response length: " . strlen($encrypted_response) . "\n";
$decrypted = decryptLicenseData($encrypted_response);
echo "Decrypted result:\n";
print_r($decrypted);
echo "\n";
