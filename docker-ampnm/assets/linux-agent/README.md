# AMPNM Linux Monitoring Agent

The Linux agent installs a small Bash-based collector under `/opt/ampnm-agent/`, stores runtime configuration in `/etc/ampnm-agent/config.env`, and runs continuously as a `systemd` service named `ampnm-agent.service`.

It is designed to submit a payload compatible with the current Supabase function at `supabase/functions/agent-metrics/index.ts`, including:

- `hostname` / `host_name`
- `ip_address` / `host_ip`
- `cpu_usage` / `cpu`
- `memory_usage`, `memory_total`
- `disk_usage`, `disk_total`
- `network_in`, `network_out`
- `uptime_seconds`, `boot_time`, `os_version`
- `load_1`, `load_5`, `load_15`
- `temperature_c`, `sensor_summary`
- `services`

## Supported Linux distributions

The agent targets `systemd`-based distributions with the following common packages available:

- Debian 11+ / Ubuntu 20.04+
- RHEL 8+ / Rocky Linux 8+ / AlmaLinux 8+
- Fedora 38+
- openSUSE Leap / Tumbleweed with `systemd`

### Runtime dependencies

- `bash`
- `coreutils`
- `curl`
- `python3`
- `systemd`
- `iproute2`
- `procps`
- `util-linux`

## Quick install with the generic shell installer

```bash
curl -O https://your-server.example/docker-ampnm/assets/linux-agent/install.sh
chmod +x install.sh
sudo ./install.sh \
  --server-url "https://YOUR-PROJECT.supabase.co/functions/v1/agent-metrics" \
  --agent-token "YOUR-AGENT-TOKEN" \
  --interval 60
```

### Installer behavior

The installer will:

1. Copy the runtime script to `/opt/ampnm-agent/ampnm-agent.sh`
2. Write `/etc/ampnm-agent/config.env`
3. Install `/etc/systemd/system/ampnm-agent.service`
4. Run `systemctl daemon-reload`
5. Run `systemctl enable --now ampnm-agent.service`

### Uninstall

```bash
sudo ./install.sh --uninstall
```

## Package-based installation

Package definitions are included in:

- `packaging/deb/`
- `packaging/rpm/`

### Debian / Ubuntu (`.deb`)

Build:

```bash
./packaging/deb/build.sh
```

Install:

```bash
sudo dpkg -i dist/ampnm-agent_<version>_all.deb
sudo install -d /etc/ampnm-agent
sudo tee /etc/ampnm-agent/config.env >/dev/null <<'CFG'
SERVER_URL=https://YOUR-PROJECT.supabase.co/functions/v1/agent-metrics
AGENT_TOKEN=YOUR-AGENT-TOKEN
INTERVAL=60
REQUEST_TIMEOUT=30
CFG
sudo chmod 600 /etc/ampnm-agent/config.env
sudo systemctl restart ampnm-agent.service
```

### RHEL / Rocky / Alma / Fedora (`.rpm`)

Build:

```bash
./packaging/rpm/build.sh
```

Install:

```bash
sudo rpm -ivh dist/ampnm-agent-<version>-1.noarch.rpm
sudo install -d /etc/ampnm-agent
sudo tee /etc/ampnm-agent/config.env >/dev/null <<'CFG'
SERVER_URL=https://YOUR-PROJECT.supabase.co/functions/v1/agent-metrics
AGENT_TOKEN=YOUR-AGENT-TOKEN
INTERVAL=60
REQUEST_TIMEOUT=30
CFG
sudo chmod 600 /etc/ampnm-agent/config.env
sudo systemctl restart ampnm-agent.service
```

## Service management

```bash
sudo systemctl status ampnm-agent.service
sudo journalctl -u ampnm-agent.service -n 100 --no-pager
sudo systemctl restart ampnm-agent.service
```

## Notes on metrics collection

- CPU and network throughput are sampled over one second to align with the Windows agent behavior.
- Disk metrics are based on the root filesystem (`/`).
- Temperature values are read from `/sys/class/thermal` when the kernel exports them.
- Service snapshots are collected from `systemctl list-unit-files --type=service` and enriched with active/sub-state values.
- GPU metrics are currently left `null` for Linux to avoid introducing vendor-specific dependencies.

## Troubleshooting

### Service fails to start

```bash
sudo journalctl -u ampnm-agent.service -n 50 --no-pager
```

### Dependency missing

Install the packages listed under **Runtime dependencies** and rerun the installer.

### Connectivity issues

- Verify the `SERVER_URL` points at the Supabase function endpoint.
- Verify the token is enabled.
- Confirm outbound HTTPS access from the host.
