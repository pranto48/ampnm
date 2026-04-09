#!/usr/bin/env bash
set -euo pipefail

APACHE_PORT="${APACHE_PORT:-2266}"

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

# Set secure permissions
echo "→ Setting secure file permissions..."
chown -R www-data:www-data /var/www/html
find /var/www/html -type f -name "*.php" -exec chmod 644 {} \;
find /var/www/html -type d -exec chmod 755 {} \;

# Make license files read-only for www-data
chmod 444 /var/www/html/license_guard.php
chmod 444 /var/www/html/includes/license_manager.php
echo "✓ Permissions configured"
echo ""

echo "→ Configuring Apache to listen on port ${APACHE_PORT}..."
sed -ri "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf || true
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf || true
echo "✓ Apache configured"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "  AMPNM is starting on port ${APACHE_PORT}"
echo "  Access at: http://localhost:${APACHE_PORT}"
echo "════════════════════════════════════════════════════════════"
echo ""

exec apache2-foreground
