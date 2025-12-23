# AMPNM Docker - Security Features

## Overview

AMPNM Docker version implements multiple layers of security to protect the licensed software and ensure only authorized users can access the application.

## 🔐 License Protection System

### Multi-Layer Security

1. **Startup Validation**
   - License files verified on container start
   - Critical files integrity check
   - Read-only permissions enforced

2. **Runtime Protection**
   - License validated every 5 minutes
   - Automatic portal verification
   - No bypass mechanisms

3. **File Integrity Monitoring**
   - Core files checksummed
   - Tampering detection
   - Automatic disable on modification

4. **Complete Lockout**
   - Application disabled without valid license
   - API access blocked
   - No workarounds possible

## 🛡️ Security Components

### License Guard (`license_guard.php`)

**Purpose**: Primary security enforcement layer

**Features**:
- Enforces license validation on every page load
- Blocks unauthorized access
- Redirects to lockout page if license invalid
- Monitors file integrity
- Cannot be bypassed

**Protection**:
- Read-only permissions (444)
- Included in all protected pages
- Tampering detection enabled

### License Manager (`includes/license_manager.php`)

**Purpose**: License verification and management

**Features**:
- Communicates with portal API
- Encrypted license data exchange
- Grace period management
- Device limit enforcement
- Automatic re-verification

**Security**:
- AES-256-CBC encryption
- HMAC integrity checks
- Anti-tamper fingerprinting
- SSL/TLS communication

### Authentication Check (`includes/auth_check.php`)

**Purpose**: Combined auth + license validation

**Features**:
- User authentication
- License status check
- Role-based access control
- Automatic redirects

**Enforcement**:
- Calls `enforceLicenseValidation()`
- Blocks access if license invalid
- No exceptions allowed

## 🔒 License Validation Flow

```
Container Start
    ↓
Verify Critical Files ───→ Missing File? → Exit Container
    ↓                         ❌
Set Read-Only Permissions
    ↓
Start Apache
    ↓
User Access Attempt
    ↓
Load license_guard.php ───→ File Modified? → Disable App
    ↓                           ❌
Check License Status
    ↓
┌───────────────────────────────┐
│ License Status Check          │
├───────────────────────────────┤
│ Active? → Allow Access   ✓    │
│ Grace Period? → Allow Access ✓│
│ Expired? → Block Access  ❌   │
│ Invalid? → Block Access  ❌   │
│ Disabled? → Block Access ❌   │
└───────────────────────────────┘
    ↓
Re-verify every 5 minutes
```

## 📊 License Status Codes

| Status | Description | Access |
|--------|-------------|--------|
| `active` | Valid license | ✓ Full access |
| `grace_period` | Expired but in grace period (7 days) | ✓ Full access with warning |
| `expired` | Expired, grace period ended | ❌ Blocked |
| `disabled` | Revoked or tampered | ❌ Blocked |
| `invalid` | Invalid license key | ❌ Blocked |
| `unconfigured` | No license key set | ❌ Blocked (redirected to setup) |
| `portal_unreachable` | Cannot connect to license server | ⚠️ Temporary access (cached status) |

## 🚫 What Cannot Be Done

### Impossible to Bypass

1. **Cannot remove license check**
   - Integrated into every page
   - File integrity monitoring active
   - Modifications disable app

2. **Cannot fake license response**
   - AES-256 encryption
   - HMAC verification
   - Portal-side validation

3. **Cannot edit license files**
   - Read-only permissions (444)
   - Integrity checksums
   - Automatic detection

4. **Cannot bypass API validation**
   - All API calls check license
   - 403 Forbidden if invalid
   - No exception handling

5. **Cannot extend grace period**
   - Portal-controlled
   - Calculated server-side
   - Cannot be modified locally

6. **Cannot use without internet**
   - Requires portal verification
   - Maximum 5-minute cache
   - No offline mode

## 🔧 Configuration Security

### Environment Variables (docker-compose.yml)

```yaml
environment:
  APP_LICENSE_KEY: "your-license-key-here"  # REQUIRED
  LICENSE_DATA_KEY: ""                      # Optional encryption key
  LICENSE_FINGERPRINT_MODE: "enforce"       # enforce | allow-rebaseline
```

**Important**:
- `APP_LICENSE_KEY` must be set
- Stored encrypted in database
- Cannot be changed via UI
- Portal validation required

### File Permissions

Set automatically on container start:

```bash
# Application files
chmod 644 *.php
chmod 755 directories

# License files (read-only)
chmod 444 license_guard.php
chmod 444 includes/license_manager.php
```

**Result**:
- Web server can read, not write
- Users cannot modify
- Tampering prevented

## 🛠️ Tamper Detection

### File Integrity Monitoring

**Monitored Files**:
- `license_guard.php`
- `includes/license_manager.php`
- `includes/auth_check.php`
- `config.php`

**Detection Method**:
- HMAC-SHA256 fingerprints
- Stored in database
- Checked on every access

**Response to Tampering**:
```
File Modified
    ↓
Fingerprint Mismatch Detected
    ↓
Set license_status_code = 'disabled'
    ↓
Redirect to license_expired.php
    ↓
Application Locked ❌
```

**Recovery**:
- Re-baseline: Set `LICENSE_FINGERPRINT_MODE=allow-rebaseline`
- Or restore original files
- Or contact support

### Anti-Modification Protection

1. **Code Obfuscation**
   - License validation logic protected
   - Cannot be easily modified
   - Integrity checks in place

2. **Database Protection**
   - License data encrypted
   - Cannot modify status directly
   - Portal is source of truth

3. **Session Protection**
   - License status in session
   - Re-verified every 5 minutes
   - Cannot be forged

## 🔄 Verification Frequency

```
Event                          Verification Triggered
─────────────────────────────────────────────────────
Container Start                ✓ File integrity check
First Page Access             ✓ Full portal verification
Every Page Load               ✓ Session check only
Every 5 Minutes               ✓ Full portal re-verification
File Modification             ✓ Integrity check fails → disable
License Expiry                ✓ Status updated to expired
Grace Period End              ✓ Status updated to disabled
```

## 🚨 Security Best Practices

### For Administrators

1. **Protect License Key**
   ```yaml
   # Use secrets or environment
   APP_LICENSE_KEY: ${LICENSE_KEY}
   ```

2. **Restrict Container Access**
   ```bash
   # Don't expose to public internet directly
   # Use reverse proxy with authentication
   ```

3. **Monitor Logs**
   ```bash
   docker-compose logs app | grep LICENSE
   ```

4. **Regular Backups**
   ```bash
   # Backup doesn't include license
   # License must be activated per installation
   ```

### For Clients

1. **Keep License Current**
   - Renew before expiration
   - Monitor grace period warnings
   - Check email notifications

2. **Don't Modify Files**
   - Will trigger tamper detection
   - Application will be disabled
   - Requires support to restore

3. **Secure Environment**
   - Use strong passwords
   - Restrict network access
   - Enable HTTPS

## 📞 License Issues Support

### Common Issues

**"License Expired"**
- Grace period: 7 days
- Renew at: https://portal.itsupport.com.bd
- Support: support@itsupport.com.bd

**"Portal Unreachable"**
- Check internet connection
- Verify firewall allows HTTPS
- Check portal.itsupport.com.bd accessible

**"File Integrity Failed"**
- Files were modified
- Restore from backup
- Or contact support for re-baseline

**"Application Disabled"**
- License invalid or tampered
- Cannot be bypassed
- Contact support: support@itsupport.com.bd

### Getting Support

1. **Check Logs**
   ```bash
   docker-compose logs app | grep -i license
   ```

2. **Collect Information**
   - License key (if known)
   - Installation ID (from database)
   - Error messages

3. **Contact Support**
   - Email: support@itsupport.com.bd
   - Portal: https://portal.itsupport.com.bd/support.php
   - Include logs and error details

## 🔐 Encryption Details

### License Data Encryption

**Algorithm**: AES-256-CBC
**Key**: Defined in `license_manager.php`
**IV**: Random, prepended to ciphertext

**Process**:
```
Portal → Encrypt(license_data) → Application
Application → Decrypt(encrypted_data) → Validate
```

**Security**:
- Key must match portal and application
- Tampering detected via decryption failure
- Cannot forge valid response

### Database Encryption

**Sensitive Data Encrypted**:
- License keys
- API keys
- SMTP passwords

**Method**: AES-256-GCM with random IV

**Storage**:
```json
{
  "iv": "base64_iv",
  "tag": "base64_tag",
  "ciphertext": "base64_encrypted",
  "v": 1
}
```

## 🎯 Security Goals Achieved

✅ **License Enforcement**
- No unauthorized use possible
- Automatic validation
- Portal-controlled

✅ **Tamper Detection**
- File modifications detected
- Automatic disable
- Cannot be bypassed

✅ **Complete Lockout**
- All features disabled
- API access blocked
- No workarounds

✅ **Grace Period**
- 7-day buffer after expiry
- Clear warnings
- Smooth renewals

✅ **Minimal Friction**
- Valid licenses: no interruption
- Cached verification (5 min)
- Background checks

## 📋 Compliance

### License Terms

1. **Single Installation ID**
   - One license per installation
   - Cannot share between servers
   - Validated via installation_id

2. **Device Limits**
   - Enforced by license tier
   - Cannot exceed limit
   - Portal-controlled

3. **Renewal Required**
   - Expired licenses disabled
   - Grace period: 7 days
   - Must renew through portal

### Audit Trail

All license events logged:
```
LICENSE_INFO: License verification completed. Status: active
LICENSE_ERROR: Failed to decrypt license response
LICENSE_INTEGRITY: Fingerprint mismatch for license_guard.php
```

---

**This security system ensures AMPNM can only be used with valid authorization from portal.itsupport.com.bd**

No bypasses, no workarounds, no exceptions.
