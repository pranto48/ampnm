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
# TARGET_DIR is the mounted repository root receiving synced files.
# Example mapping after restructure: host /var/www/html -> container /var/www/html.
TARGET_DIR="${TARGET_DIR:-/var/www/html}"
REPO_ZIP_URL="${REPO_ZIP_URL:-https://github.com/pranto48/ampnm/archive/refs/heads/main.zip}"
# SUBDIR_PATH is relative to extracted zip root; "." means sync from repo root
# (no docker-ampnm nested subdirectory expected after restructure).
SUBDIR_PATH="${SUBDIR_PATH:-.}"
# BACKUP_BASE stores snapshots in the root-level data path.
BACKUP_BASE="${BACKUP_BASE:-/var/www/html/data/code_backups}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_BASE}/${TIMESTAMP}"
LOG_FILE="${LOG_FILE:-/tmp/ampnm_direct_update_${TIMESTAMP}.log}"
RESULT_ENV_FILE="${RESULT_ENV_FILE:-/tmp/ampnm_direct_update_result.env}"

mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$LOG_FILE") 2>&1

write_result(){ printf '%s=%s\n' "$1" "$2" >> "$RESULT_ENV_FILE"; }
fail(){ write_result STATUS failed; write_result ERROR "$1"; write_result LOG_FILE "$LOG_FILE"; echo "$1"; exit 1; }
: > "$RESULT_ENV_FILE"

[ -d "$TARGET_DIR" ] || fail "Target directory does not exist: $TARGET_DIR"
mkdir -p "$BACKUP_DIR"
rsync -a --delete --exclude='data' --exclude='storage' --exclude='logs' "$TARGET_DIR/" "$BACKUP_DIR/code/" || true
write_result BACKUP_PATH "$BACKUP_DIR"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

curl -fsSL "$REPO_ZIP_URL" -o "$TMP_DIR/repo.zip"
unzip -q "$TMP_DIR/repo.zip" -d "$TMP_DIR"
EXTRACTED_ROOT="$(find "$TMP_DIR" -maxdepth 1 -type d -name 'ampnm-*' | head -n1)"
[ -n "$EXTRACTED_ROOT" ] || fail "Could not find extracted repository root"
SOURCE_DIR="$EXTRACTED_ROOT/$SUBDIR_PATH"
[ -d "$SOURCE_DIR" ] || fail "Could not find source subdir: $SOURCE_DIR"

rsync -a --delete --exclude='data' --exclude='storage' --exclude='logs' "$SOURCE_DIR/" "$TARGET_DIR/"

cd "$TARGET_DIR"
if [ -f docker-compose.yml ] || [ -f compose.yml ]; then
  if docker compose version >/dev/null 2>&1; then docker compose restart; else docker-compose restart; fi
fi

write_result STATUS success
write_result TIMESTAMP "$TIMESTAMP"
write_result LOG_FILE "$LOG_FILE"
echo "Direct update completed successfully"
