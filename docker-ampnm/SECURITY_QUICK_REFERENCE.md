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

## 🧭 HA Failover Runbook (Production)

### Architecture Baseline
- Run `docker-compose.ha.yml` profile with separated roles (`web`, `queue`, `workers`, `scheduler`).
- Use external HA database endpoint (managed MySQL/Postgres-equivalent HA proxy endpoint).
- Keep Redis persistent (`appendonly yes`) and monitored.

### Probe Endpoints and Checks
- Web liveness: `curl -fsS http://<host>:2266/health/live.php`
- Web readiness: `curl -fsS http://<host>:2266/health/ready.php`
- Worker health: `docker compose -f docker-compose.ha.yml exec workers php /var/www/html/api/workers/healthcheck.php worker`
- Scheduler health: `docker compose -f docker-compose.ha.yml exec scheduler php /var/www/html/api/workers/healthcheck.php scheduler`
- Queue health: `docker compose -f docker-compose.ha.yml exec queue redis-cli ping`

### Incident: Web/API replica failure
1. Confirm probe failures on impacted replica(s).
2. Restart only the unhealthy instance:
   ```bash
   docker compose -f docker-compose.ha.yml restart web
   ```
3. Validate readiness endpoint before reintroducing traffic.

### Incident: Worker backlog growth
1. Check Redis stream lag and worker health.
2. Scale workers:
   ```bash
   docker compose -f docker-compose.ha.yml up -d --scale workers=5
   ```
3. Verify dead-letter queue growth is stable and investigate poison messages.

### Incident: Scheduler stopped
1. Check heartbeat-based scheduler healthcheck status.
2. Restart scheduler:
   ```bash
   docker compose -f docker-compose.ha.yml restart scheduler
   ```
3. Confirm queue retention cleanup resumes.

### Incident: Database primary failover
1. Trigger/confirm DB vendor failover to replica.
2. Ensure `DB_HOST` points to HA endpoint (not node IP).
3. Recycle app roles to force fresh connections:
   ```bash
   docker compose -f docker-compose.ha.yml restart web workers scheduler
   ```
4. Validate `/health/ready.php` across all web replicas.

### Incident: Redis failure
1. Restore Redis service or promote Redis HA node.
2. Confirm `redis-cli ping` and app readiness.
3. Restart `workers` and `scheduler` if stream consumers did not rejoin.

---

**Full Documentation**: See SECURITY.md
