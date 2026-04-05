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
DEMO_AUTOMATION_REPOSITORY_LABEL="${DEMO_AUTOMATION_REPOSITORY_LABEL:-PymeSec Official Repository}"
DEMO_AUTOMATION_REPOSITORY_URL="${DEMO_AUTOMATION_REPOSITORY_URL:-https://repository.pimesec.com/repository.json}"
DEMO_AUTOMATION_REPOSITORY_SIGN_URL="${DEMO_AUTOMATION_REPOSITORY_SIGN_URL:-}"
DEMO_AUTOMATION_REPOSITORY_TRUST_TIER="${DEMO_AUTOMATION_REPOSITORY_TRUST_TIER:-trusted-first-party}"
DEMO_AUTOMATION_REPOSITORY_ORGANIZATION_ID="${DEMO_AUTOMATION_REPOSITORY_ORGANIZATION_ID:-org-a}"
DEMO_AUTOMATION_REPOSITORY_SCOPE_ID="${DEMO_AUTOMATION_REPOSITORY_SCOPE_ID:-}"
DEMO_AUTOMATION_REPOSITORY_OWNER_PRINCIPAL_ID="${DEMO_AUTOMATION_REPOSITORY_OWNER_PRINCIPAL_ID:-principal-org-a}"
DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH="${DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH:-$CORE_DIR/storage/app/demo/repository-public.pem}"
SKIP_COMPOSER=0

usage() {
    cat <<'EOF'
Usage:
  demo_builder/deploy-demo.sh [options]

Options:
  --app-url URL          Public URL for the demo installation.
  --web-root PATH        Absolute project root as seen by the web server.
  --web-prefix PREFIX    Prefix added to the SSH-visible repo root to derive the web-visible root.
  --demo-pack-repo-url URL
                         External automation repository index URL.
  --demo-pack-repo-sign-url URL
                         Signature URL for the external repository index.
  --demo-pack-repo-label LABEL
                         Repository label shown in the demo.
  --demo-pack-repo-trust-tier TIER
                         Trust tier for the seeded repository.
  --demo-pack-repo-public-key-path PATH
                         Path where the demo repository public key is written/read.
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
            --demo-pack-repo-url)
                DEMO_AUTOMATION_REPOSITORY_URL="${2:-}"
                shift 2
                ;;
            --demo-pack-repo-sign-url)
                DEMO_AUTOMATION_REPOSITORY_SIGN_URL="${2:-}"
                shift 2
                ;;
            --demo-pack-repo-label)
                DEMO_AUTOMATION_REPOSITORY_LABEL="${2:-}"
                shift 2
                ;;
            --demo-pack-repo-trust-tier)
                DEMO_AUTOMATION_REPOSITORY_TRUST_TIER="${2:-}"
                shift 2
                ;;
            --demo-pack-repo-public-key-path)
                DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH="${2:-}"
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

resolve_repository_sign_url() {
    if [[ -n "$DEMO_AUTOMATION_REPOSITORY_SIGN_URL" ]]; then
        printf '%s\n' "$DEMO_AUTOMATION_REPOSITORY_SIGN_URL"
        return 0
    fi

    printf '%s.sign\n' "$DEMO_AUTOMATION_REPOSITORY_URL"
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
        $encodedValue = "\"".addcslashes($value, "\\\"\n\r\t")."\"";

        foreach ($lines as $index => $line) {
            if (preg_match("/^\s*".preg_quote($key, "/")."=/", $line) === 1) {
                $lines[$index] = $key."=".$encodedValue;
                $updated = true;
            }
        }

        if (! $updated) {
            $lines[] = $key."=".$encodedValue;
        }

        file_put_contents($file, implode(PHP_EOL, $lines).PHP_EOL);
    ' "$file" "$key" "$value"
}

configure_env() {
    local env_file="$CORE_DIR/.env"
    local repository_sign_url
    repository_sign_url="$(resolve_repository_sign_url)"

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
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_LABEL" "$DEMO_AUTOMATION_REPOSITORY_LABEL"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_URL" "$DEMO_AUTOMATION_REPOSITORY_URL"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_SIGN_URL" "$repository_sign_url"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_TRUST_TIER" "$DEMO_AUTOMATION_REPOSITORY_TRUST_TIER"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_ORGANIZATION_ID" "$DEMO_AUTOMATION_REPOSITORY_ORGANIZATION_ID"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_SCOPE_ID" "$DEMO_AUTOMATION_REPOSITORY_SCOPE_ID"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_OWNER_PRINCIPAL_ID" "$DEMO_AUTOMATION_REPOSITORY_OWNER_PRINCIPAL_ID"
    set_env_value "$env_file" "DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH" "$DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH"

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

write_demo_repository_public_key() {
    local public_key_path="$DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH"
    local public_key_dir
    public_key_dir="$(dirname "$public_key_path")"

    mkdir -p "$public_key_dir"

cat >"$public_key_path" <<'EOF'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwfsOim5JGN+PpVjZDPdZ
q35+S46276kE8RwT7eJz6zfZ3TrgDAIbX2h02AFK8HY2k8g0xm9IDPvjwDOAmGhU
GmOzStg3DLmxxlTvmX32UPFfc8vbNmbsggW+wLwh1lUGAYy6WJOfxg8JslpbVc3p
7xKsAgn68+ww3Lr9gvM0moHN3xzj+0JbqzVxEFzAgpR+BUgpiONLhauWBXOJ1AuO
fgyUk0hPQ/CskW2I+P5keqeJ66DqxwFH+G+VqnNhS0X8z61xVVBXWj96+nkLYSYI
M0+lQA9E7TpLjy9Am3LQIif/77N3+tqGHmoaGyIFrBuPbHd2WGUStzMkyUhZESRO
d8qiRiWV6iUk2aeFfYPKB+sVPWfiN/Dp/kEEX+yKcI6OT6DtHEacH5H106fhDKXY
jGCUKIs2TenzSiPDzMjQuX9y/tjqgFDKkYtBPfLBl0t8KRLjEI0ezlfiKcNWwBCu
ZuzK4SnX6aH72Wv3IkzsofxvsKjPu0nkQQ1efz1lcdACoL68FkJhvAhftiSPOjBz
xrAg5o6UJUSkClx2AsNCctAgXxnNVB/inzFXlHa3AsIwB9qY9q/hGWR44jDxV97l
4fBdFQyJM6th9diU0h+2+pol0IN/7RtLPPlR2rrNdqka1mX+U7zbvQlLo+KIE0yf
PdIw9rMdRdW+UuCpagE9m48CAwEAAQ==
-----END PUBLIC KEY-----
EOF

    chmod 600 "$public_key_path"
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

configure_demo_automation_defaults() {
    local repository_sign_url
    repository_sign_url="$(resolve_repository_sign_url)"

    "$PHP_BIN" -r '
        $templateDatabase = $argv[1];
        $label = $argv[2];
        $repositoryUrl = $argv[3];
        $repositorySignUrl = $argv[4];
        $trustTier = $argv[5];
        $organizationId = $argv[6];
        $scopeId = $argv[7];
        $ownerPrincipalId = $argv[8];
        $publicKeyPath = $argv[9];

        if (! is_file($templateDatabase)) {
            fwrite(STDERR, "Template database not found.\n");
            exit(1);
        }

        if (! is_file($publicKeyPath)) {
            fwrite(STDERR, "Repository public key file not found.\n");
            exit(1);
        }

        $publicKeyPem = trim((string) file_get_contents($publicKeyPath));
        if ($publicKeyPem === "") {
            fwrite(STDERR, "Repository public key file is empty.\n");
            exit(1);
        }

        $pdo = new PDO("sqlite:".$templateDatabase);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tableExists = (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE type = '\''table'\'' AND name = '\''automation_pack_repositories'\''")->fetchColumn();
        if ($tableExists === 0) {
            exit(0);
        }

        $now = (new DateTimeImmutable("now"))->format("Y-m-d H:i:s");

        $pdo->beginTransaction();

        $deleteMappings = $pdo->prepare("DELETE FROM automation_pack_output_mappings WHERE organization_id = :organization_id");
        $deleteMappings->execute([
            ":organization_id" => $organizationId,
        ]);

        $deletePacks = $pdo->prepare("DELETE FROM automation_packs WHERE organization_id = :organization_id");
        $deletePacks->execute([
            ":organization_id" => $organizationId,
        ]);

        $existing = $pdo->prepare("SELECT id FROM automation_pack_repositories WHERE organization_id = :organization_id AND repository_url = :repository_url LIMIT 1");
        $existing->execute([
            ":organization_id" => $organizationId,
            ":repository_url" => $repositoryUrl,
        ]);

        $existingId = $existing->fetchColumn();

        if (is_string($existingId) && $existingId !== "") {
            $update = $pdo->prepare("
                UPDATE automation_pack_repositories
                SET scope_id = :scope_id,
                    label = :label,
                    repository_sign_url = :repository_sign_url,
                    public_key_pem = :public_key_pem,
                    trust_tier = :trust_tier,
                    is_enabled = 1,
                    last_refreshed_at = NULL,
                    last_status = '\''never'\'',
                    last_error = NULL,
                    updated_by_principal_id = :updated_by_principal_id,
                    updated_at = :updated_at
                WHERE id = :id
            ");

            $update->execute([
                ":id" => $existingId,
                ":scope_id" => $scopeId !== "" ? $scopeId : null,
                ":label" => $label,
                ":repository_sign_url" => $repositorySignUrl,
                ":public_key_pem" => $publicKeyPem,
                ":trust_tier" => $trustTier,
                ":updated_by_principal_id" => $ownerPrincipalId !== "" ? $ownerPrincipalId : null,
                ":updated_at" => $now,
            ]);
        } else {
            $id = "automation-pack-repository-official";

            $insert = $pdo->prepare("
                INSERT INTO automation_pack_repositories (
                    id,
                    organization_id,
                    scope_id,
                    label,
                    repository_url,
                    repository_sign_url,
                    public_key_pem,
                    trust_tier,
                    is_enabled,
                    last_refreshed_at,
                    last_status,
                    last_error,
                    created_by_principal_id,
                    updated_by_principal_id,
                    created_at,
                    updated_at
                ) VALUES (
                    :id,
                    :organization_id,
                    :scope_id,
                    :label,
                    :repository_url,
                    :repository_sign_url,
                    :public_key_pem,
                    :trust_tier,
                    1,
                    NULL,
                    '\''never'\'',
                    NULL,
                    :created_by_principal_id,
                    :updated_by_principal_id,
                    :created_at,
                    :updated_at
                )
            ");

            $insert->execute([
                ":id" => $id,
                ":organization_id" => $organizationId,
                ":scope_id" => $scopeId !== "" ? $scopeId : null,
                ":label" => $label,
                ":repository_url" => $repositoryUrl,
                ":repository_sign_url" => $repositorySignUrl,
                ":public_key_pem" => $publicKeyPem,
                ":trust_tier" => $trustTier,
                ":created_by_principal_id" => $ownerPrincipalId !== "" ? $ownerPrincipalId : null,
                ":updated_by_principal_id" => $ownerPrincipalId !== "" ? $ownerPrincipalId : null,
                ":created_at" => $now,
                ":updated_at" => $now,
            ]);
        }

        $pdo->commit();
    ' \
    "$CORE_DIR/storage/app/demo/template.sqlite" \
    "$DEMO_AUTOMATION_REPOSITORY_LABEL" \
    "$DEMO_AUTOMATION_REPOSITORY_URL" \
    "$repository_sign_url" \
    "$DEMO_AUTOMATION_REPOSITORY_TRUST_TIER" \
    "$DEMO_AUTOMATION_REPOSITORY_ORGANIZATION_ID" \
    "$DEMO_AUTOMATION_REPOSITORY_SCOPE_ID" \
    "$DEMO_AUTOMATION_REPOSITORY_OWNER_PRINCIPAL_ID" \
    "$DEMO_AUTOMATION_REPOSITORY_PUBLIC_KEY_PATH"
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
    write_demo_repository_public_key
    configure_env
    prepare_storage

    log "installing dependencies"
    install_dependencies

    log "building demo template"
    build_demo_template

    log "applying demo automation defaults"
    configure_demo_automation_defaults

    print_summary
}

main "$@"
