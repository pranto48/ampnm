<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/auth_check.php';
include 'header.php';

// Only admins should access
if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    echo '<div class="container mx-auto px-4 py-8 text-center text-red-500 font-bold">Access Denied. Admins only.</div>';
    include 'footer.php';
    exit;
}

$message = '';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'activate') {
        $entered_key = trim($_POST['license_key'] ?? '');
        if (empty($entered_key)) {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Please enter a license key.</div>';
        } else {
            // Save the key
            if (setAppLicenseKey($entered_key)) {
                // Force verify immediately
                unset($_SESSION['license_last_verified']);
                unset($_SESSION['license_last_verified_key']);
                verifyLicenseWithPortal(true);
                
                $status = $_SESSION['license_status_code'] ?? 'unknown';
                if ($status === 'active' || $status === 'grace_period' || $status === 'free') {
                    $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center mb-4">License updated and verified successfully! Status: ' . htmlspecialchars($status) . '</div>';
                } else {
                    $message = '<div class="bg-amber-500/20 border border-amber-500/30 text-amber-300 text-sm rounded-lg p-3 text-center mb-4">License key saved, but verification reported: ' . htmlspecialchars($_SESSION['license_message'] ?? 'Invalid license') . '</div>';
                }
            } else {
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Failed to save the license key to database.</div>';
            }
        }
    } elseif ($action === 'verify') {
        // Force verify immediately
        unset($_SESSION['license_last_verified']);
        verifyLicenseWithPortal(true);
        $status = $_SESSION['license_status_code'] ?? 'unknown';
        if ($status === 'active' || $status === 'grace_period' || $status === 'free') {
            $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center mb-4">License re-verified successfully! Status: ' . htmlspecialchars($status) . '</div>';
        } else {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Verification failed: ' . htmlspecialchars($_SESSION['license_message'] ?? 'Connection error') . '</div>';
        }
    } elseif ($action === 'deactivate') {
        // Remove license key
        if (setAppLicenseKey('')) {
            $_SESSION['license_status_code'] = 'unconfigured';
            $_SESSION['license_message'] = 'Application license key is missing.';
            $_SESSION['license_max_devices'] = 0;
            $_SESSION['license_expires_at'] = null;
            $_SESSION['license_last_verified'] = time();
            $_SESSION['license_last_verified_key'] = null;
            updateAppSetting('license_cache', '');
            
            $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center mb-4">License key removed successfully.</div>';
        } else {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center mb-4">Failed to deactivate license in database.</div>';
        }
    }
}

// Fetch current details
$current_key = getAppLicenseKey() ?: '';
$installation_id = getInstallationId();
$license_status = $_SESSION['license_status_code'] ?? 'unknown';
$license_message = $_SESSION['license_message'] ?? 'No license loaded.';
$max_devices = $_SESSION['license_max_devices'] ?? 0;
$expires_at = $_SESSION['license_expires_at'] ?? null;
$last_verified = $_SESSION['license_last_verified'] ?? null;
$current_devices = $_SESSION['current_device_count'] ?? 0;

?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">License Management</h1>
        
        <?= $message ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left status panel -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-6 shadow-lg">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-shield-halved text-cyan-400"></i>
                        <span>License Status</span>
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Key and badge -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">License Key</label>
                                <div class="flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2">
                                    <input type="password" id="licenseKeyDisplay" readonly value="<?= htmlspecialchars($current_key) ?>" 
                                           class="bg-transparent border-none text-white text-sm focus:outline-none w-full select-all font-mono">
                                    <button type="button" onclick="toggleKeyMask()" class="text-slate-400 hover:text-white focus:outline-none">
                                        <i id="maskEyeIcon" class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Status Code</label>
                                <div class="flex items-center gap-2">
                                    <?php
                                    $badgeClass = 'bg-slate-700 text-slate-300';
                                    $statusLabel = ucfirst($license_status);
                                    if ($license_status === 'active' || $license_status === 'free') {
                                        $badgeClass = 'bg-green-500/20 border border-green-500/30 text-green-300';
                                    } elseif ($license_status === 'expired' || $license_status === 'disabled' || $license_status === 'offline_expired') {
                                        $badgeClass = 'bg-red-500/20 border border-red-500/30 text-red-300';
                                    } elseif (str_contains($license_status, 'warning') || str_contains($license_status, 'mode')) {
                                        $badgeClass = 'bg-amber-500/20 border border-amber-500/30 text-amber-300';
                                    }
                                    ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full border <?= $badgeClass ?>">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description/Details -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Status Message</label>
                                <p class="text-sm text-slate-300"><?= htmlspecialchars($license_message) ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Installation Signature ID</label>
                                <div class="flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5">
                                    <span class="text-xs font-mono text-slate-300 truncate w-full select-all" id="installId"><?= htmlspecialchars($installation_id ?? 'N/A') ?></span>
                                    <button type="button" onclick="copyInstallId()" class="text-slate-400 hover:text-white text-xs" title="Copy ID">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Usage details -->
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-6 shadow-lg">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-line text-cyan-400"></i>
                        <span>Device & Subscription Details</span>
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Subscription Expiration</label>
                                <p class="text-sm text-slate-200">
                                    <?php if ($expires_at): ?>
                                        <i class="far fa-calendar-alt text-slate-400 mr-1.5"></i>
                                        <?= htmlspecialchars(date('F d, Y', strtotime($expires_at))) ?>
                                        <span class="text-xs text-slate-400 font-mono">(<?= htmlspecialchars($expires_at) ?>)</span>
                                    <?php else: ?>
                                        <i class="fas fa-infinity text-slate-400 mr-1.5"></i>Permanent / Lifetime Free
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Last Portal Verification</label>
                                <p class="text-sm text-slate-200">
                                    <i class="fas fa-clock text-slate-400 mr-1.5"></i>
                                    <?= $last_verified ? htmlspecialchars(date('Y-m-d H:i:s', $last_verified)) : 'Never' ?>
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-1.5">Device Limit Usage</label>
                            <div class="flex items-center justify-between text-sm text-slate-300 mb-1">
                                <span>Used Devices</span>
                                <span class="font-semibold text-white"><?= htmlspecialchars($current_devices) ?> / <?= $max_devices > 0 ? htmlspecialchars($max_devices) : 'Unlimited' ?></span>
                            </div>
                            <?php
                            $percentage = 0;
                            if ($max_devices > 0) {
                                $percentage = min(100, round(($current_devices / $max_devices) * 100));
                            }
                            $barColor = 'bg-cyan-500';
                            if ($percentage > 90) {
                                $barColor = 'bg-red-500';
                            } elseif ($percentage > 75) {
                                $barColor = 'bg-amber-500';
                            }
                            ?>
                            <div class="w-full bg-slate-900 rounded-full h-3.5 border border-slate-700 overflow-hidden">
                                <div class="<?= $barColor ?> h-full transition-all duration-500" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-1.5">Your license allows monitoring up to <?= $max_devices > 0 ? htmlspecialchars($max_devices) : 'unlimited' ?> devices on this installation.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right management panel -->
            <div class="space-y-6">
                <!-- Change license form -->
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-6 shadow-lg">
                    <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-key text-cyan-400"></i>
                        <span>Update License Key</span>
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="activate">
                        <div>
                            <label for="license_key" class="block text-sm font-medium text-slate-300 mb-1.5">New License Key</label>
                            <input type="text" name="license_key" id="license_key" required
                                   class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-600 focus:ring-2 focus:ring-cyan-500 focus:outline-none"
                                   placeholder="XXXX-XXXX-XXXX-XXXX">
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white text-sm font-semibold rounded-lg shadow-lg hover:shadow-cyan-500/20 transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            <span>Save and Activate</span>
                        </button>
                    </form>
                </div>
                
                <!-- Quick actions -->
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-6 shadow-lg">
                    <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-toolbox text-cyan-400"></i>
                        <span>System Actions</span>
                    </h2>
                    
                    <div class="space-y-3">
                        <form method="POST">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="w-full px-4 py-2 bg-slate-700 text-slate-200 hover:bg-slate-650 hover:text-white text-sm font-medium rounded-lg border border-slate-600 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-sync-alt"></i>
                                <span>Force Portal Re-verify</span>
                            </button>
                        </form>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate and remove this license key? The monitoring application will enter setup mode.');">
                            <input type="hidden" name="action" value="deactivate">
                            <button type="submit" class="w-full px-4 py-2 bg-red-950/20 hover:bg-red-900/30 text-red-400 hover:text-red-300 text-sm font-medium rounded-lg border border-red-900/30 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-trash-alt"></i>
                                <span>Deactivate & Remove Key</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Registration banner -->
                <div class="bg-gradient-to-br from-slate-850 to-slate-900 border border-cyan-500/10 rounded-lg p-6 shadow-lg text-center relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 text-cyan-500/5 text-9xl font-bold select-none pointer-events-none">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 class="text-md font-bold text-white mb-2">Need a License Key?</h3>
                    <p class="text-xs text-slate-400 mb-4 leading-relaxed">You can register and obtain a free lifetime key or standard premium key directly on our client portal site.</p>
                    <a href="https://portal.itsupport.com.bd" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-cyan-400 hover:text-cyan-300 font-bold hover:underline">
                        <span>Visit portal.itsupport.com.bd</span>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function toggleKeyMask() {
    const input = document.getElementById('licenseKeyDisplay');
    const eye = document.getElementById('maskEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        eye.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        eye.className = 'fas fa-eye';
    }
}

function copyInstallId() {
    const text = document.getElementById('installId').textContent;
    navigator.clipboard.writeText(text).then(() => {
        window.notyf.success({ message: 'Installation ID copied to clipboard.', duration: 3000 });
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}
</script>

<?php include 'footer.php'; ?>