#!/usr/bin/env bash
set -euo pipefail

export HOME=/tmp

REPO_PATH="${AMPNM_REPO_PATH:-/var/www/html}"
BRANCH="${AMPNM_UPDATE_BRANCH:-main}"
REPO_URL="${AMPNM_UPDATE_REPO_URL:-https://github.com/pranto48/ampnm.git}"
UPSTREAM_REF="origin/${BRANCH}"
STATE_FILE="${AMPNM_UPDATE_STATE_FILE:-/var/www/html/storage/update_state.json}"

mkdir -p "$(dirname "$STATE_FILE")"

# Configure git safe directory to avoid dubious ownership issues
if command -v git >/dev/null 2>&1; then
  git config --global --add safe.directory "${REPO_PATH}" || true
  git config --global --add safe.directory '*' || true
fi

now="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

if ! command -v git >/dev/null 2>&1; then
  printf '{"ok":false,"error":"git unavailable","checked_at":"%s"}\n' "$now" > "$STATE_FILE"
  exit 0
fi

if [ ! -d "$REPO_PATH/.git" ] && [ ! -f "$REPO_PATH/.git" ]; then
  printf '{"ok":false,"error":"repo missing .git","repo_path":%s,"checked_at":"%s"}\n' "$(printf '%s' "$REPO_PATH" | jq -Rsa . 2>/dev/null || printf '"%s"' "$REPO_PATH")" "$now" > "$STATE_FILE"
  exit 0
fi

fetch_output="$(cd "$REPO_PATH" && git fetch --all --prune 2>&1 || true)"
ahead_behind="$(cd "$REPO_PATH" && git rev-list --left-right --count "${UPSTREAM_REF}...HEAD" 2>/dev/null || true)"
behind=0
ahead=0
if [ -n "$ahead_behind" ]; then
  behind="$(printf '%s' "$ahead_behind" | awk '{print $1}')"
  ahead="$(printf '%s' "$ahead_behind" | awk '{print $2}')"
fi

if command -v jq >/dev/null 2>&1; then
  jq -n --arg checked_at "$now" --arg repo "$REPO_PATH" --arg repo_url "$REPO_URL" --arg branch "$BRANCH" --arg upstream_ref "$UPSTREAM_REF" --arg fetch "$fetch_output" --argjson behind "${behind:-0}" --argjson ahead "${ahead:-0}" '{ok:true,checked_at:$checked_at,repo_path:$repo,repo_url:$repo_url,branch:$branch,upstream_ref:$upstream_ref,behind_count:$behind,ahead_count:$ahead,update_available:($behind>0),last_fetch_output:$fetch}' > "$STATE_FILE"
else
  printf '{"ok":true,"checked_at":"%s","repo_path":"%s","repo_url":"%s","branch":"%s","upstream_ref":"%s","behind_count":%s,"ahead_count":%s,"update_available":%s}\n' "$now" "$REPO_PATH" "$REPO_URL" "$BRANCH" "$UPSTREAM_REF" "${behind:-0}" "${ahead:-0}" "$([ "${behind:-0}" -gt 0 ] && echo true || echo false)" > "$STATE_FILE"
fi
