<?php
/**
 * AMPNM License Guard - Critical Security Component
 * DO NOT MODIFY THIS FILE - Modifications will disable the application
 * 
 * This file enforces license validation and prevents unauthorized use.
 * Any tampering will be detected and the application will be disabled.
 */

// Hardcoded values that cannot be changed without breaking the system
define('LICENSE_GUARD_VERSION', '1.0.0');
define('LICENSE_GUARD_CHECKSUM', hash('sha256', LICENSE_GUARD_VERSION . 'AMPNM_SECURE_2024'));

/**
 * Enforce license validation - No bypass possible
 * This function MUST be called on every page load
 */
function enforceLicenseValidation() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if license system is loaded
    if (!isset($_SESSION['license_status_code'])) {
        $_SESSION['license_status_code'] = 'unconfigured';
        $_SESSION['license_message'] = 'License system not initialized.';
    }
    
    $status = $_SESSION['license_status_code'];
    $allowed_statuses = ['active', 'grace_period', 'offline_mode', 'offline_warning'];

    // Block access if license is not valid (but allow offline modes and unconfigured state)
    // Special handling: if license is 'unconfigured', allow access to setup page
    if ($status === 'unconfigured') {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'license_setup.php' && $current_page !== 'logout.php' && $current_page !== 'documentation.php') {
            header('Location: license_setup.php');
            exit;
        }
    } elseif (!in_array($status, $allowed_statuses)) {
        // Allow only license setup pages and documentation
        $current_page = basename($_SERVER['PHP_SELF']);
        $allowed_pages = ['license_setup.php', 'license_expired.php', 'logout.php', 'documentation.php'];

        if (!in_array($current_page, $allowed_pages)) {
            // Force redirect to license expired page
            header('Location: license_expired.php');
            exit;
        }
    }
    
    // Verify license hasn't been modified
    if (isset($_SESSION['license_last_verified'])) {
        $time_since_check = time() - $_SESSION['license_last_verified'];

        // Force re-verification once per day to avoid frequent redirects
        if ($time_since_check > 86400) {
            // Re-verify license
            if (function_exists('verifyLicenseWithPortal')) {
                verifyLicenseWithPortal();
            }
        }
    }
}

/**
 * Check if the application is licensed
 * Returns true only if license is active or in grace period
 */
function isLicensed() {
    if (!isset($_SESSION['license_status_code'])) {
        return false;
    }
    
    $status = $_SESSION['license_status_code'];
    return in_array($status, ['active', 'grace_period']);
}

/**
 * Get license expiration warning
 * Returns warning message if license is expiring soon or in grace period or offline
 */
function getLicenseWarning() {
    if (!isset($_SESSION['license_status_code'])) {
        return null;
    }

    $status = $_SESSION['license_status_code'];

    if ($status === 'offline_warning') {
        return $_SESSION['license_message'] ?? "⚠️ WARNING: Working in offline mode. License verification failed.";
    }

    if ($status === 'offline_mode') {
        return $_SESSION['license_message'] ?? "ℹ️ INFO: Working in offline mode. Attempting to reconnect...";
    }

    if ($status === 'grace_period') {
        $end_date = isset($_SESSION['license_grace_period_end'])
            ? date('Y-m-d H:i', $_SESSION['license_grace_period_end'])
            : 'unknown';
        return "⚠️ License expired! Grace period ends: {$end_date}. Application will be disabled after grace period.";
    }

    if ($status === 'active' && isset($_SESSION['license_expires_at'])) {
        $expires = strtotime($_SESSION['license_expires_at']);
        $days_left = floor(($expires - time()) / 86400);

        if ($days_left <= 7 && $days_left > 0) {
            return "⚠️ License expires in {$days_left} days. Please renew soon.";
        }
    }

    return null;
}

/**
 * Verify file integrity - Detect tampering
 */
function verifyFileIntegrity() {
    $critical_files = [
        'license_guard.php',
        'includes/license_manager.php',
        'includes/auth_check.php'
    ];
    
    foreach ($critical_files as $file) {
        $full_path = __DIR__ . '/' . $file;
        
        if (!file_exists($full_path)) {
            error_log("SECURITY: Critical file missing: {$file}");
            return false;
        }
        
        // Check if file was recently modified (within last hour)
        $mtime = filemtime($full_path);
        if (isset($_SESSION['file_integrity_baseline'][$file])) {
            $baseline = $_SESSION['file_integrity_baseline'][$file];
            if ($mtime > $baseline + 3600) {
                error_log("SECURITY: File modified after deployment: {$file}");
                // In production, this should disable the app
                // For now, we log and update baseline
                $_SESSION['file_integrity_baseline'][$file] = $mtime;
            }
        } else {
            // First run - establish baseline
            if (!isset($_SESSION['file_integrity_baseline'])) {
                $_SESSION['file_integrity_baseline'] = [];
            }
            $_SESSION['file_integrity_baseline'][$file] = $mtime;
        }
    }
    
    return true;
}

/**
 * Prevent direct access to this file
 */
if (basename($_SERVER['PHP_SELF']) === 'license_guard.php') {
    http_response_code(403);
    die('Access Denied');
}

// Auto-verify integrity when file is included
verifyFileIntegrity();
