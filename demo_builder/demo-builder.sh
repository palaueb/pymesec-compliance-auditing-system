#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || true)"

if [[ -z "$REPO_ROOT" ]]; then
    echo "demo-builder: this script must run from inside a git repository." >&2
    exit 1
fi

DEMO_BUILDER_DIR="$REPO_ROOT/demo_builder"
PATCH_DIR="$DEMO_BUILDER_DIR/patches"
STATE_DIR="$DEMO_BUILDER_DIR/state"
WORKFLOW_FILE="$STATE_DIR/workflow.env"
EXPORT_FILE="$STATE_DIR/last_export.env"
FILES_FILE="$STATE_DIR/patched-files.txt"

timestamp_utc() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

fail() {
    echo "demo-builder: $*" >&2
    exit 1
}

ensure_dirs() {
    mkdir -p "$PATCH_DIR" "$STATE_DIR"
}

git_branch_exists() {
    local branch="$1"
    git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$branch"
}

load_workflow() {
    BASE_BRANCH="${BASE_BRANCH:-}"
    DEMO_BRANCH="${DEMO_BRANCH:-}"

    if [[ -f "$WORKFLOW_FILE" ]]; then
        # shellcheck disable=SC1090
        source "$WORKFLOW_FILE"
    fi

    BASE_BRANCH="${BASE_BRANCH:-main}"
    DEMO_BRANCH="${DEMO_BRANCH:-demo}"
}

write_workflow() {
    local base_branch="$1"
    local demo_branch="$2"
    local base_head="$3"

    cat >"$WORKFLOW_FILE" <<EOF
BASE_BRANCH=$base_branch
DEMO_BRANCH=$demo_branch
INITIALIZED_FROM_COMMIT=$base_head
INITIALIZED_AT=$(timestamp_utc)
EOF
}

write_export_metadata() {
    local merge_base="$1"
    local base_head="$2"
    local demo_head="$3"

    cat >"$EXPORT_FILE" <<EOF
BASE_BRANCH=$BASE_BRANCH
DEMO_BRANCH=$DEMO_BRANCH
EXPORT_MERGE_BASE=$merge_base
EXPORT_BASE_HEAD=$base_head
EXPORT_DEMO_HEAD=$demo_head
EXPORTED_AT=$(timestamp_utc)
EOF
}

remove_previous_patch_files() {
    if [[ ! -f "$FILES_FILE" ]]; then
        return
    fi

    while IFS= read -r file_path; do
        [[ -z "$file_path" ]] && continue
        rm -f "$PATCH_DIR/$file_path.patch"
    done <"$FILES_FILE"
}

cmd_init() {
    local base_branch="${1:-main}"
    local demo_branch="${2:-demo}"
    local base_head

    ensure_dirs

    git_branch_exists "$base_branch" || fail "base branch [$base_branch] does not exist locally."

    if git_branch_exists "$demo_branch"; then
        echo "demo-builder: demo branch [$demo_branch] already exists."
    else
        git -C "$REPO_ROOT" branch "$demo_branch" "$base_branch"
        echo "demo-builder: created demo branch [$demo_branch] from [$base_branch]."
    fi

    base_head="$(git -C "$REPO_ROOT" rev-parse "$base_branch")"
    write_workflow "$base_branch" "$demo_branch" "$base_head"

    echo "demo-builder: workflow ready."
    echo "  base branch: $base_branch"
    echo "  demo branch: $demo_branch"
    echo "  base head:   $base_head"
    echo "  workflow:    $WORKFLOW_FILE"
}

cmd_export() {
    load_workflow
    ensure_dirs

    git_branch_exists "$BASE_BRANCH" || fail "base branch [$BASE_BRANCH] does not exist locally."
    git_branch_exists "$DEMO_BRANCH" || fail "demo branch [$DEMO_BRANCH] does not exist locally. Run init first."

    local merge_base
    local base_head
    local demo_head
    merge_base="$(git -C "$REPO_ROOT" merge-base "$BASE_BRANCH" "$DEMO_BRANCH")"
    base_head="$(git -C "$REPO_ROOT" rev-parse "$BASE_BRANCH")"
    demo_head="$(git -C "$REPO_ROOT" rev-parse "$DEMO_BRANCH")"

    remove_previous_patch_files

    mapfile -t changed_files < <(git -C "$REPO_ROOT" diff --name-only --diff-filter=ACMRTUXB "$BASE_BRANCH...$DEMO_BRANCH")

    : >"$FILES_FILE"

    for file_path in "${changed_files[@]}"; do
        [[ -z "$file_path" ]] && continue

        local patch_path="$PATCH_DIR/$file_path.patch"
        mkdir -p "$(dirname "$patch_path")"
        git -C "$REPO_ROOT" diff --binary --full-index --no-color "$BASE_BRANCH...$DEMO_BRANCH" -- "$file_path" >"$patch_path"
        printf '%s\n' "$file_path" >>"$FILES_FILE"
    done

    write_export_metadata "$merge_base" "$base_head" "$demo_head"

    echo "demo-builder: exported ${#changed_files[@]} patch file(s)."
    echo "  merge base:  $merge_base"
    echo "  base head:   $base_head"
    echo "  demo head:   $demo_head"
    echo "  patch dir:   $PATCH_DIR"
}

cmd_drift() {
    load_workflow

    [[ -f "$EXPORT_FILE" ]] || fail "missing export metadata [$EXPORT_FILE]. Run export first."
    [[ -f "$FILES_FILE" ]] || fail "missing patched files list [$FILES_FILE]. Run export first."
    git_branch_exists "$BASE_BRANCH" || fail "base branch [$BASE_BRANCH] does not exist locally."

    # shellcheck disable=SC1090
    source "$EXPORT_FILE"

    local drift_count=0

    while IFS= read -r file_path; do
        [[ -z "$file_path" ]] && continue

        if ! git -C "$REPO_ROOT" diff --quiet "$EXPORT_MERGE_BASE..$BASE_BRANCH" -- "$file_path"; then
            printf 'DRIFT %s\n' "$file_path"
            drift_count=$((drift_count + 1))
        fi
    done <"$FILES_FILE"

    if (( drift_count > 0 )); then
        fail "$drift_count file(s) changed on [$BASE_BRANCH] since the last patch export."
    fi

    echo "demo-builder: no drift detected for exported patch targets."
}

cmd_apply() {
    ensure_dirs

    [[ -f "$EXPORT_FILE" ]] || fail "missing export metadata [$EXPORT_FILE]. Run export first."
    [[ -f "$FILES_FILE" ]] || fail "missing patched files list [$FILES_FILE]. Run export first."

    # shellcheck disable=SC1090
    source "$EXPORT_FILE"

    local current_head
    current_head="$(git -C "$REPO_ROOT" rev-parse HEAD)"

    if [[ "${DEMO_BUILDER_ALLOW_DIRTY:-0}" != "1" ]] && [[ -n "$(git -C "$REPO_ROOT" status --porcelain)" ]]; then
        fail "working tree is dirty. Commit, stash, or set DEMO_BUILDER_ALLOW_DIRTY=1."
    fi

    if [[ "$current_head" != "$EXPORT_BASE_HEAD" ]]; then
        fail "current HEAD [$current_head] does not match exported base head [$EXPORT_BASE_HEAD]. Run drift or export again."
    fi

    mapfile -t patch_files < <(find "$PATCH_DIR" -type f -name '*.patch' | LC_ALL=C sort)

    for patch_file in "${patch_files[@]}"; do
        git -C "$REPO_ROOT" apply --check --binary "$patch_file"
    done

    for patch_file in "${patch_files[@]}"; do
        git -C "$REPO_ROOT" apply --3way --binary "$patch_file"
        echo "demo-builder: applied $(realpath --relative-to="$REPO_ROOT" "$patch_file")"
    done

    echo "demo-builder: applied ${#patch_files[@]} patch file(s)."
}

cmd_worktree() {
    load_workflow
    ensure_dirs

    git_branch_exists "$DEMO_BRANCH" || fail "demo branch [$DEMO_BRANCH] does not exist locally. Run init first."

    local target_path="${1:-$REPO_ROOT/../$(basename "$REPO_ROOT")-demo-worktree}"
    local absolute_target
    absolute_target="$(mkdir -p "$(dirname "$target_path")" && cd "$(dirname "$target_path")" && pwd)/$(basename "$target_path")"

    if [[ -d "$absolute_target/.git" ]] || git -C "$REPO_ROOT" worktree list --porcelain | grep -Fqx "worktree $absolute_target"; then
        echo "demo-builder: worktree already exists at [$absolute_target]."
        return 0
    fi

    git -C "$REPO_ROOT" worktree add "$absolute_target" "$DEMO_BRANCH"

    echo "demo-builder: created demo worktree."
    echo "  branch: $DEMO_BRANCH"
    echo "  path:   $absolute_target"
}

print_help() {
    cat <<'EOF'
Usage:
  demo_builder/demo-builder.sh init [base_branch] [demo_branch]
  demo_builder/demo-builder.sh worktree [target_path]
  demo_builder/demo-builder.sh export
  demo_builder/demo-builder.sh drift
  demo_builder/demo-builder.sh apply

Commands:
  init    Create the demo branch and save the workflow metadata.
  worktree Create or reuse a dedicated git worktree for the demo branch.
  export  Export one patch file per changed file from demo branch vs base branch.
  drift   Report files in the patch pack that changed on the base branch since export.
  apply   Apply the exported patch pack to a clean checkout at the exported base commit.
EOF
}

main() {
    local command="${1:-help}"

    case "$command" in
        init)
            shift
            cmd_init "${1:-main}" "${2:-demo}"
            ;;
        worktree)
            shift
            cmd_worktree "${1:-}"
            ;;
        export)
            cmd_export
            ;;
        drift)
            cmd_drift
            ;;
        apply)
            cmd_apply
            ;;
        help|-h|--help)
            print_help
            ;;
        *)
            print_help
            fail "unknown command [$command]."
            ;;
    esac
}

main "$@"
