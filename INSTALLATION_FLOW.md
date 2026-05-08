# Docker AMPNM Installation Flow

## Flow Diagram

```
┌─────────────────────────────────────────┐
│  Client Accesses Docker AMPNM          │
│  http://192.168.20.5:2266               │
└────────────────┬────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│  bootstrap.php Checks Database          │
│  - Tries to query 'users' table         │
└────────────────┬────────────────────────┘
                 │
        ┌────────┴────────┐
        │                 │
  Table Exists?      Table Missing?
        │                 │
        ▼                 ▼
   Continue      ┌──────────────────────┐
                 │ Redirect to:         │
                 │ database_setup.php   │
                 │ - Create tables      │
                 │ - Create admin user  │
                 └──────────┬───────────┘
                            │
                            ▼
                      [Setup Complete]
                            │
┌───────────────────────────┴─────────────┐
│  All Pages Include: auth_check.php      │
│  - Check if user logged in              │
│  - Check license status                 │
└────────────────┬────────────────────────┘
                 │
        ┌────────┴────────┐
        │                 │
   Not Logged In?    Logged In?
        │                 │
        ▼                 ▼
   ┌─────────┐    ┌──────────────────────┐
   │ login.php│    │ Check License Key    │
   └─────────┘    │ getAppLicenseKey()   │
                  └──────────┬───────────┘
                             │
                    ┌────────┴────────┐
                    │                 │
              No License Key?    Has License Key?
                    │                 │
                    ▼                 ▼
        ┌────────────────────┐  ┌──────────────────────┐
        │ Redirect to:       │  │ Verify License       │
        │ license_setup.php  │  │ - Call portal API    │
        │ - Enter key        │  │ - Check status       │
        │ - Validate         │  │ - Update session     │
        └────────┬───────────┘  └──────────┬───────────┘
                 │                         │
                 │                ┌────────┴────────┐
                 │                │                 │
                 │          License Valid?    License Invalid?
                 │                │                 │
                 └────────────────┼─────────────────┘
                                  │
                         ┌────────┴────────┐
                         │                 │
                    Status OK?       Status Failed?
                    (active,         (disabled,
                  grace_period,      expired,
                  offline_mode,      offline_expired)
                  offline_warning)        │
                         │                │
                         ▼                ▼
                  ┌──────────┐    ┌──────────────────┐
                  │ Allow    │    │ Redirect to:     │
                  │ Access   │    │ license_expired. │
                  │ to App   │    │ php              │
                  └──────────┘    │ - Show error     │
                                  │ - Block all pages│
                                  └──────────────────┘
```

## Key Components

### 1. bootstrap.php
- Loads database connection
- Checks if database tables exist
- Starts session
- Redirects to database_setup.php if tables missing

### 2. auth_check.php
- Included on every protected page
- Checks user authentication
- Loads license_guard.php
- Checks license key existence
- Enforces license validation (except on license_setup.php)
- Redirects based on license status

### 3. license_guard.php
- Core security component
- Enforces license validation on every page load
- Allows specific pages when license is unconfigured:
  - license_setup.php (to enter license key)
  - license_expired.php (to show errors)
  - logout.php (to logout)
  - documentation.php (user manual)
- Blocks all other pages when license invalid

### 4. license_manager.php
- Contains verifyLicenseWithPortal() function
- Handles API communication with portal
- Manages offline mode (30-day policy)
- Updates session variables with license status
- Encrypts/decrypts license data

### 5. license_setup.php
- First-time license activation page
- Validates license key against portal API
- Saves license key to database
- Triggers immediate verification
- Redirects to index.php on success

## License Status Codes

| Status Code | Meaning | Application Access |
|-------------|---------|-------------------|
| `unconfigured` | No license key entered | Only setup pages |
| `active` | Valid, active license | Full access |
| `grace_period` | Expired, in 7-day grace | Full access with warning |
| `offline_mode` | 0-9 days offline | Full access, silent retry |
| `offline_warning` | 9-30 days offline | Full access with warning |
| `offline_expired` | 30+ days offline | Blocked - license_expired.php |
| `disabled` | Revoked or invalid | Blocked - license_expired.php |
| `expired` | Past grace period | Blocked - license_expired.php |

## Fix Applied (Nov 2024)

**Problem:** On fresh installation, clients were redirected to `license_expired.php` instead of `license_setup.php`

**Root Cause:**
- `license_guard.php` was blocking ALL pages before checking if license was configured
- System assumed any non-active status meant "expired"

**Solution:**
1. Added special handling for `unconfigured` status in `license_guard.php`
2. Modified `auth_check.php` to skip `enforceLicenseValidation()` on `license_setup.php`
3. When status is `unconfigured`, redirect to `license_setup.php` (not `license_expired.php`)

**Result:**
- Fresh installations now correctly redirect to license setup
- Clients can enter their license key on first access
- After activation, normal license validation applies

## Testing the Installation

### Test 1: Fresh Installation
```bash
# Remove existing data
docker-compose down -v
docker-compose up -d

# Access application
curl -I http://localhost:2266
# Should redirect to database_setup.php
```

### Test 2: Database Setup
```bash
# After database setup, should redirect to create admin user
# After admin user creation, should redirect to license_setup.php
```

### Test 3: License Activation
```bash
# Enter license key on license_setup.php
# Should verify with portal and redirect to index.php
# Check session variables for license status
```

### Test 4: Valid License
```bash
# All pages should be accessible
# Dashboard, devices, maps, users, etc.
```

### Test 5: No License (Unconfigured)
```bash
# Remove license key from database
UPDATE app_settings SET setting_value = '' WHERE setting_key = 'app_license_key';

# Access any page - should redirect to license_setup.php
# Direct access to license_setup.php should work
```

### Test 6: Expired License
```bash
# Portal should return 'expired' status
# Should redirect to license_expired.php
# Should show renewal options
```

## Common Issues & Solutions

### Issue: Redirect Loop
**Symptom:** Browser shows "Too many redirects"
**Cause:** Conflicting redirect logic between auth_check.php and license_guard.php
**Solution:** Ensure license_setup.php is excluded from enforceLicenseValidation()

### Issue: Cannot Access Setup Page
**Symptom:** Always redirects to license_expired.php
**Cause:** license_guard.php not recognizing 'unconfigured' status
**Solution:** Check license_manager.php sets status to 'unconfigured' when no key exists

### Issue: License Not Saving
**Symptom:** License validation succeeds but not persisted
**Cause:** Database write permissions or setAppLicenseKey() function error
**Solution:** Check app_settings table exists and is writable

## Environment Variables

No environment variables required for license system. Configuration is in:
- `config.php` - LICENSE_API_URL, encryption settings
- `license_manager.php` - Intervals, grace periods, offline limits

## Files Modified for Fix

1. `/docker-ampnm/includes/auth_check.php`
   - Added conditional license enforcement
   - Skip validation on license_setup.php

2. `/docker-ampnm/license_guard.php`
   - Added special handling for 'unconfigured' status
   - Redirect to license_setup.php instead of license_expired.php

3. `/docker-ampnm/includes/license_manager.php`
   - Already had 'unconfigured' status (no changes needed)
   - Sets status when getAppLicenseKey() returns empty

## Support Resources

- **Setup Guide:** `/docker-ampnm/SETUP_GUIDE.md`
- **Installation Flow:** `/docker-ampnm/INSTALLATION_FLOW.md` (this file)
- **User Manual:** http://localhost:2266/documentation.php
- **Portal Support:** https://portal.itsupport.com.bd/support.php
