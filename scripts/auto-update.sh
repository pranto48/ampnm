#!/usr/bin/env bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# AMPNM Docker & Git Safe Auto-Update Script with Health-Check Gate & Auto-Rollback
#
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$PROJECT_DIR/.update-state"
TIMESTAMP="$(date -u +%Y%m%d_%H%M%SZ)"
RUN_DIR="$STATE_DIR/$TIMESTAMP"

mkdir -p "$RUN_DIR"
cd "$PROJECT_DIR"

echo "=== [1/6] Capturing Pre-Update State ==="
PRE_COMMIT="$(git rev-parse HEAD 2>/dev/null || echo "unknown")"
echo "Current Git Commit: $PRE_COMMIT" > "$RUN_DIR/pre-update-state.txt"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  COMPOSE=()
fi

if [ ${#COMPOSE[@]} -gt 0 ]; then
  "${COMPOSE[@]}" ps > "$RUN_DIR/pre-update-ps.txt" 2>/dev/null || true
  "${COMPOSE[@]}" images > "$RUN_DIR/pre-update-images.txt" 2>/dev/null || true
fi

cat > "$RUN_DIR/rollback.env" <<META
TIMESTAMP=$TIMESTAMP
PRE_COMMIT=$PRE_COMMIT
PROJECT_DIR=$PROJECT_DIR
META

echo "=== [2/6] Generating Pre-Update Database & Topology Backup ==="
if [ -f "$PROJECT_DIR/scripts/backup_network_topology.php" ]; then
  if command -v php >/dev/null 2>&1; then
    php "$PROJECT_DIR/scripts/backup_network_topology.php" || true
  elif [ ${#COMPOSE[@]} -gt 0 ]; then
    "${COMPOSE[@]}" exec -T app php /var/www/html/scripts/backup_network_topology.php 2>/dev/null || true
  fi
fi

echo "=== [3/6] Pulling Latest Source Code & Migrations ==="
if [ -d "$PROJECT_DIR/.git" ]; then
  git fetch origin main
  git reset --hard origin/main
fi

echo "=== [4/6] Applying Database Schema Migrations ==="
if command -v php >/dev/null 2>&1 && [ -f "$PROJECT_DIR/database_setup.php" ]; then
  php "$PROJECT_DIR/database_setup.php" || true
elif [ ${#COMPOSE[@]} -gt 0 ]; then
  "${COMPOSE[@]}" exec -T app php /var/www/html/database_setup.php 2>/dev/null || true
fi

echo "=== [5/6] Restarting Containers / Application Stack ==="
if [ ${#COMPOSE[@]} -gt 0 ]; then
  "${COMPOSE[@]}" up -d --remove-orphans || "${COMPOSE[@]}" restart app || true
fi

echo "=== [6/6] Executing Post-Update Health Check Gate ==="
HEALTH_CHECK_URL="${AMPNM_HEALTH_URL:-http://127.0.0.1:2266/login.php}"
MAX_RETRIES=10
RETRY_INTERVAL=3
HEALTHY=0

for ((i=1; i<=MAX_RETRIES; i++)); do
  HTTP_STATUS="$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_CHECK_URL" 2>/dev/null || echo "000")"
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

echo "SUCCESS: Update completed and verified healthy. Rollback metadata saved at: $RUN_DIR/rollback.env"


