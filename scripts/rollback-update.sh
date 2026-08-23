#!/usr/bin/env bash
#
# Copyright (c) IT Support BD. All rights reserved.
# This file is part of AMPNM.
# 
# AMPNM Safe Auto-Rollback Engine
#
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$PROJECT_DIR/.update-state"

cd "$PROJECT_DIR"

TARGET_ENV="${1:-}"
if [ -z "$TARGET_ENV" ]; then
  TARGET_ENV="$(find "$STATE_DIR" -name rollback.env 2>/dev/null | sort | tail -n1 || true)"
fi

if [ -z "$TARGET_ENV" ] || [ ! -f "$TARGET_ENV" ]; then
  echo "No rollback metadata found. Reverting to HEAD@{1} as safety fallback..." >&2
  if [ -d "$PROJECT_DIR/.git" ]; then
    git reset --hard HEAD@{1} || true
  fi
  exit 0
fi

# shellcheck source=/dev/null
source "$TARGET_ENV"

echo "=== Rolling back to pre-update state ($TIMESTAMP) ==="

# 1. Revert Git Commit if tracked
if [ -n "${PRE_COMMIT:-}" ] && [ "$PRE_COMMIT" != "unknown" ] && [ -d "$PROJECT_DIR/.git" ]; then
  echo "Reverting repository to commit: $PRE_COMMIT"
  git reset --hard "$PRE_COMMIT"
fi

# 2. Revert Docker Images if image IDs were recorded
if [ -n "${APP_IMAGE_ID:-}" ] && [ -n "${DB_IMAGE_ID:-}" ]; then
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
  elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
  else
    COMPOSE=()
  fi

  if [ ${#COMPOSE[@]} -gt 0 ]; then
    APP_TAG="ampnm-app:rollback-${TIMESTAMP}"
    DB_TAG="mysql:rollback-${TIMESTAMP}"

    docker tag "$APP_IMAGE_ID" "$APP_TAG" 2>/dev/null || true
    docker tag "$DB_IMAGE_ID" "$DB_TAG" 2>/dev/null || true

    cat > docker-compose.rollback.yml <<YAML
services:
  app:
    image: $APP_TAG
  db:
    image: $DB_TAG
YAML

    "${COMPOSE[@]}" -f docker-compose.yml -f docker-compose.rollback.yml up -d --remove-orphans || true
    rm -f docker-compose.rollback.yml
  fi
fi

# 3. Restart container stack
if command -v docker >/dev/null 2>&1; then
  docker restart ampnm_server 2>/dev/null || true
fi

echo "SUCCESS: Rollback successfully applied using metadata: $TARGET_ENV"

