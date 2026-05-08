#!/bin/bash
set -euo pipefail

TARGET_DIR="${TARGET_DIR:-/var/www/html}"
REPO_ZIP_URL="${REPO_ZIP_URL:-https://github.com/pranto48/ampnm/archive/refs/heads/main.zip}"
SUBDIR_PATH="${SUBDIR_PATH:-docker-ampnm}"
BACKUP_BASE="${BACKUP_BASE:-/var/www/html/docker-ampnm/data/code_backups}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_BASE}/${TIMESTAMP}"
LOG_FILE="${LOG_FILE:-/tmp/ampnm_direct_update_${TIMESTAMP}.log}"
RESULT_ENV_FILE="${RESULT_ENV_FILE:-/tmp/ampnm_direct_update_result.env}"
REMOTE_COMMIT_API="${REMOTE_COMMIT_API:-https://api.github.com/repos/pranto48/ampnm/commits/main}"

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
REMOTE_COMMIT="$(curl -fsSL -H 'User-Agent: AMPNM-Updater' "$REMOTE_COMMIT_API" 2>/dev/null | sed -n 's/.*"sha": "\([a-f0-9]\{40\}\)".*/\1/p' | head -n1 || true)"
unzip -q "$TMP_DIR/repo.zip" -d "$TMP_DIR"
EXTRACTED_ROOT="$(find "$TMP_DIR" -maxdepth 1 -type d -name 'ampnm-*' | head -n1)"
[ -n "$EXTRACTED_ROOT" ] || fail "Could not find extracted repository root"
SOURCE_DIR="$EXTRACTED_ROOT/$SUBDIR_PATH"
[ -d "$SOURCE_DIR" ] || fail "Could not find source subdir: $SOURCE_DIR"

rsync -a --delete --exclude='data' --exclude='storage' --exclude='logs' "$SOURCE_DIR/" "$TARGET_DIR/"
mkdir -p "$TARGET_DIR/storage"
[ -n "$REMOTE_COMMIT" ] && printf '%s\n' "$REMOTE_COMMIT" > "$TARGET_DIR/storage/direct_update_commit.txt"

cd "$TARGET_DIR"
if [ -f docker-compose.yml ] || [ -f compose.yml ]; then
  if docker compose version >/dev/null 2>&1; then docker compose restart; else docker-compose restart; fi
fi

write_result STATUS success
write_result TIMESTAMP "$TIMESTAMP"
write_result NEW_COMMIT "$REMOTE_COMMIT"
write_result LOG_FILE "$LOG_FILE"
echo "Direct update completed successfully"
