# AMPNM Windows Monitoring Agent

A lightweight Windows agent that sends system metrics (CPU, Memory, Disk, Network, GPU) to your AMPNM Docker server.

## Quick Start

### 1. Generate an Agent Token

1. Log into your AMPNM web interface
2. Go to **Host Metrics** page
3. Click **Manage Agent Tokens**
4. Click **Create New Token**
5. Copy the generated token

### 2. Install the Agent

Open PowerShell **as Administrator** and run:

```powershell
# Download the installer (or copy from AMPNM assets folder)
# Then run:
.\AMPNM-Agent-Installer.ps1 -ServerUrl "http://YOUR-SERVER:2266/docker-ampnm/api/agent/windows-metrics" -AgentToken "YOUR-TOKEN-HERE"
```

Replace:
- `YOUR-SERVER` with your AMPNM Docker server IP/hostname
- `YOUR-TOKEN-HERE` with the token from step 1

### 3. Verify Installation

```powershell
# Check service status
Get-Service AMPNM-Agent

# View recent logs
Get-Content "$env:ProgramData\AMPNM-Agent\logs\agent.log" -Tail 20
```

## Configuration Options

| Parameter | Description | Default |
|-----------|-------------|---------|
| `-ServerUrl` | Full URL to AMPNM metrics endpoint | Required |
| `-AgentToken` | Authentication token from AMPNM | Required |
| `-Interval` | Seconds between metric collections | 60 |
| `-Uninstall` | Remove the agent completely | - |

## Collected Metrics

| Metric | Description |
|--------|-------------|
| CPU % | Processor utilization |
| Memory % | RAM usage percentage |
| Memory Total/Free | RAM in GB |
| Disk Total/Free | Primary drive space in GB |
| Network In/Out | Bandwidth in Mbps |
| GPU % | Graphics card utilization (if available) |

## Troubleshooting

### Service won't start
```powershell
# Check logs
Get-Content "$env:ProgramData\AMPNM-Agent\logs\agent-error.log"
```

### Network errors
- Verify the server URL is accessible
- Check Windows Firewall allows outbound HTTP
- Verify the agent token is valid and enabled

### Reinstall
```powershell
# Uninstall first
.\AMPNM-Agent-Installer.ps1 -Uninstall

# Then reinstall
.\AMPNM-Agent-Installer.ps1 -ServerUrl "..." -AgentToken "..."
```

## Uninstall

```powershell
.\AMPNM-Agent-Installer.ps1 -Uninstall
```

## Files Location

- **Install Path**: `C:\ProgramData\AMPNM-Agent\`
- **Config**: `C:\ProgramData\AMPNM-Agent\config.json`
- **Logs**: `C:\ProgramData\AMPNM-Agent\logs\`
- **Service Name**: `AMPNM-Agent`

## Linking to Network Map Devices

The agent reports its IP address with each metric submission. If you have a device in your AMPNM network map with the same IP, the metrics will automatically be linked to that device and shown in the device details panel.

## Security

- Agent tokens are 64-character random hex strings
- Tokens can be disabled/revoked from the web interface
- Old metrics are automatically cleaned up (7 days retention)
- All communication is over HTTP (use a reverse proxy for HTTPS)
