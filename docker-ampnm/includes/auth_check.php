<?php
// Include the main bootstrap file which handles DB checks and starts the session.
require_once __DIR__ . '/bootstrap.php';
// Include config.php to make license-related functions available
require_once __DIR__ . '/../config.php';
// Include license guard - CRITICAL SECURITY COMPONENT
require_once __DIR__ . '/../license_guard.php';
// Include license_manager.php for license verification logic
require_once __DIR__ . '/license_manager.php';

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ensure user_role is set in session. If not, fetch it from DB (e.g., after an upgrade)
if (!isset($_SESSION['user_role'])) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user_role'] = $user_data['role'] ?? 'viewer'; // Default to viewer if not found
}

// --- Role-based page access control ---
$current_page = basename($_SERVER['PHP_SELF']);
$admin_only_pages = ['users.php', 'email_notifications.php', 'create-device.php', 'edit-device.php', 'license_management.php', 'code_updates.php']; // Add license_management.php here

if ($_SESSION['user_role'] !== 'admin' && in_array($current_page, $admin_only_pages)) {
    header('Location: index.php'); // Redirect non-admins from admin-only pages
    exit;
}

// --- External License Validation ---
// The verifyLicenseWithPortal() function is now called in license_manager.php
// which is included above. It populates $_SESSION with license status.

// Check license status and redirect if necessary
$license_status_code = $_SESSION['license_status_code'] ?? 'unknown';
$app_license_key = getAppLicenseKey();

// If license key is not configured, redirect to setup page
// BUT: Allow access to license_setup.php without validation
if (empty($app_license_key) && $current_page !== 'license_setup.php') {
    header('Location: license_setup.php');
    exit;
}

// ENFORCE LICENSE VALIDATION - NO BYPASS POSSIBLE
// But only if we're not on the setup page
if ($current_page !== 'license_setup.php') {
    enforceLicenseValidation();
}

// If license is disabled (grace period over, revoked, offline expired, etc.), redirect to license_expired.php
if (in_array($license_status_code, ['disabled', 'offline_expired']) && $current_page !== 'license_expired.php') {
    header('Location: license_expired.php');
    exit;
}

// For other statuses (active, grace_period, expired, invalid, portal_unreachable),
// the application remains accessible, and the header will display the appropriate message.
?>