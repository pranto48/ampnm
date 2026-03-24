#!/usr/bin/env python3
import json
import math
import os
import pwd
import shutil
import socket
import subprocess
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Optional, Tuple
from urllib import error, request

CONFIG_FILE = Path(os.environ.get("AMPNM_AGENT_CONFIG", "/etc/ampnm-agent/config.env"))
LOG_DIR = Path("/var/log/ampnm-agent")
LOG_FILE = LOG_DIR / "agent.log"
DEFAULT_INTERVAL = 60
DEFAULT_TIMEOUT = 30
DEFAULT_CPU_SAMPLE = 1.0
DEFAULT_NET_SAMPLE = 1.0
DEFAULT_RETRY_COUNT = 3
DEFAULT_RETRY_DELAY = 5.0
DEFAULT_MAX_SERVICES = 100
DEFAULT_MAX_PROCESSES = 50
DEFAULT_MAX_FILESYSTEMS = 5
PRIMARY_INTERFACE_LIMIT = 3


def log(message: str) -> None:
    timestamp = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
    line = f"{timestamp} {message}\n"
    try:
        LOG_DIR.mkdir(parents=True, exist_ok=True)
        with LOG_FILE.open("a", encoding="utf-8") as handle:
            handle.write(line)
    except Exception:
        sys.stderr.write(line)


def load_config() -> Dict[str, str]:
    if not CONFIG_FILE.exists():
        raise RuntimeError(f"Config file not found: {CONFIG_FILE}")

    config: Dict[str, str] = {}
    for raw_line in CONFIG_FILE.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        config[key.strip()] = value.strip().strip('"').strip("'")

    server_url = config.get("SERVER_URL", "")
    agent_token = config.get("AGENT_TOKEN", "")
    if not server_url or not agent_token:
        raise RuntimeError(f"SERVER_URL and AGENT_TOKEN must be set in {CONFIG_FILE}")

    config.setdefault("INTERVAL", str(DEFAULT_INTERVAL))
    config.setdefault("REQUEST_TIMEOUT", str(DEFAULT_TIMEOUT))
    config.setdefault("CPU_SAMPLE_SECONDS", str(DEFAULT_CPU_SAMPLE))
    config.setdefault("NETWORK_SAMPLE_SECONDS", str(DEFAULT_NET_SAMPLE))
    config.setdefault("RETRY_COUNT", str(DEFAULT_RETRY_COUNT))
    config.setdefault("RETRY_DELAY_SECONDS", str(DEFAULT_RETRY_DELAY))
    config.setdefault("MAX_SERVICES", str(DEFAULT_MAX_SERVICES))
    config.setdefault("MAX_PROCESSES", str(DEFAULT_MAX_PROCESSES))
    config.setdefault("MAX_FILESYSTEMS", str(DEFAULT_MAX_FILESYSTEMS))
    return config


def to_int(value: str, default: int) -> int:
    try:
        return int(float(value))
    except (TypeError, ValueError):
        return default


def to_float(value: str, default: float) -> float:
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def run_command(command: List[str]) -> str:
    try:
        result = subprocess.run(command, capture_output=True, text=True, check=True)
        return result.stdout
    except (subprocess.CalledProcessError, FileNotFoundError):
        return ""


def get_hostname() -> str:
    return socket.gethostname()


def parse_os_release() -> str:
    os_release = Path("/etc/os-release")
    if os_release.exists():
        values: Dict[str, str] = {}
        for line in os_release.read_text(encoding="utf-8").splitlines():
            if "=" not in line:
                continue
            key, value = line.split("=", 1)
            values[key] = value.strip().strip('"')
        return values.get("PRETTY_NAME") or values.get("NAME") or os.uname().sysname
    return f"{os.uname().sysname} {os.uname().release}"


def get_default_interfaces() -> List[str]:
    interfaces: List[str] = []
    output = run_command(["ip", "route", "show", "default"])
    for line in output.splitlines():
        parts = line.split()
        if "dev" in parts:
            index = parts.index("dev")
            if index + 1 < len(parts):
                iface = parts[index + 1]
                if iface not in interfaces and iface != "lo":
                    interfaces.append(iface)
    if interfaces:
        return interfaces[:PRIMARY_INTERFACE_LIMIT]

    net_dir = Path("/sys/class/net")
    if net_dir.exists():
        for iface_dir in sorted(net_dir.iterdir()):
            iface = iface_dir.name
            if iface == "lo":
                continue
            state_path = iface_dir / "operstate"
            state = state_path.read_text(encoding="utf-8").strip() if state_path.exists() else "unknown"
            if state in {"up", "unknown"}:
                interfaces.append(iface)
    return interfaces[:PRIMARY_INTERFACE_LIMIT]


def get_primary_ip() -> Optional[str]:
    output = run_command(["ip", "route", "get", "1.1.1.1"])
    for token_index, token in enumerate(output.split()):
        if token == "src" and token_index + 1 < len(output.split()):
            return output.split()[token_index + 1]

    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.connect(("1.1.1.1", 80))
            return sock.getsockname()[0]
    except OSError:
        return None


def read_proc_stat_cpu() -> Tuple[int, int]:
    with open("/proc/stat", "r", encoding="utf-8") as handle:
        line = handle.readline().strip()
    parts = line.split()
    values = [int(part) for part in parts[1:]]
    idle = values[3] + (values[4] if len(values) > 4 else 0)
    total = sum(values)
    return idle, total


def get_cpu_usage(sample_seconds: float) -> float:
    idle1, total1 = read_proc_stat_cpu()
    time.sleep(max(sample_seconds, 0.1))
    idle2, total2 = read_proc_stat_cpu()
    total_delta = max(total2 - total1, 1)
    idle_delta = max(idle2 - idle1, 0)
    usage = (1.0 - (idle_delta / total_delta)) * 100.0
    return round(max(0.0, min(usage, 100.0)), 2)


def get_memory_stats() -> Dict[str, Optional[float]]:
    values: Dict[str, int] = {}
    with open("/proc/meminfo", "r", encoding="utf-8") as handle:
        for line in handle:
            if ":" not in line:
                continue
            key, value = line.split(":", 1)
            number = value.strip().split()[0]
            if number.isdigit():
                values[key] = int(number)
    total_kb = values.get("MemTotal", 0)
    available_kb = values.get("MemAvailable", values.get("MemFree", 0))
    used_kb = max(total_kb - available_kb, 0)
    usage = round((used_kb / total_kb) * 100.0, 2) if total_kb else None
    return {
        "memory_usage": usage,
        "memory_total": round(total_kb / 1024 / 1024, 2) if total_kb else None,
        "memory_free_gb": round(available_kb / 1024 / 1024, 2) if available_kb else None,
        "memory_available_gb": round(available_kb / 1024 / 1024, 2) if available_kb else None,
        "memory_used_gb": round(used_kb / 1024 / 1024, 2) if used_kb else 0.0,
    }


def get_disk_stats(max_filesystems: int) -> Dict[str, object]:
    output = run_command(["df", "-B1", "--output=source,target,size,used,avail,pcent", "-x", "tmpfs", "-x", "devtmpfs"])
    filesystems: List[Dict[str, object]] = []
    root_record: Optional[Dict[str, object]] = None

    for line in output.splitlines()[1:]:
        parts = line.split()
        if len(parts) < 6:
            continue
        source, target, size, used, avail, pcent = parts[:6]
        try:
            size_i = int(size)
            used_i = int(used)
            avail_i = int(avail)
            usage_pct = float(pcent.rstrip("%"))
        except ValueError:
            continue
        record = {
            "filesystem": source,
            "mount_point": target,
            "size_bytes": size_i,
            "used_bytes": used_i,
            "available_bytes": avail_i,
            "usage_percent": round(usage_pct, 2),
            "size_gb": round(size_i / 1024 / 1024 / 1024, 2),
            "available_gb": round(avail_i / 1024 / 1024 / 1024, 2),
        }
        filesystems.append(record)
        if target == "/":
            root_record = record

    if root_record is None and filesystems:
        root_record = filesystems[0]

    ranked = sorted(filesystems, key=lambda item: item["used_bytes"], reverse=True)
    top_mounts = ranked[: max(1, max_filesystems)] if ranked else []

    return {
        "disk_usage": root_record.get("usage_percent") if root_record else None,
        "disk_total": root_record.get("size_gb") if root_record else None,
        "disk_free_gb": root_record.get("available_gb") if root_record else None,
        "filesystems": filesystems,
        "top_filesystems": top_mounts,
    }


def get_load_average() -> Dict[str, Optional[float]]:
    with open("/proc/loadavg", "r", encoding="utf-8") as handle:
        parts = handle.read().strip().split()
    if len(parts) < 3:
        return {"load_1": None, "load_5": None, "load_15": None}
    return {
        "load_1": round(float(parts[0]), 2),
        "load_5": round(float(parts[1]), 2),
        "load_15": round(float(parts[2]), 2),
    }


def read_net_dev() -> Dict[str, Dict[str, int]]:
    data: Dict[str, Dict[str, int]] = {}
    with open("/proc/net/dev", "r", encoding="utf-8") as handle:
        lines = handle.read().splitlines()[2:]
    for line in lines:
        if ":" not in line:
            continue
        iface, payload = line.split(":", 1)
        iface = iface.strip()
        fields = payload.split()
        if len(fields) < 16:
            continue
        data[iface] = {
            "rx_bytes": int(fields[0]),
            "tx_bytes": int(fields[8]),
        }
    return data


def get_network_stats(sample_seconds: float, preferred_interfaces: List[str]) -> Dict[str, object]:
    first = read_net_dev()
    time.sleep(max(sample_seconds, 0.1))
    second = read_net_dev()
    interval = max(sample_seconds, 0.1)

    interfaces = [iface for iface in preferred_interfaces if iface in first and iface in second]
    if not interfaces:
        interfaces = [iface for iface in sorted(second) if iface != "lo"][:PRIMARY_INTERFACE_LIMIT]

    interface_stats: List[Dict[str, object]] = []
    total_rx_per_sec = 0.0
    total_tx_per_sec = 0.0

    for iface in interfaces:
        rx_delta = max(second[iface]["rx_bytes"] - first[iface]["rx_bytes"], 0)
        tx_delta = max(second[iface]["tx_bytes"] - first[iface]["tx_bytes"], 0)
        rx_per_sec = rx_delta / interval
        tx_per_sec = tx_delta / interval
        total_rx_per_sec += rx_per_sec
        total_tx_per_sec += tx_per_sec
        interface_stats.append({
            "interface": iface,
            "rx_bytes_per_sec": round(rx_per_sec, 2),
            "tx_bytes_per_sec": round(tx_per_sec, 2),
            "rx_mbps": round((rx_per_sec * 8) / 1_000_000, 2),
            "tx_mbps": round((tx_per_sec * 8) / 1_000_000, 2),
        })

    return {
        "network_in": int(round(total_rx_per_sec)),
        "network_out": int(round(total_tx_per_sec)),
        "network_in_mbps": round((total_rx_per_sec * 8) / 1_000_000, 2),
        "network_out_mbps": round((total_tx_per_sec * 8) / 1_000_000, 2),
        "network_interfaces": interface_stats,
    }


def get_uptime() -> Dict[str, Optional[object]]:
    with open("/proc/uptime", "r", encoding="utf-8") as handle:
        uptime_seconds = int(float(handle.read().split()[0]))

    boot_time_value: Optional[str] = None
    with open("/proc/stat", "r", encoding="utf-8") as handle:
        for line in handle:
            if line.startswith("btime "):
                boot_epoch = int(line.split()[1])
                boot_time_value = datetime.fromtimestamp(boot_epoch, tz=timezone.utc).isoformat().replace("+00:00", "Z")
                break

    return {
        "uptime_seconds": uptime_seconds,
        "boot_time": boot_time_value,
    }


def get_top_processes(max_processes: int) -> List[Dict[str, object]]:
    output = run_command([
        "ps",
        "-eo",
        "pid,ppid,comm,%cpu,%mem,rss,state,args",
        "--sort=-%cpu,-%mem",
    ])
    processes: List[Dict[str, object]] = []
    for line in output.splitlines()[1: max_processes + 1]:
        parts = line.split(None, 7)
        if len(parts) < 7:
            continue
        pid, ppid, comm, cpu, mem, rss, state = parts[:7]
        args = parts[7] if len(parts) > 7 else comm
        try:
            processes.append({
                "pid": int(pid),
                "ppid": int(ppid),
                "name": comm,
                "command": args,
                "cpu_percent": round(float(cpu), 2),
                "memory_percent": round(float(mem), 2),
                "memory_mb": round(int(rss) / 1024, 2),
                "state": state,
            })
        except ValueError:
            continue
    return processes


def parse_service_states(max_services: int) -> List[Dict[str, object]]:
    enabled_states: Dict[str, str] = {}
    for line in run_command([
        "systemctl",
        "list-unit-files",
        "--type=service",
        "--no-pager",
        "--no-legend",
    ]).splitlines():
        parts = line.split()
        if len(parts) >= 2:
            enabled_states[parts[0]] = parts[1]

    services: List[Dict[str, object]] = []
    for line in run_command([
        "systemctl",
        "list-units",
        "--type=service",
        "--all",
        "--no-pager",
        "--no-legend",
    ]).splitlines():
        parts = line.split(None, 4)
        if len(parts) < 4:
            continue
        unit, load_state, active_state, sub_state = parts[:4]
        description = parts[4] if len(parts) > 4 else ""
        services.append({
            "name": unit,
            "display_name": unit[:-8] if unit.endswith(".service") else unit,
            "load_state": load_state,
            "state": active_state,
            "sub_state": sub_state,
            "enabled_state": enabled_states.get(unit),
            "enabled": enabled_states.get(unit) in {"enabled", "enabled-runtime", "static", "alias", "indirect"},
            "description": description,
        })
        if len(services) >= max_services:
            break
    return services


def parse_sensors_output() -> Dict[str, object]:
    if shutil.which("sensors") is None:
        return {}
    output = run_command(["sensors"])
    readings: List[Dict[str, object]] = []
    current_chip: Optional[str] = None
    labels = ("Package id", "Tctl", "Tdie", "Core ", "CPU", "temp1", "Physical id")

    for raw_line in output.splitlines():
        line = raw_line.rstrip()
        stripped = line.strip()
        if not stripped:
            continue
        if not raw_line.startswith(" ") and not raw_line.startswith("\t") and ":" not in stripped:
            current_chip = stripped
            continue
        if ":" not in stripped:
            continue
        label, remainder = stripped.split(":", 1)
        if not any(label.startswith(prefix) for prefix in labels):
            continue
        marker_index = remainder.find("+")
        if marker_index == -1:
            continue
        temp_chars = []
        for char in remainder[marker_index + 1:]:
            if char.isdigit() or char == ".":
                temp_chars.append(char)
            elif temp_chars:
                break
        if not temp_chars:
            continue
        try:
            temp_value = round(float("".join(temp_chars)), 2)
        except ValueError:
            continue
        readings.append({
            "chip": current_chip,
            "name": label,
            "temperature_c": temp_value,
        })

    if not readings:
        return {}

    summary = round(sum(item["temperature_c"] for item in readings) / len(readings), 2)
    return {
        "temperature_c": summary,
        "sensor_summary": readings[:20],
    }


def collect_payload(config: Dict[str, str]) -> Dict[str, object]:
    hostname = get_hostname()
    ip_address = get_primary_ip()
    preferred_interfaces = get_default_interfaces()
    cpu_sample = to_float(config.get("CPU_SAMPLE_SECONDS", str(DEFAULT_CPU_SAMPLE)), DEFAULT_CPU_SAMPLE)
    net_sample = to_float(config.get("NETWORK_SAMPLE_SECONDS", str(DEFAULT_NET_SAMPLE)), DEFAULT_NET_SAMPLE)
    max_services = to_int(config.get("MAX_SERVICES", str(DEFAULT_MAX_SERVICES)), DEFAULT_MAX_SERVICES)
    max_processes = to_int(config.get("MAX_PROCESSES", str(DEFAULT_MAX_PROCESSES)), DEFAULT_MAX_PROCESSES)
    max_filesystems = to_int(config.get("MAX_FILESYSTEMS", str(DEFAULT_MAX_FILESYSTEMS)), DEFAULT_MAX_FILESYSTEMS)

    memory = get_memory_stats()
    disk = get_disk_stats(max_filesystems)
    loadavg = get_load_average()
    uptime = get_uptime()
    network = get_network_stats(net_sample, preferred_interfaces)
    payload: Dict[str, object] = {
        "hostname": hostname,
        "host_name": hostname,
        "ip_address": ip_address,
        "host_ip": ip_address,
        "os_version": parse_os_release(),
        "cpu_usage": get_cpu_usage(cpu_sample),
        "cpu": None,
        **memory,
        **disk,
        **loadavg,
        **network,
        **uptime,
        "top_processes": get_top_processes(max_processes),
        "services": parse_service_states(max_services),
        "agent_runtime": {
            "collector": "ampnm-linux-agent",
            "language": "python3",
            "config_file": str(CONFIG_FILE),
            "interval_seconds": to_int(config.get("INTERVAL", str(DEFAULT_INTERVAL)), DEFAULT_INTERVAL),
            "sample_windows": {
                "cpu_seconds": cpu_sample,
                "network_seconds": net_sample,
            },
        },
    }
    payload["cpu"] = payload["cpu_usage"]
    payload.update(parse_sensors_output())
    return payload


def send_payload(server_url: str, agent_token: str, payload: Dict[str, object], timeout: int, retry_count: int, retry_delay: float) -> bool:
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    headers = {
        "Content-Type": "application/json",
        "x-agent-token": agent_token,
    }

    for attempt in range(1, retry_count + 1):
        req = request.Request(server_url, data=body, headers=headers, method="POST")
        try:
            with request.urlopen(req, timeout=timeout) as response:
                response_body = response.read().decode("utf-8", errors="replace")
                if 200 <= response.status < 300:
                    log(f"metrics sent successfully (attempt={attempt}, status={response.status}) {response_body}")
                    return True
                log(f"metrics submission failed (attempt={attempt}, status={response.status}) {response_body}")
        except error.HTTPError as exc:
            response_body = exc.read().decode("utf-8", errors="replace")
            transient = exc.code in {408, 425, 429, 500, 502, 503, 504}
            log(f"metrics submission HTTP error (attempt={attempt}, status={exc.code}, transient={transient}) {response_body}")
            if not transient or attempt >= retry_count:
                return False
        except (error.URLError, TimeoutError, ConnectionError, OSError) as exc:
            log(f"metrics submission network error (attempt={attempt}/{retry_count}): {exc}")
            if attempt >= retry_count:
                return False
        time.sleep(retry_delay * attempt)
    return False


def maybe_drop_privileges() -> None:
    target_user = os.environ.get("AMPNM_RUN_AS_USER", "")
    if not target_user or os.geteuid() != 0:
        return
    try:
        pw_entry = pwd.getpwnam(target_user)
        os.setgid(pw_entry.pw_gid)
        os.setuid(pw_entry.pw_uid)
        log(f"dropped privileges to {target_user}")
    except Exception as exc:
        log(f"failed to drop privileges to {target_user}: {exc}")


def main() -> int:
    try:
        config = load_config()
    except Exception as exc:
        log(str(exc))
        return 1

    maybe_drop_privileges()

    server_url = config["SERVER_URL"]
    agent_token = config["AGENT_TOKEN"]
    interval = max(5, to_int(config.get("INTERVAL", str(DEFAULT_INTERVAL)), DEFAULT_INTERVAL))
    timeout = max(5, to_int(config.get("REQUEST_TIMEOUT", str(DEFAULT_TIMEOUT)), DEFAULT_TIMEOUT))
    retry_count = max(1, to_int(config.get("RETRY_COUNT", str(DEFAULT_RETRY_COUNT)), DEFAULT_RETRY_COUNT))
    retry_delay = max(1.0, to_float(config.get("RETRY_DELAY_SECONDS", str(DEFAULT_RETRY_DELAY)), DEFAULT_RETRY_DELAY))

    log(f"starting Linux monitoring loop; interval={interval}s server={server_url}")

    while True:
        started = time.monotonic()
        try:
            payload = collect_payload(config)
            send_payload(server_url, agent_token, payload, timeout, retry_count, retry_delay)
        except Exception as exc:
            log(f"collection cycle failed: {exc}")
        elapsed = time.monotonic() - started
        sleep_for = max(interval - elapsed, 1.0)
        time.sleep(sleep_for)


if __name__ == "__main__":
    sys.exit(main())
