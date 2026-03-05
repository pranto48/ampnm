# AMPNM Docker Topology Agent

Lightweight scripts that collect Docker container, network, and volume data from your host and push it to AMPNM's centralized topology dashboard.

## Prerequisites

- **Docker** installed and running
- **curl** and **jq** (Linux/macOS) or **PowerShell 5.1+** (Windows)

## Setup

### 1. Set Environment Variables

| Variable | Description |
|---|---|
| `AMPNM_SYNC_URL` | Full URL to the docker-sync edge function, e.g. `https://<project-id>.supabase.co/functions/v1/docker-sync` |
| `AMPNM_ANON_KEY` | Your project's anon/publishable key |

### 2. Run the Agent

#### Linux / macOS (Bash)

```bash
export AMPNM_SYNC_URL="https://<project-id>.supabase.co/functions/v1/docker-sync"
export AMPNM_ANON_KEY="<your-anon-key>"

# Run once
chmod +x ampnm-docker-agent.sh
./ampnm-docker-agent.sh

# Run in a loop (every 60 seconds)
POLL_INTERVAL=60 ./ampnm-docker-agent.sh
```

#### Windows (PowerShell)

```powershell
$env:AMPNM_SYNC_URL = "https://<project-id>.supabase.co/functions/v1/docker-sync"
$env:AMPNM_ANON_KEY = "<your-anon-key>"

# Run once
.\AMPNM-Docker-Agent.ps1

# Run in a loop (every 60 seconds)
.\AMPNM-Docker-Agent.ps1 -PollInterval 60
```

## What Gets Collected

| Data | Details |
|---|---|
| **Host** | Hostname, IP, Docker version |
| **Containers** | ID, name, image, state, ports, networks, internal IP |
| **Networks** | Name, driver, subnet, gateway, connected containers |
| **Volumes** | Count of orphaned (dangling) volumes |

## Running as a Service

### Linux (systemd)

```ini
[Unit]
Description=AMPNM Docker Topology Agent
After=docker.service

[Service]
Environment=AMPNM_SYNC_URL=https://<id>.supabase.co/functions/v1/docker-sync
Environment=AMPNM_ANON_KEY=<key>
Environment=POLL_INTERVAL=60
ExecStart=/opt/ampnm/ampnm-docker-agent.sh
Restart=always

[Install]
WantedBy=multi-user.target
```

### Windows (Task Scheduler)

Create a scheduled task that runs `AMPNM-Docker-Agent.ps1 -PollInterval 60` with the environment variables set.
