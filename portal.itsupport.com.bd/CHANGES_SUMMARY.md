# Changes Summary - License Setup & Portal SMTP Fix

**Date:** November 24, 2024
**Issue:** Docker AMPNM showing license_expired.php on fresh installation + Missing SMTP links in admin panel

---

## Problems Identified

### 1. Docker AMPNM License Setup Issue
**Problem:**
- Fresh Docker AMPNM installations were immediately showing `license_expired.php`
- Clients could not access `license_setup.php` to enter their license key
- System was blocking access before license could be configured

**Root Cause:**
- `license_guard.php` was enforcing license validation on ALL pages
- No exception for the initial setup scenario
- System treated "unconfigured" (no license key) the same as "expired" (invalid license)

### 2. Portal Admin SMTP Missing
**Problem:**
- SMTP Settings page (`smtp_settings.php`) existed but not linked in admin navigation
- Send Notifications page (`send_notifications.php`) existed but not linked in admin navigation
- Admins couldn't easily access email notification features

---

## Solutions Implemented

### Fix 1: Docker AMPNM License Setup Flow

#### File: `/docker-ampnm/includes/auth_check.php`
**Changes:**
- Moved license enforcement to AFTER checking if on license_setup.php
- Added conditional: Skip `enforceLicenseValidation()` when current page is `license_setup.php`
- Allows access to setup page even when license is unconfigured

**Before:**
```php
// ENFORCE LICENSE VALIDATION - NO BYPASS POSSIBLE
enforceLicenseValidation();

// Check license status and redirect if necessary
$license_status_code = $_SESSION['license_status_code'] ?? 'unknown';
$app_license_key = getAppLicenseKey();

// If license key is not configured, redirect to setup page
if (empty($app_license_key) && $current_page !== 'license_setup.php') {
    header('Location: license_setup.php');
    exit;
}
```

**After:**
```php
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
```

#### File: `/docker-ampnm/license_guard.php`
**Changes:**
- Added special handling for `unconfigured` license status
- When status is `unconfigured`, redirect to `license_setup.php` instead of `license_expired.php`
- Distinguishes between "not configured yet" and "configured but invalid"

**Before:**
```php
// Block access if license is not valid (but allow offline modes)
if (!in_array($status, $allowed_statuses)) {
    // Allow only license setup pages and documentation
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_pages = ['license_setup.php', 'license_expired.php', 'logout.php', 'documentation.php'];

    if (!in_array($current_page, $allowed_pages)) {
        // Force redirect to license expired page
        header('Location: license_expired.php');
        exit;
    }
}
```

**After:**
```php
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
```

### Fix 2: Portal Admin SMTP Navigation

#### File: `/includes/functions.php`
**Function:** `admin_header()`
**Changes:**
- Added SMTP Settings link to admin navigation menu
- Added Send Notifications link to admin navigation menu
- Added Font Awesome icons for visual clarity

**Before:**
```php
$admin_nav_links = [
    'index.php' => 'Dashboard',
    'users.php' => 'Customers',
    'license-manager.php' => 'Licenses',
    'products.php' => 'Products',
    'tickets.php' => '<i class="fas fa-headset mr-1"></i> Tickets',
];
```

**After:**
```php
$admin_nav_links = [
    'index.php' => 'Dashboard',
    'users.php' => 'Customers',
    'license-manager.php' => 'Licenses',
    'products.php' => 'Products',
    'tickets.php' => '<i class="fas fa-headset mr-1"></i> Tickets',
    'smtp_settings.php' => '<i class="fas fa-envelope-open-text mr-1"></i> SMTP',
    'send_notifications.php' => '<i class="fas fa-paper-plane mr-1"></i> Notifications',
];
```

---

## New Documentation Files

### 1. SETUP_GUIDE.md
**Location:** `/docker-ampnm/SETUP_GUIDE.md`
**Purpose:** Complete step-by-step guide for clients installing Docker AMPNM
**Contents:**
- Prerequisites
- Installation steps
- License activation process
- Troubleshooting common issues
- Offline mode explanation
- Support resources

### 2. INSTALLATION_FLOW.md
**Location:** `/docker-ampnm/INSTALLATION_FLOW.md`
**Purpose:** Technical documentation of the installation flow
**Contents:**
- Visual flow diagram
- Component descriptions
- License status codes
- Fix explanation
- Testing procedures
- Common issues and solutions

---

## Testing Results

### Test Case 1: Fresh Installation
✅ **PASS** - New installations redirect to `database_setup.php`

### Test Case 2: Database Setup
✅ **PASS** - After database setup, redirects to admin user creation

### Test Case 3: License Setup
✅ **PASS** - After admin creation, redirects to `license_setup.php`

### Test Case 4: License Activation
✅ **PASS** - License key validation works, redirects to dashboard

### Test Case 5: Unconfigured License
✅ **PASS** - Accessing any page without license redirects to `license_setup.php` (not `license_expired.php`)

### Test Case 6: SMTP Navigation
✅ **PASS** - SMTP Settings and Send Notifications visible in admin menu

---

## Impact Assessment

### Security
✅ **No security reduction** - License enforcement still active after setup
✅ **Improved UX** - Clients can now complete setup without confusion
✅ **Maintained protection** - All other pages still protected by license validation

### Functionality
✅ **Setup flow works correctly** - Fresh installations can complete setup
✅ **SMTP accessible** - Admin panel now includes email management
✅ **No breaking changes** - Existing installations unaffected

### User Experience
✅ **Clear setup path** - Logical flow from install to activation
✅ **Better documentation** - Comprehensive guides included
✅ **Reduced support burden** - Self-service setup documentation

---

## Installation Flow (Updated)

```
Fresh Install
    ↓
database_setup.php (Create tables)
    ↓
Create admin user
    ↓
license_setup.php (Enter license key) ← FIX APPLIED HERE
    ↓
License validation
    ↓
Dashboard (index.php)
```

**Previously:** System showed `license_expired.php` at the "Enter license key" step
**Now:** System correctly shows `license_setup.php` for license entry

---

## Files Modified

1. ✏️ `/docker-ampnm/includes/auth_check.php` - License enforcement logic
2. ✏️ `/docker-ampnm/license_guard.php` - Unconfigured status handling
3. ✏️ `/includes/functions.php` - Admin navigation menu
4. ➕ `/docker-ampnm/SETUP_GUIDE.md` - Client setup instructions
5. ➕ `/docker-ampnm/INSTALLATION_FLOW.md` - Technical documentation
6. ➕ `/CHANGES_SUMMARY.md` - This file

---

## Deployment Instructions

### For Docker AMPNM
No action required. Changes are in PHP files that are loaded dynamically.

**Option 1: Hot Deploy (No Restart)**
- Changes take effect immediately on next page load
- No service interruption

**Option 2: Restart (Recommended for testing)**
```bash
docker-compose restart
```

### For Portal Website
No action required. Changes are in PHP files that are loaded dynamically.

**To verify changes:**
1. Login to admin panel
2. Check navigation menu for "SMTP" and "Notifications" links
3. Verify links work and pages load correctly

---

## Backward Compatibility

✅ **Fully backward compatible**
- Existing Docker AMPNM installations with valid licenses: No impact
- Existing Portal admin users: Enhanced functionality (new menu items)
- Database schema: No changes required
- API compatibility: No changes

---

## Support Notes

### For Client Support Teams

**If client reports "License expired on fresh install":**
1. Ask client to manually navigate to: `http://[SERVER_IP]:2266/license_setup.php`
2. This should no longer happen with the fix applied
3. If still occurs, check if they're using old Docker image

**If client cannot find SMTP settings:**
1. Ensure they're logged in as admin
2. Look for "SMTP" link in top navigation bar
3. Click "SMTP" for settings, "Notifications" for email campaigns

**If license setup page shows error:**
1. Check network connectivity to portal.itsupport.com.bd
2. Verify license key is valid and active in portal
3. Check Docker container can resolve DNS
4. Ensure firewall allows outbound HTTPS

---

## Version Information

**AMPNM Docker Version:** 1.0
**Portal Version:** 1.0
**Fix Version:** 1.0.1
**Fix Date:** November 24, 2024

---

## Rollback Procedure

If issues occur, rollback by reverting the three modified files:

```bash
# Backup current files
cp docker-ampnm/includes/auth_check.php docker-ampnm/includes/auth_check.php.new
cp docker-ampnm/license_guard.php docker-ampnm/license_guard.php.new
cp includes/functions.php includes/functions.php.new

# Restore from git (if using version control)
git checkout docker-ampnm/includes/auth_check.php
git checkout docker-ampnm/license_guard.php
git checkout includes/functions.php

# Restart services
docker-compose restart
```

---

## Future Improvements

### Suggested Enhancements
1. Add license status indicator in dashboard header
2. Create automated license renewal reminders
3. Add license usage analytics
4. Implement license transfer capability
5. Add batch license activation for multiple installations

### Known Limitations
1. License verification requires internet connectivity
2. Offline mode limited to 30 days
3. License tied to installation_id (prevents easy migration)

---

## Conclusion

Both issues have been successfully resolved:

1. ✅ Docker AMPNM fresh installations now correctly show license setup page
2. ✅ Portal admin panel now includes SMTP and Notifications in navigation
3. ✅ Comprehensive documentation added for client support
4. ✅ No security compromises or breaking changes
5. ✅ Backward compatible with existing installations

The fixes improve user experience while maintaining security and license protection.

---

**Prepared by:** AI Assistant
**Reviewed:** Pending
**Approved:** Pending
**Deployed:** Pending

---

## Quick Reference

| Issue | Status | Files Changed |
|-------|--------|--------------|
| License setup redirect | ✅ Fixed | auth_check.php, license_guard.php |
| SMTP navigation missing | ✅ Fixed | functions.php |
| Documentation | ✅ Created | SETUP_GUIDE.md, INSTALLATION_FLOW.md |

