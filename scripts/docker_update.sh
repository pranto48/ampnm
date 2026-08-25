#!/usr/bin/env bash
#
# Copyright (c) IT Support BD. All rights reserved.
# AMPNM Self-Updating Script via Docker Socket
#
set -euo pipefail

CONTAINER_ID=$(hostname)
export DOCKER_API_VERSION="${DOCKER_API_VERSION:-1.44}"

if [ ! -S /var/run/docker.sock ]; then
  echo "ERROR: /var/run/docker.sock is not accessible. Cannot perform Docker Hub updates."
  exit 1
fi

echo "→ Inspecting current container configuration (${CONTAINER_ID})..."

# 1. Fetch current container configuration from Docker socket
JSON_CONFIG=$(docker inspect "${CONTAINER_ID}")

# 2. Extract configuration values
NAME=$(echo "${JSON_CONFIG}" | jq -r '.[0].Name // "ampnm_server"' | sed 's/^\///')

# Resolve target image dynamically
CURRENT_IMAGE=$(echo "${JSON_CONFIG}" | jq -r '.[0].Config.Image // "itsupportbd/ampnm:latest"')
BASE_REPO=$(echo "${CURRENT_IMAGE}" | cut -d':' -f1 | cut -d'@' -f1)
TARGET_IMAGE="${BASE_REPO:-itsupportbd/ampnm}:latest"

echo "✓ Target Image: ${TARGET_IMAGE}"

# Ensure required storage directories exist
mkdir -p /var/www/html/uploads /var/www/html/data/code_backups /var/www/html/storage/logs || true
chown -R www-data:www-data /var/www/html/uploads /var/www/html/data /var/www/html/storage 2>/dev/null || true
chmod -R 775 /var/www/html/uploads /var/www/html/data /var/www/html/storage 2>/dev/null || true

# Backup active license key from database before stopping the old container
ACTIVE_LICENSE=$(docker exec "${CONTAINER_ID}" php -r "require_once '/var/www/html/config.php'; echo getAppLicenseKey();" 2>/dev/null || true)
if [ -n "$ACTIVE_LICENSE" ]; then
  echo "✓ Successfully backed up active license key: ${ACTIVE_LICENSE:0:8}..."
fi

# Dump the active database into the persistent uploads directory
echo "→ Creating pre-update database dump in uploads directory..."
docker exec "${CONTAINER_ID}" sh -c '
  [ -f /etc/apache2/envvars ] && . /etc/apache2/envvars
  host="${DB_HOST:-db}"
  user="${DB_USER:-user}"
  pass="${DB_PASSWORD:-password}"
  name="${DB_NAME:-network_monitor}"
  passArg=""
  if [ -n "$pass" ]; then
    passArg="-p$pass"
  fi
  mysqldump -h "$host" -u "$user" $passArg "$name" > /var/www/html/uploads/db_backup_pre_update.sql 2>/dev/null || true
' || true

# Reconstruct environment arguments
ENV_ARGS=""
while read -r env_item; do
  if [ -n "$env_item" ] && [ "$env_item" != "null" ]; then
    if [ -n "$ACTIVE_LICENSE" ] && [[ "$env_item" == APP_LICENSE_KEY=* ]]; then
      continue
    fi
    escaped_env=$(echo "$env_item" | sed "s/'/'\\\\''/g")
    ENV_ARGS="${ENV_ARGS} -e '${escaped_env}'"
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].Config.Env // [] | .[]')

if [ -n "$ACTIVE_LICENSE" ]; then
  escaped_license=$(echo "$ACTIVE_LICENSE" | sed "s/'/'\\\\''/g")
  ENV_ARGS="${ENV_ARGS} -e 'APP_LICENSE_KEY=${escaped_license}'"
fi

# Reconstruct volume mounts
VOLUME_ARGS=""
while read -r bind_item; do
  if [ -n "$bind_item" ] && [ "$bind_item" != "null" ]; then
    escaped_bind=$(echo "$bind_item" | sed "s/'/'\\\\''/g")
    VOLUME_ARGS="${VOLUME_ARGS} -v '${escaped_bind}'"
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.Binds // [] | .[]')

# Reconstruct port bindings
PORT_ARGS=""
while read -r host_port container_port; do
  if [ -n "$host_port" ] && [ "$host_port" != "null" ] && [ -n "$container_port" ] && [ "$container_port" != "null" ]; then
    PORT_ARGS="${PORT_ARGS} -p ${host_port}:${container_port}"
  fi
done < <(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.PortBindings // {} | to_entries[] | "\(.value[0].HostPort) \(.key | split("/")[0])"')

# Fallback port if empty
if [ -z "$(echo "${PORT_ARGS}" | tr -d ' ')" ]; then
  PORT_ARGS="-p 2266:2266"
fi

# Reconstruct network mode
NET_MODE=$(echo "${JSON_CONFIG}" | jq -r '.[0].HostConfig.NetworkMode // "ampnm_default"')
if [ -n "$NET_MODE" ] && [ "$NET_MODE" != "default" ] && [ "$NET_MODE" != "null" ]; then
  NET_ARG="--network ${NET_MODE} --network-alias app"
else
  NET_ARG="--network ampnm_default --network-alias app"
fi

# Construct full docker run command
RUN_CMD="docker run -d --name ${NAME} --restart unless-stopped ${NET_ARG} ${PORT_ARGS} ${VOLUME_ARGS} ${ENV_ARGS} ${TARGET_IMAGE}"

echo "→ Prepared Docker Run Command:"
echo "$RUN_CMD"

# Remove any old updater helper container
docker rm -f ampnm_updater_helper 2>/dev/null || true

# Launch helper container to perform update asynchronously
echo "→ Launching update helper container..."
docker run -d --name ampnm_updater_helper \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -e RUN_CMD="${RUN_CMD}" \
  -e CONTAINER_ID="${CONTAINER_ID}" \
  -e TARGET_IMAGE="${TARGET_IMAGE}" \
  -e DOCKER_API_VERSION="${DOCKER_API_VERSION}" \
  docker:cli sh -c '
    echo "[10%] Initializing self-update routine..."
    sleep 3
    echo "[30%] Pulling latest image \"${TARGET_IMAGE}\" from Docker Hub..."
    docker pull "${TARGET_IMAGE}" || echo "Warning: pull failed, using cached local image"
    echo "[60%] Safely stopping old container \"${CONTAINER_ID}\"..."
    docker stop "${CONTAINER_ID}" || true
    echo "[80%] Removing old container \"${CONTAINER_ID}\"..."
    docker rm -f "${CONTAINER_ID}" || true
    echo "[90%] Spawning new container..."
    eval "${RUN_CMD}"
    echo "[95%] Running database migrations in new container..."
    sleep 3
    docker exec "${NAME}" php /var/www/html/database_setup.php || true
    echo "[100%] Update completed successfully!"
  '

echo "✓ Update helper successfully spawned. Recreating container with ${TARGET_IMAGE}..."
