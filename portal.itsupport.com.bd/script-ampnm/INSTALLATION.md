# AMPNM Script Installation Guide (XAMPP/LAMP Version)

This is the PHP script version of AMPNM that runs on XAMPP/LAMP without Docker.

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server
- PHP extensions: PDO, PDO_MySQL, OpenSSL, JSON, cURL

## Installation Steps

### For XAMPP (Windows/Mac/Linux)

1. **Install XAMPP**
   - Download from: https://www.apachefriends.org/
   - Install and start Apache and MySQL

2. **Copy Files**
   ```bash
   # Copy this folder to XAMPP htdocs
   cp -r script-ampnm /path/to/xampp/htdocs/ampnm
   
   # Windows example:
   # Copy to C:\xampp\htdocs\ampnm
   ```

3. **Configure Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Create database: `network_monitor`
   - Or edit `config.php` to use different database name

4. **Setup Database**
   - Open browser: http://localhost/ampnm/database_setup.php
   - Click "Setup Database" button
   - This will create all required tables

5. **Login**
   - URL: http://localhost/ampnm/
   - Default credentials:
     - Username: `admin`
     - Password: `admin123`
   - **IMPORTANT**: Change password after first login!

### For LAMP (Linux Server)

1. **Install LAMP Stack**
   ```bash
   sudo apt update
   sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql php-curl php-json
   ```

2. **Copy Files**
   ```bash
   sudo cp -r script-ampnm /var/www/html/ampnm
   sudo chown -R www-data:www-data /var/www/html/ampnm
   sudo chmod -R 755 /var/www/html/ampnm
   ```

3. **Configure Database**
   ```bash
   sudo mysql
   CREATE DATABASE network_monitor;
   CREATE USER 'ampnm_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON network_monitor.* TO 'ampnm_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

4. **Update config.php**
   ```php
   define('DB_USERNAME', 'ampnm_user');
   define('DB_PASSWORD', 'your_password');
   ```

5. **Setup Database**
   - Open browser: http://your-server-ip/ampnm/database_setup.php
   - Click "Setup Database"

6. **Login**
   - URL: http://your-server-ip/ampnm/
   - Default credentials: admin / admin123

## Configuration

### Database Settings (config.php)

```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'network_monitor');
```

### License Settings

```php
define('LICENSE_API_URL', 'https://portal.itsupport.com.bd/verify_license.php');
```

## Features

- Real-time network monitoring
- Device ping checks
- Service monitoring (HTTP, HTTPS, TCP)
- Network topology map
- Alert notifications
- Email notifications
- Historical data and reports
- User management
- License management

## File Structure

```
script-ampnm/
├── config.php              # Database and license configuration
├── database_setup.php      # Database installation script
├── index.php               # Dashboard
├── login.php               # Login page
├── devices.php             # Device list
├── create-device.php       # Add new device
├── edit-device.php         # Edit device
├── map.php                 # Network topology map
├── history.php             # Historical data
├── users.php               # User management
├── email_notifications.php # Email settings
├── license_setup.php       # License activation
├── api.php                 # API endpoints
├── header.php              # Common header
├── footer.php              # Common footer
├── includes/               # Helper functions
│   ├── auth_check.php
│   ├── functions.php
│   └── license_manager.php
├── assets/                 # CSS, JS, images
│   ├── css/
│   ├── js/
│   └── sounds/
└── api/                    # API handlers
    └── handlers/
```

## Troubleshooting

### Database Connection Error

- Check MySQL is running
- Verify database credentials in config.php
- Ensure database exists

### Permission Errors

```bash
# Linux/Mac
sudo chown -R www-data:www-data /path/to/ampnm
sudo chmod -R 755 /path/to/ampnm
```

### PHP Extensions Missing

```bash
# Ubuntu/Debian
sudo apt install php-mysql php-curl php-json

# CentOS/RHEL
sudo yum install php-mysqlnd php-curl php-json
```

### License Activation Issues

- Verify internet connection
- Check LICENSE_API_URL in config.php
- Contact support: support@itsupport.com.bd

## Monitoring Setup

1. **Add Devices**
   - Go to "Devices" > "Add Device"
   - Enter IP address and hostname
   - Configure check intervals

2. **Configure Checks**
   - PING: Basic connectivity
   - HTTP: Web server monitoring
   - TCP: Port monitoring

3. **Set Up Alerts**
   - Configure email notifications
   - Set alert thresholds
   - Choose notification methods

4. **View Map**
   - Network topology visualization
   - Real-time status updates
   - Color-coded device status

## Cron Job (Automatic Monitoring)

For automatic monitoring, set up a cron job:

```bash
# Edit crontab
crontab -e

# Add this line (checks every minute)
* * * * * curl http://localhost/ampnm/api.php?action=check_all_devices
```

## Security Recommendations

1. **Change Default Password**
   - Login and go to profile settings
   - Change from default `admin123`

2. **Restrict Access**
   - Use .htaccess to limit IP access
   - Enable HTTPS/SSL
   - Use strong passwords

3. **Regular Backups**
   ```bash
   # Backup database
   mysqldump -u root -p network_monitor > backup.sql
   ```

4. **Update PHP**
   - Keep PHP version updated
   - Enable security patches

## Support

- Website: https://portal.itsupport.com.bd
- Email: support@itsupport.com.bd
- Documentation: See README.md

## Differences from Docker Version

The script version is identical in functionality to the Docker version, with these differences:

1. **Installation**: Direct PHP files instead of Docker containers
2. **Configuration**: Standard php config instead of environment variables
3. **Database**: Direct MySQL connection instead of Docker networking
4. **Updates**: Manual file updates instead of Docker image pulls

All features, UI, and functionality are exactly the same.

## License

This software requires a valid license key. Purchase at https://portal.itsupport.com.bd
