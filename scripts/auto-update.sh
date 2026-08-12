#!/usr/bin/env bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License...
# (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
#
set -euo pipefail

# AMPNM Docker Auto Update Script
# - Creates rollback metadata
# - Optionally creates DB dump backup
# - Pulls latest images and recreates containers

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
STATE_DIR="$PROJECT_DIR/.update-state"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RUN_DIR="$STATE_DIR/$TIMESTAMP"

mkdir -p "$RUN_DIR"

cd "$PROJECT_DIR"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
else
  COMPOSE=(docker-compose)
fi

"${COMPOSE[@]}" ps > "$RUN_DIR/pre-update-ps.txt" || true
"${COMPOSE[@]}" images > "$RUN_DIR/pre-update-images.txt" || true

APP_IMAGE_BEFORE="$("${COMPOSE[@]}" images app --format json 2>/dev/null | head -n1 | sed -n 's/.*"ID":"\([^"]*\)".*/\1/p' || true)"
DB_IMAGE_BEFORE="$("${COMPOSE[@]}" images db --format json 2>/dev/null | head -n1 | sed -n 's/.*"ID":"\([^"]*\)".*/\1/p' || true)"

cat > "$RUN_DIR/rollback.env" <<META
TIMESTAMP=$TIMESTAMP
APP_IMAGE_ID=$APP_IMAGE_BEFORE
DB_IMAGE_ID=$DB_IMAGE_BEFORE
META

if [ "${AMPNM_BACKUP_DB:-1}" = "1" ]; then
  BACKUP_FILE="$RUN_DIR/db-backup.sql"
  "${COMPOSE[@]}" exec -T db sh -lc 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > "$BACKUP_FILE" || {
    echo "Warning: database backup failed; continuing update" >&2
  }
fi

echo "Pulling latest images..."
"${COMPOSE[@]}" pull

echo "Recreating containers..."
"${COMPOSE[@]}" up -d --remove-orphans

echo "Capturing post-update state..."
"${COMPOSE[@]}" ps > "$RUN_DIR/post-update-ps.txt" || true
"${COMPOSE[@]}" images > "$RUN_DIR/post-update-images.txt" || true

# Health Check & Auto-Rollback Mechanism
HEALTH_CHECK_URL="${AMPNM_HEALTH_URL:-http://localhost:2266/}"
MAX_RETRIES=12
RETRY_INTERVAL=5
HEALTHY=0

echo "Performing post-update health check on $HEALTH_CHECK_URL..."
for ((i=1; i<=MAX_RETRIES; i++)); do
  HTTP_STATUS="$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_CHECK_URL" || echo "000")"
  if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
    echo "Health check PASSED (HTTP $HTTP_STATUS) on attempt $i/$MAX_RETRIES."
    HEALTHY=1
    break
  fi
  echo "Health check attempt $i/$MAX_RETRIES failed (HTTP $HTTP_STATUS). Retrying in ${RETRY_INTERVAL}s..."
  sleep "$RETRY_INTERVAL"
done

if [ "$HEALTHY" -ne 1 ]; then
  echo "ERROR: Health check failed after $MAX_RETRIES attempts! Initiating automatic rollback..." >&2
  "$PROJECT_DIR/scripts/rollback-update.sh" "$RUN_DIR/rollback.env"
  echo "CRITICAL: Auto-update failed and system was rolled back to previous state." >&2
  exit 1
fi

echo "Update complete and verified healthy. Rollback metadata: $RUN_DIR/rollback.env"

