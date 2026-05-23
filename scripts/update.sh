#!/bin/bash
set -e

export HOME=/tmp

REPO_URL="${AMPNM_UPDATE_REPO_URL:-${REPO_URL:-https://github.com/pranto48/ampnm.git}}"
UPDATE_BRANCH="${AMPNM_UPDATE_BRANCH:-main}"
UPSTREAM_REF="origin/${UPDATE_BRANCH}"
#
# APP_DIR is the container path where docker compose is executed (repo root mount).
# Example mapping after restructure: host /var/www/html -> container /var/www/html.
APP_DIR="${APP_DIR:-/var/www/html}"
# HOST_APP_DIR should point to the host-mounted project root used by this container
# (same root path by default after removing docker-ampnm subfolder nesting).
HOST_APP_DIR="${HOST_APP_DIR:-${APP_DIR}}"
# BACKUP_BASE stores code snapshots under the repo root's data tree.
BACKUP_BASE="${BACKUP_BASE:-/var/www/html/data/code_backups}"
TIMESTAMP="${TIMESTAMP:-$(date +%Y%m%d_%H%M%S)}"
BACKUP_DIR="${BACKUP_BASE}/${TIMESTAMP}"
LOG_FILE="${LOG_FILE:-/tmp/ampnm_update_${TIMESTAMP}.log}"
RESULT_ENV_FILE="${RESULT_ENV_FILE:-${AMPNM_RESULT_FILE:-/tmp/ampnm_update_result_$(whoami).env}}"

mkdir -p "$(dirname "${LOG_FILE}")"
exec > >(tee -a "${LOG_FILE}") 2>&1

# Configure git safe directory to avoid dubious ownership issues
if command -v git >/dev/null 2>&1; then
  git config --global --add safe.directory "${HOST_APP_DIR}" || true
  git config --global --add safe.directory '*' || true
fi

write_result() {
  local key="$1"
  local value="$2"
  printf '%s=%s\n' "${key}" "${value}" >> "${RESULT_ENV_FILE}"
}

mark_failure() {
  local line_no="$1"
  local exit_code="$2"
  local message="Update failed at line ${line_no} (exit code ${exit_code})"

  write_result "STATUS" "failed"
  write_result "TIMESTAMP" "${TIMESTAMP}"
  write_result "LOG_FILE" "${LOG_FILE}"
  write_result "ERROR" "${message}"

  echo "${message}"
  exit "${exit_code}"
}

: > "${RESULT_ENV_FILE}"
trap 'mark_failure "${LINENO}" "$?"' ERR

echo "[$(date -Iseconds)] Starting AMPNM update"
echo "REPO_URL=${REPO_URL}"
echo "UPDATE_BRANCH=${UPDATE_BRANCH}"
echo "UPSTREAM_REF=${UPSTREAM_REF}"
echo "APP_DIR=${APP_DIR}"
echo "HOST_APP_DIR=${HOST_APP_DIR}"

# Step 1: backup
mkdir -p "${BACKUP_DIR}"
echo "[$(date -Iseconds)] Step 1/4: Creating backup at ${BACKUP_DIR}"

if [ -d "${HOST_APP_DIR}" ]; then
  rsync -a --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='dist' \
    --exclude='data' \
    --exclude='storage' \
    --exclude='logs' \
    "${HOST_APP_DIR}/" "${BACKUP_DIR}/code/"

  if command -v git >/dev/null 2>&1 && [ -d "${HOST_APP_DIR}/.git" ]; then
    (cd "${HOST_APP_DIR}" && git rev-parse HEAD) > "${BACKUP_DIR}/previous_commit.txt" || true
  fi
else
  echo "HOST_APP_DIR does not exist yet, skipping file backup"
fi

write_result "BACKUP_PATH" "${BACKUP_DIR}"

# Step 2: code sync
echo "[$(date -Iseconds)] Step 2/4: Syncing code"
if [ -d "${HOST_APP_DIR}/.git" ]; then
  cd "${HOST_APP_DIR}"
  git fetch origin --prune

  if git show-ref --verify --quiet "refs/remotes/${UPSTREAM_REF}"; then
    git reset --hard "${UPSTREAM_REF}"
  else
    echo "Configured upstream ref ${UPSTREAM_REF} not found after fetch"
    exit 1
  fi
else
  TMP_CLONE_DIR="$(mktemp -d)"
  git clone --branch "${UPDATE_BRANCH}" --single-branch "${REPO_URL}" "${TMP_CLONE_DIR}"

  mkdir -p "${HOST_APP_DIR}"
  rsync -a --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='data' \
    --exclude='storage' \
    --exclude='logs' \
    "${TMP_CLONE_DIR}/" "${HOST_APP_DIR}/"

  rm -rf "${TMP_CLONE_DIR}"
  cd "${HOST_APP_DIR}"
fi

NEW_COMMIT=""
if command -v git >/dev/null 2>&1; then
  if [ -d "${HOST_APP_DIR}/.git" ]; then
    NEW_COMMIT="$(cd "${HOST_APP_DIR}" && git rev-parse HEAD)"
  fi
fi
write_result "NEW_COMMIT" "${NEW_COMMIT}"

# Step 3: post-update dependencies/build
echo "[$(date -Iseconds)] Step 3/4: Post-update dependencies/build"
cd "${HOST_APP_DIR}"

if [ -f "composer.json" ] && command -v composer >/dev/null 2>&1; then
  composer install --no-interaction --prefer-dist --no-progress
elif [ -f "package.json" ] && command -v pnpm >/dev/null 2>&1; then
  pnpm install --frozen-lockfile || pnpm install
  if [ -f "vite.config.ts" ] || [ -f "vite.config.js" ]; then
    pnpm build
  fi
else
  echo "No container-side dependency/build step required; skipped"
fi

# Step 4: restart services
echo "[$(date -Iseconds)] Step 4/4: Restarting services"
COMPOSE_DIR="${APP_DIR}"
if [ -f "${COMPOSE_DIR}/docker-compose.yml" ] || [ -f "${COMPOSE_DIR}/docker-compose.yaml" ] || [ -f "${COMPOSE_DIR}/compose.yml" ] || [ -f "${COMPOSE_DIR}/compose.yaml" ]; then
  cd "${COMPOSE_DIR}"
  if docker compose version >/dev/null 2>&1; then
    docker compose restart
  else
    docker-compose restart
  fi
else
  echo "No compose file found in ${COMPOSE_DIR}; skipping restart"
fi

write_result "STATUS" "success"
write_result "TIMESTAMP" "${TIMESTAMP}"
write_result "LOG_FILE" "${LOG_FILE}"

echo "[$(date -Iseconds)] Update completed successfully"
