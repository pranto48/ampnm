#!/usr/bin/env bash
# AMPNM Self-Updating Script via Docker Socket
set -euo pipefail

CONTAINER_ID=$(hostname)

# Force minimum supported API version for compatibility with newer host engines (e.g. Docker 25/26/28+)
export DOCKER_API_VERSION="${DOCKER_API_VERSION:-1.44}"

if [ ! -S /var/run/docker.sock ]; then
  echo "ERROR: /var/run/docker.sock is not accessible. Cannot perform Docker Hub updates."
  exit 1
fi

echo "→ Inspecting current container configuration..."

# 1. Fetch current container configuration from Docker socket
JSON_CONFIG=$(docker inspect "${CONTAINER_ID}")

# 2. Extract configuration values
NAME=$(echo "${JSON_CONFIG}" | jq -r '.[0].Name' | sed 's/^\///')

# Resolve target image dynamically from the currently running container's repository
CURRENT_IMAGE=$(echo "${JSON_CONFIG}" | jq -r '.[0].Config.Image')
BASE_REPO=$(echo "${CURRENT_IMAGE}" | cut -d':' -f1 | cut -d'@' -f1)
TARGET_IMAGE="${BASE_REPO:-itsupportbd/ampnm}:latest"

# Backup active license key from database before stopping the old container
ACTIVE_LICENSE=$(docker exec "${CONTAINER_ID}" php -r "require '/var/www/html/config.php'; echo getAppLicenseKey();" 2>/dev/null || true)
if [ -n "$ACTIVE_LICENSE" ]; then
  echo "✓ Successfully backed up active license key during update."
fi

echo "Recreating container '${NAME}' with image '${TARGET_IMAGE}'..."

# Reconstruct environment arguments safely using single quote escaping
ENV_ARGS=""
while read -r env; do
  if [ -n "$env" ]; then
    # Skip the old APP_LICENSE_KEY if we successfully retrieved the active license from DB
    if [ -n "$ACTIVE_LICENSE" ] && [[ "$env" == APP_LICENSE_KEY=* ]]; then
      continue
    fi
    # Escape any single quotes inside the env variable
    escaped_env=$(echo "$env" | sed "s/'/'\\\\''/g")
    ENV_ARGS="${ENV_ARGS} -e '${escaped_env}'"
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].Config.Env[]')

# Append the backed up active license key
if [ -n "$ACTIVE_LICENSE" ]; then
  escaped_license=$(echo "$ACTIVE_LICENSE" | sed "s/'/'\\\\''/g")
  ENV_ARGS="${ENV_ARGS} -e 'APP_LICENSE_KEY=${escaped_license}'"
fi

# Reconstruct volume mounts safely
VOLUME_ARGS=""
while read -r bind; do
  if [ -n "$bind" ] && [ "$bind" != "null" ]; then
    escaped_bind=$(echo "$bind" | sed "s/'/'\\\\''/g")
    VOLUME_ARGS="${VOLUME_ARGS} -v '${escaped_bind}'"
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.Binds[]')

# Reconstruct port bindings
PORT_ARGS=""
while read -r port_binding; do
  if [ -n "$port_binding" ] && [ "$port_binding" != "null" ]; then
    CONTAINER_PORT=$(echo "$port_binding" | cut -d'/' -f1)
    HOST_PORT=$(echo "$port_binding" | jq -r '.[0].HostPort')
    if [ -n "$HOST_PORT" ] && [ "$HOST_PORT" != "null" ]; then
      PORT_ARGS="${PORT_ARGS} -p ${HOST_PORT}:${CONTAINER_PORT}"
    fi
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.PortBindings | to_entries[] | "\(.key) \(.value)"')

# Reconstruct restart policy
RESTART_POLICY=$(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.RestartPolicy.Name')
if [ -n "$RESTART_POLICY" ] && [ "$RESTART_POLICY" != "no" ] && [ "$RESTART_POLICY" != "null" ]; then
  RESTART_ARG="--restart ${RESTART_POLICY}"
else
  RESTART_ARG="--restart unless-stopped"
fi

# Reconstruct network mode
NET_MODE=$(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.NetworkMode')
if [ -n "$NET_MODE" ] && [ "$NET_MODE" != "default" ] && [ "$NET_MODE" != "null" ]; then
  NET_ARG="--network ${NET_MODE}"
else
  NET_ARG=""
fi

# Construct the full docker run command
RUN_CMD="docker run -d --name ${NAME} ${RESTART_ARG} ${NET_ARG} ${PORT_ARGS} ${VOLUME_ARGS} ${ENV_ARGS} ${TARGET_IMAGE}"
RUN_CMD=$(echo "$RUN_CMD" | tr -s ' ')

echo "→ Prepared Docker Run Command:"
echo "$RUN_CMD"

# Launch helper container to perform update asynchronously, passing environment variables safely
echo "→ Launching update helper container..."
docker run -d --name ampnm_updater_helper --rm \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -e RUN_CMD="${RUN_CMD}" \
  -e CONTAINER_ID="${CONTAINER_ID}" \
  -e TARGET_IMAGE="${TARGET_IMAGE}" \
  -e DOCKER_API_VERSION="${DOCKER_API_VERSION}" \
  docker:cli sh -c '
    echo "[10%] Initializing self-update routine..."
    echo "[20%] Waiting for host container request context to close..."
    sleep 3
    echo "[40%] Pulling latest image \"\${TARGET_IMAGE}\" from Docker Hub..."
    if ! docker pull "\${TARGET_IMAGE}"; then
      echo "❌ ERROR [40%]: Failed to pull image \"\${TARGET_IMAGE}\". Please check internet connectivity or repository credentials." >&2
      exit 1
    fi
    echo "[60%] Safely stopping old container \"\${CONTAINER_ID}\"..."
    if ! docker stop "\${CONTAINER_ID}"; then
      echo "⚠️ WARNING [60%]: Graceful shutdown failed. Forcing removal..."
    fi
    echo "[80%] Removing old container \"\${CONTAINER_ID}\"..."
    if ! docker rm -f "\${CONTAINER_ID}"; then
      echo "❌ ERROR [80%]: Failed to remove old container context." >&2
      exit 1
    fi
    echo "[90%] Spawning new container..."
    if ! eval "\${RUN_CMD}"; then
      echo "❌ ERROR [90%]: Failed to start new container with command: \${RUN_CMD}" >&2
      exit 1
    fi
    echo "[100%] Update completed successfully!"
  '

echo "✓ Update helper successfully spawned. Recreating container shortly..."
