# AMPNM: Docker vs Script Version Comparison

## Overview

AMPNM is available in two installation methods:
1. **Docker Version** (`docker-ampnm/`) - Containerized deployment
2. **Script Version** (`script-ampnm/`) - Traditional PHP/LAMP deployment

**Both versions are functionally identical** - they share the same codebase, features, and user interface.

## ✅ What's Identical

### Core Features (100% Same)
- ✓ Real-time network monitoring
- ✓ Device management (add/edit/delete)
- ✓ Multiple check types (PING, HTTP, HTTPS, TCP)
- ✓ Network topology visualization
- ✓ Alert system with notifications
- ✓ Email notifications
- ✓ Historical data and reports
- ✓ User management
- ✓ License management system
- ✓ API endpoints
- ✓ Database schema
- ✓ UI/UX design
- ✓ All PHP files
- ✓ All JavaScript/CSS assets
- ✓ Sound alerts
- ✓ Map functionality
- ✓ Status monitoring
- ✓ Device groups

### Files (Identical Content)
```
✓ index.php
✓ devices.php
✓ create-device.php
✓ edit-device.php
✓ map.php
✓ history.php
✓ users.php
✓ email_notifications.php
✓ license_setup.php
✓ api.php
✓ header.php / footer.php
✓ includes/functions.php
✓ includes/license_manager.php
✓ api/handlers/* (all API handlers)
✓ assets/* (all CSS/JS/images)
```

## 🔄 What's Different

### 1. Installation Method

**Docker Version:**
```bash
docker-compose up -d
```
- Runs in Docker containers
- Isolated environment
- Automatic MySQL setup

**Script Version:**
```bash
# Copy to htdocs or /var/www/html
# Run setup.php
```
- Runs on existing PHP server
- Uses existing MySQL
- Manual database setup

### 2. Configuration File

**Docker Version (`config.php`):**
```php
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'network_monitor');
```
- Uses environment variables
- Docker networking

**Script Version (`config.php`):**
```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'network_monitor');
```
- Direct configuration
- Standard PHP constants

### 3. Deployment Files

**Docker Version has:**
- `Dockerfile`
- `docker-compose.yml`
- `docker-entrypoint.sh`
- `.dockerignore`

**Script Version has:**
- `setup.php` (setup wizard)
- `INSTALLATION.md` (detailed guide)

**Both these are deployment-specific and don't affect functionality.**

### 4. Database Connection

**Docker Version:**
- Connects to MySQL container on 127.0.0.1:3306
- Container networking

**Script Version:**
- Connects to localhost MySQL
- Standard PHP/MySQL connection

**Result: Same database schema, same queries, same functionality**

## 📊 Feature Comparison Table

| Feature | Docker | Script | Notes |
|---------|--------|--------|-------|
| Real-time monitoring | ✓ | ✓ | Identical |
| Device management | ✓ | ✓ | Identical |
| Network map | ✓ | ✓ | Identical |
| Alert system | ✓ | ✓ | Identical |
| Email notifications | ✓ | ✓ | Identical |
| User management | ✓ | ✓ | Identical |
| License system | ✓ | ✓ | Identical |
| API endpoints | ✓ | ✓ | Identical |
| UI/UX | ✓ | ✓ | Identical |
| Database schema | ✓ | ✓ | Identical |
| Sound alerts | ✓ | ✓ | Identical |
| Historical data | ✓ | ✓ | Identical |
| Export functionality | ✓ | ✓ | Identical |
| Multi-user support | ✓ | ✓ | Identical |
| Role-based access | ✓ | ✓ | Identical |

## 🎯 Which Version to Choose?

### Choose Docker Version If:
- ✓ You want isolated, containerized deployment
- ✓ You're familiar with Docker
- ✓ You want easy updates (pull new image)
- ✓ You need to run multiple instances
- ✓ You prefer infrastructure-as-code
- ✓ Your server supports Docker

### Choose Script Version If:
- ✓ You have existing XAMPP/LAMP setup
- ✓ You're on shared hosting without Docker
- ✓ You prefer traditional PHP deployment
- ✓ You want direct file access
- ✓ You need to customize PHP configuration
- ✓ Your hosting doesn't support containers

## 🔧 Converting Between Versions

### From Docker to Script:
1. Export Docker database: `docker exec mysql mysqldump ...`
2. Copy to script installation
3. Import database
4. Update config.php with new credentials

### From Script to Docker:
1. Export existing database: `mysqldump ...`
2. Set up Docker version
3. Import database into Docker MySQL
4. Configure environment variables

**No data loss - databases are compatible!**

## 📁 Directory Structure Comparison

```
docker-ampnm/                script-ampnm/
├── Dockerfile               ├── setup.php (NEW)
├── docker-compose.yml       ├── INSTALLATION.md (NEW)
├── docker-entrypoint.sh     
├── .dockerignore            
├── config.php               ├── config.php (Modified)
├── index.php                ├── index.php (Identical)
├── devices.php              ├── devices.php (Identical)
├── map.php                  ├── map.php (Identical)
├── api.php                  ├── api.php (Identical)
├── includes/                ├── includes/ (Identical)
├── api/handlers/            ├── api/handlers/ (Identical)
└── assets/                  └── assets/ (Identical)
```

**All functional files are identical!**

## 🚀 Performance

Both versions have:
- Same database queries
- Same PHP execution
- Same client-side JavaScript
- Same asset delivery

**Performance difference**: Negligible in most cases
- Docker adds minimal overhead (~1-2%)
- Script version uses existing PHP-FPM/Apache

## 🔒 Security

Both versions use:
- Same authentication system
- Same password hashing
- Same session management
- Same license validation
- Same encryption for sensitive data

**Security is identical** - depends on your server configuration, not the deployment method.

## 📝 Maintenance

### Updates

**Docker Version:**
```bash
docker-compose pull
docker-compose up -d
```

**Script Version:**
```bash
cp new-version/* /var/www/html/ampnm/
# Keep config.php
```

### Backups

**Docker Version:**
```bash
docker exec mysql mysqldump ...
```

**Script Version:**
```bash
mysqldump ...
```

**Database backups are compatible between versions!**

## ✨ Conclusion

**The choice between Docker and Script versions is purely about deployment preference.**

- **Functionality**: 100% Identical
- **Features**: 100% Identical  
- **UI/UX**: 100% Identical
- **Database**: 100% Compatible
- **Performance**: Virtually Identical

**Pick the deployment method that fits your infrastructure!**

Both versions:
- Use the same codebase
- Share the same updates
- Have the same license requirements
- Provide the same support

---

**Need help choosing?** Contact: support@itsupport.com.bd

**Want to try both?** They can run side-by-side (use different databases)
