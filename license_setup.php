<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once __DIR__ . '/includes/bootstrap.php'; // Load bootstrap for DB connection and functions
require_once __DIR__ . '/includes/license_manager.php'; // Load license manager for decryption function

$message = '';

// If a license key is already set AND active/grace_period, redirect to index
if (getAppLicenseKey() && ($_SESSION['license_status_code'] === 'active' || $_SESSION['license_status_code'] === 'grace_period')) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_license_key = trim($_POST['license_key'] ?? '');
    error_log("DEBUG: license_setup.php received POST with license_key: " . (empty($entered_license_key) ? 'EMPTY' : 'PRESENT'));

    if (empty($entered_license_key)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Please enter a license key.</div>';
    } else {
        // Attempt to validate the license key against the external API
        $installation_id = getInstallationId(); // Ensure installation ID is available
        if (empty($installation_id)) {
            error_log("ERROR: license_setup.php failed to get installation ID.");
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Application installation ID missing. Please re-run database setup.</div>';
        } else {
            $license_api_url = LICENSE_API_URL;
            $post_data = [
                'app_license_key' => $entered_license_key,
                'user_id' => 'setup_user', // A dummy user ID for initial validation
                'current_device_count' => 0, // No devices yet
                'installation_id' => $installation_id // Pass the unique installation ID
            ];

            // --- Use stream context for POST request with file_get_contents ---
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($post_data),
                    'timeout' => 10, // 10 second timeout
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $encrypted_response = @file_get_contents($license_api_url, false, $context);

            if ($encrypted_response === false) {
                $error = error_get_last();
                $error_message = $error['message'] ?? 'Unknown connection error.';
                error_log("License API connection Error during setup: {$error_message}");
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Failed to connect to license verification service. Network/DNS error: ' . htmlspecialchars($error_message) . '</div>';
            } else {
                $licenseData = decryptLicenseData($encrypted_response);

                if ($licenseData === false) {
                    error_log("License API Decryption/Parse Error during setup.");
                    $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Invalid or corrupted response from license verification service.</div>';
                } elseif (isset($licenseData['success']) && $licenseData['success'] === true) {
                    // License is valid, save it to app_settings
                    if (setAppLicenseKey($entered_license_key)) {
                        error_log("DEBUG: license_setup.php successfully saved license key: " . $entered_license_key);
                        // Force re-verification to update session variables immediately for the new key
                        unset($_SESSION['license_last_verified']);
                        unset($_SESSION['license_last_verified_key']);
                        verifyLicenseWithPortal(true);
                        $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center">License key saved successfully! Redirecting...</div>';
                        header('Refresh: 3; url=index.php'); // Redirect to index after 3 seconds
                        exit;
                    } else {
                        error_log("ERROR: license_setup.php failed to save license key to database.");
                        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Failed to save license key to database.</div>';
                    }
                } else {
                    error_log("DEBUG: license_setup.php license validation failed: " . ($licenseData['message'] ?? 'Unknown reason.'));
                    $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">' . ($licenseData['message'] ?? 'Invalid license key.') . '</div>';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM License Setup</title>
    <!-- Animated SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><style>@keyframes spin{from{transform-origin:32px 32px;transform:rotate(0deg)}to{transform-origin:32px 32px;transform:rotate(360deg)}}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}.ring{animation:spin 3s linear infinite}.dot{animation:pulse 1.5s ease-in-out infinite}</style></defs><circle cx='32' cy='32' r='28' fill='%230f172a' stroke='%2306b6d4' stroke-width='3'/><circle cx='32' cy='32' r='18' fill='none' stroke='%2322d3ee' stroke-width='1.5' stroke-dasharray='8 4' class='ring'/><circle cx='32' cy='32' r='9' fill='%2306b6d4'/><circle cx='32' cy='32' r='5' fill='%230f172a' class='dot'/><circle cx='32' cy='11' r='3' fill='%2322d3ee' class='dot'/><circle cx='53' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:.5s'/><circle cx='11' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:1s'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl shadow-xl shadow-blue-500/30 mx-auto flex items-center justify-center mb-3">
                <i class="fas fa-heartbeat text-2xl animate-pulse"></i>
            </div>
            <div class="flex items-center justify-center gap-2">
                <h1 class="text-3xl font-extrabold text-white">AMPNM License Setup</h1>
                <span class="text-xs bg-blue-900/80 text-blue-300 font-bold px-2 py-0.5 rounded border border-blue-700/50">v1.15</span>
            </div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase mt-1">by IT Support BD</p>
        </div>
        <form method="POST" action="license_setup.php" class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-xl p-8 space-y-6">
            <?= $message ?>
            <div>
                <label for="license_key" class="block text-sm font-medium text-slate-300 mb-2">License Key</label>
                <input type="text" name="license_key" id="license_key" required
                       class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white"
                       placeholder="XXXX-XXXX-XXXX-XXXX" value="<?= htmlspecialchars(getAppLicenseKey() ?? '') ?>">
            </div>
            <button type="submit"
                    class="w-full px-6 py-3 bg-cyan-600 text-white font-semibold rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                Activate License
            </button>
            <div class="text-center text-xs text-slate-400 select-none">
                Don't have a license key? Get a <strong>Free & Open Source</strong> license key by registering at our <a href="https://portal.itsupport.com.bd" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:underline font-bold">Client Portal</a>.
            </div>
        </form>
    </div>
</body>
</html>