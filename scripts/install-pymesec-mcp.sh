#!/usr/bin/env bash

set -euo pipefail

SCRIPT_NAME="install-pymesec-mcp.sh"
SCRIPT_VERSION="0.1.0"
REPO_SLUG="${PYMESEC_INSTALLER_REPO:-palaueb/pymesec-compliance-auditing-system}"
SERVER_NAME="pymesec"
GITHUB_API_BASE="https://api.github.com/repos/${REPO_SLUG}"
GITHUB_DOWNLOAD_BASE="https://github.com/${REPO_SLUG}/releases/download"
USER_AGENT="PymeSec-MCP-Installer/${SCRIPT_VERSION}"
DEFAULT_INSTALL_ROOT="${HOME}/.local/share/pymesec/mcp"
CURRENT_DIR_INSTALL_ROOT="${PWD}/.pymesec-mcp"
METADATA_FILE_NAME=".pymesec-mcp-install.env"
PROMPT_FD=3
TMP_DIR=""

COLOR_RESET=""
COLOR_BLUE=""
COLOR_GREEN=""
COLOR_YELLOW=""
COLOR_RED=""
COLOR_BOLD=""

PLATFORM_FAMILY=""
PLATFORM_ARCH=""
ARCHIVE_EXT=""
RELEASE_BINARY_BASENAME=""
INSTALLED_BINARY_NAME=""

HAS_CODEX=0
HAS_CLAUDE=0
LATEST_RELEASE_TAG=""
EXISTING_INSTALL_ROOT=""
EXISTING_BINARY_PATH=""
EXISTING_RELEASE_TAG=""
EXISTING_API_BASE_URL=""
EXISTING_CLIENT_SELECTION=""

cleanup() {
    if [[ -n "${TMP_DIR}" && -d "${TMP_DIR}" ]]; then
        rm -rf "${TMP_DIR}"
    fi
}

trap cleanup EXIT

tty_printf() {
    if [[ -w /dev/tty ]]; then
        printf "%s" "$*" > /dev/tty
    else
        printf "%s" "$*" >&2
    fi
}

init_colors() {
    if [[ -t 1 ]] && command -v tput >/dev/null 2>&1; then
        COLOR_RESET="$(tput sgr0 || true)"
        COLOR_BLUE="$(tput setaf 4 || true)"
        COLOR_GREEN="$(tput setaf 2 || true)"
        COLOR_YELLOW="$(tput setaf 3 || true)"
        COLOR_RED="$(tput setaf 1 || true)"
        COLOR_BOLD="$(tput bold || true)"
    fi
}

log_line() {
    local prefix="$1"
    local color="$2"
    shift 2
    if [[ -w /dev/tty ]]; then
        printf "%b%s%b %s\n" "${color}" "${prefix}" "${COLOR_RESET}" "$*" > /dev/tty
    else
        printf "%b%s%b %s\n" "${color}" "${prefix}" "${COLOR_RESET}" "$*" >&2
    fi
}

info() {
    log_line "INFO" "${COLOR_BLUE}" "$@"
}

success() {
    log_line "OK" "${COLOR_GREEN}" "$@"
}

warn() {
    log_line "WARN" "${COLOR_YELLOW}" "$@"
}

error() {
    log_line "ERROR" "${COLOR_RED}" "$@" >&2
}

die() {
    error "$1"
    if [[ $# -ge 2 ]]; then
        printf "       %s\n" "$2" >&2
    fi
    exit 1
}

print_logo() {
    cat <<'EOF'
 ____                        _____
|  _ \ _   _ _ __ ___   ___ / ____|  ___  ___
| |_) | | | | '_ ` _ \ / _ \\___ \  / _ \/ __|
|  __/| |_| | | | | | |  __/____) ||  __/ (__
|_|    \__, |_| |_| |_|\___|_____/  \___|\___|
       |___/

PymeSec MCP local installer
EOF
    printf "\n"
}

usage() {
    cat <<EOF
Usage:
  ${SCRIPT_NAME} [install|doctor|uninstall]
  ${SCRIPT_NAME} --help

Interactive installer for the PymeSec MCP local binary and Codex/Claude CLI setup.

Typical remote execution:
  curl -fsSL https://raw.githubusercontent.com/${REPO_SLUG}/main/scripts/install-pymesec-mcp.sh | bash
EOF
}

require_tty() {
    if [[ ! -r /dev/tty ]]; then
        die "Interactive mode requires a TTY." "Run the installer from a terminal window instead of a detached shell."
    fi

    exec 3</dev/tty
}

prompt() {
    local message="$1"
    local default_value="${2:-}"
    local value=""

    while true; do
        if [[ -n "${default_value}" ]]; then
            printf "%s [%s]: " "${message}" "${default_value}" > /dev/tty
        else
            printf "%s: " "${message}" > /dev/tty
        fi

        IFS= read -r -u "${PROMPT_FD}" value || true

        if [[ -n "${value}" ]]; then
            printf "%s" "${value}"
            return 0
        fi

        if [[ -n "${default_value}" ]]; then
            printf "%s" "${default_value}"
            return 0
        fi

        warn "A value is required."
    done
}

prompt_secret() {
    local message="$1"
    local value=""

    while true; do
        printf "%s: " "${message}" > /dev/tty
        IFS= read -r -s -u "${PROMPT_FD}" value || true
        printf "\n" > /dev/tty

        if [[ -n "${value}" ]]; then
            printf "%s" "${value}"
            return 0
        fi

        warn "A value is required."
    done
}

confirm() {
    local message="$1"
    local default_answer="${2:-y}"
    local answer=""
    local prompt_suffix="[Y/n]"

    if [[ "${default_answer}" == "n" ]]; then
        prompt_suffix="[y/N]"
    fi

    while true; do
        printf "%s %s " "${message}" "${prompt_suffix}" > /dev/tty
        IFS= read -r -u "${PROMPT_FD}" answer || true
        answer="${answer:-${default_answer}}"

        case "${answer}" in
            y|Y|yes|YES)
                return 0
                ;;
            n|N|no|NO)
                return 1
                ;;
        esac

        warn "Please answer yes or no."
    done
}

menu_choose() {
    local title="$1"
    shift
    local -a options=("$@")
    local selection=""
    local index=1

    printf "\n%s%s%s\n" "${COLOR_BOLD}" "${title}" "${COLOR_RESET}" > /dev/tty
    for option in "${options[@]}"; do
        printf "  %d. %s\n" "${index}" "${option}" > /dev/tty
        index=$((index + 1))
    done

    while true; do
        printf "Choose an option [1-%d]: " "${#options[@]}" > /dev/tty
        IFS= read -r -u "${PROMPT_FD}" selection || true

        if [[ "${selection}" =~ ^[0-9]+$ ]] && (( selection >= 1 && selection <= ${#options[@]} )); then
            printf "%s" "${options[$((selection - 1))]}"
            return 0
        fi

        warn "Invalid selection."
    done
}

require_commands() {
    local missing=()
    local command_name=""

    for command_name in curl awk sed grep uname mktemp chmod rm mkdir cp find sort head tr; do
        if ! command -v "${command_name}" >/dev/null 2>&1; then
            missing+=("${command_name}")
        fi
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        die "Missing required commands: ${missing[*]}" "Install the missing tools and rerun the installer."
    fi
}

detect_clients() {
    HAS_CODEX=0
    HAS_CLAUDE=0

    if command -v codex >/dev/null 2>&1; then
        HAS_CODEX=1
    fi

    if command -v claude >/dev/null 2>&1; then
        HAS_CLAUDE=1
    fi
}

detect_platform() {
    local uname_s
    local uname_m

    uname_s="$(uname -s)"
    uname_m="$(uname -m)"

    case "${uname_s}" in
        Linux)
            PLATFORM_FAMILY="linux"
            ARCHIVE_EXT="tar.gz"
            ;;
        Darwin)
            PLATFORM_FAMILY="darwin"
            ARCHIVE_EXT="tar.gz"
            ;;
        MINGW*|MSYS*|CYGWIN*)
            PLATFORM_FAMILY="windows"
            ARCHIVE_EXT="zip"
            ;;
        *)
            die "Unsupported platform [${uname_s}]." "Use Linux, macOS, WSL, or Git Bash on Windows."
            ;;
    esac

    case "${uname_m}" in
        x86_64|amd64)
            PLATFORM_ARCH="amd64"
            ;;
        aarch64|arm64)
            PLATFORM_ARCH="arm64"
            ;;
        *)
            die "Unsupported CPU architecture [${uname_m}]." "Use amd64/x86_64 or arm64/aarch64."
            ;;
    esac

    RELEASE_BINARY_BASENAME="pymesec-mcp-${PLATFORM_FAMILY}-${PLATFORM_ARCH}"
    INSTALLED_BINARY_NAME="pymesec-mcp"

    if [[ "${PLATFORM_FAMILY}" == "windows" ]]; then
        INSTALLED_BINARY_NAME="${INSTALLED_BINARY_NAME}.exe"
    fi

    if [[ "${ARCHIVE_EXT}" == "zip" ]]; then
        command -v unzip >/dev/null 2>&1 || die "Missing [unzip]." "Install unzip to extract the Windows release archive."
    else
        command -v tar >/dev/null 2>&1 || die "Missing [tar]." "Install tar to extract the release archive."
    fi

    if ! command -v sha256sum >/dev/null 2>&1 && ! command -v shasum >/dev/null 2>&1; then
        die "Missing checksum tool." "Install sha256sum (or shasum) to verify downloaded assets."
    fi
}

load_existing_metadata() {
    local candidate_paths=()
    local metadata_path=""

    candidate_paths+=("${DEFAULT_INSTALL_ROOT}/${METADATA_FILE_NAME}")
    candidate_paths+=("${CURRENT_DIR_INSTALL_ROOT}/${METADATA_FILE_NAME}")

    for metadata_path in "${candidate_paths[@]}"; do
        if [[ -f "${metadata_path}" ]]; then
            # shellcheck disable=SC1090
            source "${metadata_path}"
            EXISTING_INSTALL_ROOT="${INSTALL_ROOT:-}"
            EXISTING_BINARY_PATH="${BINARY_PATH:-}"
            EXISTING_RELEASE_TAG="${RELEASE_TAG:-}"
            EXISTING_API_BASE_URL="${API_BASE_URL:-}"
            EXISTING_CLIENT_SELECTION="${CLIENT_SELECTION:-}"
            return 0
        fi
    done
}

api_get() {
    local path="$1"

    curl -fsSL \
        -H "Accept: application/vnd.github+json" \
        -H "User-Agent: ${USER_AGENT}" \
        "${GITHUB_API_BASE}${path}"
}

fetch_latest_release_tag() {
    local latest_payload
    latest_payload="$(api_get "/releases/latest")" || return 1
    printf "%s\n" "${latest_payload}" | awk -F'"' '/"tag_name":/ {print $4; exit}'
}

fetch_recent_release_tags() {
    local releases_payload
    releases_payload="$(api_get "/releases?per_page=8")" || return 1
    printf "%s\n" "${releases_payload}" | awk -F'"' '/"tag_name":/ {print $4}' | awk '!seen[$0]++'
}

choose_release_tag() {
    local chosen=""
    local -a recent_tags=()
    local selection=""
    local custom_tag=""

    if ! LATEST_RELEASE_TAG="$(fetch_latest_release_tag)"; then
        LATEST_RELEASE_TAG=""
        warn "Could not query GitHub for the latest release."
    fi

    if mapfile -t recent_tags < <(fetch_recent_release_tags 2>/dev/null); then
        :
    fi

    if [[ -n "${LATEST_RELEASE_TAG}" ]]; then
        info "Latest release on GitHub: ${LATEST_RELEASE_TAG}"
    fi

    if [[ ${#recent_tags[@]} -gt 0 ]]; then
        printf "Recent releases:\n" > /dev/tty
        local tag=""
        for tag in "${recent_tags[@]}"; do
            printf "  - %s\n" "${tag}" > /dev/tty
        done
    fi

    if [[ -n "${LATEST_RELEASE_TAG}" ]] && confirm "Install the latest release?" "y"; then
        printf "%s" "${LATEST_RELEASE_TAG}"
        return 0
    fi

    while true; do
        custom_tag="$(prompt "Enter the release tag to install (example: v0.3.1)" "${LATEST_RELEASE_TAG}")"
        if [[ "${custom_tag}" =~ ^v[0-9]+(\.[0-9]+){2}$ ]]; then
            printf "%s" "${custom_tag}"
            return 0
        fi
        warn "Release tags must look like v0.3.1."
    done
}

choose_install_root() {
    local recommendation="${DEFAULT_INSTALL_ROOT}"
    local current_dir_option="${CURRENT_DIR_INSTALL_ROOT}"
    local selection=""
    local custom_path=""
    local -a menu_options=()

    if [[ -n "${EXISTING_INSTALL_ROOT}" ]]; then
        menu_options+=("Reuse existing install directory (${EXISTING_INSTALL_ROOT})")
    fi
    menu_options+=("Recommended user directory (${recommendation})")
    menu_options+=("Current working directory (${current_dir_option})")
    menu_options+=("Choose another directory")

    selection="$(menu_choose "Where should PymeSec MCP be installed?" "${menu_options[@]}")"

    case "${selection}" in
        "Reuse existing install directory ("*)
            printf "%s" "${EXISTING_INSTALL_ROOT}"
            ;;
        "Recommended user directory ("*)
            printf "%s" "${recommendation}"
            ;;
        "Current working directory ("*)
            printf "%s" "${current_dir_option}"
            ;;
        *)
            while true; do
                custom_path="$(prompt "Enter the install directory path")"
                custom_path="$(expand_path "${custom_path}")"
                if [[ -n "${custom_path}" ]]; then
                    printf "%s" "${custom_path}"
                    return 0
                fi
            done
            ;;
    esac
}

expand_path() {
    local raw_path="$1"

    if [[ "${raw_path}" == "~" ]]; then
        printf "%s" "${HOME}"
        return 0
    fi

    if [[ "${raw_path}" == ~/* ]]; then
        printf "%s/%s" "${HOME}" "${raw_path#~/}"
        return 0
    fi

    if [[ "${raw_path}" == /* ]]; then
        printf "%s" "${raw_path}"
        return 0
    fi

    printf "%s/%s" "${PWD}" "${raw_path}"
}

normalize_host() {
    local host="$1"
    host="${host%/}"
    printf "%s" "${host}"
}

prompt_api_base_url() {
    local default_value="${EXISTING_API_BASE_URL:-}"
    local api_base_url=""

    while true; do
        api_base_url="$(normalize_host "$(prompt "PymeSec base URL (without /api/v1)" "${default_value}")")"
        if [[ "${api_base_url}" =~ ^https?:// ]]; then
            printf "%s" "${api_base_url}"
            return 0
        fi

        warn "The base URL must start with http:// or https://."
    done
}

download_file() {
    local url="$1"
    local destination="$2"

    curl -fL --retry 3 --retry-delay 1 -A "${USER_AGENT}" -o "${destination}" "${url}"
}

checksum_for_file() {
    local target_file="$1"
    local output=""

    if command -v sha256sum >/dev/null 2>&1; then
        output="$(sha256sum "${target_file}")"
        printf "%s" "${output%% *}"
        return 0
    fi

    output="$(shasum -a 256 "${target_file}")"
    printf "%s" "${output%% *}"
}

expected_checksum_from_file() {
    local checksums_file="$1"
    local asset_name="$2"

    awk -v asset_name="${asset_name}" '$2 == asset_name {print $1; exit}' "${checksums_file}"
}

verify_checksum() {
    local target_file="$1"
    local checksums_file="$2"
    local asset_name
    local expected_checksum
    local actual_checksum

    asset_name="$(basename "${target_file}")"
    expected_checksum="$(expected_checksum_from_file "${checksums_file}" "${asset_name}")"

    if [[ -z "${expected_checksum}" ]]; then
        die "No checksum entry found for ${asset_name}." "Re-download the release assets or check that SHA256SUMS was uploaded for this release."
    fi

    actual_checksum="$(checksum_for_file "${target_file}")"

    if [[ "${actual_checksum}" != "${expected_checksum}" ]]; then
        die "Checksum verification failed for ${asset_name}." "Delete the downloaded files and retry. If the problem persists, do not trust that release asset."
    fi
}

extract_archive() {
    local archive_path="$1"
    local destination_dir="$2"

    mkdir -p "${destination_dir}"

    if [[ "${ARCHIVE_EXT}" == "zip" ]]; then
        unzip -qo "${archive_path}" -d "${destination_dir}" >/dev/null
    else
        tar -xzf "${archive_path}" -C "${destination_dir}"
    fi
}

download_and_extract_release() {
    local release_tag="$1"
    local archive_asset="${RELEASE_BINARY_BASENAME}-${release_tag}.${ARCHIVE_EXT}"
    local checksums_asset="SHA256SUMS"
    local archive_url="${GITHUB_DOWNLOAD_BASE}/${release_tag}/${archive_asset}"
    local checksums_url="${GITHUB_DOWNLOAD_BASE}/${release_tag}/${checksums_asset}"
    local archive_path="${TMP_DIR}/${archive_asset}"
    local checksums_path="${TMP_DIR}/${checksums_asset}"
    local extracted_dir="${TMP_DIR}/extracted"
    local extracted_binary=""

    info "Downloading ${archive_asset} from ${release_tag}."
    download_file "${archive_url}" "${archive_path}" || die "Failed to download ${archive_asset}." "Check the release assets on GitHub and confirm that this platform build exists."

    info "Downloading ${checksums_asset}."
    download_file "${checksums_url}" "${checksums_path}" || die "Failed to download SHA256SUMS." "Upload SHA256SUMS with the release assets and rerun the installer."

    info "Verifying downloaded archive checksum."
    verify_checksum "${archive_path}" "${checksums_path}"
    success "Archive checksum matches SHA256SUMS."

    info "Extracting archive."
    extract_archive "${archive_path}" "${extracted_dir}"

    extracted_binary="$(find "${extracted_dir}" -type f -name "${RELEASE_BINARY_BASENAME}*" | head -n 1)"
    if [[ -z "${extracted_binary}" ]]; then
        die "The archive did not contain the expected binary." "Check the release packaging for ${archive_asset}."
    fi

    if [[ -n "$(expected_checksum_from_file "${checksums_path}" "$(basename "${extracted_binary}")")" ]]; then
        info "Verifying extracted binary checksum."
        verify_checksum "${extracted_binary}" "${checksums_path}"
        success "Binary checksum matches SHA256SUMS."
    else
        warn "No direct checksum entry found for the extracted binary; archive verification already passed."
    fi

    printf "%s" "${extracted_binary}"
}

install_binary() {
    local extracted_binary="$1"
    local install_root="$2"
    local binary_dir="${install_root}/bin"
    local target_binary_path="${binary_dir}/${INSTALLED_BINARY_NAME}"

    mkdir -p "${binary_dir}"
    cp "${extracted_binary}" "${target_binary_path}"
    chmod 0755 "${target_binary_path}"
    printf "%s" "${target_binary_path}"
}

validate_pyme_sec_server() {
    local api_base_url="$1"
    local api_token="$2"

    info "Checking ${api_base_url}/openapi/v1.json."
    curl -fsSL -A "${USER_AGENT}" "${api_base_url}/openapi/v1.json" >/dev/null \
        || die "Cannot reach ${api_base_url}/openapi/v1.json." "Confirm the application origin is correct and publicly reachable from this machine."

    info "Checking authenticated MCP profile endpoint."
    curl -fsSL -A "${USER_AGENT}" \
        -H "Authorization: Bearer ${api_token}" \
        "${api_base_url}/api/v1/meta/mcp-server" >/dev/null \
        || die "Cannot authenticate against ${api_base_url}/api/v1/meta/mcp-server." "Confirm the API key is valid and has access to the MCP profile endpoint."

    success "HTTP preflight checks passed."
}

run_mcp_doctor() {
    local binary_path="$1"
    local api_base_url="$2"
    local api_token="$3"
    local smoke_output=""

    info "Running MCP smoke check through the local binary."
    smoke_output="$(
        {
            printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"pymesec-installer","version":"0.1.0"}}}'
            printf '%s\n' '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"pymesec_get_mcp_profile","arguments":{}}}'
        } | \
            PYMESEC_API_BASE_URL="${api_base_url}" \
            PYMESEC_API_TOKEN="${api_token}" \
            PYMESEC_API_PREFIX="/api/v1" \
            PYMESEC_SYNC_OPENAPI_ON_START="true" \
            "${binary_path}"
    )" || die "The MCP binary failed during the smoke test." "Reinstall the binary, then check the API base URL and token again."

    if ! printf "%s\n" "${smoke_output}" | grep -q 'PymeSec Official MCP Server'; then
        die "The MCP smoke test returned an unexpected payload." "Inspect the server output and confirm that /api/v1/meta/mcp-server is reachable with the supplied token."
    fi

    success "MCP smoke check passed."
}

configure_codex() {
    local binary_path="$1"
    local api_base_url="$2"
    local api_token="$3"

    if codex mcp get "${SERVER_NAME}" >/dev/null 2>&1; then
        info "Updating existing Codex MCP entry [${SERVER_NAME}]."
        codex mcp remove "${SERVER_NAME}" >/dev/null
    fi

    codex mcp add "${SERVER_NAME}" \
        --env "PYMESEC_API_BASE_URL=${api_base_url}" \
        --env "PYMESEC_API_TOKEN=${api_token}" \
        --env "PYMESEC_API_PREFIX=/api/v1" \
        --env "PYMESEC_SYNC_OPENAPI_ON_START=true" \
        -- "${binary_path}" >/dev/null

    success "Codex MCP entry updated."
}

configure_claude() {
    local binary_path="$1"
    local api_base_url="$2"
    local api_token="$3"

    if claude mcp get "${SERVER_NAME}" >/dev/null 2>&1; then
        info "Updating existing Claude MCP entry [${SERVER_NAME}] in user scope."
        claude mcp remove --scope user "${SERVER_NAME}" >/dev/null
    fi

    claude mcp add "${SERVER_NAME}" \
        --scope user \
        --env "PYMESEC_API_BASE_URL=${api_base_url}" \
        --env "PYMESEC_API_TOKEN=${api_token}" \
        --env "PYMESEC_API_PREFIX=/api/v1" \
        --env "PYMESEC_SYNC_OPENAPI_ON_START=true" \
        -- "${binary_path}" >/dev/null

    success "Claude MCP entry updated."
}

json_escape() {
    printf "%s" "$1" | sed \
        -e 's/\\/\\\\/g' \
        -e 's/"/\\"/g'
}

print_manual_client_block() {
    local binary_path="$1"
    local api_base_url="$2"
    local api_token="$3"

    printf "\nManual MCP JSON block:\n"
    cat <<EOF
{
  "mcpServers": {
    "${SERVER_NAME}": {
      "command": "$(json_escape "${binary_path}")",
      "args": [],
      "env": {
        "PYMESEC_API_BASE_URL": "$(json_escape "${api_base_url}")",
        "PYMESEC_API_TOKEN": "$(json_escape "${api_token}")",
        "PYMESEC_API_PREFIX": "/api/v1",
        "PYMESEC_SYNC_OPENAPI_ON_START": "true"
      }
    }
  }
}
EOF
}

choose_client_configuration() {
    local -a options=()

    if (( HAS_CODEX )); then
        options+=("Configure Codex CLI")
    fi
    if (( HAS_CLAUDE )); then
        options+=("Configure Claude CLI")
    fi
    if (( HAS_CODEX && HAS_CLAUDE )); then
        options+=("Configure both Codex and Claude")
    fi
    options+=("Skip CLI configuration")

    menu_choose "Which AI client should be configured?" "${options[@]}"
}

write_metadata() {
    local install_root="$1"
    local release_tag="$2"
    local binary_path="$3"
    local api_base_url="$4"
    local client_selection="$5"
    local metadata_path="${install_root}/${METADATA_FILE_NAME}"

    cat > "${metadata_path}" <<EOF
INSTALL_ROOT="${install_root}"
RELEASE_TAG="${release_tag}"
BINARY_PATH="${binary_path}"
API_BASE_URL="${api_base_url}"
CLIENT_SELECTION="${client_selection}"
UPDATED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
EOF
    chmod 0600 "${metadata_path}"
}

show_install_summary() {
    local install_root="$1"
    local binary_path="$2"
    local release_tag="$3"

    printf "\n%sInstallation summary%s\n" "${COLOR_BOLD}" "${COLOR_RESET}"
    printf "  Release:    %s\n" "${release_tag}"
    printf "  Install:    %s\n" "${install_root}"
    printf "  Binary:     %s\n" "${binary_path}"
    printf "  GitHub repo: %s\n" "${REPO_SLUG}"
}

install_or_update_flow() {
    local release_tag=""
    local install_root=""
    local binary_path=""
    local extracted_binary=""
    local api_base_url=""
    local api_token=""
    local client_selection=""

    release_tag="$(choose_release_tag)"
    install_root="$(choose_install_root)"
    api_base_url="$(prompt_api_base_url)"
    api_token="$(prompt_secret "PymeSec API key")"

    show_install_summary "${install_root}" "${install_root}/bin/${INSTALLED_BINARY_NAME}" "${release_tag}"
    printf "  API base:   %s\n\n" "${api_base_url}"

    confirm "Proceed with installation?" "y" || return 0

    validate_pyme_sec_server "${api_base_url}" "${api_token}"

    TMP_DIR="$(mktemp -d)"
    extracted_binary="$(download_and_extract_release "${release_tag}")"
    binary_path="$(install_binary "${extracted_binary}" "${install_root}")"
    run_mcp_doctor "${binary_path}" "${api_base_url}" "${api_token}"

    client_selection="$(choose_client_configuration)"
    case "${client_selection}" in
        "Configure Codex CLI")
            configure_codex "${binary_path}" "${api_base_url}" "${api_token}"
            ;;
        "Configure Claude CLI")
            configure_claude "${binary_path}" "${api_base_url}" "${api_token}"
            ;;
        "Configure both Codex and Claude")
            configure_codex "${binary_path}" "${api_base_url}" "${api_token}"
            configure_claude "${binary_path}" "${api_base_url}" "${api_token}"
            ;;
        *)
            warn "Skipping CLI configuration."
            print_manual_client_block "${binary_path}" "${api_base_url}" "${api_token}"
            ;;
    esac

    write_metadata "${install_root}" "${release_tag}" "${binary_path}" "${api_base_url}" "${client_selection}"

    success "PymeSec MCP is installed and ready."
    printf "Restart Codex or Claude if it was already running.\n"
}

resolve_existing_install_root() {
    if [[ -n "${EXISTING_INSTALL_ROOT}" ]]; then
        printf "%s" "${EXISTING_INSTALL_ROOT}"
        return 0
    fi

    choose_install_root
}

doctor_flow() {
    local install_root=""
    local binary_path=""
    local api_base_url=""
    local api_token=""

    install_root="$(resolve_existing_install_root)"
    binary_path="${install_root}/bin/${INSTALLED_BINARY_NAME}"

    [[ -x "${binary_path}" ]] || die "No installed binary found at ${binary_path}." "Run the installer first or point the doctor at the correct install directory."

    api_base_url="$(prompt_api_base_url)"
    api_token="$(prompt_secret "PymeSec API key for the doctor check")"

    validate_pyme_sec_server "${api_base_url}" "${api_token}"
    run_mcp_doctor "${binary_path}" "${api_base_url}" "${api_token}"

    if (( HAS_CODEX )) && codex mcp get "${SERVER_NAME}" >/dev/null 2>&1; then
        success "Codex currently knows the MCP server [${SERVER_NAME}]."
    elif (( HAS_CODEX )); then
        warn "Codex is installed but does not have an MCP entry named [${SERVER_NAME}]."
    fi

    if (( HAS_CLAUDE )) && claude mcp get "${SERVER_NAME}" >/dev/null 2>&1; then
        success "Claude currently knows the MCP server [${SERVER_NAME}]."
    elif (( HAS_CLAUDE )); then
        warn "Claude is installed but does not have an MCP entry named [${SERVER_NAME}]."
    fi

    success "Doctor checks passed."
}

uninstall_flow() {
    local install_root=""

    install_root="$(resolve_existing_install_root)"

    [[ -d "${install_root}" ]] || die "Install directory not found: ${install_root}" "Confirm the correct path and rerun uninstall."

    if (( HAS_CODEX )) && codex mcp get "${SERVER_NAME}" >/dev/null 2>&1; then
        if confirm "Remove the Codex MCP entry [${SERVER_NAME}] too?" "y"; then
            codex mcp remove "${SERVER_NAME}" >/dev/null
            success "Removed the Codex MCP entry."
        fi
    fi

    if (( HAS_CLAUDE )) && claude mcp get "${SERVER_NAME}" >/dev/null 2>&1; then
        if confirm "Remove the Claude MCP entry [${SERVER_NAME}] from user scope too?" "y"; then
            claude mcp remove --scope user "${SERVER_NAME}" >/dev/null
            success "Removed the Claude MCP entry."
        fi
    fi

    confirm "Delete ${install_root}?" "n" || return 0
    rm -rf "${install_root}"
    success "Removed ${install_root}."
}

show_intro() {
    printf "%sPymeSec MCP local installer%s\n" "${COLOR_BOLD}" "${COLOR_RESET}"
    printf "This script will:\n"
    printf "  - detect your platform and pick the right MCP binary\n"
    printf "  - download a release archive and SHA256SUMS from GitHub\n"
    printf "  - verify the downloaded archive checksum\n"
    printf "  - install the binary in a stable local directory\n"
    printf "  - validate the PymeSec host, API key, and MCP stdio startup\n"
    printf "  - optionally register the MCP server in Codex or Claude CLI\n\n"

    if (( HAS_CODEX == 0 && HAS_CLAUDE == 0 )); then
        warn "Neither Codex CLI nor Claude CLI was detected. Binary-only installation is still possible."
    else
        if (( HAS_CODEX )); then
            info "Codex CLI detected."
        fi
        if (( HAS_CLAUDE )); then
            info "Claude CLI detected."
        fi
    fi

    info "Detected platform: ${PLATFORM_FAMILY}/${PLATFORM_ARCH}"
}

main_menu() {
    menu_choose \
        "What would you like to do?" \
        "Install or update PymeSec MCP" \
        "Run doctor checks" \
        "Uninstall PymeSec MCP" \
        "Exit"
}

main() {
    local requested_action="${1:-}"
    local action=""

    init_colors

    case "${requested_action}" in
        -h|--help|help)
            usage
            return 0
            ;;
        ""|install|doctor|uninstall)
            ;;
        *)
            die "Unknown action [${requested_action}]." "Use --help to see the supported commands."
            ;;
    esac

    require_tty
    require_commands
    detect_clients
    detect_platform
    load_existing_metadata || true

    print_logo
    show_intro
    confirm "Continue?" "y" || return 0

    if [[ -n "${requested_action}" ]]; then
        action="${requested_action}"
    else
        action="$(main_menu)"
    fi

    case "${action}" in
        "Install or update PymeSec MCP"|install)
            install_or_update_flow
            ;;
        "Run doctor checks"|doctor)
            doctor_flow
            ;;
        "Uninstall PymeSec MCP"|uninstall)
            uninstall_flow
            ;;
        *)
            info "Nothing changed."
            ;;
    esac
}

main "$@"
