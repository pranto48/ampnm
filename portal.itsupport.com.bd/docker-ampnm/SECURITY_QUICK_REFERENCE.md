# AMPNM Security - Quick Reference

## 🔐 Security Features at a Glance

### License Protection
- ✅ Required for all operations
- ✅ Validated every 5 minutes
- ✅ Portal-controlled
- ✅ Cannot be bypassed

### File Protection
- ✅ Read-only permissions (444)
- ✅ Integrity monitoring
- ✅ Tamper detection
- ✅ Auto-disable on modification

### Access Control
- ✅ Complete lockout without license
- ✅ API access blocked
- ✅ Grace period: 7 days
- ✅ No offline mode

## 🚀 Quick Setup

```yaml
# docker-compose.yml
environment:
  APP_LICENSE_KEY: "AMPNM-XXXX-XXXX-XXXX-XXXX"  # GET FROM PORTAL
```

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

## 📊 License Status

| Status | Access | Action |
|--------|--------|--------|
| ✅ Active | Full | None needed |
| ⚠️ Grace Period | Full + Warning | Renew soon |
| ❌ Expired | Blocked | Renew now |
| ❌ Invalid | Blocked | Contact support |

## 🛡️ What's Protected

```
✓ All PHP pages          - License check required
✓ API endpoints          - 403 if no license
✓ Database operations    - Blocked without license
✓ Monitoring functions   - Disabled without license
✓ Configuration files    - Read-only, cannot modify
```

## ⚠️ What NOT to Do

1. ❌ Don't modify license files → App will disable
2. ❌ Don't remove license checks → Integrated everywhere
3. ❌ Don't share license keys → Tracked per installation
4. ❌ Don't expect offline use → Portal verification required
5. ❌ Don't ignore warnings → Grace period is only 7 days

## 🔧 Troubleshooting

**"License Expired"**
→ Renew at portal.itsupport.com.bd

**"Portal Unreachable"**
→ Check internet, firewall, DNS

**"File Integrity Failed"**
→ Files modified, contact support

**"Application Disabled"**
→ License invalid/tampered, cannot bypass

## 📞 Quick Support

- **Portal**: https://portal.itsupport.com.bd
- **Email**: support@itsupport.com.bd
- **Logs**: `docker-compose logs app | grep -i license`

## 🔍 Verification Commands

```bash
# Check license key is set
docker-compose config | grep APP_LICENSE_KEY

# View startup logs
docker-compose logs app | head -50

# Check file permissions
docker-compose exec app ls -la /var/www/html/license_guard.php

# Monitor license checks
docker-compose logs -f app | grep LICENSE
```

## 🎯 Security Guarantee

**Multi-layer protection ensures:**
- No unauthorized use possible
- No bypass mechanisms exist
- No offline workarounds available
- Portal controls all licensing
- Automatic enforcement at system level

**Result**: Application only works with valid license from portal.itsupport.com.bd

---

**Full Documentation**: See SECURITY.md
