# 🛡️ AMPNM — Advanced Monitoring & Network Manager

**Free & Open Source** | Network Monitoring · License Management · Docker-First

> AMPNM is a self-hosted, Docker-native network monitoring and license management platform built for IT teams. Monitor devices, enforce software licenses, stream agent telemetry, and manage everything from a clean web console — all for free.

[![Docker Pulls](https://img.shields.io/docker/pulls/pranto48/ampnm?style=flat-square&color=0ea5e9&label=pulls)](https://hub.docker.com/r/pranto48/ampnm)
[![Docker Image Size](https://img.shields.io/docker/image-size/pranto48/ampnm/latest?style=flat-square&color=6366f1)](https://hub.docker.com/r/pranto48/ampnm/tags)
[![License: MIT](https://img.shields.io/badge/license-MIT-22c55e?style=flat-square)](https://github.com/pranto48/ampnm)
[![PHP](https://img.shields.io/badge/PHP-8.2-7c3aed?style=flat-square)](https://hub.docker.com/r/pranto48/ampnm)

---

## 📦 Image Tags & Versions

All AMPNM components are versioned together under a unified release cycle.

### 🔵 AMPNM Core App — `pranto48/ampnm`

| Tag | Description | Status |
|-----|-------------|--------|
| `latest` | Always points to the most recent stable release | ✅ Stable |
| `v1.1.0` | Tamper-proof licensing, self-healing auth, open-source free tier | ✅ Stable |
| `v1.0.0` | Initial Docker release with license verification | ✅ Legacy |
| `nightly` | Latest development build (may be unstable) | ⚠️ Dev |

### 🌐 AMPNM Web (Landing Site) — `pranto48/ampnm-web`

| Tag | Description | Status |
|-----|-------------|--------|
| `latest` | Most recent stable web release | ✅ Stable |
| `web-v1.1.0` | Docker Hub install guide, Solutions page with screenshots & fix walkthrough | ✅ Stable |
| `web-v1.0.0` | Initial landing page release | ✅ Legacy |

### 🔑 AMPNM Portal (License Management) — `pranto48/ampnm-portal`

| Tag | Description | Status |
|-----|-------------|--------|
| `latest` | Most recent stable portal release | ✅ Stable |
| `portal-v1.1.0` | Free + commercial license tiers, bKash/Rocket/Nagad payment support | ✅ Stable |
| `portal-v1.0.0` | Initial portal with paid license management only | ✅ Legacy |

### 📡 AMPNM Agent (Telemetry Daemon) — `pranto48/ampnm-agent`

| Tag | Description | Status |
|-----|-------------|--------|
| `latest` | Most recent stable agent release | ✅ Stable |
| `agent-v1.1.0` | Go-based telemetry daemon, Docker + native host support | 🧪 Beta |
| `agent-v1.0.0` | Initial agent release | ✅ Legacy |

---

## 🚀 Quick Start

### Pull the image

```bash
docker pull pranto48/ampnm
```

### Full stack with docker compose (Recommended)

```bash
# 1. Create docker-compose.yml (see below)
# 2. Start all services:
docker compose up -d

# 3. Open the web console:
http://localhost:2266
```

---

## 🐳 docker-compose.yml

```yaml
services:
  ampnm-app:
    image: pranto48/ampnm:latest
    container_name: ampnm-app
    restart: unless-stopped
    ports:
      - "2266:2266"
      - "10051:10051"
    environment:
      - DB_HOST=db
      - DB_NAME=network_monitor
      - DB_USER=user
      - DB_PASSWORD=password
      - MYSQL_ROOT_PASSWORD=rootpassword
      - ADMIN_PASSWORD=admin123
      - APP_LICENSE_KEY=your_free_license_key_here
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.0
    container_name: ampnm-db
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: network_monitor
      MYSQL_USER: user
      MYSQL_PASSWORD: password
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u root -prootpassword"]
      interval: 10s
      timeout: 5s
      retries: 60
      start_period: 300s

volumes:
  db_data:
```

---

## 🔑 Get Your Free License Key

AMPNM Core is **completely free**. A license key is required but costs nothing.

1. **Register** at [portal.itsupport.com.bd/register](https://portal.itsupport.com.bd/register)
2. Navigate to **Licenses → AMPNM Core → Generate Free Key**
3. Copy your `AMP256-XXXX-XXXX-XXXX-XXXX` key
4. Open `http://localhost:2266/license_setup.php`
5. Paste the key and click **Verify License** ✅

---

## 🏗️ AMPNM Platform Architecture

AMPNM is a multi-component platform. All components work together:

```
┌─────────────────────────────────────────────────────────────┐
│                   AMPNM Platform  v1.1.0                    │
├──────────────────┬──────────────────────────────────────────┤
│ ampnm            │ Core PHP app — device monitor,           │
│ (this image)     │ license enforcer, agent dashboard        │
│ pranto48/ampnm   │ Port: 2266  |  DB: MySQL 8.0             │
│ v1.1.0           │ Base: PHP 8.2 + Apache                   │
├──────────────────┼──────────────────────────────────────────┤
│ ampnm-web        │ Next.js 16 marketing & docs site         │
│ pranto48/ampnm   │ Download page, pricing, solutions,       │
│ -web  v1.1.0     │ Docker Hub install guide, screenshots    │
├──────────────────┼──────────────────────────────────────────┤
│ ampnm-portal     │ Next.js 16 license management portal     │
│ pranto48/ampnm   │ Issue free + commercial license keys,    │
│ -portal v1.1.0   │ manage organizations, bKash/Rocket pay   │
├──────────────────┼──────────────────────────────────────────┤
│ ampnm-agent      │ Go-based telemetry daemon                │
│ pranto48/ampnm   │ Streams CPU, RAM, disk, net metrics      │
│ -agent  v1.1.0   │ Port: 10050 (passive) | 10051 (trapper) │
└──────────────────┴──────────────────────────────────────────┘
```

---

## ⚙️ Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `db` | MySQL hostname (docker service name) |
| `DB_NAME` | `network_monitor` | Database name |
| `DB_USER` | `user` | Database username |
| `DB_PASSWORD` | `password` | Database password |
| `MYSQL_ROOT_PASSWORD` | `rootpassword` | MySQL root password |
| `ADMIN_PASSWORD` | `password` | AMPNM admin panel password |
| `APP_LICENSE_KEY` | *(empty)* | Pre-set license key (skips setup wizard) |
| `APACHE_PORT` | `2266` | Web console HTTP port |
| `LICENSE_API_URL` | *(portal URL)* | Override license verification endpoint |
| `LICENSE_FINGERPRINT_MODE` | `allow-rebaseline` | `strict` or `allow-rebaseline` |
| `SMS_ALERTS_ENABLED` | `1` | Enable Alpha SMS alert notifications |
| `SMS_COOLDOWN_MINUTES` | `30` | Minimum minutes between SMS alerts |

---

## 🔄 Upgrading

```bash
# Pull the latest image
docker pull pranto48/ampnm:latest

# Recreate only the app container (DB volume is preserved)
docker compose up -d --no-deps --force-recreate ampnm-app
```

> ✅ Your database is stored in the `db_data` named volume and is **never lost** on upgrade.

---

## 📋 Changelog

### v1.1.0 — 2026-06-27 *(Latest)*
- 🔧 **Self-healing auth:** `auth_check.php` now auto-recovers `core_key` on container restart — eliminates `ERR_TOO_MANY_REDIRECTS`
- 🔒 **Tamper-proof core:** AES-256-CBC encrypted PHP logic, machine-locked decryption key
- 🆓 **Open source & free:** AMPNM Core is free for all registered portal users
- 🛒 **Portal: commercial support:** bKash, Rocket, Nagad payment gateway integration
- 🌐 **Web: install guide:** Docker Hub banner, 4-step setup, solutions screenshots

### v1.0.0 — 2026-06-20 *(Initial Release)*
- 🚀 First Docker Hub public release
- 🔑 License verification via portal.itsupport.com.bd
- 📡 SNMP + ICMP device monitoring
- 📊 Network bandwidth & device health dashboard
- 👤 Multi-user admin panel

---

## 🔒 Security Architecture

| Feature | Implementation |
|---------|---------------|
| Core logic protection | AES-256-CBC encrypted `auth_check.enc` |
| Key storage | Machine-locked, host-fingerprint-derived key |
| License payloads | HMAC-SHA256 signed JSON, AES encrypted response |
| DB sensitive values | `encryptSensitiveValue()` — all keys encrypted at rest |
| Container restart | Self-healing: auto re-verifies license on boot |

---

## 📞 Support & Links

| Resource | Link |
|----------|------|
| 🌍 Website | [ampnm.itsupport.com.bd](https://ampnm.itsupport.com.bd) |
| 🔑 License Portal | [portal.itsupport.com.bd](https://portal.itsupport.com.bd) |
| 📦 Docker Hub | [hub.docker.com/r/pranto48/ampnm](https://hub.docker.com/r/pranto48/ampnm) |
| 📄 GitHub | [github.com/pranto48/ampnm](https://github.com/pranto48/ampnm) |
| 📚 Docs | [ampnm.itsupport.com.bd/docs](https://ampnm.itsupport.com.bd/docs) |
| 💬 Contact | [itsupport.com.bd](https://itsupport.com.bd) |

---

## 📝 License

**MIT License** — Free to use, modify, and distribute.

Commercial support, enterprise licensing, and additional IT monitoring products are available at [portal.itsupport.com.bd](https://portal.itsupport.com.bd).

---

*Made with ❤️ by [IT Support BD](https://itsupport.com.bd)*
