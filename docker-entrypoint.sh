#!/usr/bin/env bash
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

# Only apply recursive permissions to directories that require write access
if [ -d /var/www/html/uploads ]; then
    chown -R www-data:www-data /var/www/html/uploads || true
    chmod -R 775 /var/www/html/uploads || true
fi
if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage || true
    chmod -R 775 /var/www/html/storage || true
fi

# Make license files read-only for www-data if they exist
[ -f /var/www/html/license_guard.php ] && chmod 444 /var/www/html/license_guard.php || true
[ -f /var/www/html/includes/license_manager.php ] && chmod 444 /var/www/html/includes/license_manager.php || true
echo "✓ Permissions configured"
echo ""

echo "→ Configuring Apache to listen on port ${APACHE_PORT}..."
sed -ri "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf || true
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf || true
echo "✓ Apache configured"
echo ""

# Export env variables to Apache so they are accessible in PHP scripts
echo "→ Exporting environment variables to Apache..."
for var in DB_HOST DB_NAME DB_USER DB_PASSWORD MYSQL_ROOT_PASSWORD ADMIN_PASSWORD APP_LICENSE_KEY ALPHA_SMS_USERNAME ALPHA_SMS_API_KEY ALPHA_SMS_SENDER_ID SMS_ALERTS_ENABLED SMS_COOLDOWN_MINUTES APACHE_PORT CLOUD_SYNC_URL CLOUD_ANON_KEY CLOUD_POLL_INTERVAL AMPNM_ENABLE_UPDATE_CHECK_SCHEDULER AMPNM_UPDATE_CHECK_INTERVAL_SECONDS; do
    if [ -n "${!var+x}" ]; then
        val="${!var}"
        # Escape single quotes for safe shell evaluation
        escaped_val="${val//\'/\'\\\'\'}"
        echo "export $var='$escaped_val'" >> /etc/apache2/envvars
    fi
done
echo "✓ Environment variables exported"
echo ""

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
