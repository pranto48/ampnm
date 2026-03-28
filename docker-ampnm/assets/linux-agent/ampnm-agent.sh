#!/usr/bin/env bash
set -euo pipefail

CONFIG_FILE="${AMPNM_AGENT_CONFIG:-/etc/ampnm-agent/config.env}"
LOG_TAG="ampnm-agent"

load_config() {
  if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "Config file not found: $CONFIG_FILE" >&2
    exit 1
  fi

  # shellcheck disable=SC1090
  source "$CONFIG_FILE"

  SERVER_URL="${SERVER_URL:-}"
  AGENT_TOKEN="${AGENT_TOKEN:-}"
  INTERVAL="${INTERVAL:-60}"
  REQUEST_TIMEOUT="${REQUEST_TIMEOUT:-30}"

  if [[ -z "$SERVER_URL" || -z "$AGENT_TOKEN" ]]; then
    echo "SERVER_URL and AGENT_TOKEN must be set in $CONFIG_FILE" >&2
    exit 1
  fi

  command -v curl >/dev/null 2>&1 || { echo "curl is required" >&2; exit 1; }
  command -v python3 >/dev/null 2>&1 || { echo "python3 is required" >&2; exit 1; }
}

get_hostname() {
  hostnamectl --static 2>/dev/null || hostname -s 2>/dev/null || hostname
}

get_primary_ip() {
  ip route get 1.1.1.1 2>/dev/null | awk '/src/ {for (i = 1; i <= NF; i++) if ($i == "src") {print $(i+1); exit}}'
}

read_cpu_jiffies() {
  awk '/^cpu / {print $2,$3,$4,$5,$6,$7,$8,$9}' /proc/stat
}

get_cpu_usage() {
  local cpu1 total1 idle1 cpu2 total2 idle2
  read -r user nice system idle iowait irq softirq steal < <(read_cpu_jiffies)
  total1=$((user + nice + system + idle + iowait + irq + softirq + steal))
  idle1=$((idle + iowait))
  sleep 1
  read -r user nice system idle iowait irq softirq steal < <(read_cpu_jiffies)
  total2=$((user + nice + system + idle + iowait + irq + softirq + steal))
  idle2=$((idle + iowait))

  python3 - <<PY
from decimal import Decimal, ROUND_HALF_UP
usage = 0.0
try:
    usage = (1 - (($idle2 - $idle1) / max(($total2 - $total1), 1))) * 100
except ZeroDivisionError:
    usage = 0.0
print(Decimal(str(usage)).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP))
PY
}

get_memory_stats() {
  awk '
    /^MemTotal:/ {total=$2}
    /^MemAvailable:/ {available=$2}
    END {
      used=total-available
      printf "%.2f %.2f %.2f\n", (used/total)*100, total/1048576, available/1048576
    }
  ' /proc/meminfo
}

get_disk_stats() {
  df -BG --output=size,avail,pcent / | awk 'NR==2 {
    gsub(/G/, "", $1); gsub(/G/, "", $2); gsub(/%/, "", $3);
    printf "%.2f %.2f %.2f\n", $3 + 0, $1 + 0, $2 + 0
  }'
}

read_net_bytes() {
  local iface
  iface=$(ip route show default 2>/dev/null | awk '/default/ {print $5; exit}')
  [[ -n "$iface" ]] || return 1
  awk -v iface="$iface" -F'[: ]+' '$1 == iface {print $3, $11}' /proc/net/dev
}

get_network_stats() {
  local rx1=0 tx1=0 rx2=0 tx2=0
  read -r rx1 tx1 < <(read_net_bytes || echo "0 0")
  sleep 1
  read -r rx2 tx2 < <(read_net_bytes || echo "0 0")
  python3 - <<PY
from decimal import Decimal, ROUND_HALF_UP
rx = ((${rx2:-0} - ${rx1:-0}) * 8) / 1_000_000
_tx = ((${tx2:-0} - ${tx1:-0}) * 8) / 1_000_000
fmt = lambda v: Decimal(str(max(v, 0))).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)
print(f"{fmt(rx)} {fmt(_tx)}")
PY
}

get_uptime_seconds() {
  awk '{print int($1)}' /proc/uptime
}

get_boot_time() {
  python3 - <<'PY'
import datetime
with open('/proc/stat', 'r', encoding='utf-8') as fh:
    for line in fh:
        if line.startswith('btime '):
            boot = int(line.split()[1])
            print(datetime.datetime.utcfromtimestamp(boot).replace(tzinfo=datetime.timezone.utc).isoformat().replace('+00:00', 'Z'))
            break
PY
}

get_loads() {
  awk '{printf "%.2f %.2f %.2f\n", $1, $2, $3}' /proc/loadavg
}

get_os_version() {
  if [[ -r /etc/os-release ]]; then
    . /etc/os-release
    printf '%s\n' "${PRETTY_NAME:-${NAME:-Linux}}"
  else
    uname -sr
  fi
}

get_temperature_and_summary() {
  python3 - <<'PY'
import json
import pathlib
from decimal import Decimal, ROUND_HALF_UP
base = pathlib.Path('/sys/class/thermal')
readings = []
for zone in sorted(base.glob('thermal_zone*')):
    try:
        raw = (zone / 'temp').read_text(encoding='utf-8').strip()
        temp = float(raw)
        if temp > 1000:
            temp /= 1000.0
        ttype = (zone / 'type').read_text(encoding='utf-8').strip() if (zone / 'type').exists() else zone.name
        readings.append({'name': ttype, 'temperature_c': round(temp, 2)})
    except Exception:
        pass
avg = None
if readings:
    avg = float(sum(item['temperature_c'] for item in readings) / len(readings))
    avg = float(Decimal(str(avg)).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP))
print(json.dumps({'temperature_c': avg, 'sensor_summary': readings[:10]}))
PY
}

get_services_json() {
  set +o pipefail
  local output
  output=$(systemctl list-unit-files --type=service --no-pager --no-legend 2>/dev/null | \
  python3 - <<'PY'
import json, sys, datetime
services = []
for idx, line in enumerate(sys.stdin):
    if idx >= 50:
        break
    parts = line.split()
    if len(parts) < 2:
        continue
    name, enabled_state = parts[0], parts[1]
    display = name[:-8] if name.endswith('.service') else name
    services.append({
        'name': name,
        'display_name': display,
        'state': None,
        'sub_state': None,
        'enabled': enabled_state in {'enabled', 'enabled-runtime', 'static', 'alias', 'indirect'},
        'recorded_at': datetime.datetime.utcnow().replace(tzinfo=datetime.timezone.utc).isoformat().replace('+00:00', 'Z'),
    })
print(json.dumps(services))
PY
  )
  set -o pipefail
  printf '%s\n' "${output:-[]}"
}

enrich_services_state() {
  local services_json="$1"
  python3 - <<PY
import json, subprocess
services = json.loads('''$services_json''')
for svc in services:
    try:
        active = subprocess.check_output(['systemctl', 'is-active', svc['name']], stderr=subprocess.DEVNULL, text=True).strip()
    except Exception:
        active = 'unknown'
    try:
        sub_state = subprocess.check_output(['systemctl', 'show', svc['name'], '--property=SubState', '--value'], stderr=subprocess.DEVNULL, text=True).strip()
    except Exception:
        sub_state = None
    svc['state'] = active or None
    svc['sub_state'] = sub_state or None
print(json.dumps(services))
PY
}

build_payload() {
  load_config
  local hostname ip cpu mem_usage mem_total mem_free disk_usage disk_total disk_free net_in net_out uptime boot_time os_version load1 load5 load15 temp_json services_json

  hostname=$(get_hostname)
  ip=$(get_primary_ip || true)
  cpu=$(get_cpu_usage)
  read -r mem_usage mem_total mem_free < <(get_memory_stats)
  read -r disk_usage disk_total disk_free < <(get_disk_stats)
  read -r net_in net_out < <(get_network_stats || echo "0.00 0.00")
  uptime=$(get_uptime_seconds)
  boot_time=$(get_boot_time)
  os_version=$(get_os_version)
  read -r load1 load5 load15 < <(get_loads)
  temp_json=$(get_temperature_and_summary)
  services_json=$(get_services_json)
  services_json=$(enrich_services_state "$services_json")

  HOSTNAME="$hostname" \
  IP_ADDRESS="$ip" \
  CPU_USAGE="$cpu" \
  MEMORY_USAGE="$mem_usage" \
  MEMORY_TOTAL="$mem_total" \
  DISK_USAGE="$disk_usage" \
  DISK_TOTAL="$disk_total" \
  NETWORK_IN="$net_in" \
  NETWORK_OUT="$net_out" \
  UPTIME_SECONDS="$uptime" \
  BOOT_TIME="$boot_time" \
  OS_VERSION="$os_version" \
  LOAD_1="$load1" \
  LOAD_5="$load5" \
  LOAD_15="$load15" \
  TEMP_JSON="$temp_json" \
  SERVICES_JSON="$services_json" \
  python3 - <<'PY'
import json, os

def num(name):
    value = os.environ.get(name, '')
    return float(value) if value else None

temp_meta = json.loads(os.environ['TEMP_JSON'])
services = json.loads(os.environ['SERVICES_JSON'])
payload = {
    'hostname': os.environ['HOSTNAME'],
    'host_name': os.environ['HOSTNAME'],
    'ip_address': os.environ.get('IP_ADDRESS') or None,
    'host_ip': os.environ.get('IP_ADDRESS') or None,
    'cpu_usage': num('CPU_USAGE'),
    'cpu': num('CPU_USAGE'),
    'memory_usage': num('MEMORY_USAGE'),
    'memory_total': num('MEMORY_TOTAL'),
    'memory_free_gb': None,
    'disk_usage': num('DISK_USAGE'),
    'disk_total': num('DISK_TOTAL'),
    'disk_free_gb': None,
    'network_in': num('NETWORK_IN'),
    'network_out': num('NETWORK_OUT'),
    'network_in_mbps': num('NETWORK_IN'),
    'network_out_mbps': num('NETWORK_OUT'),
    'gpu_usage': None,
    'gpu_percent': None,
    'uptime_seconds': int(float(os.environ['UPTIME_SECONDS'])),
    'boot_time': os.environ['BOOT_TIME'],
    'os_version': os.environ['OS_VERSION'],
    'load_1': num('LOAD_1'),
    'load_5': num('LOAD_5'),
    'load_15': num('LOAD_15'),
    'temperature_c': temp_meta.get('temperature_c'),
    'sensor_summary': temp_meta.get('sensor_summary'),
    'services': services,
}
print(json.dumps(payload, separators=(',', ':')))
PY
}

send_metrics() {
  local payload response_code body_file
  payload=$(build_payload)
  body_file=$(mktemp)

  response_code=$(curl -sS -o "$body_file" -w '%{http_code}' \
    -X POST "$SERVER_URL" \
    -H 'Content-Type: application/json' \
    -H "X-Agent-Token: $AGENT_TOKEN" \
    --max-time "$REQUEST_TIMEOUT" \
    --data "$payload" || true)

  if [[ "$response_code" =~ ^2 ]]; then
    logger -t "$LOG_TAG" "metrics sent successfully: $(cat "$body_file")"
  else
    logger -t "$LOG_TAG" "metrics submission failed (HTTP $response_code): $(cat "$body_file")"
  fi

  rm -f "$body_file"
}

main() {
  load_config
  logger -t "$LOG_TAG" "starting Linux monitoring loop; interval=${INTERVAL}s server=${SERVER_URL}"
  while true; do
    if ! send_metrics; then
      logger -t "$LOG_TAG" "collection cycle failed"
    fi
    sleep "$INTERVAL"
  done
}

main "$@"
