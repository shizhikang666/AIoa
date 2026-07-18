#!/usr/bin/env bash

set -Eeuo pipefail
umask 022

ACTION="prepare"
ACTION_EXPLICIT=0
ROOT="/www/wwwroot/oa.fucity.cn"
RELEASE_ID=""
ARCHIVE=""
ARCHIVE_SHA256=""
ARCHIVE_FORMAT=""
ENV_SOURCE=""
EXPECTED_ENV_SHA256=""
EXPECTED_DB_NAME_SHA256=""
EXPECTED_CURRENT=""
CONFIRM_INITIALIZE=0
CONFIRM_ACTIVATE=0
CONFIRM_ROLLBACK=0
SERVICE_OWNER="www:www"
PROTECTED_OWNER="root:www"
PHP_BIN="/www/server/php/83/bin/php"
PHP_FPM_CONTROL="/etc/init.d/php-fpm-83"
NGINX_BIN="/www/server/nginx/sbin/nginx"
CURL_BIN="/usr/bin/curl"
VHOST_FILE="/www/server/panel/vhost/nginx/oa.fucity.cn.conf"
REWRITE_FILE="/www/server/panel/vhost/rewrite/oa.fucity.cn.conf"
HEALTH_URLS=()

RELEASES_ROOT=""
SHARED_ROOT=""
SHARED_ENV_ROOT=""
DEPLOY_ROOT=""
BACKUPS_ROOT=""
MANIFESTS_ROOT=""
PROVENANCE_ROOT=""
STAGING_ROOT=""
CURRENT_LINK=""
PREPARE_TEMP=""
PREPARE_ENV=""
PREPARE_ENV_CREATED=0
PREPARE_FINALIZED=0
STAGED_ARCHIVE=""
STAGED_ENV=""
ENV_SHA256=""
DB_NAME_SHA256=""
AUDIT_PENDING=""
AUDIT_COMMIT_TEMP=""
AUDIT_RUNTIME_FAILURE_TEMP=""
AUDIT_COMMIT_FAILURE_TEMP=""
AUDIT_UNVERIFIED_TEMP=""
AUDIT_NOT_SWITCHED_TEMP=""
AUDIT_FINAL=""

log() {
    printf '[atomic-deploy] %s\n' "$*"
}

die() {
    printf '[atomic-deploy][ERROR] %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'USAGE'
Usage:
  # Safe default: prepare a candidate only; never changes current.
  scripts/oa-fucity-atomic-deploy.sh \
    --release-id ID --archive ABSOLUTE_FILE --archive-sha256 SHA256 \
    --env-source ABSOLUTE_ENV_FILE --expected-env-sha256 SHA256 \
    --expected-db-name-sha256 SHA256 [options]

  # One-time conversion of the existing live tree into releases/current.
  scripts/oa-fucity-atomic-deploy.sh --initialize-baseline \
    --release-id baseline-ID --expected-current absent \
    --confirm-initialize-baseline --health-url URL [options]

  # Atomic activation with compare-and-swap protection.
  scripts/oa-fucity-atomic-deploy.sh --activate \
    --release-id ID --expected-current CURRENT_ID \
    --expected-env-sha256 SHA256 --expected-db-name-sha256 SHA256 \
    --confirm-activate --health-url URL [options]

  # Atomic rollback with compare-and-swap protection.
  scripts/oa-fucity-atomic-deploy.sh --rollback \
    --release-id ID --expected-current CURRENT_ID \
    --expected-env-sha256 SHA256 --expected-db-name-sha256 SHA256 \
    --confirm-rollback --health-url URL [options]

Actions:
  --prepare                    Prepare only (default).
  --initialize-baseline        Copy the existing live code into a baseline
                               release, install current, and patch the three
                               BaoTa Nginx code paths exactly once.
  --activate                   Atomically switch current to --release-id.
  --rollback                   Atomically switch current to --release-id as an
                               explicit rollback.

Required action gates:
  --expected-current ID|absent Compare-and-swap expectation. Initialization
                               requires absent; activation/rollback require ID.
  --confirm-initialize-baseline
  --confirm-activate
  --confirm-rollback

Prepare inputs:
  --archive FILE               Absolute .zip, .tar.gz, or .tgz package path.
  --archive-sha256 SHA256      Required expected package SHA-256.
  --env-source FILE            Absolute regular, non-symlink environment file.
                               Archive/env inputs must be root-owned, single-link,
                               and not group/world writable. They are opened once
                               and copied into protected staging before use.
  --expected-env-sha256 SHA256 Externally approved hash of the exact env bytes.
  --expected-db-name-sha256 SHA256
                               Externally approved hash commitment to DB_NAME.
                               Both hashes are mandatory for candidate prepare
                               and every activate/rollback operation.

Options:
  --root DIR                   Physical site root.
  --release-id ID              Strict release identifier.
  --health-url URL             Repeatable HTTP(S) health URL. Required for
                               initialize/activate/rollback. Bodies are discarded.
  --php-bin FILE               PHP 8.3 CLI binary.
  --php-fpm-control FILE       PHP 8.3 FPM service control script.
  --nginx-bin FILE             BaoTa Nginx binary.
  --curl-bin FILE              curl binary.
  --vhost-file FILE            BaoTa site vhost file.
  --rewrite-file FILE          BaoTa site rewrite include.
  -h, --help                   Show help.

The script never invokes mysql, mysqldump, a schema installer, a migration
script, a queue, or a scheduler. Database migration is a separate gated phase.
Candidate packages must contain a clean, non-diagnostic RELEASE-MANIFEST.json
and matching RELEASE-* markers, including a candidate/oa-* or release/oa-* tag.
USAGE
}

set_action() {
    local next="$1"
    if [ "$ACTION_EXPLICIT" -eq 1 ]; then
        die "only one action may be selected"
    fi
    ACTION="$next"
    ACTION_EXPLICIT=1
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --prepare)
            set_action prepare
            shift
            ;;
        --initialize-baseline)
            set_action initialize-baseline
            shift
            ;;
        --activate)
            set_action activate
            shift
            ;;
        --rollback)
            set_action rollback
            shift
            ;;
        --root)
            ROOT="${2:-}"
            shift 2
            ;;
        --release-id)
            RELEASE_ID="${2:-}"
            shift 2
            ;;
        --archive)
            ARCHIVE="${2:-}"
            shift 2
            ;;
        --archive-sha256)
            ARCHIVE_SHA256="${2:-}"
            shift 2
            ;;
        --env-source)
            ENV_SOURCE="${2:-}"
            shift 2
            ;;
        --expected-env-sha256)
            EXPECTED_ENV_SHA256="${2:-}"
            shift 2
            ;;
        --expected-db-name-sha256)
            EXPECTED_DB_NAME_SHA256="${2:-}"
            shift 2
            ;;
        --expected-current)
            EXPECTED_CURRENT="${2:-}"
            shift 2
            ;;
        --confirm-initialize-baseline)
            CONFIRM_INITIALIZE=1
            shift
            ;;
        --confirm-activate)
            CONFIRM_ACTIVATE=1
            shift
            ;;
        --confirm-rollback)
            CONFIRM_ROLLBACK=1
            shift
            ;;
        --health-url)
            HEALTH_URLS+=("${2:-}")
            shift 2
            ;;
        --php-bin)
            PHP_BIN="${2:-}"
            shift 2
            ;;
        --php-fpm-control)
            PHP_FPM_CONTROL="${2:-}"
            shift 2
            ;;
        --nginx-bin)
            NGINX_BIN="${2:-}"
            shift 2
            ;;
        --curl-bin)
            CURL_BIN="${2:-}"
            shift 2
            ;;
        --vhost-file)
            VHOST_FILE="${2:-}"
            shift 2
            ;;
        --rewrite-file)
            REWRITE_FILE="${2:-}"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            die "unknown option: $1"
            ;;
    esac
done

require_command() {
    local command_path="$1"
    if [[ "$command_path" == */* ]]; then
        [ -x "$command_path" ] || die "required executable missing: $command_path"
    else
        command -v "$command_path" >/dev/null 2>&1 || die "required command missing: $command_path"
    fi
}

require_absolute_path() {
    local label="$1"
    local path="$2"
    case "$path" in
        /*) ;;
        *) die "$label must be an absolute path" ;;
    esac
    case "$path" in
        *$'\n'*|*$'\r'*) die "$label contains a line break" ;;
    esac
}

require_regular_not_link() {
    local label="$1"
    local path="$2"
    require_absolute_path "$label" "$path"
    [ -f "$path" ] || die "$label is not a regular file: $path"
    [ ! -L "$path" ] || die "$label must not be a symlink: $path"
    local physical
    physical="$(readlink -f -- "$path")"
    [ "$physical" = "$path" ] || die "$label must be canonical and contain no symlink components: $path"
}

validate_release_id() {
    local value="$1"
    [ -n "$value" ] || die "--release-id is required"
    [ "${#value}" -le 80 ] || die "release id is too long"
    [[ "$value" =~ ^[A-Za-z0-9]([A-Za-z0-9._-]{0,78}[A-Za-z0-9])?$ ]] \
        || die "invalid release id: use 1-80 alphanumeric, dot, underscore, or hyphen characters and alphanumeric ends"
    [ "$value" != "." ] && [ "$value" != ".." ] || die "invalid release id"
}

require_expected_binding_approvals() {
    [ -n "$EXPECTED_ENV_SHA256" ] || die "--expected-env-sha256 is required for this action"
    [ -n "$EXPECTED_DB_NAME_SHA256" ] || die "--expected-db-name-sha256 is required for this action"
    EXPECTED_ENV_SHA256="$(printf '%s' "$EXPECTED_ENV_SHA256" | tr '[:upper:]' '[:lower:]')"
    EXPECTED_DB_NAME_SHA256="$(printf '%s' "$EXPECTED_DB_NAME_SHA256" | tr '[:upper:]' '[:lower:]')"
    [[ "$EXPECTED_ENV_SHA256" =~ ^[0-9a-f]{64}$ ]] || die "--expected-env-sha256 must be exactly 64 hexadecimal characters"
    [[ "$EXPECTED_DB_NAME_SHA256" =~ ^[0-9a-f]{64}$ ]] || die "--expected-db-name-sha256 must be exactly 64 hexadecimal characters"
}

verify_running_as_root() {
    require_command id
    [ "$(id -u)" = "0" ] || die "atomic deployment must run as root"
}

require_exact_owner() {
    local label="$1"
    local path="$2"
    local expected="$3"
    local actual
    actual="$(stat -c '%U:%G' -- "$path")"
    [ "$actual" = "$expected" ] || die "$label owner must be $expected"
}

require_exact_mode() {
    local label="$1"
    local path="$2"
    local expected="$3"
    local actual
    actual="$(stat -c '%a' -- "$path")"
    [ "$actual" = "$expected" ] || die "$label mode must be $expected"
}

ensure_physical_directory() {
    local label="$1"
    local path="$2"
    require_absolute_path "$label" "$path"
    [ ! -L "$path" ] || die "$label must not be a symlink: $path"
    if [ ! -e "$path" ]; then
        mkdir -- "$path"
    fi
    [ -d "$path" ] && [ ! -L "$path" ] || die "$label is not a physical directory: $path"
    local physical
    physical="$(cd "$path" && pwd -P)"
    [ "$physical" = "$path" ] || die "$label must be canonical and contain no symlink components: $path"
}

normalize_root() {
    require_absolute_path "root" "$ROOT"
    case "$ROOT" in
        /|/www|/www/|/www/wwwroot|/www/wwwroot/)
            die "refusing unsafe site root: $ROOT"
            ;;
    esac
    [ -d "$ROOT" ] || die "site root does not exist: $ROOT"
    [ ! -L "$ROOT" ] || die "site root must be a physical directory"
    local physical
    physical="$(cd "$ROOT" && pwd -P)"
    ROOT="${ROOT%/}"
    [ "$ROOT" = "$physical" ] || die "site root must be canonical and contain no symlink components"
    case "$ROOT" in
        *'#'*|*'&'*) die "site root contains an unsupported character" ;;
    esac

    RELEASES_ROOT="$ROOT/releases"
    SHARED_ROOT="$ROOT/shared"
    SHARED_ENV_ROOT="$SHARED_ROOT/env"
    DEPLOY_ROOT="$ROOT/.deploy"
    BACKUPS_ROOT="$DEPLOY_ROOT/backups"
    MANIFESTS_ROOT="$DEPLOY_ROOT/manifests"
    PROVENANCE_ROOT="$DEPLOY_ROOT/provenance"
    STAGING_ROOT="$DEPLOY_ROOT/staging"
    CURRENT_LINK="$ROOT/current"
}

prepare_layout() {
    chown "$PROTECTED_OWNER" "$ROOT"
    chmod 750 "$ROOT"
    require_exact_owner "current parent site root" "$ROOT" "$PROTECTED_OWNER"
    require_exact_mode "current parent site root" "$ROOT" "750"
    ensure_physical_directory "releases root" "$RELEASES_ROOT"
    ensure_physical_directory "shared root" "$SHARED_ROOT"
    ensure_physical_directory "shared env root" "$SHARED_ENV_ROOT"
    ensure_physical_directory "deploy metadata root" "$DEPLOY_ROOT"
    ensure_physical_directory "deployment backup root" "$BACKUPS_ROOT"
    ensure_physical_directory "deployment manifest root" "$MANIFESTS_ROOT"
    ensure_physical_directory "deployment provenance root" "$PROVENANCE_ROOT"
    ensure_physical_directory "deployment staging root" "$STAGING_ROOT"
    chmod 755 "$RELEASES_ROOT" 2>/dev/null || true
    chmod 750 "$SHARED_ROOT" "$SHARED_ENV_ROOT" 2>/dev/null || true
    chmod 700 "$DEPLOY_ROOT" "$BACKUPS_ROOT" "$MANIFESTS_ROOT" "$PROVENANCE_ROOT" "$STAGING_ROOT" 2>/dev/null || true
    chown root:root "$RELEASES_ROOT" "$DEPLOY_ROOT" "$BACKUPS_ROOT" "$MANIFESTS_ROOT" "$PROVENANCE_ROOT" "$STAGING_ROOT"
    chown "$PROTECTED_OWNER" "$SHARED_ROOT" "$SHARED_ENV_ROOT"
    require_exact_owner "deployment staging root" "$STAGING_ROOT" "root:root"
    require_exact_mode "deployment staging root" "$STAGING_ROOT" "700"
    require_exact_owner "shared env root" "$SHARED_ENV_ROOT" "$PROTECTED_OWNER"
    require_exact_mode "shared env root" "$SHARED_ENV_ROOT" "750"
}

acquire_lock() {
    require_command flock
    exec 9>"$DEPLOY_ROOT/deploy.lock"
    flock -n 9 || die "another atomic deployment operation holds the lock"
}

safe_remove_prepare_temp() {
    local path="$1"
    [ -n "$path" ] || return 0
    case "$path" in
        "$RELEASES_ROOT"/.prepare-"$RELEASE_ID"-*) rm -rf -- "$path" ;;
        *) die "refusing to remove unsafe prepare path: $path" ;;
    esac
}

safe_remove_staged_file() {
    local path="$1"
    [ -n "$path" ] || return 0
    case "$path" in
        "$STAGING_ROOT"/archive-"$RELEASE_ID"-*|"$STAGING_ROOT"/env-"$RELEASE_ID"-*) rm -f -- "$path" ;;
        *) die "refusing to remove unsafe staged input: $path" ;;
    esac
}

clear_staged_inputs() {
    if [ -n "$STAGED_ARCHIVE" ] && { [ -e "$STAGED_ARCHIVE" ] || [ -L "$STAGED_ARCHIVE" ]; }; then
        safe_remove_staged_file "$STAGED_ARCHIVE"
    fi
    if [ -n "$STAGED_ENV" ] && { [ -e "$STAGED_ENV" ] || [ -L "$STAGED_ENV" ]; }; then
        safe_remove_staged_file "$STAGED_ENV"
    fi
    STAGED_ARCHIVE=""
    STAGED_ENV=""
}

cleanup() {
    local status=$?
    if [ "$PREPARE_FINALIZED" -eq 0 ] && [ -n "$PREPARE_TEMP" ] && { [ -e "$PREPARE_TEMP" ] || [ -L "$PREPARE_TEMP" ]; }; then
        safe_remove_prepare_temp "$PREPARE_TEMP" || true
    fi
    if [ "$PREPARE_ENV_CREATED" -eq 1 ] && [ "$PREPARE_FINALIZED" -eq 0 ] && [ -n "$PREPARE_ENV" ] && [ -f "$PREPARE_ENV" ] && [ ! -L "$PREPARE_ENV" ]; then
        case "$PREPARE_ENV" in
            "$SHARED_ENV_ROOT/$RELEASE_ID.env") rm -f -- "$PREPARE_ENV" ;;
        esac
    fi
    if [ -n "$STAGED_ARCHIVE" ] && { [ -e "$STAGED_ARCHIVE" ] || [ -L "$STAGED_ARCHIVE" ]; }; then
        safe_remove_staged_file "$STAGED_ARCHIVE" || true
    fi
    if [ -n "$STAGED_ENV" ] && { [ -e "$STAGED_ENV" ] || [ -L "$STAGED_ENV" ]; }; then
        safe_remove_staged_file "$STAGED_ENV" || true
    fi
    exit "$status"
}

trap cleanup EXIT

verify_php83() {
    require_command "$PHP_BIN"
    local version
    version="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')" || die "unable to query PHP version"
    [ "$version" = "8.3" ] || die "PHP 8.3 is required; selected binary reported a different major/minor version"
}

stage_root_owned_input() {
    local label="$1"
    local source="$2"
    local kind="$3"
    local output_variable="$4"
    require_regular_not_link "$label" "$source"
    local source_identity
    source_identity="$(stat -c '%d:%i' -- "$source")"
    local descriptor
    exec {descriptor}<"$source" || die "cannot open $label"
    local descriptor_path="/dev/fd/$descriptor"
    local file_type_hex uid mode links identity expected_size
    file_type_hex="$(stat -Lc '%f' -- "$descriptor_path")"
    uid="$(stat -Lc '%u' -- "$descriptor_path")"
    mode="$(stat -Lc '%a' -- "$descriptor_path")"
    links="$(stat -Lc '%h' -- "$descriptor_path")"
    identity="$(stat -Lc '%d:%i:%s' -- "$descriptor_path")"
    expected_size="${identity##*:}"
    [ "${identity%:*}" = "$source_identity" ] || {
        exec {descriptor}<&-
        die "$label changed inode before its protected snapshot was opened"
    }
    local file_type_value=$((16#$file_type_hex))
    (( (file_type_value & 0170000) == 0100000 )) || {
        exec {descriptor}<&-
        die "$label must resolve to an opened regular file"
    }
    [ "$uid" = "0" ] || {
        exec {descriptor}<&-
        die "$label must be owned by root"
    }
    [ "$links" = "1" ] || {
        exec {descriptor}<&-
        die "$label must have exactly one hard link"
    }
    local mode_value=$((8#$mode))
    [ $((mode_value & 0022)) -eq 0 ] || {
        exec {descriptor}<&-
        die "$label must not be group- or world-writable"
    }
    local staged
    staged="$(mktemp "$STAGING_ROOT/$kind-$RELEASE_ID-XXXXXXXX")"
    chmod 600 "$staged"
    chown root:root "$staged"
    if ! dd bs=1048576 status=none <&"$descriptor" > "$staged"; then
        exec {descriptor}<&-
        rm -f -- "$staged"
        die "unable to copy opened $label into protected staging"
    fi
    exec {descriptor}<&-
    [ "$(stat -Lc '%s' -- "$staged")" = "$expected_size" ] || {
        rm -f -- "$staged"
        die "$label changed size while it was being staged"
    }
    require_regular_not_link "staged $label" "$staged"
    require_exact_owner "staged $label" "$staged" "root:root"
    require_exact_mode "staged $label" "$staged" "600"
    printf -v "$output_variable" '%s' "$staged"
    log "$label was fixed to a protected staging snapshot"
}

env_value() {
    local key="$1"
    local path="$2"
    awk -v target="$key" '
        function trim(value) {
            gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
            return value
        }
        {
            line = $0
            sub(/\r$/, "", line)
            if (line ~ /^[ \t]*($|#)/) next
            separator = index(line, "=")
            if (separator == 0) next
            name = trim(substr(line, 1, separator - 1))
            if (name == target) {
                value = trim(substr(line, separator + 1))
                gsub(/^[\047\042]|[\047\042]$/, "", value)
                print value
                exit
            }
        }
    ' "$path"
}

env_key_count() {
    local key="$1"
    local path="$2"
    awk -v target="$key" '
        function trim(value) {
            gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
            return value
        }
        BEGIN { count = 0 }
        {
            line = $0
            sub(/\r$/, "", line)
            if (line ~ /^[ \t]*($|#)/) next
            separator = index(line, "=")
            if (separator == 0) next
            name = trim(substr(line, 1, separator - 1))
            if (name == target) count++
        }
        END { print count }
    ' "$path"
}

validate_environment_file() {
    local path="$1"
    local debug db_name
    [ "$(env_key_count APP_DEBUG "$path")" = "1" ] || die "environment file must define APP_DEBUG exactly once"
    [ "$(env_key_count DB_NAME "$path")" = "1" ] || die "environment file must define DB_NAME exactly once"
    debug="$(env_value APP_DEBUG "$path")"
    debug="$(printf '%s' "$debug" | tr '[:upper:]' '[:lower:]')"
    case "$debug" in
        false|0|off|no) ;;
        *) die "environment file must set APP_DEBUG=false" ;;
    esac
    db_name="$(env_value DB_NAME "$path")"
    [ -n "$db_name" ] || die "environment file must set a non-empty DB_NAME"
}

install_versioned_env() {
    local source="$1"
    local approval_required="${2:-0}"
    PREPARE_ENV="$SHARED_ENV_ROOT/$RELEASE_ID.env"
    [ ! -e "$PREPARE_ENV" ] && [ ! -L "$PREPARE_ENV" ] || die "versioned env already exists: $PREPARE_ENV"
    validate_environment_file "$source"
    ENV_SHA256="$(sha256sum "$source" | awk '{print tolower($1)}')"
    local db_name
    db_name="$(env_value DB_NAME "$source")"
    DB_NAME_SHA256="$(printf '%s' "$db_name" | sha256sum | awk '{print tolower($1)}')"
    if [ "$approval_required" -eq 1 ]; then
        [ "$ENV_SHA256" = "$EXPECTED_ENV_SHA256" ] || die "environment bytes do not match the externally approved SHA-256"
        [ "$DB_NAME_SHA256" = "$EXPECTED_DB_NAME_SHA256" ] || die "environment DB_NAME binding does not match the externally approved SHA-256 commitment"
        ENV_SHA256="$EXPECTED_ENV_SHA256"
        DB_NAME_SHA256="$EXPECTED_DB_NAME_SHA256"
    fi
    install -m 640 -- "$source" "$PREPARE_ENV"
    PREPARE_ENV_CREATED=1
    chown "$PROTECTED_OWNER" "$PREPARE_ENV"
    require_regular_not_link "versioned env" "$PREPARE_ENV"
    require_exact_owner "versioned env" "$PREPARE_ENV" "$PROTECTED_OWNER"
    require_exact_mode "versioned env" "$PREPARE_ENV" "640"
    [ "$(sha256sum "$PREPARE_ENV" | awk '{print tolower($1)}')" = "$ENV_SHA256" ] || die "versioned env hash changed during installation"
}

validate_archive_entry_name() {
    local original="$1"
    local name="$original"
    [ -n "$name" ] || die "archive contains an empty entry name"
    case "$name" in
        *'\'*) die "archive entry contains a backslash: $original" ;;
        /*) die "archive entry is absolute: $original" ;;
        [A-Za-z]:/*) die "archive entry uses a drive-qualified path: $original" ;;
        *$'\n'*|*$'\r'*) die "archive entry contains a line break" ;;
    esac
    while [[ "$name" == ./* ]]; do
        name="${name#./}"
    done
    [ -n "$name" ] || return 0
    local component
    local IFS='/'
    read -r -a components <<< "$name"
    for component in "${components[@]}"; do
        [ -n "$component" ] && [ "$component" != "." ] || die "archive entry contains an empty or dot component: $original"
        [ "$component" != ".." ] || die "archive entry traverses outside release root: $original"
    done
    case "${components[0]}" in
        .env|.user.ini|.git|.deploy|.release-id|.release-source|.release-manifest-sha256|.release-env-sha256|.release-db-name-sha256|.baseline-content-manifest.json|releases|shared|current)
            die "archive contains reserved top-level entry: ${components[0]}"
            ;;
    esac
}

validate_tar_archive() {
    require_command tar
    tar -tzf "$ARCHIVE" >/dev/null
    local line type name
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        type="${line:0:1}"
        case "$type" in
            -|d) ;;
            *) die "tar archive contains a link or special entry" ;;
        esac
    done < <(tar -tvzf "$ARCHIVE")
    while IFS= read -r name; do
        validate_archive_entry_name "$name"
    done < <(tar -tzf "$ARCHIVE")
}

validate_zip_archive() {
    require_command unzip
    "$PHP_BIN" -r '
        if (!class_exists("ZipArchive")) {
            fwrite(STDERR, "ZipArchive extension is required for strict zip validation\n");
            exit(2);
        }
        $path = $argv[1] ?? "";
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            fwrite(STDERR, "cannot open zip archive\n");
            exit(3);
        }
        $reserved = [".env", ".user.ini", ".git", ".deploy", ".release-id", ".release-source", ".release-manifest-sha256", ".release-env-sha256", ".release-db-name-sha256", ".baseline-content-manifest.json", "releases", "shared", "current"];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || $name === "" || str_contains($name, "\\") || str_starts_with($name, "/") || preg_match("/^[A-Za-z]:\\//", $name) || preg_match("/[\x00-\x1F\x7F]/", $name)) {
                fwrite(STDERR, "zip contains an unsafe entry name\n");
                exit(4);
            }
            while (str_starts_with($name, "./")) {
                $name = substr($name, 2);
            }
            if ($name !== "") {
                $parts = explode("/", rtrim($name, "/"));
                if (in_array("", $parts, true) || in_array(".", $parts, true) || in_array("..", $parts, true) || in_array($parts[0] ?? "", $reserved, true)) {
                    fwrite(STDERR, "zip contains traversal or a reserved top-level entry\n");
                    exit(5);
                }
            }
            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
                $mode = ($attributes >> 16) & 0xffff;
                $type = $mode & 0170000;
                if (!in_array($type, [0, 0040000, 0100000], true)) {
                    fwrite(STDERR, "zip contains a link or special entry\n");
                    exit(6);
                }
            }
        }
        $zip->close();
    ' "$ARCHIVE"
}

validate_archive() {
    require_regular_not_link "staged archive" "$STAGED_ARCHIVE"
    require_exact_owner "staged archive" "$STAGED_ARCHIVE" "root:root"
    require_exact_mode "staged archive" "$STAGED_ARCHIVE" "600"
    require_command sha256sum
    ARCHIVE_SHA256="$(printf '%s' "$ARCHIVE_SHA256" | tr '[:upper:]' '[:lower:]')"
    [[ "$ARCHIVE_SHA256" =~ ^[0-9a-f]{64}$ ]] || die "--archive-sha256 must be exactly 64 hexadecimal characters"
    local actual
    actual="$(sha256sum "$STAGED_ARCHIVE" | awk '{print tolower($1)}')"
    [ "$actual" = "$ARCHIVE_SHA256" ] || die "archive SHA-256 mismatch"

    ARCHIVE="$STAGED_ARCHIVE"
    case "$ARCHIVE_FORMAT" in
        zip) validate_zip_archive ;;
        tar.gz) validate_tar_archive ;;
        *) die "internal archive format is invalid" ;;
    esac
}

extract_archive() {
    local destination="$1"
    case "$ARCHIVE_FORMAT" in
        zip)
            unzip -q "$ARCHIVE" -d "$destination"
            ;;
        tar.gz)
            tar -xzf "$ARCHIVE" --no-same-owner --no-same-permissions -C "$destination"
            ;;
    esac
}

validate_extracted_tree() {
    local root="$1"
    local link special relative
    link="$(find "$root" -type l -print -quit)"
    [ -z "$link" ] || die "extracted release contains a symlink"
    special="$(find "$root" ! -type f ! -type d -print -quit)"
    [ -z "$special" ] || die "extracted release contains a special filesystem entry"
    while IFS= read -r -d '' relative; do
        relative="${relative#"$root"/}"
        [ "$relative" != "$root" ] || continue
        validate_archive_entry_name "$relative"
    done < <(find "$root" -mindepth 1 -print0)
}

validate_release_provenance() {
    local release_root="$1"
    local phase="$2"
    local expected_id="${3:-$RELEASE_ID}"
    local manifest_sha
    manifest_sha="$("$PHP_BIN" -r '
        $fail = static function (string $message): never {
            fwrite(STDERR, "release provenance validation failed: " . $message . PHP_EOL);
            exit(71);
        };
        $stripBom = static function (string $value): string {
            return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
        };
        $readFile = static function (string $path) use ($fail, $stripBom): string {
            if (!is_file($path) || is_link($path)) {
                $fail("required marker is missing or is a link: " . basename($path));
            }
            $value = file_get_contents($path);
            if (!is_string($value)) {
                $fail("cannot read marker: " . basename($path));
            }
            return $stripBom($value);
        };
        $readScalar = static function (string $path) use ($readFile, $fail): string {
            $value = str_replace(["\r\n", "\r"], "\n", $readFile($path));
            $value = rtrim($value, "\n");
            if ($value === "" || str_contains($value, "\n")) {
                $fail("marker must contain exactly one non-empty line: " . basename($path));
            }
            return $value;
        };
        $root = rtrim($argv[1] ?? "", "/\\");
        $expectedId = $argv[2] ?? "";
        $phase = $argv[3] ?? "";
        if ($root === "" || !is_dir($root) || is_link($root)) {
            $fail("release root is invalid");
        }
        if (!in_array($phase, ["extracted", "prepared"], true)) {
            $fail("validation phase is invalid");
        }
        $releaseId = $readScalar($root . "/RELEASE-ID");
        $commit = $readScalar($root . "/RELEASE-COMMIT");
        $sourceDirty = $readScalar($root . "/RELEASE-SOURCE-DIRTY");
        $diagnostic = $readScalar($root . "/RELEASE-DIAGNOSTIC");
        if ($releaseId !== $expectedId) {
            $fail("RELEASE-ID does not match --release-id");
        }
        if (!preg_match("/^[0-9a-f]{40}$/D", $commit)) {
            $fail("RELEASE-COMMIT must be a lowercase 40-character commit id");
        }
        if ($sourceDirty !== "false") {
            $fail("sourceDirty=true or invalid RELEASE-SOURCE-DIRTY is forbidden");
        }
        if ($diagnostic !== "false") {
            $fail("diagnostic=true or invalid RELEASE-DIAGNOSTIC is forbidden");
        }
        $tagText = str_replace(["\r\n", "\r"], "\n", $readFile($root . "/RELEASE-TAGS"));
        $tagText = rtrim($tagText, "\n");
        $markerTags = $tagText === "" ? [] : explode("\n", $tagText);
        if ($markerTags === []) {
            $fail("RELEASE-TAGS is empty");
        }
        foreach ($markerTags as $tag) {
            if ($tag === "" || trim($tag) !== $tag || preg_match("/[\x00-\x1F\x7F]/", $tag)) {
                $fail("RELEASE-TAGS contains an invalid tag");
            }
        }
        $manifestPath = $root . "/RELEASE-MANIFEST.json";
        $manifestRaw = $readFile($manifestPath);
        try {
            $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $fail("RELEASE-MANIFEST.json is not valid JSON");
        }
        if (!is_array($manifest) || array_is_list($manifest)) {
            $fail("manifest root must be an object");
        }
        if (($manifest["manifestVersion"] ?? null) !== 1) {
            $fail("manifestVersion must equal 1");
        }
        if (($manifest["releaseId"] ?? null) !== $releaseId) {
            $fail("manifest releaseId does not match RELEASE-ID");
        }
        if (($manifest["gitCommit"] ?? null) !== $commit) {
            $fail("manifest gitCommit does not match RELEASE-COMMIT");
        }
        if (($manifest["sourceDirty"] ?? null) !== false) {
            $fail("manifest sourceDirty must be false");
        }
        if (($manifest["diagnostic"] ?? null) !== false) {
            $fail("manifest diagnostic must be false");
        }
        $gitTags = $manifest["gitTags"] ?? null;
        $releaseTags = $manifest["releaseTags"] ?? null;
        if (!is_array($gitTags) || !array_is_list($gitTags) || !is_array($releaseTags) || !array_is_list($releaseTags)) {
            $fail("manifest tag fields must be arrays");
        }
        foreach ($gitTags as $tag) {
            if (!is_string($tag) || $tag === "" || trim($tag) !== $tag || preg_match("/[\x00-\x1F\x7F]/", $tag)) {
                $fail("manifest gitTags contains an invalid tag");
            }
        }
        if ($gitTags !== $markerTags) {
            $fail("RELEASE-TAGS does not exactly match manifest gitTags");
        }
        $allowedPattern = "#^(?:candidate|release)/oa-.+$#D";
        $filteredReleaseTags = [];
        foreach ($gitTags as $tag) {
            if (preg_match($allowedPattern, $tag)) {
                $filteredReleaseTags[] = $tag;
            }
        }
        if ($filteredReleaseTags === [] || $releaseTags !== $filteredReleaseTags) {
            $fail("at least one candidate/oa-* or release/oa-* tag is required and releaseTags must match");
        }
        $files = $manifest["files"] ?? null;
        $fileCount = $manifest["fileCount"] ?? null;
        if (!is_array($files) || !array_is_list($files) || !is_int($fileCount) || $fileCount !== count($files)) {
            $fail("manifest fileCount does not match files");
        }
        $expected = [];
        foreach ($files as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                $fail("manifest contains an invalid file entry");
            }
            $path = $entry["path"] ?? null;
            $bytes = $entry["bytes"] ?? null;
            $sha = $entry["sha256"] ?? null;
            if (!is_string($path) || $path === "" || str_contains($path, "\\") || str_starts_with($path, "/") || preg_match("/^[A-Za-z]:\//", $path) || preg_match("/[\x00-\x1F\x7F]/", $path)) {
                $fail("manifest contains an unsafe file path");
            }
            $parts = explode("/", $path);
            if (in_array("", $parts, true) || in_array(".", $parts, true) || in_array("..", $parts, true) || $path === "RELEASE-MANIFEST.json") {
                $fail("manifest contains an unsafe or self-referential file path");
            }
            if (isset($expected[$path])) {
                $fail("manifest contains a duplicate file path: " . $path);
            }
            if (!is_int($bytes) || $bytes < 0 || !is_string($sha) || !preg_match("/^[0-9a-f]{64}$/D", $sha)) {
                $fail("manifest file metadata is invalid: " . $path);
            }
            $expected[$path] = true;
            $persistentPlaceholder = preg_match("#^(?:public/(?:upload|storage)|runtime/(?:log|session|storage|upload|backup))/\.gitignore$#D", $path) === 1;
            if ($phase === "prepared" && $persistentPlaceholder) {
                continue;
            }
            $full = $root . "/" . $path;
            if (!is_file($full) || is_link($full)) {
                $fail("manifest file is missing or is a link: " . $path);
            }
            if (filesize($full) !== $bytes || hash_file("sha256", $full) !== $sha) {
                $fail("manifest file bytes or hash mismatch: " . $path);
            }
        }
        $allowedPreparedLinks = array_fill_keys([
            ".env", "public/.user.ini", "public/upload", "public/storage",
            "runtime/log", "runtime/session", "runtime/storage", "runtime/upload", "runtime/backup"
        ], true);
        $ignoredPreparedFiles = array_fill_keys([
            ".release-id", ".release-source", ".release-manifest-sha256", ".release-env-sha256", ".release-db-name-sha256"
        ], true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $info) {
            $full = str_replace("\\", "/", $info->getPathname());
            $base = str_replace("\\", "/", $root) . "/";
            $path = substr($full, strlen($base));
            if ($info->isLink()) {
                if ($phase === "prepared" && isset($allowedPreparedLinks[$path])) {
                    continue;
                }
                $fail("unexpected symlink in release provenance tree: " . $path);
            }
            if (!$info->isFile()) {
                continue;
            }
            if ($path === "RELEASE-MANIFEST.json") {
                continue;
            }
            if ($phase === "prepared" && (isset($ignoredPreparedFiles[$path]) || str_starts_with($path, "runtime/cache/") || str_starts_with($path, "runtime/temp/"))) {
                continue;
            }
            if (!isset($expected[$path])) {
                $fail("package contains a file omitted from manifest: " . $path);
            }
        }
        echo hash("sha256", $manifestRaw);
    ' "$release_root" "$expected_id" "$phase")" || die "release provenance validation failed"
    [[ "$manifest_sha" =~ ^[0-9a-f]{64}$ ]] || die "release provenance validator returned an invalid manifest hash"
    printf '%s\n' "$manifest_sha"
}

create_baseline_content_manifest() {
    local release_root="$1"
    local manifest_path="$release_root/.baseline-content-manifest.json"
    [ ! -e "$manifest_path" ] && [ ! -L "$manifest_path" ] || die "baseline content manifest already exists"
    local manifest_sha
    manifest_sha="$("$PHP_BIN" -r '
        $root = rtrim($argv[1] ?? "", "/\\");
        $releaseId = $argv[2] ?? "";
        $manifestPath = $root . "/.baseline-content-manifest.json";
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $info) {
            if ($info->isLink()) {
                fwrite(STDERR, "baseline source contains a symlink\n");
                exit(72);
            }
            if (!$info->isFile()) {
                continue;
            }
            $full = str_replace("\\", "/", $info->getPathname());
            $base = str_replace("\\", "/", $root) . "/";
            $path = substr($full, strlen($base));
            if ($path === ".baseline-content-manifest.json") {
                continue;
            }
            $files[] = [
                "path" => $path,
                "bytes" => $info->getSize(),
                "sha256" => hash_file("sha256", $info->getPathname()),
            ];
        }
        usort($files, static fn(array $left, array $right): int => strcmp($left["path"], $right["path"]));
        $manifest = [
            "manifestVersion" => 1,
            "kind" => "baseline-copy",
            "releaseId" => $releaseId,
            "fileCount" => count($files),
            "files" => $files,
        ];
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($manifestPath, $json, LOCK_EX) !== strlen($json)) {
            fwrite(STDERR, "cannot write baseline content manifest\n");
            exit(73);
        }
        echo hash("sha256", $json);
    ' "$release_root" "$RELEASE_ID")" || die "unable to create baseline content manifest"
    chmod 600 "$manifest_path"
    chown root:root "$manifest_path"
    [[ "$manifest_sha" =~ ^[0-9a-f]{64}$ ]] || die "baseline content manifest hash is invalid"
    printf '%s\n' "$manifest_sha"
}

validate_baseline_content_manifest() {
    local release_root="$1"
    local expected_id="$2"
    local manifest_sha
    manifest_sha="$("$PHP_BIN" -r '
        $fail = static function (string $message): never {
            fwrite(STDERR, "baseline content validation failed: " . $message . PHP_EOL);
            exit(74);
        };
        $root = rtrim($argv[1] ?? "", "/\\");
        $expectedId = $argv[2] ?? "";
        $manifestPath = $root . "/.baseline-content-manifest.json";
        if (!is_file($manifestPath) || is_link($manifestPath)) {
            $fail("manifest is missing or is a link");
        }
        $raw = file_get_contents($manifestPath);
        if (!is_string($raw)) {
            $fail("manifest cannot be read");
        }
        try {
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $fail("manifest JSON is invalid");
        }
        if (!is_array($manifest) || array_is_list($manifest) || ($manifest["manifestVersion"] ?? null) !== 1 || ($manifest["kind"] ?? null) !== "baseline-copy" || ($manifest["releaseId"] ?? null) !== $expectedId) {
            $fail("manifest identity is invalid");
        }
        $files = $manifest["files"] ?? null;
        if (!is_array($files) || !array_is_list($files) || ($manifest["fileCount"] ?? null) !== count($files)) {
            $fail("manifest fileCount is invalid");
        }
        $expected = [];
        foreach ($files as $entry) {
            $path = is_array($entry) ? ($entry["path"] ?? null) : null;
            $bytes = is_array($entry) ? ($entry["bytes"] ?? null) : null;
            $sha = is_array($entry) ? ($entry["sha256"] ?? null) : null;
            if (!is_string($path) || $path === "" || str_contains($path, "\\") || str_starts_with($path, "/") || preg_match("/^[A-Za-z]:\//", $path) || preg_match("/[\x00-\x1F\x7F]/", $path)) {
                $fail("manifest contains an unsafe path");
            }
            $parts = explode("/", $path);
            if (in_array("", $parts, true) || in_array(".", $parts, true) || in_array("..", $parts, true) || $path === ".baseline-content-manifest.json" || isset($expected[$path])) {
                $fail("manifest contains a duplicate or invalid path");
            }
            if (!is_int($bytes) || $bytes < 0 || !is_string($sha) || !preg_match("/^[0-9a-f]{64}$/D", $sha)) {
                $fail("manifest file metadata is invalid");
            }
            $expected[$path] = true;
            $placeholder = preg_match("#^(?:public/(?:upload|storage)|runtime/(?:log|session|storage|upload|backup))/\.gitignore$#D", $path) === 1;
            if ($placeholder) {
                continue;
            }
            $full = $root . "/" . $path;
            if (!is_file($full) || is_link($full) || filesize($full) !== $bytes || hash_file("sha256", $full) !== $sha) {
                $fail("baseline file hash mismatch: " . $path);
            }
        }
        $allowedLinks = array_fill_keys([
            ".env", "public/.user.ini", "public/upload", "public/storage",
            "runtime/log", "runtime/session", "runtime/storage", "runtime/upload", "runtime/backup"
        ], true);
        $ignoredFiles = array_fill_keys([
            ".baseline-content-manifest.json", ".release-id", ".release-source", ".release-manifest-sha256",
            ".release-env-sha256", ".release-db-name-sha256"
        ], true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $info) {
            $full = str_replace("\\", "/", $info->getPathname());
            $base = str_replace("\\", "/", $root) . "/";
            $path = substr($full, strlen($base));
            if ($info->isLink()) {
                if (isset($allowedLinks[$path])) {
                    continue;
                }
                $fail("unexpected symlink: " . $path);
            }
            if (!$info->isFile() || isset($ignoredFiles[$path]) || str_starts_with($path, "runtime/cache/") || str_starts_with($path, "runtime/temp/")) {
                continue;
            }
            if (!isset($expected[$path])) {
                $fail("baseline contains a file omitted from its manifest: " . $path);
            }
        }
        echo hash("sha256", $raw);
    ' "$release_root" "$expected_id")" || die "baseline content validation failed"
    [[ "$manifest_sha" =~ ^[0-9a-f]{64}$ ]] || die "baseline content validator returned an invalid hash"
    printf '%s\n' "$manifest_sha"
}

normalize_baseline_code_permissions() {
    local release_root="$1"
    validate_extracted_tree "$release_root"
    chown -R root:root "$release_root"
    find "$release_root" -type d -exec chmod 755 {} +
    find "$release_root" -type f -perm /111 -exec chmod 755 {} +
    find "$release_root" -type f ! -perm /111 -exec chmod 644 {} +
}

validate_baseline_code_permissions() {
    local release_root="$1"
    local entry owner mode mode_value
    while IFS= read -r -d '' entry; do
        case "$entry" in
            "$release_root/runtime"|"$release_root/runtime"/*) continue ;;
        esac
        [ ! -L "$entry" ] || continue
        if [ ! -f "$entry" ] && [ ! -d "$entry" ]; then
            die "baseline contains a special filesystem entry: $entry"
        fi
        owner="$(stat -c '%U:%G' -- "$entry")"
        [ "$owner" = "root:root" ] || die "baseline code entry must be owned by root:root: $entry"
        mode="$(stat -c '%a' -- "$entry")"
        mode_value=$((8#$mode))
        [ $((mode_value & 0022)) -eq 0 ] || die "baseline code entry is group- or world-writable: $entry"
    done < <(find "$release_root" -xdev -print0)
}

remove_release_placeholder() {
    local path="$1"
    if [ ! -e "$path" ] && [ ! -L "$path" ]; then
        return 0
    fi
    [ -d "$path" ] && [ ! -L "$path" ] || die "release placeholder is not a physical directory: $path"
    local entry base
    while IFS= read -r -d '' entry; do
        base="$(basename "$entry")"
        if [ "$base" = ".gitignore" ] && [ -f "$entry" ] && [ ! -L "$entry" ]; then
            rm -f -- "$entry"
        else
            die "release placeholder is not empty: $path"
        fi
    done < <(find "$path" -mindepth 1 -maxdepth 1 -print0)
    rmdir -- "$path"
}

link_persistent_paths() {
    local release_root="$1"
    local name source target
    [ -d "$release_root/public" ] || die "release missing public directory"
    mkdir -p "$release_root/runtime"
    [ ! -L "$release_root/runtime" ] || die "release runtime must not be a symlink"

    ensure_physical_directory "stable runtime root" "$ROOT/runtime"
    ensure_physical_directory "stable public root" "$ROOT/public"
    chmod 750 "$ROOT/runtime" "$release_root/runtime"
    chown "$PROTECTED_OWNER" "$ROOT/runtime" "$release_root/runtime"
    require_exact_owner "stable runtime parent" "$ROOT/runtime" "$PROTECTED_OWNER"
    require_exact_mode "stable runtime parent" "$ROOT/runtime" "750"
    require_exact_owner "release runtime parent" "$release_root/runtime" "$PROTECTED_OWNER"
    require_exact_mode "release runtime parent" "$release_root/runtime" "750"
    for target in \
        "$ROOT/runtime/log" \
        "$ROOT/runtime/session" \
        "$ROOT/runtime/storage" \
        "$ROOT/runtime/upload" \
        "$ROOT/runtime/backup" \
        "$ROOT/public/upload" \
        "$ROOT/public/storage"; do
        ensure_physical_directory "stable mutable directory" "$target"
    done

    for name in log session storage upload backup; do
        source="$ROOT/runtime/$name"
        target="$release_root/runtime/$name"
        remove_release_placeholder "$target"
        ln -s "$source" "$target"
    done

    for name in upload storage; do
        source="$ROOT/public/$name"
        target="$release_root/public/$name"
        remove_release_placeholder "$target"
        ln -s "$source" "$target"
    done

    mkdir -p "$release_root/runtime/cache" "$release_root/runtime/temp"
    chmod 750 "$release_root/runtime/cache" "$release_root/runtime/temp" 2>/dev/null || true
    chown "$SERVICE_OWNER" \
        "$ROOT/runtime/log" "$ROOT/runtime/session" "$ROOT/runtime/storage" "$ROOT/runtime/upload" "$ROOT/runtime/backup" \
        "$ROOT/public/upload" "$ROOT/public/storage"
    chown -R "$SERVICE_OWNER" "$release_root/runtime/cache" "$release_root/runtime/temp"
    require_exact_owner "release runtime cache" "$release_root/runtime/cache" "$SERVICE_OWNER"
    require_exact_owner "release runtime temp" "$release_root/runtime/temp" "$SERVICE_OWNER"

    [ ! -e "$release_root/.env" ] && [ ! -L "$release_root/.env" ] || die "release unexpectedly contains .env"
    ln -s "$PREPARE_ENV" "$release_root/.env"

    if [ -f "$ROOT/.user.ini" ] && [ ! -L "$ROOT/.user.ini" ]; then
        [ ! -e "$release_root/public/.user.ini" ] && [ ! -L "$release_root/public/.user.ini" ] || die "release unexpectedly contains public/.user.ini"
        ln -s "$ROOT/.user.ini" "$release_root/public/.user.ini"
    fi
}

write_release_marker() {
    local release_root="$1"
    local source_kind="$2"
    local manifest_sha="$3"
    local env_sha="$4"
    local db_name_sha="$5"
    printf '%s\n' "$RELEASE_ID" > "$release_root/.release-id"
    printf '%s\n' "$source_kind" > "$release_root/.release-source"
    printf '%s\n' "$manifest_sha" > "$release_root/.release-manifest-sha256"
    printf '%s\n' "$env_sha" > "$release_root/.release-env-sha256"
    printf '%s\n' "$db_name_sha" > "$release_root/.release-db-name-sha256"
    chmod 600 \
        "$release_root/.release-id" "$release_root/.release-source" "$release_root/.release-manifest-sha256" \
        "$release_root/.release-env-sha256" "$release_root/.release-db-name-sha256" 2>/dev/null || true
    chown root:root \
        "$release_root/.release-id" "$release_root/.release-source" "$release_root/.release-manifest-sha256" \
        "$release_root/.release-env-sha256" "$release_root/.release-db-name-sha256"
}

write_release_provenance_record() {
    local id="$1"
    local source_kind="$2"
    local manifest_sha="$3"
    local env_sha="$4"
    local db_name_sha="$5"
    local path="$PROVENANCE_ROOT/$id.txt"
    local temporary="$PROVENANCE_ROOT/.provenance-$id-$$"
    [ ! -e "$path" ] && [ ! -L "$path" ] || die "release provenance record already exists: $path"
    {
        printf 'release=%s\n' "$id"
        printf 'archive_sha256=%s\n' "$source_kind"
        printf 'manifest_sha256=%s\n' "$manifest_sha"
        printf 'env_sha256=%s\n' "$env_sha"
        printf 'expected_db_name_sha256=%s\n' "$db_name_sha"
        printf 'database_write=none\n'
    } > "$temporary"
    chmod 600 "$temporary" 2>/dev/null || true
    mv -f -- "$temporary" "$path"
}

validate_release_provenance_record() {
    local id="$1"
    local source_kind="$2"
    local manifest_sha="$3"
    local env_sha="$4"
    local db_name_sha="$5"
    local path="$PROVENANCE_ROOT/$id.txt"
    require_regular_not_link "release provenance record" "$path"
    [ "$(wc -l < "$path" | tr -d '[:space:]')" = "6" ] || die "release provenance record has an unexpected shape"
    grep -Fqx -- "release=$id" "$path" || die "release provenance record release mismatch"
    grep -Fqx -- "archive_sha256=$source_kind" "$path" || die "release provenance record package hash mismatch"
    grep -Fqx -- "manifest_sha256=$manifest_sha" "$path" || die "release provenance record manifest hash mismatch"
    grep -Fqx -- "env_sha256=$env_sha" "$path" || die "release provenance record env hash mismatch"
    grep -Fqx -- "expected_db_name_sha256=$db_name_sha" "$path" || die "release provenance record expected database binding mismatch"
    grep -Fqx -- "database_write=none" "$path" || die "release provenance record database gate mismatch"
}

require_expected_link() {
    local link="$1"
    local expected="$2"
    [ -L "$link" ] || die "required release link is missing: $link"
    local resolved expected_resolved
    resolved="$(readlink -f "$link")"
    expected_resolved="$(readlink -f "$expected")"
    [ -n "$expected_resolved" ] || die "expected release link target is missing: $expected"
    [ "$link" -ef "$expected" ] || die "release link target mismatch: $link (resolved=$resolved expected=$expected_resolved)"
}

validate_prepared_release() {
    local id="$1"
    validate_release_id "$id"
    local release_root="$RELEASES_ROOT/$id"
    [ -d "$release_root" ] && [ ! -L "$release_root" ] || die "prepared release is missing: $release_root"
    [ -f "$release_root/public/index.php" ] || die "release missing public/index.php"
    [ -f "$release_root/vendor/autoload.php" ] || die "release missing vendor/autoload.php"
    [ -f "$release_root/snowy-admin-web/dist/index.html" ] || die "release missing frontend dist/index.html"
    [ -f "$release_root/.release-id" ] || die "release marker missing"
    [ "$(tr -d '\r\n' < "$release_root/.release-id")" = "$id" ] || die "release marker mismatch"
    [ -f "$release_root/.release-source" ] && [ ! -L "$release_root/.release-source" ] || die "release source marker missing"
    [ -f "$release_root/.release-manifest-sha256" ] && [ ! -L "$release_root/.release-manifest-sha256" ] || die "release manifest hash marker missing"
    [ -f "$release_root/.release-env-sha256" ] && [ ! -L "$release_root/.release-env-sha256" ] || die "release env hash marker missing"
    [ -f "$release_root/.release-db-name-sha256" ] && [ ! -L "$release_root/.release-db-name-sha256" ] || die "release database binding marker missing"
    local protected_marker
    for protected_marker in \
        "$release_root/.release-id" "$release_root/.release-source" "$release_root/.release-manifest-sha256" \
        "$release_root/.release-env-sha256" "$release_root/.release-db-name-sha256"; do
        require_regular_not_link "protected release marker" "$protected_marker"
        require_exact_owner "protected release marker" "$protected_marker" "root:root"
        require_exact_mode "protected release marker" "$protected_marker" "600"
    done
    local source_marker manifest_marker env_marker db_name_marker actual_manifest_sha
    source_marker="$(tr -d '\r\n' < "$release_root/.release-source")"
    manifest_marker="$(tr -d '\r\n' < "$release_root/.release-manifest-sha256")"
    env_marker="$(tr -d '\r\n' < "$release_root/.release-env-sha256")"
    db_name_marker="$(tr -d '\r\n' < "$release_root/.release-db-name-sha256")"
    [[ "$env_marker" =~ ^[0-9a-f]{64}$ ]] || die "prepared release env hash marker is invalid"
    [[ "$db_name_marker" =~ ^[0-9a-f]{64}$ ]] || die "prepared release database binding marker is invalid"
    if [ "$source_marker" = "baseline-copy" ]; then
        case "$id" in
            baseline-*) ;;
            *) die "only a baseline-* release may use baseline-copy provenance" ;;
        esac
        [[ "$manifest_marker" =~ ^[0-9a-f]{64}$ ]] || die "baseline manifest provenance marker is invalid"
        require_regular_not_link "baseline content manifest" "$release_root/.baseline-content-manifest.json"
        require_exact_owner "baseline content manifest" "$release_root/.baseline-content-manifest.json" "root:root"
        require_exact_mode "baseline content manifest" "$release_root/.baseline-content-manifest.json" "600"
        actual_manifest_sha="$(validate_baseline_content_manifest "$release_root" "$id")"
        [ "$actual_manifest_sha" = "$manifest_marker" ] || die "baseline content manifest hash changed after initialization"
        validate_baseline_code_permissions "$release_root"
    else
        [[ "$source_marker" =~ ^[0-9a-f]{64}$ ]] || die "prepared release package hash marker is invalid"
        [[ "$manifest_marker" =~ ^[0-9a-f]{64}$ ]] || die "prepared release manifest hash marker is invalid"
        actual_manifest_sha="$(validate_release_provenance "$release_root" prepared "$id")"
        [ "$actual_manifest_sha" = "$manifest_marker" ] || die "prepared release manifest hash changed after prepare"
    fi
    validate_release_provenance_record "$id" "$source_marker" "$manifest_marker" "$env_marker" "$db_name_marker"
    require_regular_not_link "versioned release env" "$SHARED_ENV_ROOT/$id.env"
    require_exact_owner "versioned release env" "$SHARED_ENV_ROOT/$id.env" "$PROTECTED_OWNER"
    require_exact_mode "versioned release env" "$SHARED_ENV_ROOT/$id.env" "640"
    [ "$(sha256sum "$SHARED_ENV_ROOT/$id.env" | awk '{print tolower($1)}')" = "$env_marker" ] || die "versioned release env hash mismatch"
    local current_db_name current_db_name_sha
    current_db_name="$(env_value DB_NAME "$SHARED_ENV_ROOT/$id.env")"
    current_db_name_sha="$(printf '%s' "$current_db_name" | sha256sum | awk '{print tolower($1)}')"
    [ "$current_db_name_sha" = "$db_name_marker" ] || die "versioned release expected database binding mismatch"
    require_expected_link "$release_root/.env" "$SHARED_ENV_ROOT/$id.env"
    validate_environment_file "$SHARED_ENV_ROOT/$id.env"
    local name
    for name in log session storage upload backup; do
        require_expected_link "$release_root/runtime/$name" "$ROOT/runtime/$name"
    done
    for name in upload storage; do
        require_expected_link "$release_root/public/$name" "$ROOT/public/$name"
    done
    if [ -e "$release_root/public/.user.ini" ] || [ -L "$release_root/public/.user.ini" ]; then
        require_regular_not_link "stable .user.ini" "$ROOT/.user.ini"
        require_expected_link "$release_root/public/.user.ini" "$ROOT/.user.ini"
    elif [ -e "$ROOT/.user.ini" ] || [ -L "$ROOT/.user.ini" ]; then
        die "release is missing the stable public/.user.ini link"
    fi
}

validate_release_binding_approval() {
    local id="$1"
    local approved_env_sha="$2"
    local approved_db_name_sha="$3"
    local release_root="$RELEASES_ROOT/$id"
    local pinned_env_sha pinned_db_name_sha
    pinned_env_sha="$(tr -d '\r\n' < "$release_root/.release-env-sha256")"
    pinned_db_name_sha="$(tr -d '\r\n' < "$release_root/.release-db-name-sha256")"
    [ "$pinned_env_sha" = "$approved_env_sha" ] || die "selected release environment does not match the externally approved SHA-256"
    [ "$pinned_db_name_sha" = "$approved_db_name_sha" ] || die "selected release DB_NAME binding does not match the externally approved SHA-256 commitment"
}

finalize_prepared_release() {
    local source_kind="$1"
    local manifest_sha="$2"
    local final="$RELEASES_ROOT/$RELEASE_ID"
    [ ! -e "$final" ] && [ ! -L "$final" ] || die "release already exists: $final"
    write_release_marker "$PREPARE_TEMP" "$source_kind" "$manifest_sha" "$ENV_SHA256" "$DB_NAME_SHA256"
    mv -T -- "$PREPARE_TEMP" "$final"
    PREPARE_FINALIZED=1
    PREPARE_TEMP=""
    write_release_provenance_record "$RELEASE_ID" "$source_kind" "$manifest_sha" "$ENV_SHA256" "$DB_NAME_SHA256"
    validate_prepared_release "$RELEASE_ID"
}

prepare_candidate() {
    [ -n "$ARCHIVE" ] || die "prepare requires --archive"
    [ -n "$ARCHIVE_SHA256" ] || die "prepare requires --archive-sha256"
    [ -n "$ENV_SOURCE" ] || die "prepare requires --env-source"
    require_expected_binding_approvals
    case "$ARCHIVE" in
        *.zip) ARCHIVE_FORMAT="zip" ;;
        *.tar.gz|*.tgz) ARCHIVE_FORMAT="tar.gz" ;;
        *) die "archive must end in .zip, .tar.gz, or .tgz" ;;
    esac
    stage_root_owned_input "archive" "$ARCHIVE" "archive" STAGED_ARCHIVE
    stage_root_owned_input "env source" "$ENV_SOURCE" "env" STAGED_ENV
    validate_archive

    PREPARE_TEMP="$RELEASES_ROOT/.prepare-$RELEASE_ID-$$"
    [ ! -e "$PREPARE_TEMP" ] && [ ! -L "$PREPARE_TEMP" ] || die "prepare temp already exists"
    mkdir -m 755 "$PREPARE_TEMP"
    extract_archive "$PREPARE_TEMP"
    validate_extracted_tree "$PREPARE_TEMP"
    local manifest_sha
    manifest_sha="$(validate_release_provenance "$PREPARE_TEMP" extracted)"
    install_versioned_env "$STAGED_ENV" 1
    link_persistent_paths "$PREPARE_TEMP"
    finalize_prepared_release "$ARCHIVE_SHA256" "$manifest_sha"
    write_operation_manifest "prepare" "" "$RELEASE_ID"
    clear_staged_inputs
    log "candidate prepared; current was not changed: $RELEASE_ID"
}

copy_baseline() {
    case "$RELEASE_ID" in
        baseline-*) ;;
        *) die "baseline release id must start with baseline-" ;;
    esac
    stage_root_owned_input "live env" "$ROOT/.env" "env" STAGED_ENV
    require_command rsync

    PREPARE_TEMP="$RELEASES_ROOT/.prepare-$RELEASE_ID-$$"
    [ ! -e "$PREPARE_TEMP" ] && [ ! -L "$PREPARE_TEMP" ] || die "prepare temp already exists"
    mkdir -m 755 "$PREPARE_TEMP"
    rsync -a \
        --exclude='/.deploy/' \
        --exclude='/releases/' \
        --exclude='/shared/' \
        --exclude='/current' \
        --exclude='/.env' \
        --exclude='/.user.ini' \
        --exclude='/.release-id' \
        --exclude='/.release-source' \
        --exclude='/.release-manifest-sha256' \
        --exclude='/.release-env-sha256' \
        --exclude='/.release-db-name-sha256' \
        --exclude='/.baseline-content-manifest.json' \
        --exclude='/runtime/' \
        --exclude='/public/upload/' \
        --exclude='/public/storage/' \
        "$ROOT/" "$PREPARE_TEMP/"
    validate_extracted_tree "$PREPARE_TEMP"
    normalize_baseline_code_permissions "$PREPARE_TEMP"
    validate_baseline_code_permissions "$PREPARE_TEMP"
    local baseline_manifest_sha
    baseline_manifest_sha="$(create_baseline_content_manifest "$PREPARE_TEMP")"
    [ "$(validate_baseline_content_manifest "$PREPARE_TEMP" "$RELEASE_ID")" = "$baseline_manifest_sha" ] \
        || die "baseline content manifest changed immediately after creation"
    validate_baseline_code_permissions "$PREPARE_TEMP"
    if [ -n "$EXPECTED_ENV_SHA256" ] || [ -n "$EXPECTED_DB_NAME_SHA256" ]; then
        require_expected_binding_approvals
        install_versioned_env "$STAGED_ENV" 1
    else
        install_versioned_env "$STAGED_ENV" 0
    fi
    link_persistent_paths "$PREPARE_TEMP"
    finalize_prepared_release "baseline-copy" "$baseline_manifest_sha"
    clear_staged_inputs
}

current_release_id() {
    if [ ! -e "$CURRENT_LINK" ] && [ ! -L "$CURRENT_LINK" ]; then
        printf 'absent\n'
        return 0
    fi
    [ -L "$CURRENT_LINK" ] || die "current exists but is not a symlink"
    local resolved parent releases_resolved id
    resolved="$(readlink -f "$CURRENT_LINK")"
    parent="$(dirname "$resolved")"
    releases_resolved="$(readlink -f "$RELEASES_ROOT")"
    [ "$parent" -ef "$RELEASES_ROOT" ] || die "current points outside releases"
    id="$(basename "$resolved")"
    validate_release_id "$id"
    [ -d "$resolved" ] || die "current points to a missing release"
    printf '%s\n' "$id"
}

assert_expected_current() {
    local actual
    actual="$(current_release_id)"
    [ "$actual" = "$EXPECTED_CURRENT" ] || die "expected-current compare-and-swap failed"
}

atomic_set_current() {
    local id="$1"
    local expected="$2"
    local approved_env_sha="${3:-}"
    local approved_db_name_sha="${4:-}"
    validate_prepared_release "$id"
    if [ -n "$approved_env_sha" ] || [ -n "$approved_db_name_sha" ]; then
        [ -n "$approved_env_sha" ] && [ -n "$approved_db_name_sha" ] || die "internal approved binding pair is incomplete"
        validate_release_binding_approval "$id" "$approved_env_sha" "$approved_db_name_sha"
    fi
    local temporary="$ROOT/.current-$id-$$"
    [ ! -e "$temporary" ] && [ ! -L "$temporary" ] || die "temporary current link already exists"
    ln -s "releases/$id" "$temporary"
    local actual
    actual="$(current_release_id)"
    if [ "$actual" != "$expected" ]; then
        rm -f -- "$temporary"
        die "final expected-current compare-and-swap check failed immediately before current rename"
    fi
    mv -Tf -- "$temporary" "$CURRENT_LINK"
    [ "$(current_release_id)" = "$id" ] || die "atomic current switch verification failed"
}

remove_current_if() {
    local expected="$1"
    local actual
    actual="$(current_release_id)"
    [ "$actual" = "$expected" ] || return 1
    [ -L "$CURRENT_LINK" ] || return 1
    rm -f -- "$CURRENT_LINK"
}

literal_count() {
    local file="$1"
    local needle="$2"
    grep -F -c -- "$needle" "$file" || true
}

replace_literal_once() {
    local file="$1"
    local old="$2"
    local new="$3"
    [ "$(literal_count "$file" "$old")" = "1" ] || die "Nginx path replacement did not match exactly once"
    local temporary="$file.atomic-$$"
    local old_pattern new_replacement
    old_pattern="$(printf '%s' "$old" | sed 's/[][\\.^$*#]/\\&/g')"
    new_replacement="$(printf '%s' "$new" | sed 's/[\\&#]/\\&/g')"
    cp -p -- "$file" "$temporary"
    sed -i "s#${old_pattern}#${new_replacement}#" "$temporary"
    [ "$(literal_count "$temporary" "$new")" = "1" ] || {
        rm -f -- "$temporary"
        die "Nginx path replacement verification failed"
    }
    mv -f -- "$temporary" "$file"
}

assert_nginx_current_config() {
    require_regular_not_link "vhost file" "$VHOST_FILE"
    require_regular_not_link "rewrite file" "$REWRITE_FILE"
    [ "$(literal_count "$VHOST_FILE" "root $ROOT/current/snowy-admin-web/dist;")" = "1" ] || die "vhost is not pinned to current frontend"
    [ "$(literal_count "$REWRITE_FILE" "fastcgi_param SCRIPT_FILENAME $ROOT/current/public/index.php;")" = "1" ] || die "rewrite is not pinned to current backend entry"
    [ "$(literal_count "$REWRITE_FILE" "fastcgi_param DOCUMENT_ROOT $ROOT/current/public;")" = "1" ] || die "rewrite is not pinned to current backend document root"
    [ "$(literal_count "$REWRITE_FILE" "alias $ROOT/public/storage/;")" = "1" ] || die "stable storage alias changed or is missing"
}

patch_nginx_for_current() {
    local backup_dir="$1"
    require_regular_not_link "vhost file" "$VHOST_FILE"
    require_regular_not_link "rewrite file" "$REWRITE_FILE"
    [ "$(literal_count "$REWRITE_FILE" "alias $ROOT/public/storage/;")" = "1" ] || die "stable storage alias is missing before initialization"
    mkdir -m 700 "$backup_dir"
    cp -p -- "$VHOST_FILE" "$backup_dir/vhost.conf"
    cp -p -- "$REWRITE_FILE" "$backup_dir/rewrite.conf"

    replace_literal_once "$VHOST_FILE" \
        "root $ROOT/snowy-admin-web/dist;" \
        "root $ROOT/current/snowy-admin-web/dist;"
    replace_literal_once "$REWRITE_FILE" \
        "fastcgi_param SCRIPT_FILENAME $ROOT/public/index.php;" \
        "fastcgi_param SCRIPT_FILENAME $ROOT/current/public/index.php;"
    replace_literal_once "$REWRITE_FILE" \
        "fastcgi_param DOCUMENT_ROOT $ROOT/public;" \
        "fastcgi_param DOCUMENT_ROOT $ROOT/current/public;"
    assert_nginx_current_config
}

restore_nginx_backup() {
    local backup_dir="$1"
    [ -f "$backup_dir/vhost.conf" ] && [ -f "$backup_dir/rewrite.conf" ] || return 1
    cp -p -- "$backup_dir/vhost.conf" "$VHOST_FILE"
    cp -p -- "$backup_dir/rewrite.conf" "$REWRITE_FILE"
}

run_health_checks() {
    local url
    for url in "${HEALTH_URLS[@]}"; do
        case "$url" in
            http://*|https://*) ;;
            *) return 1 ;;
        esac
        "$CURL_BIN" --fail --silent --show-error --location --max-time 15 --output /dev/null "$url" || return 1
    done
}

validate_health_urls() {
    local url
    [ "${#HEALTH_URLS[@]}" -gt 0 ] || die "$ACTION requires at least one --health-url"
    for url in "${HEALTH_URLS[@]}"; do
        case "$url" in
            http://*|https://*) ;;
            *) die "health URL must use HTTP or HTTPS" ;;
        esac
    done
}

reload_fpm_and_check() {
    "$PHP_FPM_CONTROL" reload || return 1
    run_health_checks || return 1
}

write_current_marker() {
    local id="$1"
    local temporary="$DEPLOY_ROOT/.current-release-$$"
    printf '%s\n' "$id" > "$temporary"
    chmod 600 "$temporary" 2>/dev/null || true
    mv -f -- "$temporary" "$DEPLOY_ROOT/current-release"
}

write_switch_audit_variant() {
    local path="$1"
    local status="$2"
    local action="$3"
    local previous="$4"
    local selected="$5"
    local stamp="$6"
    {
        printf 'action=%s\n' "$action"
        printf 'status=%s\n' "$status"
        printf 'release=%s\n' "$selected"
        printf 'previous=%s\n' "$previous"
        printf 'timestamp_utc=%s\n' "$stamp"
        printf 'env_sha256=%s\n' "$(tr -d '\r\n' < "$RELEASES_ROOT/$selected/.release-env-sha256")"
        printf 'expected_db_name_sha256=%s\n' "$(tr -d '\r\n' < "$RELEASES_ROOT/$selected/.release-db-name-sha256")"
        printf 'database_write=none\n'
    } > "$path"
    chmod 600 "$path"
    chown root:root "$path"
    require_regular_not_link "switch audit variant" "$path"
    require_exact_owner "switch audit variant" "$path" "root:root"
    require_exact_mode "switch audit variant" "$path" "600"
}

prepare_switch_audit() {
    local action="$1"
    local previous="$2"
    local selected="$3"
    [ -z "$AUDIT_PENDING" ] || die "a switch audit is already prepared"
    local stamp base probe probe_moved
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    base="$stamp-$action-$selected-$$"
    AUDIT_PENDING="$MANIFESTS_ROOT/$base.pending"
    AUDIT_FINAL="$MANIFESTS_ROOT/$base.txt"
    AUDIT_COMMIT_TEMP="$MANIFESTS_ROOT/.audit-$base-committed"
    AUDIT_RUNTIME_FAILURE_TEMP="$MANIFESTS_ROOT/.audit-$base-runtime-failure"
    AUDIT_COMMIT_FAILURE_TEMP="$MANIFESTS_ROOT/.audit-$base-commit-failure"
    AUDIT_UNVERIFIED_TEMP="$MANIFESTS_ROOT/.audit-$base-unverified"
    AUDIT_NOT_SWITCHED_TEMP="$MANIFESTS_ROOT/.audit-$base-not-switched"
    for probe in \
        "$AUDIT_PENDING" "$AUDIT_FINAL" "$AUDIT_COMMIT_TEMP" "$AUDIT_RUNTIME_FAILURE_TEMP" \
        "$AUDIT_COMMIT_FAILURE_TEMP" "$AUDIT_UNVERIFIED_TEMP" "$AUDIT_NOT_SWITCHED_TEMP"; do
        [ ! -e "$probe" ] && [ ! -L "$probe" ] || die "switch audit path already exists"
    done
    write_switch_audit_variant "$AUDIT_PENDING" "pending-before-switch" "$action" "$previous" "$selected" "$stamp"
    write_switch_audit_variant "$AUDIT_COMMIT_TEMP" "committed" "$action" "$previous" "$selected" "$stamp"
    write_switch_audit_variant "$AUDIT_RUNTIME_FAILURE_TEMP" "rolled-back-after-runtime-failure" "$action" "$previous" "$selected" "$stamp"
    write_switch_audit_variant "$AUDIT_COMMIT_FAILURE_TEMP" "rolled-back-after-audit-commit-failure" "$action" "$previous" "$selected" "$stamp"
    write_switch_audit_variant "$AUDIT_UNVERIFIED_TEMP" "rollback-link-restored-service-unverified" "$action" "$previous" "$selected" "$stamp"
    write_switch_audit_variant "$AUDIT_NOT_SWITCHED_TEMP" "not-switched-cas-failed" "$action" "$previous" "$selected" "$stamp"
    probe="$MANIFESTS_ROOT/.audit-probe-$base"
    probe_moved="$MANIFESTS_ROOT/.audit-probe-$base-moved"
    printf 'audit-write-probe\n' > "$probe"
    chmod 600 "$probe"
    chown root:root "$probe"
    mv -T -- "$probe" "$probe_moved"
    mv -T -- "$probe_moved" "$probe"
    rm -f -- "$probe"
}

finalize_switch_audit() {
    local outcome="$1"
    local selected_temp
    case "$outcome" in
        committed) selected_temp="$AUDIT_COMMIT_TEMP" ;;
        runtime-failure) selected_temp="$AUDIT_RUNTIME_FAILURE_TEMP" ;;
        audit-commit-failure) selected_temp="$AUDIT_COMMIT_FAILURE_TEMP" ;;
        unverified) selected_temp="$AUDIT_UNVERIFIED_TEMP" ;;
        not-switched) selected_temp="$AUDIT_NOT_SWITCHED_TEMP" ;;
        *) die "unknown switch audit outcome" ;;
    esac
    [ -n "$AUDIT_FINAL" ] && [ -n "$selected_temp" ] || return 1
    if ! mv -Tf -- "$selected_temp" "$AUDIT_FINAL"; then
        return 1
    fi
    rm -f -- \
        "$AUDIT_PENDING" "$AUDIT_COMMIT_TEMP" "$AUDIT_RUNTIME_FAILURE_TEMP" \
        "$AUDIT_COMMIT_FAILURE_TEMP" "$AUDIT_UNVERIFIED_TEMP" "$AUDIT_NOT_SWITCHED_TEMP" \
        || log "WARNING: finalized audit was written but one or more audit templates could not be removed"
    AUDIT_PENDING=""
    AUDIT_COMMIT_TEMP=""
    AUDIT_RUNTIME_FAILURE_TEMP=""
    AUDIT_COMMIT_FAILURE_TEMP=""
    AUDIT_UNVERIFIED_TEMP=""
    AUDIT_NOT_SWITCHED_TEMP=""
    AUDIT_FINAL=""
    return 0
}

write_operation_manifest() {
    local action="$1"
    local previous="$2"
    local selected="$3"
    local stamp path temporary
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    path="$MANIFESTS_ROOT/$stamp-$action-$selected.txt"
    temporary="$MANIFESTS_ROOT/.manifest-$$"
    {
        printf 'action=%s\n' "$action"
        printf 'release=%s\n' "$selected"
        printf 'previous=%s\n' "$previous"
        printf 'timestamp_utc=%s\n' "$stamp"
        if [ "$action" = "prepare" ]; then
            printf 'archive_sha256=%s\n' "$ARCHIVE_SHA256"
            printf 'manifest_sha256=%s\n' "$(tr -d '\r\n' < "$RELEASES_ROOT/$selected/.release-manifest-sha256")"
        fi
        if [ "$selected" != "absent" ] && [ -f "$RELEASES_ROOT/$selected/.release-env-sha256" ]; then
            printf 'env_sha256=%s\n' "$(tr -d '\r\n' < "$RELEASES_ROOT/$selected/.release-env-sha256")"
            printf 'expected_db_name_sha256=%s\n' "$(tr -d '\r\n' < "$RELEASES_ROOT/$selected/.release-db-name-sha256")"
        fi
        printf 'database_write=none\n'
    } > "$temporary"
    chmod 600 "$temporary" 2>/dev/null || true
    chown root:root "$temporary"
    require_exact_owner "operation manifest" "$temporary" "root:root"
    require_exact_mode "operation manifest" "$temporary" "600"
    mv -f -- "$temporary" "$path"
}

initialize_baseline() {
    [ "$CONFIRM_INITIALIZE" -eq 1 ] || die "initialization requires --confirm-initialize-baseline"
    [ "$EXPECTED_CURRENT" = "absent" ] || die "initialization requires --expected-current absent"
    validate_health_urls
    assert_expected_current
    copy_baseline

    local backup_dir="$BACKUPS_ROOT/nginx-initialize-$RELEASE_ID"
    [ ! -e "$backup_dir" ] || die "Nginx initialization backup already exists"
    prepare_switch_audit "initialize-baseline" "absent" "$RELEASE_ID"
    if ! (atomic_set_current "$RELEASE_ID" "absent"); then
        finalize_switch_audit "not-switched" || die "baseline final CAS failed and AUDIT_FINALIZATION_FAILED; pending audit evidence was retained"
        die "unable to install baseline current link; final expected-current CAS failed"
    fi

    local initialized=0
    if (patch_nginx_for_current "$backup_dir") \
        && "$NGINX_BIN" -t \
        && "$NGINX_BIN" -s reload \
        && reload_fpm_and_check; then
        initialized=1
    fi

    if [ "$initialized" -ne 1 ]; then
        log "baseline initialization failed; restoring Nginx configuration and removing current"
        if ! restore_nginx_backup "$backup_dir" \
            || ! "$NGINX_BIN" -t \
            || ! "$NGINX_BIN" -s reload \
            || ! remove_current_if "$RELEASE_ID" \
            || ! "$PHP_FPM_CONTROL" reload \
            || ! run_health_checks; then
            finalize_switch_audit "unverified" || true
            die "baseline initialization failed; BASELINE_ROLLBACK_SERVICE_STATE_UNVERIFIED and pending audit evidence was retained"
        fi
        finalize_switch_audit "runtime-failure" || die "baseline initialization was reversed and health-checked but AUDIT_FINALIZATION_FAILED"
        die "baseline initialization failed; original paths were restored and verified healthy"
    fi

    if ! finalize_switch_audit "committed"; then
        log "baseline health checks passed but audit commit failed; restoring original Nginx paths"
        if ! restore_nginx_backup "$backup_dir" \
            || ! "$NGINX_BIN" -t \
            || ! "$NGINX_BIN" -s reload \
            || ! remove_current_if "$RELEASE_ID" \
            || ! "$PHP_FPM_CONTROL" reload \
            || ! run_health_checks; then
            finalize_switch_audit "unverified" || true
            die "baseline audit commit failed; BASELINE_ROLLBACK_SERVICE_STATE_UNVERIFIED and pending audit evidence was retained"
        fi
        finalize_switch_audit "audit-commit-failure" || die "baseline was reversed and health-checked but AUDIT_FINALIZATION_FAILED"
        die "baseline initialization was automatically rolled back because its audit record could not be committed"
    fi
    if ! write_current_marker "$RELEASE_ID"; then
        log "WARNING: advisory current-release marker could not be updated; the current symlink and committed audit remain authoritative"
    fi
    log "baseline initialized: $RELEASE_ID"
}

switch_release() {
    local action="$1"
    local confirm="$2"
    [ "$confirm" -eq 1 ] || die "$action requires its explicit confirmation flag"
    require_expected_binding_approvals
    validate_release_id "$EXPECTED_CURRENT"
    validate_health_urls
    assert_expected_current
    assert_nginx_current_config
    validate_prepared_release "$RELEASE_ID"
    validate_release_binding_approval "$RELEASE_ID" "$EXPECTED_ENV_SHA256" "$EXPECTED_DB_NAME_SHA256"

    local previous="$EXPECTED_CURRENT"
    [ "$previous" != "$RELEASE_ID" ] || die "target release is already current"
    prepare_switch_audit "$action" "$previous" "$RELEASE_ID"
    if ! (atomic_set_current "$RELEASE_ID" "$previous" "$EXPECTED_ENV_SHA256" "$EXPECTED_DB_NAME_SHA256"); then
        local actual_after_failed_switch
        actual_after_failed_switch="$(current_release_id)"
        if [ "$actual_after_failed_switch" = "$RELEASE_ID" ]; then
            if ! (atomic_set_current "$previous" "$RELEASE_ID") \
                || ! "$PHP_FPM_CONTROL" reload \
                || ! run_health_checks; then
                finalize_switch_audit "unverified" || true
                die "$action failed during current installation; ROLLBACK_SERVICE_STATE_UNVERIFIED and pending audit evidence was retained"
            fi
            finalize_switch_audit "runtime-failure" || die "$action was rolled back and health-checked but AUDIT_FINALIZATION_FAILED; pending audit evidence was retained"
        else
            finalize_switch_audit "not-switched" || die "$action final CAS failed and AUDIT_FINALIZATION_FAILED; pending audit evidence was retained"
        fi
        die "$action final expected-current CAS failed; this process did not overwrite the externally changed current link"
    fi

    if ! reload_fpm_and_check; then
        log "$action failed after current switch; restoring $previous"
        if ! (atomic_set_current "$previous" "$RELEASE_ID"); then
            finalize_switch_audit "unverified" || true
            die "$action failed and ROLLBACK_LINK_STATE_UNVERIFIED; pending audit evidence was retained"
        fi
        if ! "$PHP_FPM_CONTROL" reload; then
            finalize_switch_audit "unverified" || true
            die "$action failed; current link points to the previous release but ROLLBACK_FPM_RELOAD_FAILED and service state is unverified"
        fi
        if ! run_health_checks; then
            finalize_switch_audit "unverified" || true
            die "$action failed; current link points to the previous release but ROLLBACK_HEALTH_FAILED and service state is unverified"
        fi
        finalize_switch_audit "runtime-failure" || die "$action was rolled back and health-checked but AUDIT_FINALIZATION_FAILED; pending audit evidence was retained"
        die "$action failed; previous release was restored and verified healthy"
    fi

    if ! finalize_switch_audit "committed"; then
        log "$action health checks passed but the prepared audit commit failed; rolling back"
        if ! (atomic_set_current "$previous" "$RELEASE_ID"); then
            finalize_switch_audit "unverified" || true
            die "$action audit commit failed and ROLLBACK_LINK_STATE_UNVERIFIED; pending audit evidence was retained"
        fi
        if ! "$PHP_FPM_CONTROL" reload; then
            finalize_switch_audit "unverified" || true
            die "$action audit commit failed; current link was reversed but ROLLBACK_FPM_RELOAD_FAILED and service state is unverified"
        fi
        if ! run_health_checks; then
            finalize_switch_audit "unverified" || true
            die "$action audit commit failed; current link was reversed but ROLLBACK_HEALTH_FAILED and service state is unverified"
        fi
        finalize_switch_audit "audit-commit-failure" || die "$action was reversed and health-checked but AUDIT_FINALIZATION_FAILED; pending audit evidence was retained"
        die "$action was automatically rolled back because its audit record could not be committed"
    fi
    if ! write_current_marker "$RELEASE_ID"; then
        log "WARNING: advisory current-release marker could not be updated; the current symlink and committed audit remain authoritative"
    fi
    log "$action completed: $previous -> $RELEASE_ID"
}

validate_release_id "$RELEASE_ID"
verify_running_as_root
normalize_root
require_command stat
require_command sha256sum
require_command dd
require_command mktemp
require_command chown
prepare_layout
acquire_lock
verify_php83

case "$ACTION" in
    prepare)
        [ -z "$EXPECTED_CURRENT" ] || die "prepare does not accept --expected-current because it never changes current"
        prepare_candidate
        ;;
    initialize-baseline)
        require_command rsync
        require_command sed
        require_command "$NGINX_BIN"
        require_command "$PHP_FPM_CONTROL"
        require_command "$CURL_BIN"
        initialize_baseline
        ;;
    activate)
        require_command "$PHP_FPM_CONTROL"
        require_command "$CURL_BIN"
        switch_release "activate" "$CONFIRM_ACTIVATE"
        ;;
    rollback)
        require_command "$PHP_FPM_CONTROL"
        require_command "$CURL_BIN"
        switch_release "rollback" "$CONFIRM_ROLLBACK"
        ;;
    *)
        die "unsupported action"
        ;;
esac

trap - EXIT
exit 0
