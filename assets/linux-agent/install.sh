#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
INSTALL_DIR=/opt/ampnm-agent
CONFIG_DIR=/etc/ampnm-agent
CONFIG_FILE="$CONFIG_DIR/config.env"
SERVICE_NAME=ampnm-agent.service
SYSTEMD_DIR=/etc/systemd/system
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
  --server-url   Full Supabase metrics endpoint, e.g. https://<project>.supabase.co/functions/v1/agent-metrics
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
  for cmd in bash curl python3 systemctl install; do
    command -v "$cmd" >/dev/null 2>&1 || missing+=("$cmd")
  done
  command -v ip >/dev/null 2>&1 || missing+=("iproute2/ip")
  if (( ${#missing[@]} > 0 )); then
    printf 'Missing required dependencies: %s\n' "${missing[*]}" >&2
    exit 1
  fi
}

write_config() {
  install -d -m 0755 "$CONFIG_DIR"
  cat > "$CONFIG_FILE" <<CFG
SERVER_URL=${SERVER_URL}
AGENT_TOKEN=${AGENT_TOKEN}
INTERVAL=${INTERVAL}
REQUEST_TIMEOUT=30
CFG
  chmod 0600 "$CONFIG_FILE"
}

install_files() {
  install -d -m 0755 "$INSTALL_DIR"
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
  rm -rf "$INSTALL_DIR" "$CONFIG_DIR"
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
  install_files
  write_config
  start_service
  echo "AMPNM Linux agent installed successfully."
  echo "Service: $SERVICE_NAME"
  echo "Config : $CONFIG_FILE"
  echo "Binary : $INSTALL_DIR/ampnm-agent.sh"
}

main "$@"
