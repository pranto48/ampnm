# 🛡️ AMPNM — Advanced Monitoring & Network Manager

**Free & Open Source** | Network Monitoring · License Management · Docker-First · Zero-Config

> AMPNM is a self-hosted, Docker-native network monitoring and license management platform built for IT teams. Monitor devices, enforce software licenses, stream agent telemetry, and manage everything from a clean web console — all for free.

🌐 **Product Website**: [ampnm.itsupport.com.bd](https://ampnm.itsupport.com.bd)  
🔑 **Free License Registration**: [portal.itsupport.com.bd](https://portal.itsupport.com.bd)  
📚 **Documentation**: [ampnm.itsupport.com.bd/docs](https://ampnm.itsupport.com.bd/docs)  
💬 **Support & Contacts**: [itsupport.com.bd](https://itsupport.com.bd)

---

## 📦 Image Tags & Versions

All AMPNM components are versioned together under a unified release cycle.

### 🔵 AMPNM Core App — `arifmahmudpranto/ampnm`

| Tag | Description | Status |
|-----|-------------|--------|
| `latest` | Always points to the most recent stable release | ✅ Stable |
| `v1.6` | Self-contained local database loopback configuration, zero-config | ✅ Stable |
| `v1.5` | Lightweight embedded local MariaDB database server | ✅ Stable |
| `v1.4` | Database schema try-catch bootstrap migration recovery | ✅ Stable |
| `v1.3` | Slimmer Docker image: portal decoupled to its own repo | ✅ Stable |
| `v1.2` | Multi-tenancy user group isolation & connection thickness | ✅ Stable |
| `v1.1` | Tamper-proof licensing, self-healing auth, open-source free tier | ✅ Stable |

---

## 🚀 Quick Start (Single-Container / Zero-Config)

You can run AMPNM instantly without installing or configuring any external database! It comes with an embedded, zero-config local database out-of-the-box.

### 1. Pull the image
```bash
docker pull arifmahmudpranto/ampnm:latest
```

### 2. Start the container
```bash
docker run -d \
  --name ampnm_server \
  -p 2266:2266 \
  --restart unless-stopped \
  arifmahmudpranto/ampnm:latest
```

### 3. Initialize & Access
Open `http://localhost:2266` in your browser. The system will automatically detect the database and guide you through the 1-click schema installer.

---

## 🐳 Deployment with Docker Compose (Multi-Container / Persistent)

If you prefer to separate the web server and the database engine with a dedicated MySQL instance, use this Compose configuration:

### `docker-compose.yml`

```yaml
version: '3.8'

services:
  ampnm-app:
    image: arifmahmudpranto/ampnm:latest
    container_name: ampnm-app
    restart: unless-stopped
    ports:
      - "2266:2266"
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

### Start Compose stack:
```bash
docker compose up -d
```

---

## 🔑 Get Your Free License Key

AMPNM Core is **completely free**. A license key is required but costs nothing.

1. **Register** at [portal.itsupport.com.bd/register](https://portal.itsupport.com.bd/register)
2. Navigate to **Licenses → AMPNM Core → Generate Free Key**
3. Copy your `AMP256-XXXX-XXXX-XXXX-XXXX` key
4. Open your server url (e.g. `http://localhost:2266/license_setup.php`)
5. Paste the key and click **Verify License** ✅

---

## ⚙️ Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `127.0.0.1` | Database hostname (uses internal MariaDB if localhost/db is not resolved) |
| `DB_NAME` | `network_monitor` | Database name |
| `DB_USER` | `root` | Database username |
| `DB_PASSWORD` | *(empty)* | Database password |
| `MYSQL_ROOT_PASSWORD` | *(empty)* | MySQL root password |
| `ADMIN_PASSWORD` | `password` | AMPNM admin panel password |
| `APP_LICENSE_KEY` | *(empty)* | Pre-set license key (skips setup wizard) |
| `APACHE_PORT` | `2266` | Web console HTTP port |
| `LICENSE_API_URL` | `https://portal.itsupport.com.bd/api/license/verify` | Override license verification endpoint |
| `LICENSE_FINGERPRINT_MODE` | `allow-rebaseline` | `strict` or `allow-rebaseline` |
| `SMS_ALERTS_ENABLED` | `1` | Enable Alpha SMS alert notifications |
| `SMS_COOLDOWN_MINUTES` | `30` | Minimum minutes between SMS alerts |
| `CLOUD_SYNC_URL` | *(empty)* | Optional cloud monitoring bridge URL |

---

## 🔒 Security Architecture

| Feature | Implementation |
|---------|---------------|
| Core logic protection | AES-256-CBC encrypted auth check |
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
| 📦 Docker Hub | [hub.docker.com/r/arifmahmudpranto/ampnm](https://hub.docker.com/r/arifmahmudpranto/ampnm) |
| 📄 GitHub | [github.com/arifmahmudpranto/ampnm](https://github.com/arifmahmudpranto/ampnm) |
| 📚 Docs | [ampnm.itsupport.com.bd/docs](https://ampnm.itsupport.com.bd/docs) |
| 💬 Contact | [itsupport.com.bd](https://itsupport.com.bd) |

---

## 📝 License

**MIT License** — Free to use, modify, and distribute.

Commercial support, enterprise licensing, and additional IT monitoring products are available at [portal.itsupport.com.bd](https://portal.itsupport.com.bd).

---

*Made with ❤️ by [IT Support BD](https://itsupport.com.bd)*
