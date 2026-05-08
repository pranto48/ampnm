#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$PROJECT_DIR/.update-state"

cd "$PROJECT_DIR"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
else
  COMPOSE=(docker-compose)
fi

TARGET_ENV="${1:-}"
if [ -z "$TARGET_ENV" ]; then
  TARGET_ENV="$(find "$STATE_DIR" -name rollback.env 2>/dev/null | sort | tail -n1 || true)"
fi

if [ -z "$TARGET_ENV" ] || [ ! -f "$TARGET_ENV" ]; then
  echo "No rollback metadata found. Pass path to rollback.env." >&2
  exit 1
fi

# shellcheck source=/dev/null
source "$TARGET_ENV"

if [ -z "${APP_IMAGE_ID:-}" ] || [ -z "${DB_IMAGE_ID:-}" ]; then
  echo "Rollback metadata missing image IDs." >&2
  exit 1
fi

APP_TAG="ampnm-app:rollback-${TIMESTAMP}"
DB_TAG="mysql:rollback-${TIMESTAMP}"

docker tag "$APP_IMAGE_ID" "$APP_TAG"
docker tag "$DB_IMAGE_ID" "$DB_TAG"

cat > docker-compose.rollback.yml <<YAML
services:
  app:
    image: $APP_TAG
  db:
    image: $DB_TAG
YAML

"${COMPOSE[@]}" -f docker-compose.yml -f docker-compose.rollback.yml up -d --remove-orphans
rm -f docker-compose.rollback.yml

echo "Rollback applied using $TARGET_ENV"
