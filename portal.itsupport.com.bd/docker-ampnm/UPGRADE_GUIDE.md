# AMPNM Docker - Upgrade Guide

## Upgrading to Support ICMP Ping

If you're experiencing "sh: 1: ping: not found" errors, follow this guide to upgrade your Docker container with ping support.

## Quick Upgrade

```bash
cd /path/to/docker-ampnm
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

That's it! Your container now has ping support.

## Detailed Steps

### 1. Stop Running Containers

```bash
docker-compose down
```

This stops and removes the containers but preserves your data in volumes.

### 2. Rebuild the Image

```bash
docker-compose build --no-cache
```

The `--no-cache` flag ensures a clean build with all new packages.

**What's being added:**
- `iputils-ping` - ICMP ping command
- `net-tools` - Network utilities (ifconfig, netstat)
- `dnsutils` - DNS tools (dig, nslookup)
- `iproute2` - Advanced network management

### 3. Start Containers

```bash
docker-compose up -d
```

The `-d` flag runs containers in detached mode (background).

### 4. Verify Installation

```bash
# Check ping is available
docker-compose exec app ping -c 1 8.8.8.8

# Should see output like:
# PING 8.8.8.8 (8.8.8.8) 56(84) bytes of data.
# 64 bytes from 8.8.8.8: icmp_seq=1 ttl=118 time=10.2 ms
```

## Troubleshooting

### Rebuild Still Shows Old Image

**Problem**: After rebuild, ping still not available

**Solution**: Force remove old images

```bash
docker-compose down
docker rmi $(docker images -q ampnm-app)
docker-compose build --no-cache
docker-compose up -d
```

### Port Already in Use

**Problem**: Port 2266 already in use

**Solution**: Find and stop the conflicting service

```bash
# Find what's using port 2266
sudo lsof -i :2266

# Or kill all Docker processes
docker stop $(docker ps -aq)
docker-compose up -d
```

### Database Connection Lost After Upgrade

**Problem**: Can't connect to MySQL after upgrade

**Solution**: Database volume preserved, just restart

```bash
docker-compose restart mysql
docker-compose restart app
```

### Permission Errors

**Problem**: File permission errors in container

**Solution**: Fix permissions

```bash
docker-compose exec app chown -R www-data:www-data /var/www/html
```

## Data Safety

Your data is safe during upgrade because it's stored in Docker volumes:

- `mysql-data` - Database remains intact
- `app-uploads` - Icons, backgrounds, backups preserved

## Backup Before Upgrade (Optional)

If you want extra safety:

```bash
# Backup database
docker-compose exec mysql mysqldump -u root -p network_monitor > backup-$(date +%Y%m%d).sql

# Backup uploads
docker cp ampnm-app:/var/www/html/uploads ./uploads-backup-$(date +%Y%m%d)
```

## Post-Upgrade Verification

### 1. Check Container Status

```bash
docker-compose ps

# Should show both containers running:
# ampnm-mysql    running
# ampnm-app      running
```

### 2. Access Web Interface

Open http://localhost:2266 (or your configured port)

Login with your credentials (default: admin/password)

### 3. Test Device Monitoring

1. Add a test device:
   - Name: Test-Router
   - IP: 8.8.8.8
   - Type: Router
   - Check Interval: 60 seconds

2. Wait 60 seconds

3. Check dashboard - device should show as "Online"

4. Check map - device should be green

### 4. View Logs

```bash
# Application logs
docker-compose logs app

# MySQL logs
docker-compose logs mysql

# Follow logs in real-time
docker-compose logs -f app
```

## Rollback (If Needed)

If upgrade causes issues, rollback:

```bash
# Stop containers
docker-compose down

# Use old image (if you didn't remove it)
docker tag ampnm-app:latest ampnm-app:backup
docker-compose up -d

# Or restore from backup
docker-compose exec -i mysql mysql -u root -p network_monitor < backup.sql
```

## What Changed

### Dockerfile Updates

**Added packages:**
```dockerfile
iputils-ping    # ICMP ping command
net-tools       # Network configuration tools
dnsutils        # DNS lookup utilities
iproute2        # Advanced networking tools
```

**No changes to:**
- PHP version (8.2)
- Apache configuration
- PHP extensions
- Application code
- Database schema
- Environment variables

### Application Features Enabled

After upgrade, these features now work:

✅ ICMP ping monitoring
✅ Device reachability checks
✅ Latency measurement
✅ Packet loss detection
✅ Live status updates
✅ Threshold alerts
✅ Historical ping data
✅ Network troubleshooting tools

## Performance Impact

The new packages add minimal overhead:

- Image size increase: ~5MB
- Memory usage: No change
- CPU usage: No change
- Startup time: ~2 seconds longer (one-time)

## Need Help?

If you encounter issues:

1. **Check logs**: `docker-compose logs app`
2. **Verify ping**: `docker-compose exec app ping -c 1 8.8.8.8`
3. **Restart services**: `docker-compose restart`
4. **Contact support**: support@itsupport.com.bd

## Additional Resources

- [README.md](README.md) - Full documentation
- [docker-compose.yml](docker-compose.yml) - Configuration file
- [Portal](https://portal.itsupport.com.bd) - License and support

---

**Upgrade completed successfully?** Start monitoring your network! 🚀
