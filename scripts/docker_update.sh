#!/usr/bin/env bash
# AMPNM Self-Updating Script via Docker Socket
set -euo pipefail

CONTAINER_ID=$(hostname)

if [ ! -S /var/run/docker.sock ]; then
  echo "ERROR: /var/run/docker.sock is not accessible. Cannot perform Docker Hub updates."
  exit 1
fi

echo "→ Inspecting current container configuration..."

# 1. Fetch current container configuration from Docker socket
JSON_CONFIG=$(docker inspect "${CONTAINER_ID}")

# 2. Extract configuration values
NAME=$(echo "${JSON_CONFIG}" | jq -r '.[0].Name' | sed 's/^\///')

# Resolve target image
TARGET_IMAGE="arifmahmudpranto/ampnm:latest"

echo "Recreating container '${NAME}' with image '${TARGET_IMAGE}'..."

# Reconstruct environment arguments safely using single quote escaping
ENV_ARGS=""
while read -r env; do
  if [ -n "$env" ]; then
    # Escape any single quotes inside the env variable
    escaped_env=$(echo "$env" | sed "s/'/'\\\\''/g")
    ENV_ARGS="${ENV_ARGS} -e '${escaped_env}'"
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].Config.Env[]')

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
  docker:cli sh -c '
    echo "Waiting for parent container to finish request..."
    sleep 3
    echo "Pulling latest image..."
    docker pull "${TARGET_IMAGE}"
    echo "Stopping current container..."
    docker stop "${CONTAINER_ID}" || true
    echo "Removing current container..."
    docker rm "${CONTAINER_ID}" || true
    echo "Starting new container..."
    eval "${RUN_CMD}"
    echo "Update completed successfully!"
  '

echo "✓ Update helper successfully spawned. Recreating container shortly..."
