#!/usr/bin/env bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License...
# (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#
set -euo pipefail

APACHE_PORT="${APACHE_PORT:-2266}"

# Ensure docker socket is writeable by non-root users (like www-data) inside the container
if [ -S /var/run/docker.sock ]; then
    chmod 666 /var/run/docker.sock || true
fi

echo "╔════════════════════════════════════════════════════════════╗"
echo "║       AMPNM - Advanced Multi-Protocol Network Monitor     ║"
echo "║              Docker Version - Starting...                  ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Check if license key is provided
if [ -z "${APP_LICENSE_KEY:-}" ]; then
    echo "⚠️  WARNING: No license key provided!"
    echo "    Set APP_LICENSE_KEY in docker-compose.yml"
    echo "    Application will require license activation after startup."
    echo ""
else
    echo "✓ License key detected"
    echo "  License will be validated on first access"
    echo ""
fi

# Verify critical files exist
echo "→ Verifying application integrity..."
CRITICAL_FILES=(
    "/var/www/html/license_guard.php"
    "/var/www/html/includes/license_manager.php"
    "/var/www/html/includes/auth_check.php"
    "/var/www/html/config.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "✗ CRITICAL ERROR: Missing file: $file"
        echo "  Application cannot start without all core files."
        exit 1
    fi
done
echo "✓ All critical files present"
echo ""

# Set secure permissions (optimized to skip full recursive walk on mounted volumes)
echo "→ Setting secure file permissions..."
git config --system --add safe.directory '*' || true
chown www-data:www-data /var/www || true
chown www-data:www-data /var/www/html || true

# Ensure directories requiring write access exist and have correct permissions
mkdir -p /var/www/html/uploads /var/www/html/data /var/www/html/data/code_backups /var/www/html/storage /var/www/html/storage/logs || true
chown -R www-data:www-data /var/www/html/uploads /var/www/html/data /var/www/html/storage || true
chmod -R 777 /var/www/html/uploads /var/www/html/data /var/www/html/storage || true
chmod +x /var/www/html/scripts/*.sh /var/www/html/scripts/*.php 2>/dev/null || true

# Make license files read-only for www-data if they exist
[ -f /var/www/html/license_guard.php ] && chmod 444 /var/www/html/license_guard.php || true
[ -f /var/www/html/includes/license_manager.php ] && chmod 444 /var/www/html/includes/license_manager.php || true
echo "✓ Permissions configured"
echo ""

# Allow www-data to write to docker socket if mounted
if [ -S /var/run/docker.sock ]; then
    chmod 666 /var/run/docker.sock || true
    echo "✓ Docker socket permissions configured (chmod 666)"
    echo ""
fi

echo "→ Configuring Apache to listen on port ${APACHE_PORT}..."
sed -ri "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf || true
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf || true
echo "✓ Apache configured"
echo ""

# Export env variables to Apache so they are accessible in PHP scripts
echo "→ Exporting environment variables to Apache..."
for var in DB_HOST DB_NAME DB_USER DB_PASSWORD MYSQL_ROOT_PASSWORD ADMIN_PASSWORD APP_LICENSE_KEY ALPHA_SMS_USERNAME ALPHA_SMS_API_KEY ALPHA_SMS_SENDER_ID SMS_ALERTS_ENABLED SMS_COOLDOWN_MINUTES APACHE_PORT AMPNM_ENABLE_UPDATE_CHECK_SCHEDULER AMPNM_UPDATE_CHECK_INTERVAL_SECONDS LICENSE_API_URL; do
    if [ -n "${!var+x}" ]; then
        val="${!var}"
        # Escape single quotes for safe shell evaluation
        escaped_val="${val//\'/\'\\\'\'}"
        echo "export $var='$escaped_val'" >> /etc/apache2/envvars
    fi
done
echo "✓ Environment variables exported"
echo ""

# --- Initialize and Start Internal Database (MariaDB) if single container ---
START_INTERNAL_DB=0
DB_HOST_VAL="${DB_HOST:-127.0.0.1}"

if [ "$DB_HOST_VAL" = "127.0.0.1" ] || [ "$DB_HOST_VAL" = "localhost" ] || [ "$DB_HOST_VAL" = "db" ]; then
    if [ "$DB_HOST_VAL" = "db" ]; then
        if getent hosts db >/dev/null; then
            echo "→ External database container 'db' detected. Skipping internal MariaDB..."
        else
            echo "→ Hostname 'db' could not be resolved. Falling back to internal MariaDB..."
            START_INTERNAL_DB=1
            # Override DB_HOST to 127.0.0.1 for the container environment
            export DB_HOST="127.0.0.1"
            # Rewrite Apache env file with the updated DB_HOST
            sed -i "s/export DB_HOST=.*/export DB_HOST='127.0.0.1'/g" /etc/apache2/envvars || true
        fi
    else
        START_INTERNAL_DB=1
    fi
fi

if [ "$START_INTERNAL_DB" = "1" ]; then
    echo "→ Standalone container mode: Setting up internal MariaDB..."

    # Ensure correct ownership on database folder
    chown -R mysql:mysql /var/lib/mysql || true

    # Initialize MariaDB if data folder is uninitialized
    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "  Initializing MariaDB system tables..."
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
    fi

    # Start MariaDB service in the background
    echo "  Starting MariaDB service..."
    mariadbd-safe --user=mysql --datadir=/var/lib/mysql &
    
    # Wait for service to be active
    for i in {1..30}; do
        if mariadb-admin ping --silent; then
            echo "  ✓ MariaDB started successfully"
            break
        fi
        echo "  Waiting for MariaDB server... ($i/30)"
        sleep 1
    done

    # Setup database, user access rights and passwords
    echo "  Configuring database privileges..."
    MYSQL_DATABASE="${DB_NAME:-network_monitor}"
    MYSQL_USER="${DB_USER:-}"
    MYSQL_PASSWORD="${DB_PASSWORD:-}"
    MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"

    # Setup database and configure root user privileges
    mariadb -u root <<EOF
CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\`;
EOF

    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        mariadb -u root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
    else
        mariadb -u root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
    fi

    # Setup user if configured and not root
    if [ -n "$MYSQL_USER" ] && [ "$MYSQL_USER" != "root" ]; then
        mariadb -u root -p"$MYSQL_ROOT_PASSWORD" <<EOF
CREATE USER IF NOT EXISTS '$MYSQL_USER'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';
GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO '$MYSQL_USER'@'%';
CREATE USER IF NOT EXISTS '$MYSQL_USER'@'localhost' IDENTIFIED BY '$MYSQL_PASSWORD';
GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO '$MYSQL_USER'@'localhost';
EOF
    fi

    # Restore pre-update database backup if it exists
    if [ -f "/var/www/html/uploads/db_backup_pre_update.sql" ]; then
        echo "→ Pre-update database backup detected. Restoring database tables..."
        MYSQL_DATABASE="${DB_NAME:-network_monitor}"
        MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
        if mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /var/www/html/uploads/db_backup_pre_update.sql; then
            echo "  ✓ Database restore successful"
            rm -f /var/www/html/uploads/db_backup_pre_update.sql
        else
            echo "  ❌ ERROR: Database restore failed"
        fi
    fi

    # Auto-restore maps, devices, and license key from JSON backup if available
    if [ -f "/var/www/html/uploads/pre_update_map_license_backup.json" ]; then
        echo "→ Restoring map settings, devices, and license key from backup JSON..."
        php -r '
          try {
            require_once "/var/www/html/includes/bootstrap.php";
            require_once "/var/www/html/config.php";
            $json = @file_get_contents("/var/www/html/uploads/pre_update_map_license_backup.json");
            $data = json_decode($json, true);
            if (is_array($data)) {
              $pdo = getDbConnection();
              if ($pdo) {
                if (!empty($data["license_key"])) {
                  setAppLicenseKey($data["license_key"]);
                }
                if (!empty($data["installation_id"])) {
                  updateAppSetting("installation_id", $data["installation_id"]);
                }
                $mapCount = $pdo->query("SELECT COUNT(*) FROM maps")->fetchColumn();
                if ($mapCount == 0 && !empty($data["maps"])) {
                  foreach ($data["maps"] as $m) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO maps (id, name, description, user_id, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$m["id"], $m["name"], $m["description"] ?? "", $m["user_id"] ?? 1, $m["is_public"] ?? 0, $m["created_at"] ?? date("Y-m-d H:i:s"), $m["updated_at"] ?? date("Y-m-d H:i:s")]);
                  }
                }
                $devCount = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
                if ($devCount == 0 && !empty($data["devices"])) {
                  foreach ($data["devices"] as $d) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO devices (id, user_id, map_id, name, ip, type, monitor_method, ping_interval, status, last_seen, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$d["id"], $d["user_id"] ?? 1, $d["map_id"] ?? 1, $d["name"], $d["ip"], $d["type"] ?? "device", $d["monitor_method"] ?? "ping", $d["ping_interval"] ?? 60, $d["status"] ?? "unknown", $d["last_seen"] ?? null, $d["created_at"] ?? date("Y-m-d H:i:s")]);
                  }
                }
              }
            }
          } catch (Throwable $e) {}
        ' || true
    fi

    # Unconditionally restore environment APP_LICENSE_KEY if passed
    if [ -n "${APP_LICENSE_KEY:-}" ]; then
        echo "→ Restoring license key from container environment variable..."
        php -r '
          try {
            require_once "/var/www/html/includes/bootstrap.php";
            require_once "/var/www/html/config.php";
            $envKey = trim(getenv("APP_LICENSE_KEY"));
            if (!empty($envKey)) {
                setAppLicenseKey($envKey);
            }
          } catch (Throwable $e) {}
        ' || true
    fi

    echo "  ✓ Internal database configuration complete"
    echo ""
fi


UPDATE_CHECK_INTERVAL_SECONDS="${AMPNM_UPDATE_CHECK_INTERVAL_SECONDS:-3600}"
if [ "${AMPNM_ENABLE_UPDATE_CHECK_SCHEDULER:-1}" = "1" ]; then
    echo "→ Starting update check scheduler (${UPDATE_CHECK_INTERVAL_SECONDS}s interval)..."
    bash /var/www/html/scripts/update_check.sh || true
    (
      while true; do
        sleep "${UPDATE_CHECK_INTERVAL_SECONDS}"
        bash /var/www/html/scripts/update_check.sh || true
      done
    ) &
    echo "✓ Update check scheduler started"
    echo ""
fi

echo "→ Starting scheduled backup auditor (60s check interval)..."
(
  while true; do
    php /var/www/html/scripts/backup_check.php || true
    sleep 60
  done
) &
echo "✓ Scheduled backup auditor started"
echo ""

echo "→ Starting Active Telemetry Trapper Server..."
php /var/www/html/api/workers/trapper_server.php > /var/log/trapper_server.log 2>&1 &
echo "✓ Trapper Server started"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "  AMPNM is starting on port ${APACHE_PORT}"
echo "  Access at: http://localhost:${APACHE_PORT}"
echo "════════════════════════════════════════════════════════════"
echo ""

exec apache2-foreground
