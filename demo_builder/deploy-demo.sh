#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CORE_DIR="$REPO_ROOT/core"
PATCH_DIR="$REPO_ROOT/demo_builder/patches"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
APP_URL="${APP_URL:-https://demo.pimesec.com}"
WEB_ROOT="${WEB_ROOT:-}"
WEB_PREFIX="${WEB_PREFIX:-}"
SKIP_COMPOSER=0

usage() {
    cat <<'EOF'
Usage:
  demo_builder/deploy-demo.sh [options]

Options:
  --app-url URL          Public URL for the demo installation.
  --web-root PATH        Absolute project root as seen by the web server.
  --web-prefix PREFIX    Prefix added to the SSH-visible repo root to derive the web-visible root.
  --php-bin BIN          PHP binary to use. Defaults to php.
  --composer-bin BIN     Composer binary to use. Defaults to composer.
  --skip-composer        Skip composer install.
  -h, --help             Show this help.

Notes:
  - Run this script from the uploaded project checkout on the server.
  - The script applies the demo patch pack, prepares core/.env, installs dependencies,
    and builds the demo SQLite template.
  - If SSH is jailed and the web server sees a different absolute path, pass either
    --web-root or --web-prefix so the script can print the correct DocumentRoot.
EOF
}

fail() {
    echo "deploy-demo: $*" >&2
    exit 1
}

log() {
    echo "deploy-demo: $*"
}

relative_to_repo() {
    local path="$1"
    case "$path" in
        "$REPO_ROOT"/*)
            printf '%s\n' "${path#"$REPO_ROOT"/}"
            ;;
        *)
            printf '%s\n' "$path"
            ;;
    esac
}

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || fail "required command [$1] is not available."
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --app-url)
                APP_URL="${2:-}"
                shift 2
                ;;
            --web-root)
                WEB_ROOT="${2:-}"
                shift 2
                ;;
            --web-prefix)
                WEB_PREFIX="${2:-}"
                shift 2
                ;;
            --php-bin)
                PHP_BIN="${2:-}"
                shift 2
                ;;
            --composer-bin)
                COMPOSER_BIN="${2:-}"
                shift 2
                ;;
            --skip-composer)
                SKIP_COMPOSER=1
                shift
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                fail "unknown option [$1]."
                ;;
        esac
    done
}

resolve_web_root() {
    if [[ -n "$WEB_ROOT" ]]; then
        printf '%s\n' "$WEB_ROOT"
        return 0
    fi

    if [[ -n "$WEB_PREFIX" ]]; then
        printf '%s%s\n' "$WEB_PREFIX" "$REPO_ROOT"
        return 0
    fi

    printf '%s\n' "$REPO_ROOT"
}

apply_patch_file() {
    local patch_file="$1"

    if git -C "$REPO_ROOT" apply --check "$patch_file" >/dev/null 2>&1; then
        git -C "$REPO_ROOT" apply "$patch_file"
        log "applied $(relative_to_repo "$patch_file")"
        return 0
    fi

    if git -C "$REPO_ROOT" apply --check --reverse "$patch_file" >/dev/null 2>&1; then
        log "already applied $(relative_to_repo "$patch_file")"
        return 0
    fi

    if git -C "$REPO_ROOT" apply --3way "$patch_file" >/dev/null 2>&1; then
        log "applied with 3-way merge $(relative_to_repo "$patch_file")"
        return 0
    fi

    fail "patch cannot be applied cleanly: $patch_file"
}

apply_demo_patches() {
    [[ -d "$PATCH_DIR" ]] || fail "missing patch directory [$PATCH_DIR]."

    local patch_list
    local patch_file
    local found=0
    patch_list="$(mktemp)"

    find "$PATCH_DIR" -type f -name '*.patch' | LC_ALL=C sort >"$patch_list"

    if [[ ! -s "$patch_list" ]]; then
        rm -f "$patch_list"
        fail "no patch files found under [$PATCH_DIR]."
    fi

    while IFS= read -r patch_file; do
        [[ -z "$patch_file" ]] && continue
        apply_patch_file "$patch_file"
        found=1
    done <"$patch_list"

    rm -f "$patch_list"

    (( found == 1 )) || fail "no patch files found under [$PATCH_DIR]."
}

ensure_no_merge_conflicts() {
    local scan_roots=()
    local conflict_files_raw
    local conflict_files

    for candidate in \
        "$CORE_DIR/app" \
        "$CORE_DIR/bootstrap" \
        "$CORE_DIR/config" \
        "$CORE_DIR/database" \
        "$CORE_DIR/routes" \
        "$REPO_ROOT/plugins"; do
        [[ -d "$candidate" ]] && scan_roots+=("$candidate")
    done

    if [[ ${#scan_roots[@]} -eq 0 ]]; then
        return 0
    fi

    conflict_files_raw="$(grep -R -l -E '^(<<<<<<<|=======|>>>>>>>)' "${scan_roots[@]}" 2>/dev/null || true)"

    if [[ -z "$conflict_files_raw" ]]; then
        return 0
    fi

    conflict_files=""
    while IFS= read -r file; do
        [[ -z "$file" ]] && continue
        if [[ -n "$conflict_files" ]]; then
            conflict_files+=", "
        fi
        conflict_files+="$file"
    done <<<"$conflict_files_raw"

    fail "merge conflict markers detected after applying demo patches. Resolve or reset these files first: $conflict_files"
}

ensure_env_file() {
    if [[ ! -f "$CORE_DIR/.env" ]]; then
        cp "$CORE_DIR/.env.example" "$CORE_DIR/.env"
        log "created core/.env from .env.example"
    fi
}

set_env_value() {
    local file="$1"
    local key="$2"
    local value="$3"

    "$PHP_BIN" -r '
        $file = $argv[1];
        $key = $argv[2];
        $value = $argv[3];
        $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
        $updated = false;

        foreach ($lines as $index => $line) {
            if (preg_match("/^\s*".preg_quote($key, "/")."=/", $line) === 1) {
                $lines[$index] = $key."=".$value;
                $updated = true;
            }
        }

        if (! $updated) {
            $lines[] = $key."=".$value;
        }

        file_put_contents($file, implode(PHP_EOL, $lines).PHP_EOL);
    ' "$file" "$key" "$value"
}

configure_env() {
    local env_file="$CORE_DIR/.env"

    set_env_value "$env_file" "APP_ENV" "production"
    set_env_value "$env_file" "APP_DEBUG" "false"
    set_env_value "$env_file" "APP_URL" "$APP_URL"
    set_env_value "$env_file" "DEMO_MODE" "true"
    set_env_value "$env_file" "DB_CONNECTION" "demo_template"
    set_env_value "$env_file" "PLUGIN_STATE_PATH" "app/private/plugin-state.demo.json"
    set_env_value "$env_file" "SESSION_DRIVER" "file"
    set_env_value "$env_file" "CACHE_STORE" "file"
    set_env_value "$env_file" "QUEUE_CONNECTION" "sync"
    set_env_value "$env_file" "MAIL_MAILER" "log"
    set_env_value "$env_file" "PLUGINS_ENABLED" "hello-world,asset-catalog,actor-directory,controls-catalog,risk-management,questionnaires,collaboration,third-party-risk,findings-remediation,policy-exceptions,data-flows-privacy,continuity-bcm,automation-catalog,assessments-audits,evidence-management,identity-local"
    set_env_value "$env_file" "DEMO_LOGIN_USERNAME" "admin"
    set_env_value "$env_file" "DEMO_LOGIN_PASSWORD" "demo"
    set_env_value "$env_file" "DEMO_LOGIN_PRINCIPAL_ID" "principal-admin"
    set_env_value "$env_file" "DEMO_LOGIN_ORGANIZATION_ID" "org-a"

    log "configured core/.env for demo deployment"
}

install_dependencies() {
    if (( SKIP_COMPOSER == 1 )); then
        log "skipping composer install"
        return 0
    fi

    require_cmd "$COMPOSER_BIN"

    (
        cd "$CORE_DIR"
        "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
    )
}

prepare_storage() {
    mkdir -p \
        "$CORE_DIR/storage/app/demo" \
        "$CORE_DIR/storage/app/demo/sessions" \
        "$CORE_DIR/storage/app/private" \
        "$CORE_DIR/storage/framework/cache" \
        "$CORE_DIR/storage/framework/sessions" \
        "$CORE_DIR/storage/framework/views" \
        "$CORE_DIR/storage/logs" \
        "$CORE_DIR/bootstrap/cache"

    log "prepared writable storage directories"
}

run_artisan() {
    (
        cd "$CORE_DIR"
        "$PHP_BIN" artisan "$@"
    )
}

build_demo_template() {
    run_artisan key:generate --force
    run_artisan optimize:clear
    run_artisan demo:build-template
}

print_summary() {
    local web_project_root
    local web_document_root

    web_project_root="$(resolve_web_root)"
    web_document_root="$web_project_root/core/public"

    cat <<EOF

Demo deployment ready.

SSH-visible project root:
  $REPO_ROOT

Web-server-visible project root:
  $web_project_root

DocumentRoot / public path:
  $web_document_root

Public URL:
  $APP_URL

Demo login:
  admin / demo

Next checks:
  1. Point the hosting vhost or panel document root to: $web_document_root
  2. Open $APP_URL
  3. Sign in with admin / demo
EOF
}

main() {
    parse_args "$@"

    require_cmd "$PHP_BIN"
    require_cmd git
    [[ -d "$CORE_DIR" ]] || fail "missing core directory [$CORE_DIR]."

    log "applying demo patch pack"
    apply_demo_patches
    ensure_no_merge_conflicts

    log "preparing environment"
    ensure_env_file
    configure_env
    prepare_storage

    log "installing dependencies"
    install_dependencies

    log "building demo template"
    build_demo_template

    print_summary
}

main "$@"
