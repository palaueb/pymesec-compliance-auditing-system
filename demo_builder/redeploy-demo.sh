#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEPLOY_SCRIPT="$REPO_ROOT/demo_builder/deploy-demo.sh"

APP_URL="${APP_URL:-https://demo.pimesec.com}"
WEB_ROOT="${WEB_ROOT:-/home/pimesec.com/deploy/pymesec-compliance-auditing-system-demo}"

usage() {
    cat <<'EOF'
Usage:
  ./demo_builder/redeploy-demo.sh [deploy-demo options]

What it does:
  1. git fetch origin
  2. git reset --hard origin/main
  3. git clean -fd
  4. git pull --ff-only origin main
  5. chmod +x ./demo_builder/deploy-demo.sh
  6. run ./demo_builder/deploy-demo.sh

Defaults:
  --app-url  https://demo.pimesec.com
  --web-root /home/pimesec.com/deploy/pymesec-compliance-auditing-system-demo

Any extra options are forwarded to deploy-demo.sh.
Options passed explicitly here override the defaults because they are appended last.
EOF
}

log() {
    echo "redeploy-demo: $*"
}

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || {
        echo "redeploy-demo: required command [$1] is not available." >&2
        exit 1
    }
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

require_cmd git

[[ -x "$DEPLOY_SCRIPT" || -f "$DEPLOY_SCRIPT" ]] || {
    echo "redeploy-demo: missing deploy script [$DEPLOY_SCRIPT]." >&2
    exit 1
}

log "fetching origin"
git -C "$REPO_ROOT" fetch origin

log "resetting checkout to origin/main"
git -C "$REPO_ROOT" reset --hard origin/main

log "cleaning untracked files"
git -C "$REPO_ROOT" clean -fd

log "pulling latest main"
git -C "$REPO_ROOT" pull --ff-only origin main

log "ensuring deploy script is executable"
chmod +x "$DEPLOY_SCRIPT"

log "running demo deploy"
"$DEPLOY_SCRIPT" --app-url "$APP_URL" --web-root "$WEB_ROOT" "$@"
