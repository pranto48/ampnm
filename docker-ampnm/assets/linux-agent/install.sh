#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
INSTALL_DIR=/opt/ampnm-agent
CONFIG_DIR=/etc/ampnm-agent
CONFIG_FILE="$CONFIG_DIR/config.env"
LOG_DIR=/var/log/ampnm-agent
SERVICE_NAME=ampnm-agent.service
SYSTEMD_DIR=/etc/systemd/system
SERVICE_USER=ampnm-agent
SERVICE_GROUP=ampnm-agent
UNINSTALL=0
SERVER_URL=""
AGENT_TOKEN=""
INTERVAL=60

usage() {
  cat <<USAGE
Usage:
  sudo ./install.sh --server-url <url> --agent-token <token> [--interval <seconds>]
  sudo ./install.sh --uninstall

Options:
  --server-url   Full metrics endpoint, e.g. https://<project>.supabase.co/functions/v1/agent-metrics
  --agent-token  Agent token created in AMPNM
  --interval     Collection interval in seconds (default: 60)
  --uninstall    Remove the installed service, config, and runtime files
  -h, --help     Show this help text
USAGE
}

require_root() {
  if [[ ${EUID:-$(id -u)} -ne 0 ]]; then
    echo "Please run as root (sudo)." >&2
    exit 1
  fi
}

check_dependencies() {
  local missing=()
  for cmd in python3 systemctl install; do
    command -v "$cmd" >/dev/null 2>&1 || missing+=("$cmd")
  done
  command -v df >/dev/null 2>&1 || missing+=("df/coreutils")
  command -v ps >/dev/null 2>&1 || missing+=("ps/procps")
  command -v ip >/dev/null 2>&1 || missing+=("iproute2/ip")
  if (( ${#missing[@]} > 0 )); then
    printf 'Missing required dependencies: %s\n' "${missing[*]}" >&2
    exit 1
  fi
}

ensure_service_user() {
  if ! getent group "$SERVICE_GROUP" >/dev/null 2>&1; then
    groupadd --system "$SERVICE_GROUP"
  fi

  if ! id "$SERVICE_USER" >/dev/null 2>&1; then
    useradd --system --gid "$SERVICE_GROUP" --home-dir "$INSTALL_DIR" --shell /usr/sbin/nologin "$SERVICE_USER"
  fi
}

write_config() {
  install -d -m 0750 -o root -g "$SERVICE_GROUP" "$CONFIG_DIR"
  cat > "$CONFIG_FILE" <<CFG
SERVER_URL=${SERVER_URL}
AGENT_TOKEN=${AGENT_TOKEN}
INTERVAL=${INTERVAL}
REQUEST_TIMEOUT=30
CPU_SAMPLE_SECONDS=1
NETWORK_SAMPLE_SECONDS=1
RETRY_COUNT=3
RETRY_DELAY_SECONDS=5
MAX_SERVICES=100
MAX_PROCESSES=50
MAX_FILESYSTEMS=5
CFG
  chown root:"$SERVICE_GROUP" "$CONFIG_FILE"
  chmod 0640 "$CONFIG_FILE"
}

install_files() {
  install -d -m 0755 -o root -g root "$INSTALL_DIR"
  install -d -m 0755 -o "$SERVICE_USER" -g "$SERVICE_GROUP" "$LOG_DIR"
  install -m 0755 "$SCRIPT_DIR/ampnm-agent.sh" "$INSTALL_DIR/ampnm-agent.sh"
  install -m 0644 "$SCRIPT_DIR/ampnm-agent.service" "$SYSTEMD_DIR/$SERVICE_NAME"
}

start_service() {
  systemctl daemon-reload
  systemctl enable --now "$SERVICE_NAME"
}

stop_service() {
  if systemctl list-unit-files "$SERVICE_NAME" >/dev/null 2>&1; then
    systemctl disable --now "$SERVICE_NAME" || true
  fi
  rm -f "$SYSTEMD_DIR/$SERVICE_NAME"
  systemctl daemon-reload || true
}

uninstall_agent() {
  echo "Uninstalling AMPNM Linux agent..."
  stop_service
  rm -rf "$INSTALL_DIR" "$CONFIG_DIR" "$LOG_DIR"
  if id "$SERVICE_USER" >/dev/null 2>&1; then
    userdel "$SERVICE_USER" || true
  fi
  if getent group "$SERVICE_GROUP" >/dev/null 2>&1; then
    groupdel "$SERVICE_GROUP" || true
  fi
  echo "Uninstall complete."
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --server-url)
        SERVER_URL=${2:-}
        shift 2
        ;;
      --agent-token)
        AGENT_TOKEN=${2:-}
        shift 2
        ;;
      --interval)
        INTERVAL=${2:-}
        shift 2
        ;;
      --uninstall)
        UNINSTALL=1
        shift
        ;;
      -h|--help)
        usage
        exit 0
        ;;
      *)
        echo "Unknown argument: $1" >&2
        usage
        exit 1
        ;;
    esac
  done
}

validate_args() {
  if (( UNINSTALL == 0 )); then
    [[ -n "$SERVER_URL" ]] || { echo "--server-url is required" >&2; exit 1; }
    [[ -n "$AGENT_TOKEN" ]] || { echo "--agent-token is required" >&2; exit 1; }
    [[ "$INTERVAL" =~ ^[0-9]+$ ]] || { echo "--interval must be a positive integer" >&2; exit 1; }
    (( INTERVAL >= 5 )) || { echo "--interval must be at least 5 seconds" >&2; exit 1; }
  fi
}

main() {
  require_root
  parse_args "$@"
  if (( UNINSTALL == 1 )); then
    uninstall_agent
    exit 0
  fi
  validate_args
  check_dependencies
  ensure_service_user
  install_files
  write_config
  start_service
  echo "AMPNM Linux agent installed successfully."
  echo "Service : $SERVICE_NAME"
  echo "Config  : $CONFIG_FILE"
  echo "Runtime : $INSTALL_DIR/ampnm-agent.sh"
  echo "Logs    : $LOG_DIR/agent.log"
  echo "User    : $SERVICE_USER"
}

main "$@"
