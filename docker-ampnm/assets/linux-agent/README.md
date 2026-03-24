# AMPNM Linux Monitoring Agent

The Linux agent installs a small single-file Python collector under `/opt/ampnm-agent/`, stores runtime configuration in `/etc/ampnm-agent/config.env`, writes failures and delivery logs to `/var/log/ampnm-agent/agent.log`, and runs continuously as a `systemd` service named `ampnm-agent.service`.

It is designed to submit JSON to the existing `agent-metrics` endpoint with the `x-agent-token` header while including stable host identity fields such as `hostname`, `ip_address`, and `os_version`. The payload also preserves compatibility aliases like `host_name`, `host_ip`, and `cpu`.

## Collected metrics

The runtime collects the following Linux metrics without any third-party Python packages:

- CPU utilization derived from `/proc/stat` over a short sample window
- Memory totals and utilization parsed from `/proc/meminfo`
- Disk usage from `df -B1`, including the root filesystem plus a short list of top mounted filesystems
- Load averages from `/proc/loadavg`
- Network throughput from `/proc/net/dev`, sampled twice and reported for the primary interfaces
- Uptime and boot time from `/proc/uptime` and `/proc/stat`
- Top processes from `ps`, capped at roughly 50 rows by default
- Service states from `systemctl list-units --type=service --all --no-pager --no-legend`, optionally enriched with `list-unit-files` output
- Temperature and sensor data from `sensors` when available; sensor fields are omitted when unavailable

## Supported Linux distributions

The agent targets `systemd`-based distributions with the following common packages available:

- Debian 11+ / Ubuntu 20.04+
- RHEL 8+ / Rocky Linux 8+ / AlmaLinux 8+
- Fedora 38+
- openSUSE Leap / Tumbleweed with `systemd`

## Runtime dependencies

- `python3`
- `systemd`
- `iproute2`
- `procps`
- `coreutils`
- `util-linux`
- `lm-sensors` optional, only for temperature collection

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

1. Create a dedicated system user and group named `ampnm-agent` when possible
2. Copy the runtime script to `/opt/ampnm-agent/ampnm-agent.sh`
3. Write `/etc/ampnm-agent/config.env`
4. Create `/var/log/ampnm-agent/agent.log` on first run
5. Install `/etc/systemd/system/ampnm-agent.service`
6. Run `systemctl daemon-reload`
7. Run `systemctl enable --now ampnm-agent.service`

### Configurable settings

The collector interval remains package-configurable through `/etc/ampnm-agent/config.env`. The installer seeds the following defaults:

```dotenv
SERVER_URL=https://YOUR-PROJECT.supabase.co/functions/v1/agent-metrics
AGENT_TOKEN=YOUR-AGENT-TOKEN
INTERVAL=60
REQUEST_TIMEOUT=30
CPU_SAMPLE_SECONDS=1
NETWORK_SAMPLE_SECONDS=1
RETRY_COUNT=3
RETRY_DELAY_SECONDS=5
MAX_SERVICES=100
MAX_PROCESSES=50
MAX_FILESYSTEMS=5
```

### Uninstall

```bash
sudo ./install.sh --uninstall
```

## Package-based installation

Package definitions are included in:

- `packaging/deb/`
- `packaging/rpm/`

After installing a package, create `/etc/ampnm-agent/config.env`, set the values shown above, and restart the service.

## Service management

```bash
sudo systemctl status ampnm-agent.service
sudo journalctl -u ampnm-agent.service -n 100 --no-pager
sudo tail -n 100 /var/log/ampnm-agent/agent.log
sudo systemctl restart ampnm-agent.service
```

## Notes on metrics collection

- CPU and network throughput are sampled over short intervals so utilization and bytes-per-second are derived instead of guessed.
- Root filesystem metrics are surfaced in the top-level fields, and additional mounted filesystems are provided in structured arrays.
- Network throughput is reported in bytes/sec (`network_in`, `network_out`) and Mbps (`network_in_mbps`, `network_out_mbps`).
- Posting retries transient HTTP and network errors before giving up and logging the failure.

## Troubleshooting

### Service fails to start

```bash
sudo journalctl -u ampnm-agent.service -n 50 --no-pager
sudo tail -n 50 /var/log/ampnm-agent/agent.log
```

### Dependency missing

Install the packages listed under **Runtime dependencies** and rerun the installer.

### Connectivity issues

- Verify the `SERVER_URL` points at the `agent-metrics` endpoint.
- Verify the token is enabled.
- Confirm outbound HTTPS access from the host.
