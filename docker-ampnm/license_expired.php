<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/license_guard.php';
require_once __DIR__ . '/includes/license_manager.php';

// Handle license key submission
$license_error = '';
$license_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key'])) {
    $license_key = trim($_POST['license_key']);
    if (!empty($license_key)) {
        try {
            setAppLicenseKey($license_key);
            // Clear cached license session data
            unset($_SESSION['license_last_verified']);
            unset($_SESSION['license_last_verified_key']);
            unset($_SESSION['license_status_code']);
            unset($_SESSION['license_message']);
            // Force re-verification
            verifyLicenseWithPortal(true);
            if (isset($_SESSION['license_status_code']) && ($_SESSION['license_status_code'] === 'active' || $_SESSION['license_status_code'] === 'grace_period')) {
                header('Location: index.php');
                exit;
            } else {
                $license_error = $_SESSION['license_message'] ?? 'License verification failed. Please check your license key.';
            }
        } catch (Exception $e) {
            $license_error = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $license_error = 'Please enter a valid license key.';
    }
}

// If license is somehow active or in grace period, redirect to index
if (isset($_SESSION['license_status_code']) && ($_SESSION['license_status_code'] === 'active' || $_SESSION['license_status_code'] === 'grace_period')) {
    header('Location: index.php');
    exit;
}

// Block all API access if license is invalid
if (isset($_GET['api']) || strpos($_SERVER['REQUEST_URI'], '/api.php') !== false) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Access Denied',
        'message' => 'License expired or invalid. Application is disabled.',
        'status_code' => 403
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 License Required - AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .lock-icon {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .security-badge {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">
    <div class="w-full max-w-2xl">
        <!-- Security Badge -->
        <div class="text-center mb-8">
            <div class="inline-block security-badge rounded-full p-6 mb-4">
                <i class="fas fa-lock lock-icon text-white text-7xl"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">🔒 Application Locked</h1>
            <p class="text-white text-opacity-90 text-lg">Licensed Software - Activation Required</p>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-red-600 text-white p-6">
                <div class="flex items-center justify-center space-x-3">
                    <i class="fas fa-exclamation-triangle text-3xl pulse-slow"></i>
                    <h2 class="text-2xl font-bold">License Expired or Invalid</h2>
                </div>
            </div>

            <div class="p-8 space-y-6">
                <!-- Status Message -->
                <div class="bg-red-50 border-l-4 border-red-600 p-4 rounded">
                    <p class="text-red-800 font-semibold text-lg">
                        <?= htmlspecialchars($_SESSION['license_message'] ?? 'Your license has been disabled. The application is now non-functional.') ?>
                    </p>
                </div>

                <!-- Add License Key Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg overflow-hidden">
                    <button type="button" id="toggleLicenseForm" class="w-full text-left px-6 py-4 flex items-center justify-between hover:bg-blue-100 transition-colors">
                        <span class="font-bold text-blue-800 flex items-center">
                            <i class="fas fa-key mr-2 text-blue-600"></i>
                            Add License Key
                        </span>
                        <i id="toggleIcon" class="fas fa-chevron-down text-blue-600 transition-transform"></i>
                    </button>
                    <div id="licenseFormContainer" class="hidden px-6 pb-6">
                        <?php if ($license_error): ?>
                        <div class="bg-red-100 border border-red-300 text-red-700 p-3 rounded-lg mb-4 text-sm">
                            <i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($license_error) ?>
                        </div>
                        <?php endif; ?>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="license_key" class="block text-sm font-medium text-gray-700 mb-1">License Key</label>
                                <input type="text" id="license_key" name="license_key" required
                                    placeholder="Enter your license key (e.g., XXXX-XXXX-XXXX-XXXX)"
                                    class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-800 font-mono text-lg"
                                    value="<?= htmlspecialchars($_POST['license_key'] ?? '') ?>">
                            </div>
                            <button type="submit"
                                class="w-full px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold rounded-lg hover:from-green-700 hover:to-emerald-700 transform hover:scale-105 transition-all shadow-lg">
                                <i class="fas fa-check-circle mr-2"></i>
                                Verify & Activate License
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 mt-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Enter your license key to activate the application. The key will be verified with the licensing server.
                        </p>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                        Security Notice
                    </h3>
                    <ul class="space-y-2 text-gray-700">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                            <span>This is a licensed product and cannot be used without valid activation</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                            <span>License validation occurs automatically every 5 minutes</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                            <span>Tampering with license files will permanently disable the application</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-600 mt-1 mr-2"></i>
                            <span>All API access is blocked until license is renewed</span>
                        </li>
                    </ul>
                </div>

                <!-- License Info -->
                <?php if (isset($_SESSION['license_expires_at']) && $_SESSION['license_expires_at']): ?>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <p class="text-gray-700">
                        <strong>License Expired:</strong> <?= date('F j, Y', strtotime($_SESSION['license_expires_at'])) ?>
                    </p>
                    <?php if (isset($_SESSION['license_grace_period_end']) && $_SESSION['license_grace_period_end']): ?>
                    <p class="text-gray-700 mt-2">
                        <strong>Grace Period Ended:</strong> <?= date('F j, Y H:i', $_SESSION['license_grace_period_end']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="space-y-3">
                    <a href="https://portal.itsupport.com.bd/products.php" target="_blank"
                       class="block w-full text-center px-6 py-4 bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-bold rounded-lg hover:from-cyan-700 hover:to-blue-700 transform hover:scale-105 transition-all shadow-lg">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        Purchase or Renew License Now
                    </a>

                    <a href="https://portal.itsupport.com.bd/support.php" target="_blank"
                       class="block w-full text-center px-6 py-4 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-headset mr-2"></i>
                        Contact Support
                    </a>

                    <a href="logout.php"
                       class="block w-full text-center px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Logout
                    </a>
                </div>

                <!-- Footer Info -->
                <div class="border-t pt-6 mt-6">
                    <div class="text-center text-gray-600 text-sm">
                        <p class="mb-2">
                            <strong>AMPNM</strong> - Advanced Multi-Protocol Network Monitor
                        </p>
                        <p>
                            <i class="fas fa-globe mr-1"></i>
                            <a href="https://portal.itsupport.com.bd" target="_blank" class="text-blue-600 hover:underline">
                                portal.itsupport.com.bd
                            </a>
                        </p>
                        <p class="mt-2 text-xs text-gray-500">
                            This software is protected by copyright and license agreements.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Warning -->
        <div class="mt-6 text-center text-white text-sm bg-black bg-opacity-30 backdrop-blur p-4 rounded-lg">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Note:</strong> Application functionality is completely disabled without a valid license.
            No workarounds are possible - license validation is enforced at the system level.
        </div>
    </div>

    <script>
        // Toggle license form visibility
        const toggleBtn = document.getElementById('toggleLicenseForm');
        const formContainer = document.getElementById('licenseFormContainer');
        const toggleIcon = document.getElementById('toggleIcon');

        <?php if ($license_error): ?>
        // Auto-expand if there was an error
        formContainer.classList.remove('hidden');
        toggleIcon.style.transform = 'rotate(180deg)';
        <?php endif; ?>

        toggleBtn.addEventListener('click', function() {
            formContainer.classList.toggle('hidden');
            if (formContainer.classList.contains('hidden')) {
                toggleIcon.style.transform = 'rotate(0deg)';
            } else {
                toggleIcon.style.transform = 'rotate(180deg)';
            }
        });

        // Disable right-click on this page
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // Disable common developer shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.keyCode === 123 ||
                (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) ||
                (e.ctrlKey && e.keyCode === 85)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
