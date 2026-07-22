#!/bin/bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License...
# (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#
set -euo pipefail

#
# APP_DIR is the mounted repository root inside the container where compose commands run.
# Example mapping after restructure: host /var/www/html -> container /var/www/html.
APP_DIR="${APP_DIR:-/var/www/html}"
# HOST_APP_DIR is the host-synced project root to restore into (same as APP_DIR by default).
HOST_APP_DIR="${HOST_APP_DIR:-${APP_DIR}}"
BACKUP_PATH="${BACKUP_PATH:-}"
LOG_FILE="${LOG_FILE:-/tmp/ampnm_restore_$(date +%Y%m%d_%H%M%S).log}"
RESULT_ENV_FILE="${RESULT_ENV_FILE:-/tmp/ampnm_restore_result.env}"

mkdir -p "$(dirname "${LOG_FILE}")"
exec > >(tee -a "${LOG_FILE}") 2>&1

write_result() {
  local key="$1"
  local value="$2"
  printf '%s=%s\n' "${key}" "${value}" >> "${RESULT_ENV_FILE}"
}

fail() {
  local message="$1"
  write_result "STATUS" "failed"
  write_result "ERROR" "${message}"
  write_result "LOG_FILE" "${LOG_FILE}"
  echo "${message}"
  exit 1
}

: > "${RESULT_ENV_FILE}"

[ -n "${BACKUP_PATH}" ] || fail "BACKUP_PATH is required."
[ -d "${BACKUP_PATH}" ] || fail "Backup path does not exist: ${BACKUP_PATH}"
[ -d "${BACKUP_PATH}/code" ] || fail "Backup path missing code/ folder: ${BACKUP_PATH}/code"
[ -d "${HOST_APP_DIR}" ] || fail "HOST_APP_DIR does not exist: ${HOST_APP_DIR}"

echo "Restoring backup from ${BACKUP_PATH}/code to ${HOST_APP_DIR}"
rsync -a --delete "${BACKUP_PATH}/code/" "${HOST_APP_DIR}/"

RESTORED_COMMIT=""
if [ -f "${BACKUP_PATH}/previous_commit.txt" ]; then
  RESTORED_COMMIT="$(tr -d '\r\n' < "${BACKUP_PATH}/previous_commit.txt")"
fi

echo "Restarting services"
cd "${APP_DIR}"
if docker compose version >/dev/null 2>&1; then
  docker compose restart
else
  docker-compose restart
fi

write_result "STATUS" "success"
write_result "RESTORED_COMMIT" "${RESTORED_COMMIT}"
write_result "BACKUP_PATH" "${BACKUP_PATH}"
write_result "LOG_FILE" "${LOG_FILE}"
echo "Restore completed successfully"
