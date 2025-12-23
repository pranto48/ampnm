# Docker AMPNM Initial Setup Guide

## Prerequisites
- Docker and Docker Compose installed on your system
- A valid AMPNM license key from portal.itsupport.com.bd
- Network access to portal.itsupport.com.bd for license verification

## Installation Steps

### 1. Start the Docker Container
```bash
docker-compose up -d
```

The application will start on port 2266 (or as configured in docker-compose.yml).

### 2. Access the Application
Open your web browser and navigate to:
```
http://YOUR_SERVER_IP:2266
```

Example:
```
http://192.168.20.5:2266
```

### 3. Initial Database Setup
If this is your first installation, you will be redirected to:
```
http://YOUR_SERVER_IP:2266/database_setup.php
```

Click the **"Setup Database"** button to initialize the database tables.

### 4. Create Admin User
After database setup, you'll be redirected to create your first admin user:
- Enter a username (admin recommended)
- Enter a strong password
- Confirm your password

Click **"Create Admin User"** to proceed.

### 5. License Activation
After creating your admin account, you will be redirected to:
```
http://YOUR_SERVER_IP:2266/license_setup.php
```

**IMPORTANT:** This is where you activate your AMPNM license!

1. Enter your license key in the format: `XXXX-XXXX-XXXX-XXXX`
2. Click **"Activate License"**

The system will verify your license with the portal server and activate the application.

### 6. Login
Once your license is activated, you'll be redirected to the login page:
- Enter your admin username
- Enter your admin password
- Click **"Login"**

## Troubleshooting

### Docker compose up is slow or seems stuck
- Image download time depends on your connection. Run `docker compose pull` first to fetch layers, then `docker compose up --build --progress=plain -d` so you can see each step.
- Increase compose timeout for slow links: `export COMPOSE_HTTP_TIMEOUT=600` (10 minutes) before running compose.
- If you see `unexpected EOF` while pulling images, rerun `docker compose pull db --progress=plain` (or `docker pull mysql:8`) to resume the largest layer before starting again.
- Watch live logs to confirm progress: `docker compose logs -f --tail=50 app db`.
- Verify you have disk space (`df -h`) and that Docker can reach the registry (`ping registry-1.docker.io`).
- See `INSTALL_TROUBLESHOOTING.md` for a deeper checklist.

### License Expired Page on First Install
If you see the "License Required" page (`license_expired.php`) immediately after installation:

**This should NOT happen anymore!** The system now automatically redirects to `license_setup.php` when no license is configured.

If this still occurs:
1. Manually navigate to: `http://YOUR_SERVER_IP:2266/license_setup.php`
2. Enter your license key
3. Click "Activate License"

### Cannot Connect to License Server
If you see "Failed to connect to license verification service":

1. **Check network connectivity:**
   ```bash
   docker exec -it ampnm_app ping portal.itsupport.com.bd
   ```

2. **Check DNS resolution:**
   ```bash
   docker exec -it ampnm_app nslookup portal.itsupport.com.bd
   ```

3. **Verify firewall rules** - ensure outbound HTTPS (443) is allowed

4. **Check the portal is accessible:**
   ```bash
   curl -I https://portal.itsupport.com.bd
   ```

### Invalid License Key
If you see "Invalid license key" error:

1. Double-check the license key for typos
2. Ensure the license is active in the portal
3. Verify the license hasn't expired
4. Contact support at portal.itsupport.com.bd/support.php

## Offline Mode Support

AMPNM includes offline mode support for network outages:
- **0-9 days offline:** Application works normally, silent background verification attempts
- **9-30 days offline:** Warning banner displayed, application remains functional
- **30+ days offline:** Application locks until license can be re-verified

## License Verification

The system automatically verifies your license:
- Every 5 minutes during normal operation
- Checks license status, device limits, and expiration
- Validates installation ID to prevent unauthorized copies

## Post-Setup Configuration

After successful setup:
1. Add network devices in **"Devices"** menu
2. Configure email notifications (optional)
3. Set up network maps
4. Add additional users if needed

## Getting Your License Key

If you don't have a license key yet:
1. Visit: https://portal.itsupport.com.bd
2. Register an account
3. Purchase an AMPNM license
4. Your license key will be displayed in your dashboard
5. Use this key during the Docker AMPNM setup process

## Support

For technical support:
- **Portal:** https://portal.itsupport.com.bd/support.php
- **Documentation:** http://YOUR_SERVER_IP:2266/documentation.php
- **Email:** Contact through the portal support system

## Security Notes

- **DO NOT share your license key** - each key is tied to a specific installation
- **DO NOT modify license files** - tampering will disable the application
- Change the default admin password immediately after first login
- Use strong, unique passwords for all accounts
- Keep your Docker installation updated

## Quick Reference

| URL | Purpose |
|-----|---------|
| `/database_setup.php` | Initialize database (first run only) |
| `/license_setup.php` | Enter license key |
| `/login.php` | Login page |
| `/index.php` | Main dashboard |
| `/documentation.php` | User manual |
| `/license_expired.php` | License error page |

---

**AMPNM** - Advanced Multi-Protocol Network Monitor
Version 1.0 (Docker Edition)
© 2024 ITSupport.com.bd
