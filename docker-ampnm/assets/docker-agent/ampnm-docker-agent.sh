#!/usr/bin/env bash
# ============================================================
#  AMPNM Docker Topology Agent — Linux/macOS
#  Collects container, network, and volume data from the local
#  Docker daemon and POSTs it to the AMPNM docker-sync edge
#  function for centralized topology visualization.
#
#  Usage:
#    export AMPNM_SYNC_URL="https://<project-id>.supabase.co/functions/v1/docker-sync"
#    export AMPNM_ANON_KEY="<your-anon-key>"
#    ./ampnm-docker-agent.sh            # run once
#    POLL_INTERVAL=60 ./ampnm-docker-agent.sh   # loop every 60s
# ============================================================

set -euo pipefail

# ── Config ──────────────────────────────────────────────────
SYNC_URL="${AMPNM_SYNC_URL:?'Set AMPNM_SYNC_URL (e.g. https://<id>.supabase.co/functions/v1/docker-sync)'}"
ANON_KEY="${AMPNM_ANON_KEY:?'Set AMPNM_ANON_KEY (Supabase anon key)'}"
POLL_INTERVAL="${POLL_INTERVAL:-0}"   # 0 = run once, >0 = loop

# ── Helpers ─────────────────────────────────────────────────
require_cmd() { command -v "$1" >/dev/null 2>&1 || { echo "ERROR: '$1' not found. Please install it."; exit 1; }; }
require_cmd docker
require_cmd curl
require_cmd jq

collect_and_push() {
    echo "[$(date -Iseconds)] Collecting Docker topology…"

    local HOSTNAME_VAL
    HOSTNAME_VAL="$(hostname)"

    local HOST_IP
    HOST_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || echo '')"

    local DOCKER_VERSION
    DOCKER_VERSION="$(docker version --format '{{.Server.Version}}' 2>/dev/null || echo 'unknown')"

    # ── Containers ──────────────────────────────────────────
    local CONTAINERS
    CONTAINERS=$(docker ps -a --format json | jq -s '[.[] | {
        id: .ID,
        name: .Names,
        image: .Image,
        state: .State,
        status: .Status,
        ports: ((.Ports // "") | split(", ") | map(
            capture("(?:(?<host>[0-9.]+):)?(?<external>[0-9]+)->(?<internal>[0-9]+)/(?<protocol>[a-z]+)") // null
        ) | map(select(. != null) | {external: (.external | tonumber), internal: (.internal | tonumber), protocol})),
        networks: ((.Networks // "") | split(",")),
        ip: ""
    }]')

    # Enrich with internal IPs
    local IDS
    IDS=$(echo "$CONTAINERS" | jq -r '.[].id')
    local ENRICHED="$CONTAINERS"
    for CID in $IDS; do
        local IP
        IP=$(docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$CID" 2>/dev/null || echo '')
        ENRICHED=$(echo "$ENRICHED" | jq --arg cid "$CID" --arg ip "$IP" '
            map(if .id == $cid then .ip = $ip else . end)
        ')
    done

    # ── Networks ────────────────────────────────────────────
    local NETWORKS
    NETWORKS=$(docker network ls --format json | jq -s '[.[] | {
        name: .Name,
        driver: .Driver,
        scope: .Scope,
        subnet: "",
        gateway: "",
        containers: []
    }]')

    # Enrich networks with subnet/gateway/containers
    local NET_NAMES
    NET_NAMES=$(echo "$NETWORKS" | jq -r '.[].name')
    local ENRICHED_NETS="$NETWORKS"
    for NET in $NET_NAMES; do
        local NET_INSPECT
        NET_INSPECT=$(docker network inspect "$NET" 2>/dev/null || echo '[]')
        local SUBNET GATEWAY NET_CONTAINERS
        SUBNET=$(echo "$NET_INSPECT" | jq -r '.[0].IPAM.Config[0].Subnet // ""')
        GATEWAY=$(echo "$NET_INSPECT" | jq -r '.[0].IPAM.Config[0].Gateway // ""')
        NET_CONTAINERS=$(echo "$NET_INSPECT" | jq '[.[0].Containers // {} | to_entries[] | .value.Name]')
        ENRICHED_NETS=$(echo "$ENRICHED_NETS" | jq --arg net "$NET" --arg sub "$SUBNET" --arg gw "$GATEWAY" --argjson ctrs "$NET_CONTAINERS" '
            map(if .name == $net then .subnet = $sub | .gateway = $gw | .containers = $ctrs else . end)
        ')
    done

    # ── Orphaned Volumes ────────────────────────────────────
    local ORPHANED_VOLS
    ORPHANED_VOLS=$(docker volume ls -qf dangling=true 2>/dev/null | wc -l | tr -d ' ')

    # ── Build Payload ───────────────────────────────────────
    local PAYLOAD
    PAYLOAD=$(jq -n \
        --arg hostname "$HOSTNAME_VAL" \
        --arg ip "$HOST_IP" \
        --arg docker_version "$DOCKER_VERSION" \
        --argjson containers "$ENRICHED" \
        --argjson networks "$ENRICHED_NETS" \
        --argjson volumes_orphaned "$ORPHANED_VOLS" \
        '{
            hostname: $hostname,
            ip: $ip,
            docker_version: $docker_version,
            containers: $containers,
            networks: $networks,
            volumes_orphaned: $volumes_orphaned
        }')

    echo "[$(date -Iseconds)] Pushing topology for $HOSTNAME_VAL (${#ENRICHED} containers)…"

    local HTTP_CODE
    HTTP_CODE=$(curl -s -o /tmp/ampnm-docker-response.json -w "%{http_code}" \
        -X POST "$SYNC_URL" \
        -H "Content-Type: application/json" \
        -H "apikey: $ANON_KEY" \
        -H "Authorization: Bearer $ANON_KEY" \
        -d "$PAYLOAD")

    if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
        echo "[$(date -Iseconds)] ✅ Sync OK (HTTP $HTTP_CODE)"
    else
        echo "[$(date -Iseconds)] ❌ Sync FAILED (HTTP $HTTP_CODE)"
        cat /tmp/ampnm-docker-response.json 2>/dev/null
        echo
    fi
}

# ── Main Loop ───────────────────────────────────────────────
if [ "$POLL_INTERVAL" -gt 0 ]; then
    echo "AMPNM Docker Agent starting (poll every ${POLL_INTERVAL}s)…"
    while true; do
        collect_and_push || true
        sleep "$POLL_INTERVAL"
    done
else
    collect_and_push
fi
