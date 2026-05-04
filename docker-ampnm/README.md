# AMPNM - Advanced Multi-Protocol Network Monitor (Docker Version)

Real-time network monitoring system with visual topology mapping.

## 🚀 Quick Start

```bash
# Optional: pre-pull images on slow connections
docker compose pull

# Build and start with full progress output
docker compose up --build --progress=plain -d
```

Access at: http://localhost:2266

**Default Login:**
- Username: `admin`
- Password: `password` (change in docker-compose.yml)

> Stuck during download/build? See `INSTALL_TROUBLESHOOTING.md` for diagnostics and progress tips.
> If you see `unexpected EOF` while pulling images, rerun `docker compose pull db --progress=plain` (or `docker pull mysql:8`) to resume the largest layer, then rerun the start command.

## 📋 Requirements

- Docker & Docker Compose
- No other dependencies needed!

## ✨ Features

- **Real-time Monitoring**: ICMP Ping, HTTP/HTTPS, TCP port checks
- **Network Topology Map**: Visual representation with status colors
- **Alert System**: Email notifications on status changes
- **Historical Data**: Track performance metrics over time
- **Multi-User**: Admin and viewer roles
- **License Management**: Built-in licensing system

## 🔧 Configuration

### Environment Variables (docker-compose.yml)

```yaml
environment:
  MYSQL_ROOT_PASSWORD: yourSecurePassword
  MYSQL_DATABASE: network_monitor
  DB_USER: ampnm_user
  DB_PASSWORD: yourDbPassword
  ADMIN_PASSWORD: yourAdminPassword
  APP_LICENSE_KEY: your-license-key
```

### Important Settings

- **MYSQL_ROOT_PASSWORD**: MySQL root password
- **DB_PASSWORD**: Application database password
- **ADMIN_PASSWORD**: Admin user password (default: `password`)
- **APP_LICENSE_KEY**: Your license key from portal

## 📊 Monitoring Features

### Check Types

1. **ICMP Ping** (Default)
   - Latency monitoring
   - Packet loss detection
   - TTL tracking
   - Thresholds: Warning & Critical

2. **TCP Port Check**
   - Port availability
   - Connection time
   - Service status

3. **HTTP/HTTPS Check**
   - Response code
   - Response time
   - Content verification

### Status Levels

- 🟢 **Online**: Device responding normally
- 🟡 **Warning**: Latency or packet loss threshold exceeded
- 🔴 **Critical**: Severe latency, packet loss, or offline
- ⚪ **Offline**: Device unreachable
- ⚫ **Unknown**: No data or unconfigured

## 🗺️ Network Topology

- Drag-and-drop device placement
- Connection visualization
- Real-time status updates
- Color-coded indicators
- Custom icons and sizes
- Public map sharing option

## 📧 Email Notifications

Configure SMTP settings to receive alerts:

1. Go to **Email Notifications**
2. Enter SMTP server details
3. Add recipient emails per device
4. Choose notification triggers:
   - Device goes online
   - Device goes offline
   - Warning status
   - Critical status

## 🔐 Security

### Best Practices

1. **Change Default Passwords**
   ```yaml
   ADMIN_PASSWORD: strong_password_here
   MYSQL_ROOT_PASSWORD: another_strong_password
   ```

2. **Restrict Port Access**
   ```yaml
   ports:
     - "127.0.0.1:2266:80"  # Only accessible from localhost
   ```

3. **Use HTTPS** (Production)
   - Set up reverse proxy (Nginx/Apache)
   - Configure SSL certificates
   - Enable HTTPS redirects

4. **Regular Backups**
   ```bash
   docker exec ampnm-mysql mysqldump -u root -p network_monitor > backup.sql
   ```

## 🛠️ Advanced Configuration

### Custom Ping Intervals

Set per-device:
- Minimum: 10 seconds
- Default: 60 seconds
- Maximum: 3600 seconds (1 hour)

### Threshold Configuration

**Warning Thresholds:**
- Latency: 100ms default
- Packet Loss: 10% default

**Critical Thresholds:**
- Latency: 300ms default
- Packet Loss: 50% default

## 📂 Docker Volumes

```yaml
volumes:
  mysql-data: {}      # Database storage
  app-uploads: {}     # Icons, backgrounds, backups
```

### Backup Volumes

```bash
# Backup database
docker-compose exec mysql mysqldump -u root -p network_monitor > backup.sql

# Backup uploads
docker cp ampnm-app:/var/www/html/uploads ./uploads-backup
```

## 🔄 Updates

```bash
# Pull latest image
docker-compose pull

# Restart with new image
docker-compose down
docker-compose up -d
```

## 🐛 Troubleshooting

### Ping Not Working

**Issue**: "sh: 1: ping: not found"

**Solution**: The Dockerfile has been updated to include `iputils-ping`. Rebuild:

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Database Connection Errors

```bash
# Check MySQL is running
docker-compose ps

# View logs
docker-compose logs mysql
docker-compose logs app

# Restart services
docker-compose restart
```

### Permission Issues

```bash
# Fix ownership
docker-compose exec app chown -R www-data:www-data /var/www/html
```

### Network Access Issues

```bash
# Check if container can ping external hosts
docker-compose exec app ping 8.8.8.8

# Check internal network
docker network inspect ampnm-network
```

## 📊 Performance

### Recommended Resources

- **Small Setup** (1-10 devices): 512MB RAM, 1 CPU
- **Medium Setup** (10-50 devices): 1GB RAM, 2 CPUs
- **Large Setup** (50-200 devices): 2GB RAM, 4 CPUs

### Optimization

```yaml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
```

## 📞 Support

- **Portal**: https://portal.itsupport.com.bd
- **Email**: support@itsupport.com.bd
- **License**: Purchase at portal

## 📝 License

This software requires a valid license key.

### License Tiers

- **Starter**: Up to 10 devices
- **Professional**: Up to 50 devices
- **Enterprise**: Up to 200 devices

## 🔗 Related

- **Script Version**: For XAMPP/LAMP installations (see `../script-ampnm/`)
- **Comparison**: See `../AMPNM_VERSIONS_COMPARISON.md`

## 🆕 What's New

### v1.0 (2024)
- Initial Docker release
- ICMP Ping support with iputils-ping
- Network topology visualization
- Email notifications
- Multi-user support
- License management

---

**Made with ❤️ by IT Support Bangladesh**

## 🔄 Container Update Runtime (`scripts/update.sh`)

The update script is included in the image at `scripts/update.sh` and is executable by default.

### Required environment variables / paths

- `HOST_APP_DIR`
  - Path to the **host-mounted application source** that should be backed up and updated.
  - Example: `/var/www/html` when the host repo is bind-mounted there.
- Backup path
  - Controlled by `BACKUP_BASE` (default: `/var/www/html/docker-ampnm/data/code_backups`).
  - Ensure this directory is bind-mounted if you want durable backups across container recreations.
- Compose location
  - Controlled by `APP_DIR` (script checks compose files in this directory for restart).
  - Set this to the directory that contains `docker-compose.yml`/`compose.yml`.
- Update repo URL override
  - `REPO_URL` overrides the default repository source used for clone/fetch.

### Recommended bind mounts

```yaml
services:
  app:
    volumes:
      - ${HOST_APP_DIR}:/var/www/html
      - ./data/code_backups:/var/www/html/docker-ampnm/data/code_backups
      - /var/run/docker.sock:/var/run/docker.sock
```

Notes:
- Mounting `/var/run/docker.sock` is required if the script should run `docker compose restart` from inside the container.
- If your compose files live elsewhere, set `APP_DIR` accordingly.

