#!/bin/bash
#
# Copyright (c) IT Support BD. All rights reserved.
# AMPNM Git Source Code Updater
#
set -e

export HOME=/var/www

REPO_URL="${AMPNM_UPDATE_REPO_URL:-${REPO_URL:-https://github.com/pranto48/ampnm.git}}"
UPDATE_BRANCH="${AMPNM_UPDATE_BRANCH:-main}"
UPSTREAM_REF="origin/${UPDATE_BRANCH}"

APP_DIR="${APP_DIR:-/var/www/html}"
HOST_APP_DIR="${HOST_APP_DIR:-${APP_DIR}}"
BACKUP_BASE="${BACKUP_BASE:-/var/www/html/data/code_backups}"
TIMESTAMP="${TIMESTAMP:-$(date +%Y%m%d_%H%M%S)}"
BACKUP_DIR="${BACKUP_BASE}/${TIMESTAMP}"
LOG_FILE="${LOG_FILE:-/tmp/ampnm_update_${TIMESTAMP}.log}"
RESULT_ENV_FILE="${RESULT_ENV_FILE:-${AMPNM_RESULT_FILE:-/tmp/ampnm_update_result_$(whoami).env}}"

mkdir -p "$(dirname "${LOG_FILE}")"
exec > >(tee -a "${LOG_FILE}") 2>&1

# Ensure git safe directories
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

echo "[$(date -Iseconds)] Starting AMPNM Git Update"
echo "REPO_URL=${REPO_URL}"
echo "UPDATE_BRANCH=${UPDATE_BRANCH}"
echo "UPSTREAM_REF=${UPSTREAM_REF}"
echo "HOST_APP_DIR=${HOST_APP_DIR}"

# Step 1: Backup
if ! mkdir -p "${BACKUP_DIR}" 2>/dev/null; then
  BACKUP_BASE="/tmp/ampnm_code_backups"
  BACKUP_DIR="${BACKUP_BASE}/${TIMESTAMP}"
  mkdir -p "${BACKUP_DIR}"
fi

echo "[$(date -Iseconds)] Step 1/4: Creating backup at ${BACKUP_DIR}"

if [ -d "${HOST_APP_DIR}" ]; then
  rsync -a --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='dist' \
    --exclude='data' \
    --exclude='storage' \
    --exclude='logs' \
    --exclude='uploads' \
    "${HOST_APP_DIR}/" "${BACKUP_DIR}/code/" 2>/dev/null || true

  if command -v git >/dev/null 2>&1 && [ -d "${HOST_APP_DIR}/.git" ]; then
    (cd "${HOST_APP_DIR}" && git rev-parse HEAD) > "${BACKUP_DIR}/previous_commit.txt" 2>/dev/null || true
  fi
fi

write_result "BACKUP_PATH" "${BACKUP_DIR}"

# Step 2: Code Sync
echo "[$(date -Iseconds)] Step 2/4: Syncing latest code from GitHub"
if [ -d "${HOST_APP_DIR}/.git" ]; then
  cd "${HOST_APP_DIR}"
  git fetch origin "${UPDATE_BRANCH}" --prune || git fetch origin --prune
  git reset --hard "origin/${UPDATE_BRANCH}" || git reset --hard HEAD
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
    --exclude='uploads' \
    "${TMP_CLONE_DIR}/" "${HOST_APP_DIR}/"
  rm -rf "${TMP_CLONE_DIR}"
  cd "${HOST_APP_DIR}"
fi

NEW_COMMIT=""
if command -v git >/dev/null 2>&1 && [ -d "${HOST_APP_DIR}/.git" ]; then
  NEW_COMMIT="$(cd "${HOST_APP_DIR}" && git rev-parse HEAD 2>/dev/null || true)"
fi

# Step 3: Run Database Migrations
echo "[$(date -Iseconds)] Step 3/4: Applying database migrations"
if [ -f "${HOST_APP_DIR}/database_setup.php" ]; then
  php "${HOST_APP_DIR}/database_setup.php" >/dev/null 2>&1 || true
fi

# Step 4: Permissions & Finalization
echo "[$(date -Iseconds)] Step 4/4: Setting permissions and finalizing"
mkdir -p "${HOST_APP_DIR}/uploads" "${HOST_APP_DIR}/storage/logs" "${HOST_APP_DIR}/data/code_backups" 2>/dev/null || true
chmod -R 775 "${HOST_APP_DIR}/uploads" "${HOST_APP_DIR}/storage" "${HOST_APP_DIR}/data" 2>/dev/null || true
chmod +x "${HOST_APP_DIR}/scripts/"*.sh "${HOST_APP_DIR}/scripts/"*.php 2>/dev/null || true

write_result "STATUS" "success"
write_result "TIMESTAMP" "${TIMESTAMP}"
write_result "NEW_COMMIT" "${NEW_COMMIT}"
write_result "LOG_FILE" "${LOG_FILE}"

echo "[$(date -Iseconds)] AMPNM update finished successfully (Commit: ${NEW_COMMIT:0:7})"
