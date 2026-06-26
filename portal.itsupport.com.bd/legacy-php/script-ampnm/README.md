# AMPNM - Advanced Multi-Protocol Network Monitor
## Script Version for XAMPP/LAMP

This is the **Script Version** of AMPNM that runs on standard XAMPP/LAMP installations without Docker.

## 🎯 Overview

AMPNM (Advanced Multi-Protocol Network Monitor) is a comprehensive network monitoring solution that provides real-time monitoring, alerting, and visualization of your network infrastructure.

**This version is functionally identical to the Docker version** - same features, same UI, same capabilities - but designed to run on traditional PHP hosting environments.

## ✨ Features

- **Real-time Network Monitoring**: Monitor devices and services in real-time
- **Multiple Check Types**: PING, HTTP, HTTPS, TCP port checks
- **Visual Network Topology**: Interactive map showing network structure
- **Alert System**: Instant notifications when issues are detected
- **Email Notifications**: Configurable email alerts
- **Historical Data**: Track performance metrics over time
- **Device Management**: Easy add/edit/delete devices
- **User Management**: Multi-user support with role-based access
- **License Management**: Integrated licensing system
- **API Access**: RESTful API for integrations
- **Responsive Design**: Works on desktop, tablet, and mobile

## 📋 Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Web Server**: Apache (with mod_rewrite) or Nginx
- **PHP Extensions**:
  - PDO
  - PDO_MySQL
  - OpenSSL
  - JSON
  - cURL
  - MBString (recommended)

## 🚀 Quick Start

### XAMPP Installation (Windows/Mac/Linux)

1. **Install XAMPP** from https://www.apachefriends.org/

2. **Copy Files**:
   ```bash
   # Copy this folder to xampp/htdocs
   cp -r script-ampnm C:/xampp/htdocs/ampnm
   ```

3. **Start Services**:
   - Open XAMPP Control Panel
   - Start Apache and MySQL

4. **Run Setup**:
   - Open browser: http://localhost/ampnm/setup.php
   - Follow the setup wizard
   - Click "Setup Database" when ready

5. **Login**:
   - URL: http://localhost/ampnm/
   - Username: `admin`
   - Password: `admin123`
   - **Change password immediately!**

### LAMP Installation (Linux Server)

1. **Install LAMP Stack**:
   ```bash
   sudo apt update
   sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql php-curl php-json
   sudo systemctl enable apache2 mysql
   sudo systemctl start apache2 mysql
   ```

2. **Copy Files**:
   ```bash
   sudo cp -r script-ampnm /var/www/html/ampnm
   sudo chown -R www-data:www-data /var/www/html/ampnm
   sudo chmod -R 755 /var/www/html/ampnm
   ```

3. **Create Database**:
   ```bash
   sudo mysql
   CREATE DATABASE network_monitor;
   CREATE USER 'ampnm_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT ALL PRIVILEGES ON network_monitor.* TO 'ampnm_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

4. **Configure**:
   - Edit `config.php` with your database credentials
   - Run setup: http://your-server/ampnm/setup.php

5. **Enable mod_rewrite** (if needed):
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

## ⚙️ Configuration

### Database Configuration (`config.php`)

```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'network_monitor');
```

### License Configuration

```php
define('LICENSE_API_URL', 'https://portal.itsupport.com.bd/verify_license.php');
```

## 📱 Usage

### Adding Devices

1. Navigate to **Devices** → **Add Device**
2. Enter device information:
   - **Device Name**: Unique identifier
   - **IP Address/Hostname**: Device address
   - **Device Type**: Router, Switch, Server, etc.
   - **Check Interval**: Monitoring frequency
3. Configure check types (PING, HTTP, TCP)
4. Save device

### Monitoring Dashboard

The dashboard shows:
- **Device Status Overview**: Quick status of all devices
- **Recent Alerts**: Latest issues detected
- **Performance Metrics**: Response times and availability
- **Status Summary**: Up/Down/Warning counts

### Network Topology Map

Access via **Map** menu:
- Visual representation of your network
- Color-coded device status
- Click devices for details
- Real-time updates

### Setting Up Alerts

1. Go to **Email Notifications**
2. Configure SMTP settings
3. Set alert thresholds
4. Add notification recipients
5. Test email delivery

## 🔧 Advanced Configuration

### Automated Monitoring (Cron)

For continuous monitoring, set up a cron job:

```bash
# Edit crontab
crontab -e

# Add this line (checks every minute)
* * * * * curl -s http://localhost/ampnm/api.php?action=check_all_devices >/dev/null 2>&1

# Or use wget
* * * * * wget -q -O /dev/null http://localhost/ampnm/api.php?action=check_all_devices
```

### Apache Configuration

For cleaner URLs, create `.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /ampnm/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.+)$ index.php [L]
</IfModule>
```

### Performance Tuning

For large installations (100+ devices):

```php
// config.php additions
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');
```

## 🔒 Security

### Immediate Actions

1. **Change default password** after first login
2. **Use strong database password**
3. **Enable HTTPS/SSL** on production
4. **Restrict directory permissions**:
   ```bash
   chmod 755 /path/to/ampnm
   chmod 644 /path/to/ampnm/*.php
   ```

### IP Restriction (Optional)

Add to `.htaccess`:

```apache
<Files "admin_*.php">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.0/24
</Files>
```

### Regular Backups

```bash
# Backup database
mysqldump -u root -p network_monitor > ampnm_backup_$(date +%Y%m%d).sql

# Backup files
tar -czf ampnm_files_$(date +%Y%m%d).tar.gz /var/www/html/ampnm
```

## 🆘 Troubleshooting

### Database Connection Errors

1. Verify MySQL is running:
   ```bash
   sudo systemctl status mysql
   ```

2. Check credentials in `config.php`

3. Verify database exists:
   ```bash
   mysql -u root -p
   SHOW DATABASES;
   ```

### Permission Issues

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/html/ampnm

# Fix permissions
sudo chmod -R 755 /var/www/html/ampnm
```

### PHP Extensions Missing

```bash
# Ubuntu/Debian
sudo apt install php-mysql php-curl php-json php-mbstring

# CentOS/RHEL
sudo yum install php-mysqlnd php-curl php-json php-mbstring

# Restart Apache
sudo systemctl restart apache2
```

### Blank Pages

1. Enable error display:
   ```php
   // Add to config.php temporarily
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

2. Check PHP error log:
   ```bash
   tail -f /var/log/apache2/error.log
   ```

## 📊 API Documentation

### Endpoints

- **GET /api.php?action=get_devices** - List all devices
- **POST /api.php?action=add_device** - Add new device
- **GET /api.php?action=check_device&id=X** - Check specific device
- **GET /api.php?action=check_all_devices** - Check all devices
- **GET /api.php?action=get_history&device_id=X** - Get device history

### Example API Call

```bash
curl -X GET "http://localhost/ampnm/api.php?action=get_devices"
```

## 🔄 Updating

1. **Backup current installation**
2. **Download latest version**
3. **Copy new files over existing**:
   ```bash
   cp -r new-version/* /var/www/html/ampnm/
   ```
4. **Keep your config.php** (don't overwrite)
5. **Check database updates** in changelog
6. **Clear browser cache**

## 📁 File Structure

```
script-ampnm/
├── config.php              # Configuration
├── setup.php               # Setup wizard
├── database_setup.php      # Database installer
├── index.php               # Dashboard
├── login.php               # Authentication
├── devices.php             # Device list
├── create-device.php       # Add device
├── edit-device.php         # Edit device
├── map.php                 # Network map
├── history.php             # Historical data
├── users.php               # User management
├── email_notifications.php # Email settings
├── license_setup.php       # License activation
├── api.php                 # API endpoint
├── includes/               # Core functions
├── api/handlers/           # API handlers
├── assets/                 # Frontend assets
│   ├── css/
│   ├── js/
│   └── sounds/
└── INSTALLATION.md         # Detailed install guide
```

## 🆚 Docker vs Script Version

| Feature | Docker Version | Script Version |
|---------|---------------|----------------|
| Installation | Docker required | Standard PHP hosting |
| Database | MySQL in container | Existing MySQL |
| Updates | Pull new image | Upload new files |
| Portability | Container-based | File-based |
| Performance | Isolated resources | Shared hosting |
| **Features** | **Identical** | **Identical** |
| **UI/UX** | **Identical** | **Identical** |

**Both versions have exactly the same functionality!**

## 💳 Licensing

This software requires a valid license key for operation.

- **Purchase**: https://portal.itsupport.com.bd
- **Activation**: Settings → License Management
- **Support**: support@itsupport.com.bd

### License Plans

- **Starter**: Up to 10 devices
- **Professional**: Up to 50 devices
- **Enterprise**: Up to 200 devices

## 📞 Support

- **Website**: https://portal.itsupport.com.bd
- **Email**: support@itsupport.com.bd
- **Documentation**: See INSTALLATION.md
- **Setup Help**: Run setup.php for diagnostics

## 🙏 Credits

Developed by IT Support Bangladesh
- Website: portal.itsupport.com.bd
- Version: 1.0 (Script Edition)
- Last Updated: 2024

## 📝 License

Copyright © 2024 IT Support Bangladesh. All rights reserved.

This software is licensed, not sold. See license agreement for details.
