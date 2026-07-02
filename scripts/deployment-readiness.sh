#!/usr/bin/env bash

set -u

PHP_BIN="php"
COMPOSER_BIN="composer"
NGINX_BIN="nginx"
PHP_FPM_BIN="php-fpm"
PRODUCTION=0
STRICT=0
SKIP_THINK_BOOT=0
SKIP_WRITABLE_PROBE=0
CREATE_MISSING_WRITABLE_DIRS=0
CHECK_ERROR_LOG_POLICY=0
CHECK_OPCACHE_POLICY=0
CHECK_BACKUP_TOOLS=0
CHECK_SCHEDULER_POLICY=0
CHECK_CACHE_POLICY=0
CHECK_COOKIE_POLICY=0
CHECK_URL_POLICY=0
CHECK_STORAGE_POLICY=0
CHECK_PROVIDER_POLICY=0
CHECK_ENV_TEMPLATE_POLICY=0
CHECK_RUNTIME_PERMISSION_POLICY=0
CHECK_WEB_SERVER_POLICY=0
CHECK_SECURITY_HEADERS_POLICY=0
CHECK_CORS_POLICY=0
CHECK_DATABASE_SCHEMA=0
CHECK_ARTIFACT_POLICY=0
CHECK_FRONTEND_BUILD_POLICY=0
CHECK_COMPOSER_POLICY=0
CHECK_RELEASE_PACKAGE_POLICY=0
CHECK_NGINX_SYNTAX=0
CHECK_PHP_FPM_SYNTAX=0
LIVE_ROOT=0
MYSQL_DUMP_BIN="mysqldump"
MYSQL_CLIENT_BIN="mysql"
BACKUP_DIRECTORY="runtime/backup"
RELEASE_ROOT="."
SCHEDULER_POLICY_DOCUMENT="docs/tasks/scheduler-queue-policy.md"
CACHE_TCP_TIMEOUT_SECONDS=2
EXPECTED_PUBLIC_ROOT=""
PUBLIC_BASE_URL=""
CORS_PROBE_ORIGIN=""
CORS_PROBE_URL=""
HTTP_PROBE_TIMEOUT_SECONDS=5
MIN_UPLOAD_MAX_FILESIZE="8M"
MIN_POST_MAX_SIZE="8M"

FAILURES=0
WARNINGS=0

usage() {
    cat <<'USAGE'
Usage: scripts/deployment-readiness.sh [options]

Options:
  --php-bin PATH                     PHP binary, default: php
  --composer-bin PATH                Composer binary, default: composer
  --nginx-bin PATH                   Nginx binary, default: nginx
  --php-fpm-bin PATH                 PHP-FPM binary, default: php-fpm
  --production                       Fail when APP_DEBUG is not false
  --strict                           Treat warnings as failures
  --skip-think-boot                  Skip php think route:list
  --skip-writable-probe              Do not create temporary files in writable paths
  --create-missing-writable-dirs     Explicitly create missing writable directories before probing
  --check-error-log-policy           Check PHP error display/logging production readiness
  --check-opcache-policy             Check PHP OPcache production readiness
  --check-backup-tools               Check local database backup/restore command readiness
  --check-scheduler-policy           Check scheduler/queue worker policy without running jobs
  --check-cache-policy               Check cache/Redis policy without writing cache data
  --check-cookie-policy              Check cookie/session security policy
  --check-url-policy                 Check APP_HOST/PublicBaseUrl URL and HTTPS policy
  --check-storage-policy             Check filesystem and DevFile local storage policy without writes
  --check-provider-policy            Check provider/deferred send policy without external calls
  --check-env-template-policy        Check .example.env key coverage and placeholder policy without printing values
  --check-runtime-permission-policy  Check runtime/sensitive path scope and Unix mode policy without chmod/delete
  --check-web-server-policy          Check Nginx/PHP-FPM command availability without printing config
  --check-security-headers-policy    Check HTTP security headers through PublicBaseUrl without printing bodies
  --check-cors-policy                Check CORS source policy and optional preflight headers without printing bodies
  --check-database-schema            Check database connection, curated tables, and columns with read-only queries
  --check-artifact-policy            Check release-sensitive local/test artifacts without deleting files
  --check-frontend-build-policy      Check frontend production build policy without running npm build
  --check-composer-policy            Check Composer dependency/autoload production policy without install/update
  --check-release-package-policy     Check release package include/exclude policy without building or archiving
  --mysql-dump-bin PATH              MySQL dump binary, default: mysqldump
  --mysql-client-bin PATH            MySQL restore/query client binary, default: mysql
  --backup-directory PATH            Backup output directory to check, default: runtime/backup
  --release-root PATH                Release package root to inspect, default: current project root
  --scheduler-policy-document PATH   Scheduler/queue policy document, default: docs/tasks/scheduler-queue-policy.md
  --cache-tcp-timeout SECONDS        Cache TCP probe timeout, default: 2
  --check-nginx-syntax               Run nginx -t without printing config
  --check-php-fpm-syntax             Run php-fpm -tt without printing config
  --live-root                         Treat ReleaseRoot as the active deployed live root, not a clean release package
  --expected-public-root PATH        Verify the expected vhost document root resolves to project public
  --public-base-url URL              Probe sensitive project paths over HTTP without printing response bodies
  --cors-probe-origin URL            Frontend origin to use for CORS OPTIONS preflight checks
  --cors-probe-url URL               API URL to use for CORS OPTIONS preflight, defaults to PublicBaseUrl
  --http-probe-timeout SECONDS       HTTP probe timeout, default: 5
  --min-upload-max-filesize SIZE     Warn when PHP upload_max_filesize is below SIZE, default: 8M
  --min-post-max-size SIZE           Warn when PHP post_max_size is below SIZE, default: 8M
  -h, --help                         Show this help
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --php-bin)
            PHP_BIN="${2:-}"
            shift 2
            ;;
        --composer-bin)
            COMPOSER_BIN="${2:-}"
            shift 2
            ;;
        --nginx-bin)
            NGINX_BIN="${2:-}"
            shift 2
            ;;
        --php-fpm-bin)
            PHP_FPM_BIN="${2:-}"
            shift 2
            ;;
        --production)
            PRODUCTION=1
            shift
            ;;
        --strict)
            STRICT=1
            shift
            ;;
        --skip-think-boot)
            SKIP_THINK_BOOT=1
            shift
            ;;
        --skip-writable-probe)
            SKIP_WRITABLE_PROBE=1
            shift
            ;;
        --create-missing-writable-dirs)
            CREATE_MISSING_WRITABLE_DIRS=1
            shift
            ;;
        --check-error-log-policy)
            CHECK_ERROR_LOG_POLICY=1
            shift
            ;;
        --check-opcache-policy)
            CHECK_OPCACHE_POLICY=1
            shift
            ;;
        --check-backup-tools)
            CHECK_BACKUP_TOOLS=1
            shift
            ;;
        --check-scheduler-policy)
            CHECK_SCHEDULER_POLICY=1
            shift
            ;;
        --check-cache-policy)
            CHECK_CACHE_POLICY=1
            shift
            ;;
        --check-cookie-policy)
            CHECK_COOKIE_POLICY=1
            shift
            ;;
        --check-url-policy)
            CHECK_URL_POLICY=1
            shift
            ;;
        --check-storage-policy)
            CHECK_STORAGE_POLICY=1
            shift
            ;;
        --check-provider-policy)
            CHECK_PROVIDER_POLICY=1
            shift
            ;;
        --check-env-template-policy)
            CHECK_ENV_TEMPLATE_POLICY=1
            shift
            ;;
        --check-runtime-permission-policy)
            CHECK_RUNTIME_PERMISSION_POLICY=1
            shift
            ;;
        --check-web-server-policy)
            CHECK_WEB_SERVER_POLICY=1
            shift
            ;;
        --check-security-headers-policy)
            CHECK_SECURITY_HEADERS_POLICY=1
            shift
            ;;
        --check-cors-policy)
            CHECK_CORS_POLICY=1
            shift
            ;;
        --check-database-schema)
            CHECK_DATABASE_SCHEMA=1
            shift
            ;;
        --check-artifact-policy)
            CHECK_ARTIFACT_POLICY=1
            shift
            ;;
        --check-frontend-build-policy)
            CHECK_FRONTEND_BUILD_POLICY=1
            shift
            ;;
        --check-composer-policy)
            CHECK_COMPOSER_POLICY=1
            shift
            ;;
        --check-release-package-policy)
            CHECK_RELEASE_PACKAGE_POLICY=1
            shift
            ;;
        --mysql-dump-bin)
            MYSQL_DUMP_BIN="${2:-}"
            shift 2
            ;;
        --mysql-client-bin)
            MYSQL_CLIENT_BIN="${2:-}"
            shift 2
            ;;
        --backup-directory)
            BACKUP_DIRECTORY="${2:-}"
            shift 2
            ;;
        --release-root)
            RELEASE_ROOT="${2:-}"
            shift 2
            ;;
        --scheduler-policy-document)
            SCHEDULER_POLICY_DOCUMENT="${2:-}"
            shift 2
            ;;
        --cache-tcp-timeout)
            CACHE_TCP_TIMEOUT_SECONDS="${2:-}"
            shift 2
            ;;
        --check-nginx-syntax)
            CHECK_NGINX_SYNTAX=1
            shift
            ;;
        --check-php-fpm-syntax)
            CHECK_PHP_FPM_SYNTAX=1
            shift
            ;;
        --live-root)
            LIVE_ROOT=1
            shift
            ;;
        --expected-public-root)
            EXPECTED_PUBLIC_ROOT="${2:-}"
            shift 2
            ;;
        --public-base-url)
            PUBLIC_BASE_URL="${2:-}"
            shift 2
            ;;
        --cors-probe-origin)
            CORS_PROBE_ORIGIN="${2:-}"
            shift 2
            ;;
        --cors-probe-url)
            CORS_PROBE_URL="${2:-}"
            shift 2
            ;;
        --http-probe-timeout)
            HTTP_PROBE_TIMEOUT_SECONDS="${2:-}"
            shift 2
            ;;
        --min-upload-max-filesize)
            MIN_UPLOAD_MAX_FILESIZE="${2:-}"
            shift 2
            ;;
        --min-post-max-size)
            MIN_POST_MAX_SIZE="${2:-}"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
repo_root="$(CDPATH= cd -- "$script_dir/.." && pwd)"
cd "$repo_root" || exit 1

write_check() {
    status="$1"
    name="$2"
    detail="${3:-}"
    if [ -n "$detail" ]; then
        printf '[%s] %s - %s\n' "$status" "$name" "$detail"
    else
        printf '[%s] %s\n' "$status" "$name"
    fi
}

ok() {
    write_check "OK" "$1" "${2:-}"
}

warn() {
    WARNINGS=$((WARNINGS + 1))
    write_check "WARN" "$1" "${2:-}"
}

fail() {
    FAILURES=$((FAILURES + 1))
    write_check "FAIL" "$1" "${2:-}"
}

conditional_backup_issue() {
    if [ "$PRODUCTION" -eq 1 ]; then
        fail "$1" "${2:-}"
    else
        warn "$1" "${2:-}"
    fi
}

conditional_production_issue() {
    if [ "$PRODUCTION" -eq 1 ]; then
        fail "$1" "${2:-}"
    else
        warn "$1" "${2:-}"
    fi
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

is_local_url_host() {
    host="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
    case "$host" in
        localhost|127.0.0.1|::1)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

test_tcp_connection() {
    host="$1"
    port="$2"
    timeout_seconds="$3"

    if command_exists "$PHP_BIN"; then
        "$PHP_BIN" -r '$host = $argv[1]; $port = (int)$argv[2]; $timeout = (float)$argv[3]; $errno = 0; $errstr = ""; $socket = @fsockopen($host, $port, $errno, $errstr, $timeout); if ($socket) { fclose($socket); exit(0); } exit(1);' "$host" "$port" "$timeout_seconds" >/dev/null 2>&1
        return $?
    fi

    if command_exists nc; then
        nc -z -w "$timeout_seconds" "$host" "$port" >/dev/null 2>&1
        return $?
    fi

    return 2
}

get_env_value() {
    key="$1"
    [ -f ".env" ] || return 1
    awk -v target="$key" '
        function trim(value) {
            gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
            return value
        }
        {
            line = $0
            sub(/\r$/, "", line)
            if (NR == 1) {
                sub(/^\357\273\277/, "", line)
            }
            if (line ~ /^[ \t]*($|#)/) {
                next
            }
            separator = index(line, "=")
            if (separator == 0) {
                next
            }
            key = trim(substr(line, 1, separator - 1))
            if (key == target) {
                value = trim(substr(line, separator + 1))
                gsub(/^["\047]|["\047]$/, "", value)
                print value
                exit
            }
        }
    ' .env
}

has_env_key() {
    key="$1"
    value="$(get_env_value "$key")"
    [ -n "$value" ]
}

dotenv_key_exists() {
    file="$1"
    target_key="$2"
    [ -f "$file" ] || return 1
    if command_exists "$PHP_BIN"; then
        "$PHP_BIN" -r '$path = $argv[1]; $target = $argv[2]; $text = @file_get_contents($path); if ($text === false) { exit(1); } foreach (preg_split("/\r\n|\n|\r/", $text) as $line) { $line = preg_replace("/^\xEF\xBB\xBF/", "", trim($line)); if ($line === "" || $line[0] === "#") { continue; } if (preg_match("/^([^#=\s]+)\s*=\s*(.*)$/", $line, $m) && $m[1] === $target) { exit(0); } } exit(1);' "$file" "$target_key" >/dev/null 2>&1
        return $?
    fi

    awk -v target="$target_key" '
        function trim(value) {
            gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
            return value
        }
        {
            line = $0
            sub(/\r$/, "", line)
            if (NR == 1) {
                sub(/^\357\273\277/, "", line)
            }
            if (line ~ /^[ \t]*($|#)/) {
                next
            }
            separator = index(line, "=")
            if (separator == 0) {
                next
            }
            key = trim(substr(line, 1, separator - 1))
            if (key == target) {
                found = 1
            }
        }
        END {
            exit(found ? 0 : 1)
        }
    ' "$file"
}

get_dotenv_file_value() {
    file="$1"
    key="$2"
    [ -f "$file" ] || return 1
    if command_exists "$PHP_BIN"; then
        "$PHP_BIN" -r '$path = $argv[1]; $target = $argv[2]; $text = @file_get_contents($path); if ($text === false) { exit(1); } foreach (preg_split("/\r\n|\n|\r/", $text) as $line) { $line = preg_replace("/^\xEF\xBB\xBF/", "", trim($line)); if ($line === "" || $line[0] === "#") { continue; } if (preg_match("/^([^#=\s]+)\s*=\s*(.*)$/", $line, $m) && $m[1] === $target) { $value = trim($m[2]); if (strlen($value) >= 2 && (($value[0] === "\"" && substr($value, -1) === "\"") || ($value[0] === "'"'"'" && substr($value, -1) === "'"'"'"))) { $value = substr($value, 1, -1); } echo $value; exit(0); } } exit(1);' "$file" "$key" 2>/dev/null
        return $?
    fi

    awk -v target="$key" '
        function trim(value) {
            gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
            return value
        }
        {
            line = $0
            sub(/\r$/, "", line)
            if (NR == 1) {
                sub(/^\357\273\277/, "", line)
            }
            if (line ~ /^[ \t]*($|#)/) {
                next
            }
            separator = index(line, "=")
            if (separator == 0) {
                next
            }
            key = trim(substr(line, 1, separator - 1))
            if (key == target) {
                value = trim(substr(line, separator + 1))
                gsub(/^["\047]|["\047]$/, "", value)
                print value
                exit
            }
        }
    ' "$file"
}

test_writable_dir() {
    path="$1"
    probe="$path/.deployment-readiness-$$.tmp"
    if : > "$probe" 2>/dev/null; then
        rm -f "$probe"
        return 0
    fi

    rm -f "$probe" 2>/dev/null
    return 1
}

resolve_path() {
    path="$1"
    if command_exists realpath; then
        realpath "$path" 2>/dev/null
    elif command_exists python3; then
        python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "$path" 2>/dev/null
    else
        (CDPATH= cd -- "$path" 2>/dev/null && pwd)
    fi
}

configured_project_path() {
    path="$(printf '%s' "$1" | tr '\\' '/')"
    root="$(printf '%s' "${2:-$(pwd)}" | tr '\\' '/')"

    case "$path" in
        '')
            printf '\n'
            ;;
        /*|[A-Za-z]:/*)
            printf '%s\n' "$path"
            ;;
        *)
            printf '%s/%s\n' "${root%/}" "${path#/}"
            ;;
    esac
}

configured_path_under_root() {
    path="$1"
    root="$2"
    absolute_path="$(configured_project_path "$path" "$repo_root")"
    absolute_root="$(configured_project_path "$root" "$repo_root")"

    if command_exists "$PHP_BIN"; then
        "$PHP_BIN" -r '$path = str_replace("\\", "/", $argv[1]); $root = rtrim(str_replace("\\", "/", $argv[2]), "/"); $path = rtrim($path, "/"); exit($path === $root || str_starts_with($path, $root . "/") ? 0 : 1);' "$absolute_path" "$absolute_root" >/dev/null 2>&1
        return $?
    fi

    absolute_path="${absolute_path%/}"
    absolute_root="${absolute_root%/}"
    case "$absolute_path" in
        "$absolute_root"|"$absolute_root"/*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

is_windows_host() {
    uname_s="$(uname -s 2>/dev/null || printf unknown)"
    case "$uname_s" in
        MINGW*|MSYS*|CYGWIN*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

unix_mode_for_path() {
    path="$1"
    if stat -c '%a' "$path" 2>/dev/null; then
        return 0
    fi

    stat -f '%Lp' "$path" 2>/dev/null
}

mode_group_or_other_writable() {
    mode="$1"
    case "$mode" in
        *[!0-7]*|'')
            return 1
            ;;
    esac

    digits="${mode#"${mode%???}"}"
    group="$(printf '%s' "$digits" | cut -c2)"
    other="$(printf '%s' "$digits" | cut -c3)"
    [ -n "$group" ] || return 1
    [ -n "$other" ] || return 1

    if [ $((group & 2)) -ne 0 ] || [ $((other & 2)) -ne 0 ]; then
        return 0
    fi

    return 1
}

mode_other_readable() {
    mode="$1"
    case "$mode" in
        *[!0-7]*|'')
            return 1
            ;;
    esac

    digits="${mode#"${mode%???}"}"
    other="$(printf '%s' "$digits" | cut -c3)"
    [ -n "$other" ] || return 1

    [ $((other & 4)) -ne 0 ]
}

check_file_contains_patterns() {
    name="$1"
    path="$2"
    shift 2

    if [ ! -f "$path" ]; then
        conditional_production_issue "$name" "$path missing"
        return
    fi

    missing=""
    for pattern in "$@"; do
        if ! grep -F "$pattern" "$path" >/dev/null 2>&1; then
            if [ -z "$missing" ]; then
                missing="$pattern"
            else
                missing="$missing, $pattern"
            fi
        fi
    done

    if [ -z "$missing" ]; then
        ok "$name" "$path contains deferred/unsupported signals"
    else
        conditional_production_issue "$name" "$path missing signal(s): $missing"
    fi
}

php_size_to_bytes() {
    value="$1"
    awk -v value="$value" '
        BEGIN {
            v = tolower(value)
            gsub(/^[ \t\r]+|[ \t\r]+$/, "", v)
            if (v == "") {
                exit 1
            }
            if (v == "-1") {
                print -1
                exit 0
            }
            if (v !~ /^[+-]?[0-9]+([.][0-9]+)?[kmgtp]?b?$/) {
                exit 1
            }
            unit = substr(v, length(v), 1)
            if (unit == "b" && length(v) > 1) {
                v = substr(v, 1, length(v) - 1)
                unit = substr(v, length(v), 1)
            }
            multiplier = 1
            if (unit == "k") {
                multiplier = 1024
                v = substr(v, 1, length(v) - 1)
            } else if (unit == "m") {
                multiplier = 1024 * 1024
                v = substr(v, 1, length(v) - 1)
            } else if (unit == "g") {
                multiplier = 1024 * 1024 * 1024
                v = substr(v, 1, length(v) - 1)
            } else if (unit == "t") {
                multiplier = 1024 * 1024 * 1024 * 1024
                v = substr(v, 1, length(v) - 1)
            } else if (unit == "p") {
                multiplier = 1024 * 1024 * 1024 * 1024 * 1024
                v = substr(v, 1, length(v) - 1)
            }
            printf "%.0f\n", v * multiplier
        }
    '
}

ini_value_from_output() {
    target="$1"
    printf '%s\n' "$php_ini_output" | awk -v target="$target" '
        index($0, target "=") == 1 {
            print substr($0, length(target) + 2)
            exit
        }
    '
}

error_ini_value_from_output() {
    target="$1"
    printf '%s\n' "$php_error_ini_output" | awk -v target="$target" '
        index($0, target "=") == 1 {
            print substr($0, length(target) + 2)
            exit
        }
    '
}

opcache_ini_value_from_output() {
    target="$1"
    printf '%s\n' "$php_opcache_output" | awk -v target="$target" '
        index($0, target "=") == 1 {
            print substr($0, length(target) + 2)
            exit
        }
    '
}

security_header_value_from_output() {
    target="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
    printf '%s\n' "$security_header_output" | awk -v target="$target" '
        /^[^:]+:/ {
            separator = index($0, ":")
            name = tolower(substr($0, 1, separator - 1))
            if (name == target) {
                sub(/^[^:]*:[ \t\r]*/, "", $0)
                sub(/\r$/, "", $0)
                print $0
                exit
            }
        }
    '
}

cors_header_value_from_output() {
    target="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
    printf '%s\n' "$cors_probe_output" | awk -v target="$target" '
        /^[^:]+:/ {
            separator = index($0, ":")
            name = tolower(substr($0, 1, separator - 1))
            if (name == target) {
                sub(/^[^:]*:[ \t\r]*/, "", $0)
                sub(/\r$/, "", $0)
                print $0
                exit
            }
        }
    '
}

join_url_path() {
    base="${1%/}"
    path="$2"
    case "$path" in
        /*)
            printf '%s%s\n' "$base" "$path"
            ;;
        *)
            printf '%s/%s\n' "$base" "$path"
            ;;
    esac
}

printf 'Deployment readiness root: %s\n' "$repo_root"
printf 'Secrets are not printed. This script does not edit .env, config, database rows, or production data.\n'
printf 'Missing writable directories are created only when --create-missing-writable-dirs is supplied.\n\n'

required_files="
composer.json
composer.lock
think
public/index.php
public/router.php
public/.htaccess
config/app.php
config/database.php
config/cache.php
config/log.php
config/filesystem.php
"

for path in $required_files; do
    if [ -f "$path" ]; then
        ok "Required file $path"
    else
        fail "Required file $path" "missing"
    fi
done

public_exposure_count=0
for entry in .env .example.env composer.json composer.lock vendor runtime app config route extend docs scripts tests think PLANS.md IMPLEMENT.md STATUS.md; do
    public_entry="public/$entry"
    if [ -e "$public_entry" ] || [ -L "$public_entry" ]; then
        public_exposure_count=$((public_exposure_count + 1))
        fail "Public web exposure guard" "$public_entry must not be web-accessible"
    fi
done

if [ "$public_exposure_count" -eq 0 ]; then
    ok "Public web exposure guard" "no sensitive project entries under public"
fi

if [ -d ".git" ] && command_exists git; then
    if git ls-files --error-unmatch .env >/dev/null 2>&1; then
        fail "Git secret guard" ".env is tracked; remove it from source control history/index before deployment"
    else
        ok "Git secret guard" ".env is not tracked"
    fi

    for path in .env vendor/autoload.php runtime/test.tmp public/storage/test.tmp; do
        if git check-ignore --quiet -- "$path"; then
            ok "Git ignore guard $path"
        else
            warn "Git ignore guard $path" "not ignored; verify source-control hygiene before release"
        fi
    done
else
    if [ "$LIVE_ROOT" -eq 1 ]; then
        ok "Git secret guard" "git metadata not deployed in live root"
    else
        warn "Git secret guard" "git metadata or command unavailable; verify .env/runtime/vendor ignore rules before release"
    fi
fi

if [ -f ".env" ]; then
    ok ".env present" "values hidden"
else
    fail ".env present" "missing deployment environment file"
fi

if [ -f ".example.env" ]; then
    ok ".example.env present"
else
    warn ".example.env present" "missing sample environment file"
fi

if [ -f "vendor/autoload.php" ]; then
    ok "Composer vendor autoload present"
else
    fail "Composer vendor autoload present" "run composer install before deployment"
fi

if command_exists "$PHP_BIN"; then
    php_version="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null)"
    if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.0.0", ">=") ? 0 : 1);' >/dev/null 2>&1; then
        ok "PHP version" "$php_version"
    else
        fail "PHP version" "found $php_version, require >= 8.0.0"
    fi
else
    fail "PHP command" "$PHP_BIN not found"
fi

if command_exists "$COMPOSER_BIN"; then
    composer_version="$("$COMPOSER_BIN" --version 2>&1 | head -n 1)"
    if [ -n "$composer_version" ]; then
        ok "Composer command" "$composer_version"
    else
        warn "Composer command" "$COMPOSER_BIN --version returned no output"
    fi
else
    warn "Composer command" "$COMPOSER_BIN not found; acceptable only when vendor is already deployed"
fi

if command_exists "$PHP_BIN"; then
    for extension in pdo pdo_mysql mbstring openssl json tokenizer fileinfo curl zip dom xml; do
        if "$PHP_BIN" -r "exit(extension_loaded('$extension') ? 0 : 1);" >/dev/null 2>&1; then
            ok "PHP extension $extension"
        else
            warn "PHP extension $extension" "not loaded; verify before staging/production"
        fi
    done

    php_ini_output="$("$PHP_BIN" -r '$keys = array("file_uploads", "upload_max_filesize", "post_max_size", "max_file_uploads", "memory_limit"); foreach ($keys as $key) { echo $key . "=" . ini_get($key) . PHP_EOL; }' 2>&1)"
    php_ini_exit=$?
    if [ "$php_ini_exit" -ne 0 ] || [ -z "$php_ini_output" ]; then
        warn "PHP upload/body limits" "unable to read file_uploads, upload_max_filesize, post_max_size, max_file_uploads, and memory_limit"
    else
        file_uploads="$(ini_value_from_output file_uploads | tr '[:upper:]' '[:lower:]')"
        case "$file_uploads" in
            1|on|true|yes)
                ok "PHP file_uploads" "enabled"
                ;;
            *)
                fail "PHP file_uploads" "disabled; upload/import endpoints will not accept files"
                ;;
        esac

        min_upload_bytes="$(php_size_to_bytes "$MIN_UPLOAD_MAX_FILESIZE")"
        min_upload_exit=$?
        if [ "$min_upload_exit" -ne 0 ]; then
            min_upload_bytes=""
            warn "PHP upload_max_filesize threshold" "$MIN_UPLOAD_MAX_FILESIZE cannot be parsed"
        fi

        upload_max_filesize="$(ini_value_from_output upload_max_filesize)"
        upload_bytes="$(php_size_to_bytes "$upload_max_filesize")"
        upload_exit=$?
        if [ "$upload_exit" -ne 0 ]; then
            upload_bytes=""
            warn "PHP upload_max_filesize" "$upload_max_filesize cannot be parsed"
        elif [ -n "$min_upload_bytes" ] && [ "$upload_bytes" -ge 0 ] && [ "$upload_bytes" -lt "$min_upload_bytes" ]; then
            warn "PHP upload_max_filesize" "$upload_max_filesize below recommended minimum $MIN_UPLOAD_MAX_FILESIZE"
        else
            ok "PHP upload_max_filesize" "$upload_max_filesize"
        fi

        min_post_bytes="$(php_size_to_bytes "$MIN_POST_MAX_SIZE")"
        min_post_exit=$?
        if [ "$min_post_exit" -ne 0 ]; then
            min_post_bytes=""
            warn "PHP post_max_size threshold" "$MIN_POST_MAX_SIZE cannot be parsed"
        fi

        post_max_size="$(ini_value_from_output post_max_size)"
        post_bytes="$(php_size_to_bytes "$post_max_size")"
        post_exit=$?
        if [ "$post_exit" -ne 0 ]; then
            post_bytes=""
            warn "PHP post_max_size" "$post_max_size cannot be parsed"
        elif [ "$post_bytes" -eq 0 ]; then
            warn "PHP post_max_size" "0/unlimited; verify this is intentional and bounded by the web server"
        elif [ -n "$min_post_bytes" ] && [ "$post_bytes" -lt "$min_post_bytes" ]; then
            warn "PHP post_max_size" "$post_max_size below recommended minimum $MIN_POST_MAX_SIZE"
        elif [ -n "$upload_bytes" ] && [ "$upload_bytes" -gt 0 ] && [ "$post_bytes" -le "$upload_bytes" ]; then
            warn "PHP post_max_size" "$post_max_size should be larger than upload_max_filesize $upload_max_filesize"
        else
            ok "PHP post_max_size" "$post_max_size"
        fi

        max_file_uploads="$(ini_value_from_output max_file_uploads)"
        case "$max_file_uploads" in
            ''|*[!0-9]*)
                fail "PHP max_file_uploads" "$max_file_uploads is not a positive integer"
                ;;
            *)
                if [ "$max_file_uploads" -gt 0 ]; then
                    ok "PHP max_file_uploads" "$max_file_uploads"
                else
                    fail "PHP max_file_uploads" "$max_file_uploads is not a positive integer"
                fi
                ;;
        esac

        memory_limit="$(ini_value_from_output memory_limit)"
        memory_bytes="$(php_size_to_bytes "$memory_limit")"
        memory_exit=$?
        if [ "$memory_exit" -eq 0 ] && [ "$memory_bytes" -eq -1 ]; then
            ok "PHP memory_limit" "-1 (unlimited)"
        elif [ "$memory_exit" -ne 0 ]; then
            warn "PHP memory_limit" "$memory_limit cannot be parsed"
        elif [ -n "$post_bytes" ] && [ "$post_bytes" -gt 0 ] && [ "$memory_bytes" -lt "$post_bytes" ]; then
            warn "PHP memory_limit" "$memory_limit is lower than post_max_size $post_max_size"
        else
            ok "PHP memory_limit" "$memory_limit"
        fi
    fi
fi

if { [ "$CHECK_ERROR_LOG_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; } && command_exists "$PHP_BIN"; then
    php_error_ini_output="$("$PHP_BIN" -r '$keys = array("display_errors", "display_startup_errors", "log_errors", "error_log", "expose_php", "html_errors"); foreach ($keys as $key) { echo $key . "=" . ini_get($key) . PHP_EOL; }' 2>&1)"
    php_error_ini_exit=$?
    if [ "$php_error_ini_exit" -ne 0 ] || [ -z "$php_error_ini_output" ]; then
        warn "PHP error/log policy" "unable to read display_errors, display_startup_errors, log_errors, error_log, expose_php, and html_errors"
    else
        display_errors="$(error_ini_value_from_output display_errors | tr '[:upper:]' '[:lower:]')"
        case "$display_errors" in
            1|on|true|yes|stdout|stderr)
                conditional_production_issue "PHP display_errors" "$display_errors; disable before production"
                ;;
            *)
                ok "PHP display_errors" "$display_errors"
                ;;
        esac

        display_startup_errors="$(error_ini_value_from_output display_startup_errors | tr '[:upper:]' '[:lower:]')"
        case "$display_startup_errors" in
            1|on|true|yes|stdout|stderr)
                conditional_production_issue "PHP display_startup_errors" "$display_startup_errors; disable before production"
                ;;
            *)
                ok "PHP display_startup_errors" "$display_startup_errors"
                ;;
        esac

        log_errors="$(error_ini_value_from_output log_errors | tr '[:upper:]' '[:lower:]')"
        case "$log_errors" in
            1|on|true|yes|stdout|stderr)
                ok "PHP log_errors" "$log_errors"
                ;;
            *)
                conditional_production_issue "PHP log_errors" "$log_errors; enable error logging before production"
                ;;
        esac

        error_log="$(error_ini_value_from_output error_log)"
        if [ -n "$error_log" ]; then
            ok "PHP error_log" "$error_log"
        else
            warn "PHP error_log" "empty; verify PHP-FPM/web-server error log destination before production"
        fi

        expose_php="$(error_ini_value_from_output expose_php | tr '[:upper:]' '[:lower:]')"
        case "$expose_php" in
            1|on|true|yes|stdout|stderr)
                conditional_production_issue "PHP expose_php" "$expose_php; disable before production"
                ;;
            *)
                ok "PHP expose_php" "$expose_php"
                ;;
        esac

        html_errors="$(error_ini_value_from_output html_errors | tr '[:upper:]' '[:lower:]')"
        case "$html_errors" in
            1|on|true|yes|stdout|stderr)
                conditional_production_issue "PHP html_errors" "$html_errors; disable for API responses before production"
                ;;
            *)
                ok "PHP html_errors" "$html_errors"
                ;;
        esac
    fi
fi

if { [ "$CHECK_OPCACHE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; } && command_exists "$PHP_BIN"; then
    php_opcache_output="$("$PHP_BIN" -r '$keys = array("opcache.enable", "opcache.enable_cli", "opcache.validate_timestamps", "opcache.revalidate_freq", "opcache.memory_consumption", "opcache.max_accelerated_files"); echo "opcache.loaded=" . (extension_loaded("Zend OPcache") ? "yes" : "no") . PHP_EOL; foreach ($keys as $key) { echo $key . "=" . ini_get($key) . PHP_EOL; }' 2>&1)"
    php_opcache_exit=$?
    if [ "$php_opcache_exit" -ne 0 ] || [ -z "$php_opcache_output" ]; then
        warn "PHP OPcache policy" "unable to read OPcache settings"
    else
        opcache_loaded="$(opcache_ini_value_from_output opcache.loaded | tr '[:upper:]' '[:lower:]')"
        if [ "$opcache_loaded" = "yes" ]; then
            ok "PHP OPcache extension" "loaded"
        else
            conditional_production_issue "PHP OPcache extension" "not loaded; enable OPcache for production PHP-FPM"
        fi

        opcache_enable="$(opcache_ini_value_from_output opcache.enable | tr '[:upper:]' '[:lower:]')"
        case "$opcache_enable" in
            1|on|true|yes)
                ok "PHP opcache.enable" "$opcache_enable"
                ;;
            *)
                conditional_production_issue "PHP opcache.enable" "$opcache_enable; enable OPcache for production PHP-FPM"
                ;;
        esac

        opcache_enable_cli="$(opcache_ini_value_from_output opcache.enable_cli | tr '[:upper:]' '[:lower:]')"
        case "$opcache_enable_cli" in
            1|on|true|yes)
                ok "PHP opcache.enable_cli" "$opcache_enable_cli"
                ;;
            *)
                if [ "$LIVE_ROOT" -eq 1 ]; then
                    ok "PHP opcache.enable_cli" "$opcache_enable_cli; web runtime OPcache is enabled"
                else
                    warn "PHP opcache.enable_cli" "$opcache_enable_cli; acceptable for web runtime, but CLI warmup/checks will not use OPcache"
                fi
                ;;
        esac

        opcache_validate_timestamps="$(opcache_ini_value_from_output opcache.validate_timestamps | tr '[:upper:]' '[:lower:]')"
        case "$opcache_validate_timestamps" in
            1|on|true|yes)
                if [ "$LIVE_ROOT" -eq 1 ]; then
                    ok "PHP opcache.validate_timestamps" "$opcache_validate_timestamps; timestamp validation enabled for live-root smoke"
                else
                    warn "PHP opcache.validate_timestamps" "$opcache_validate_timestamps; confirm deploy/reload strategy and revalidate frequency"
                fi
                ;;
            '')
                warn "PHP opcache.validate_timestamps" "empty; verify OPcache web runtime settings"
                ;;
            *)
                ok "PHP opcache.validate_timestamps" "$opcache_validate_timestamps"
                ;;
        esac

        opcache_revalidate_freq="$(opcache_ini_value_from_output opcache.revalidate_freq)"
        case "$opcache_revalidate_freq" in
            ''|*[!0-9]*)
                warn "PHP opcache.revalidate_freq" "$opcache_revalidate_freq is not a non-negative integer"
                ;;
            *)
                ok "PHP opcache.revalidate_freq" "$opcache_revalidate_freq"
                ;;
        esac

        opcache_memory_consumption="$(opcache_ini_value_from_output opcache.memory_consumption)"
        case "$opcache_memory_consumption" in
            ''|*[!0-9]*)
                warn "PHP opcache.memory_consumption" "$opcache_memory_consumption is not a positive integer"
                ;;
            *)
                if [ "$opcache_memory_consumption" -gt 0 ]; then
                    if [ "$opcache_memory_consumption" -lt 64 ]; then
                        warn "PHP opcache.memory_consumption" "$opcache_memory_consumption MB below common production baseline 64"
                    else
                        ok "PHP opcache.memory_consumption" "$opcache_memory_consumption MB"
                    fi
                else
                    warn "PHP opcache.memory_consumption" "$opcache_memory_consumption is not a positive integer"
                fi
                ;;
        esac

        opcache_max_accelerated_files="$(opcache_ini_value_from_output opcache.max_accelerated_files)"
        case "$opcache_max_accelerated_files" in
            ''|*[!0-9]*)
                warn "PHP opcache.max_accelerated_files" "$opcache_max_accelerated_files is not a positive integer"
                ;;
            *)
                if [ "$opcache_max_accelerated_files" -gt 0 ]; then
                    if [ "$opcache_max_accelerated_files" -lt 4000 ]; then
                        warn "PHP opcache.max_accelerated_files" "$opcache_max_accelerated_files below common production baseline 4000"
                    else
                        ok "PHP opcache.max_accelerated_files" "$opcache_max_accelerated_files"
                    fi
                else
                    warn "PHP opcache.max_accelerated_files" "$opcache_max_accelerated_files is not a positive integer"
                fi
                ;;
        esac
    fi
fi

if [ -f ".env" ]; then
    for key in APP_DEBUG DB_TYPE DB_HOST DB_NAME DB_USER DB_PASS DB_PORT DB_CHARSET; do
        if has_env_key "$key"; then
            ok ".env key $key" "set"
        else
            warn ".env key $key" "missing or empty"
        fi
    done

    db_port="$(get_env_value DB_PORT)"
    if [ -n "$db_port" ]; then
        case "$db_port" in
            *[!0-9]*|'')
                warn ".env DB_PORT" "not a valid TCP port"
                ;;
            *)
                if [ "$db_port" -gt 0 ] && [ "$db_port" -le 65535 ]; then
                    ok ".env DB_PORT" "valid TCP port"
                else
                    warn ".env DB_PORT" "not a valid TCP port"
                fi
                ;;
        esac
    fi

    app_debug="$(get_env_value APP_DEBUG | tr '[:upper:]' '[:lower:]')"
    if [ "$PRODUCTION" -eq 1 ] && [ "$app_debug" != "false" ]; then
        fail "Production APP_DEBUG" "set APP_DEBUG=false before production"
    elif [ "$app_debug" = "true" ]; then
        warn "APP_DEBUG" "true; acceptable for local smoke, not production"
    elif [ -n "$app_debug" ]; then
        ok "APP_DEBUG" "$app_debug"
    fi

    cache_driver="$(get_env_value CACHE_DRIVER | tr '[:upper:]' '[:lower:]')"
    if [ "$cache_driver" = "redis" ]; then
        for key in REDIS_HOST REDIS_PORT; do
            if has_env_key "$key"; then
                ok ".env key $key" "set"
            else
                warn ".env key $key" "missing while CACHE_DRIVER=redis"
            fi
        done
    fi
fi

if [ "$CHECK_ENV_TEMPLATE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    if [ ! -f ".example.env" ]; then
        conditional_production_issue "Example env template" ".example.env missing or empty"
    else
        if command_exists "$PHP_BIN"; then
            example_key_count="$("$PHP_BIN" -r '$text = @file_get_contents($argv[1]); if ($text === false) { echo 0; exit; } $count = 0; foreach (preg_split("/\r\n|\n|\r/", $text) as $line) { $line = preg_replace("/^\xEF\xBB\xBF/", "", trim($line)); if ($line === "" || $line[0] === "#") { continue; } if (preg_match("/^([^#=\s]+)\s*=/", $line)) { $count++; } } echo $count;' .example.env 2>/dev/null)"
        else
            example_key_count="$(awk 'BEGIN { count = 0 } /^[ \t]*($|#)/ { next } index($0, "=") > 0 { count++ } END { print count }' .example.env)"
        fi
        if [ "$example_key_count" -gt 0 ]; then
            ok "Example env template" ".example.env parseable"
        else
            conditional_production_issue "Example env template" ".example.env missing or empty"
        fi

        missing_template_keys=""
        for key in APP_DEBUG DB_DRIVER DB_TYPE DB_HOST DB_NAME DB_USER DB_PASS DB_PORT DB_CHARSET DEFAULT_LANG CACHE_DRIVER REDIS_HOST REDIS_PORT REDIS_PASSWD REDIS_DB REDIS_TIMEOUT REDIS_EXPIRE CACHE_PREFIX APP_HOST; do
            if dotenv_key_exists .example.env "$key"; then
                ok "Example env key $key" "documented"
            else
                missing_template_keys="${missing_template_keys}${missing_template_keys:+, }$key"
            fi
        done

        if [ -z "$missing_template_keys" ]; then
            ok "Example env required key coverage" "complete"
        else
            conditional_production_issue "Example env required key coverage" "missing key(s): $missing_template_keys"
        fi

        if [ -f ".env" ]; then
            missing_from_template=""
            if command_exists "$PHP_BIN"; then
                while IFS= read -r key; do
                    case "$key" in
                        LOCAL_SUPER_ADMIN_ACCOUNT|LOCAL_SUPER_ADMIN_PASSWORD)
                            continue
                            ;;
                    esac

                    if ! dotenv_key_exists .example.env "$key"; then
                        missing_from_template="${missing_from_template}${missing_from_template:+, }$key"
                    fi
                done < <("$PHP_BIN" -r '$text = @file_get_contents($argv[1]); if ($text === false) { exit(0); } $seen = []; foreach (preg_split("/\r\n|\n|\r/", $text) as $line) { $line = preg_replace("/^\xEF\xBB\xBF/", "", trim($line)); if ($line === "" || $line[0] === "#") { continue; } if (preg_match("/^([^#=\s]+)\s*=/", $line, $m) && !isset($seen[$m[1]])) { $seen[$m[1]] = true; echo $m[1], "\n"; } }' .env 2>/dev/null)
            else
                while IFS= read -r key; do
                    case "$key" in
                        LOCAL_SUPER_ADMIN_ACCOUNT|LOCAL_SUPER_ADMIN_PASSWORD)
                            continue
                            ;;
                    esac

                    if ! dotenv_key_exists .example.env "$key"; then
                        missing_from_template="${missing_from_template}${missing_from_template:+, }$key"
                    fi
                done < <(awk '
                    function trim(value) {
                        gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
                        return value
                    }
                    /^[ \t]*($|#)/ { next }
                    index($0, "=") > 0 {
                        key = trim(substr($0, 1, index($0, "=") - 1))
                        if (!seen[key]++) {
                            print key
                        }
                    }
                ' .env)
            fi

            if [ -z "$missing_from_template" ]; then
                ok "Example env local key coverage" "all non-local .env keys documented"
            else
                conditional_production_issue "Example env local key coverage" "missing non-local key(s): $missing_from_template"
            fi
        fi

        if dotenv_key_exists .example.env APP_DEBUG; then
            example_debug="$(get_dotenv_file_value .example.env APP_DEBUG | tr '[:upper:]' '[:lower:]')"
            if [ "$example_debug" = "false" ]; then
                ok "Example env APP_DEBUG default" "false"
            else
                conditional_production_issue "Example env APP_DEBUG default" "template should default to false for release guidance"
            fi
        fi

        if dotenv_key_exists .example.env DB_PORT; then
            example_db_port="$(get_dotenv_file_value .example.env DB_PORT)"
            case "$example_db_port" in
                '')
                    ;;
                *[!0-9]*)
                    conditional_production_issue "Example env DB_PORT" "not a valid TCP port"
                    ;;
                *)
                    if [ "$example_db_port" -gt 0 ] && [ "$example_db_port" -le 65535 ]; then
                        ok "Example env DB_PORT" "valid TCP port"
                    else
                        conditional_production_issue "Example env DB_PORT" "not a valid TCP port"
                    fi
                    ;;
            esac
        fi

        for key in DB_PASS REDIS_PASSWD REDIS_PASSWORD LOCAL_SUPER_ADMIN_PASSWORD; do
            if dotenv_key_exists .example.env "$key"; then
                template_secret="$(get_dotenv_file_value .example.env "$key")"
                case "$template_secret" in
                    ''|"<"*">"|change*|CHANGE*|*example*|*EXAMPLE*|*placeholder*|*PLACEHOLDER*|*local*|*LOCAL*|*password*|*PASSWORD*)
                        ok "Example env secret placeholder $key" "placeholder or empty"
                        ;;
                    *)
                        conditional_production_issue "Example env secret placeholder $key" "non-empty value present; verify it is a placeholder, not a real secret"
                        ;;
                esac
            fi
        done

        for key in LOCAL_SUPER_ADMIN_ACCOUNT LOCAL_SUPER_ADMIN_PASSWORD; do
            if dotenv_key_exists .example.env "$key"; then
                local_smoke_value="$(get_dotenv_file_value .example.env "$key")"
                if [ -n "$local_smoke_value" ]; then
                    warn "Example env local smoke key $key" "local smoke credentials should stay out of release templates"
                fi
            fi
        done
    fi
fi

if [ "$CHECK_URL_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    app_host=""
    if [ -f ".env" ]; then
        app_host="$(get_env_value APP_HOST)"
    fi

    for item in "APP_HOST URL policy|$app_host" "PublicBaseUrl URL policy|$PUBLIC_BASE_URL"; do
        name="${item%%|*}"
        url="${item#*|}"
        if [ -z "$url" ]; then
            warn "$name" "empty; set an HTTPS URL before final staging/production gate if URL generation or HTTP exposure probes depend on it"
            continue
        fi

        if ! command_exists "$PHP_BIN"; then
            conditional_production_issue "$name" "$PHP_BIN unavailable; cannot parse URL"
            continue
        fi

        parsed_url="$("$PHP_BIN" -r '$url = $argv[1]; $parts = parse_url($url); if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"])) { exit(1); } echo strtolower((string)$parts["scheme"]) . "\n" . (string)$parts["host"] . "\n";' "$url" 2>/dev/null)"
        parsed_exit=$?
        if [ "$parsed_exit" -ne 0 ] || [ -z "$parsed_url" ]; then
            conditional_production_issue "$name" "not an absolute URL with scheme and host"
            continue
        fi

        scheme="$(printf '%s\n' "$parsed_url" | sed -n '1p')"
        host="$(printf '%s\n' "$parsed_url" | sed -n '2p')"
        case "$scheme" in
            https)
                ok "$name" "https://$host"
                ;;
            http)
                if is_local_url_host "$host"; then
                    ok "$name" "local http://$host"
                else
                    conditional_production_issue "$name" "http://$host; use HTTPS before production"
                fi
                ;;
            *)
                conditional_production_issue "$name" "$scheme://$host is not an approved HTTP(S) URL"
                ;;
        esac
    done
fi

if { [ "$CHECK_STORAGE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; } && command_exists "$PHP_BIN"; then
    storage_policy_output="$("$PHP_BIN" -r 'if (!function_exists("app")) { function app() { static $app; if ($app === null) { $app = new class { public function getRootPath() { return getcwd() . DIRECTORY_SEPARATOR; } public function getRuntimePath() { return getcwd() . DIRECTORY_SEPARATOR . "runtime" . DIRECTORY_SEPARATOR; } }; } return $app; } } $config = is_file("config/filesystem.php") ? require "config/filesystem.php" : array(); $disks = is_array($config["disks"] ?? null) ? $config["disks"] : array(); $default = (string)($config["default"] ?? ""); $values = array("filesystem.default" => $default, "disk.default.exists" => ($default !== "" && array_key_exists($default, $disks)) ? "yes" : "no", "disk.local.exists" => array_key_exists("local", $disks) ? "yes" : "no", "disk.local.type" => $disks["local"]["type"] ?? "", "disk.local.root" => $disks["local"]["root"] ?? "", "disk.public.exists" => array_key_exists("public", $disks) ? "yes" : "no", "disk.public.type" => $disks["public"]["type"] ?? "", "disk.public.root" => $disks["public"]["root"] ?? "", "disk.public.url" => $disks["public"]["url"] ?? "", "disk.public.visibility" => $disks["public"]["visibility"] ?? "", "expected.local.root" => app()->getRuntimePath() . "storage", "expected.public.root" => app()->getRootPath() . "public/storage", "expected.dev_file.root" => app()->getRuntimePath() . "upload" . DIRECTORY_SEPARATOR . "dev_file", "expected.public.webroot" => app()->getRootPath() . "public"); foreach ($values as $key => $value) { if (is_bool($value)) { $value = $value ? "true" : "false"; } elseif (is_array($value) || is_object($value)) { $value = "non-scalar"; } echo $key . "=" . (string)$value . PHP_EOL; }' 2>&1)"
    storage_policy_exit=$?
    storage_value_from_output() {
        key="$1"
        printf '%s\n' "$storage_policy_output" | awk -F= -v target="$key" '$1 == target { sub(/^[^=]*=/, ""); print; exit }'
    }

    if [ "$storage_policy_exit" -ne 0 ] || [ -z "$storage_policy_output" ]; then
        conditional_production_issue "File storage policy" "unable to read config/filesystem.php"
    else
        storage_project_root="$(pwd)"
        if pwd -W >/dev/null 2>&1; then
            storage_project_root="$(pwd -W)"
        fi
        storage_project_root="$(printf '%s' "$storage_project_root" | tr '\\' '/')"

        default_disk="$(storage_value_from_output filesystem.default)"
        if [ -z "$default_disk" ]; then
            conditional_production_issue "Filesystem default disk" "empty default disk"
        else
            ok "Filesystem default disk" "$default_disk"
            if [ "$(storage_value_from_output disk.default.exists)" = "yes" ]; then
                ok "Filesystem default disk config" "$default_disk disk configured"
            else
                conditional_production_issue "Filesystem default disk config" "$default_disk disk missing from config/filesystem.php"
            fi
        fi

        for disk_name in local public; do
            if [ "$(storage_value_from_output "disk.$disk_name.exists")" = "yes" ]; then
                ok "Filesystem $disk_name disk" "configured"
            else
                conditional_production_issue "Filesystem $disk_name disk" "missing from config/filesystem.php"
                continue
            fi

            disk_type="$(storage_value_from_output "disk.$disk_name.type" | tr '[:upper:]' '[:lower:]')"
            if [ "$disk_type" = "local" ]; then
                ok "Filesystem $disk_name disk type" "$disk_type"
            else
                conditional_production_issue "Filesystem $disk_name disk type" "$disk_type; expected local for current deployment"
            fi
        done

        check_storage_root() {
            name="$1"
            actual="$2"
            expected="$3"
            label="$4"

            actual_path="$(configured_project_path "$actual" "$storage_project_root")"
            expected_path="$(configured_project_path "$expected" "$storage_project_root")"
            actual_canonical="$(resolve_path "$actual_path")"
            expected_canonical="$(resolve_path "$expected_path")"

            if [ -z "$actual_path" ]; then
                conditional_production_issue "$name" "empty configured path"
            elif [ -z "$actual_canonical" ]; then
                conditional_production_issue "$name" "$actual_path missing; create and verify PHP-FPM permissions before upload writes"
            elif [ -n "$expected_canonical" ] && [ "$actual_canonical" = "$expected_canonical" ]; then
                ok "$name" "$label"
            else
                conditional_production_issue "$name" "$actual_canonical does not resolve to $label"
            fi
        }

        check_storage_root "Filesystem local disk root" "$(storage_value_from_output disk.local.root)" "$(storage_value_from_output expected.local.root)" "runtime/storage"
        check_storage_root "Filesystem public disk root" "$(storage_value_from_output disk.public.root)" "$(storage_value_from_output expected.public.root)" "public/storage"

        public_url="$(storage_value_from_output disk.public.url)"
        case "$public_url" in
            '')
                conditional_production_issue "Filesystem public disk URL" "empty public disk url"
                ;;
            /*)
                ok "Filesystem public disk URL" "$public_url"
                ;;
            *)
                parsed_public_url="$("$PHP_BIN" -r '$url = $argv[1]; $parts = parse_url($url); if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"])) { exit(1); } echo strtolower((string)$parts["scheme"]) . "\n" . (string)$parts["host"] . "\n";' "$public_url" 2>/dev/null)"
                parsed_public_exit=$?
                if [ "$parsed_public_exit" -ne 0 ] || [ -z "$parsed_public_url" ]; then
                    conditional_production_issue "Filesystem public disk URL" "not a root-relative path or absolute HTTP(S) URL"
                else
                    public_scheme="$(printf '%s\n' "$parsed_public_url" | sed -n '1p')"
                    public_host="$(printf '%s\n' "$parsed_public_url" | sed -n '2p')"
                    case "$public_scheme" in
                        https)
                            ok "Filesystem public disk URL" "https://$public_host"
                            ;;
                        http)
                            if is_local_url_host "$public_host"; then
                                ok "Filesystem public disk URL" "local http://$public_host"
                            else
                                conditional_production_issue "Filesystem public disk URL" "http://$public_host; use HTTPS or a root-relative path before production"
                            fi
                            ;;
                        *)
                            conditional_production_issue "Filesystem public disk URL" "$public_scheme is not approved for public disk URLs"
                            ;;
                    esac
                fi
                ;;
        esac

        public_visibility="$(storage_value_from_output disk.public.visibility | tr '[:upper:]' '[:lower:]')"
        if [ "$public_visibility" = "public" ]; then
            ok "Filesystem public disk visibility" "$public_visibility"
        else
            conditional_production_issue "Filesystem public disk visibility" "missing or not public; public disk downloads may not be served consistently"
        fi

        dev_file_root_source="default runtime/upload/dev_file"
        dev_file_root="$(storage_value_from_output expected.dev_file.root)"
        dev_file_root_configured=""
        if [ -f ".env" ]; then
            dev_file_root_configured="$(get_env_value DEV_FILE_LOCAL_ROOT)"
        fi
        if [ -n "$dev_file_root_configured" ]; then
            dev_file_root_source="DEV_FILE_LOCAL_ROOT"
            dev_file_root="$dev_file_root_configured"
        fi

        dev_file_path="$(configured_project_path "$dev_file_root" "$storage_project_root")"
        dev_file_canonical="$(resolve_path "$dev_file_path")"
        if [ -z "$dev_file_canonical" ]; then
            conditional_production_issue "Dev file local root" "$dev_file_root_source path missing; create and verify PHP-FPM permissions before upload writes"
        else
            ok "Dev file local root" "$dev_file_root_source exists"
        fi

        public_webroot_path="$(configured_project_path "$(storage_value_from_output expected.public.webroot)" "$storage_project_root")"
        public_webroot_canonical="$(resolve_path "$public_webroot_path")"
        if [ -n "$dev_file_canonical" ] && [ -n "$public_webroot_canonical" ]; then
            public_prefix="${public_webroot_canonical%/}/"
            dev_compare="${dev_file_canonical%/}/"
            case "$dev_compare" in
                "$public_prefix"*)
                    conditional_production_issue "Dev file local root exposure" "$dev_file_root_source resolves under public web root"
                    ;;
                *)
                    ok "Dev file local root exposure" "not under public web root"
                    ;;
            esac
        fi
    fi
elif [ "$CHECK_STORAGE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    conditional_production_issue "File storage policy" "$PHP_BIN not found; cannot read config/filesystem.php"
fi

if [ "$CHECK_PROVIDER_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    for path in \
        docs/tasks/upload-provider-deferred-plan.md \
        docs/api/dev-email-sms-readonly-compat.md \
        docs/api/dev-file-readonly-compat.md \
        docs/api/user-center-readonly-compat.md
    do
        if [ -f "$path" ]; then
            ok "Provider deferred document $path" "present"
        elif [ "$LIVE_ROOT" -eq 1 ]; then
            ok "Provider deferred document $path" "not deployed in live root; verify in source or staged release package"
        else
            conditional_production_issue "Provider deferred document $path" "missing provider/deferred boundary documentation"
        fi
    done

    check_file_contains_patterns "Email send deferred wrappers" "app/controller/dev/EmailController.php" \
        sendLocalTxt sendLocalHtml sendAliyunTxt sendAliyunHtml sendAliyunTmp sendTencentTxt sendTencentHtml sendTencentTmp deferredWrite
    check_file_contains_patterns "SMS send deferred wrappers" "app/controller/dev/SmsController.php" \
        sendAliyun sendTencent sendXiaonuo deferredSend
    check_file_contains_patterns "Auth phone/WebPush deferred wrappers" "app/controller/auth/AuthController.php" \
        getPhoneValidCode subscription "ApiResponse::fail"
    check_file_contains_patterns "Password recovery provider deferred wrappers" "app/controller/sys/UserCenterController.php" \
        findPasswordGetPhoneValidCode findPasswordGetEmailValidCode findPasswordByPhone findPasswordByEmail "ApiResponse::fail"
    check_file_contains_patterns "Third-party OAuth deferred wrappers" "app/controller/auth/ThirdController.php" \
        render callback "ApiResponse::fail"
    check_file_contains_patterns "Cloud file upload unsupported guard" "app/service/dev/FileService.php" \
        SNOWY_SYS_DEFAULT_FILE_ENGINE "unsupported file engine" ENGINE_LOCAL

    if [ -f "route/app.php" ]; then
        missing_route_signals=""
        for signal in \
            getPhoneValidCode \
            subscription \
            findPasswordGetPhoneValidCode \
            findPasswordGetEmailValidCode \
            findPasswordByPhone \
            findPasswordByEmail \
            auth/third \
            uploadAliyunReturnId \
            uploadTencentReturnId \
            uploadMinioReturnId \
            sendLocalTxt \
            sendAliyunTxt \
            sendTencentTmp \
            sendAliyun \
            sendTencent \
            sendXiaonuo
        do
            if ! grep -F "$signal" route/app.php >/dev/null 2>&1; then
                if [ -z "$missing_route_signals" ]; then
                    missing_route_signals="$signal"
                else
                    missing_route_signals="$missing_route_signals, $signal"
                fi
            fi
        done

        if [ -z "$missing_route_signals" ]; then
            ok "Provider deferred routes" "auth, cloud upload, email, and SMS deferred routes are registered"
        else
            conditional_production_issue "Provider deferred routes" "missing route signal(s): $missing_route_signals"
        fi
    else
        conditional_production_issue "Provider deferred routes" "route/app.php missing"
    fi

    if [ -f "composer.json" ]; then
        provider_package_signals=""
        for package in aliyun alibabacloud tencentcloud qcloud aws/aws-sdk-php minio phpmailer swiftmailer symfony/mailer minishlink/web-push; do
            if tr '[:upper:]' '[:lower:]' < composer.json | grep -F "$package" >/dev/null 2>&1; then
                if [ -z "$provider_package_signals" ]; then
                    provider_package_signals="$package"
                else
                    provider_package_signals="$provider_package_signals, $package"
                fi
            fi
        done

        if [ -z "$provider_package_signals" ]; then
            ok "Provider SDK dependencies" "no known mail/SMS/cloud/WebPush SDK package signals in composer.json"
        else
            conditional_production_issue "Provider SDK dependencies" "$provider_package_signals present; require explicit provider enablement plan before production"
        fi
    else
        conditional_production_issue "Provider SDK dependencies" "composer.json missing"
    fi

    if command_exists "$PHP_BIN" && [ -f "vendor/autoload.php" ]; then
        provider_config_output="$("$PHP_BIN" -r 'require getcwd() . "/vendor/autoload.php"; $app = new think\App(getcwd()); $app->initialize(); $value = think\facade\Db::name("dev_config")->where("CONFIG_KEY", "SNOWY_SYS_DEFAULT_FILE_ENGINE")->where(function ($query): void { $query->whereNull("DELETE_FLAG")->whereOr("DELETE_FLAG", "=", "NOT_DELETE"); })->value("CONFIG_VALUE"); echo "default_file_engine=" . strtoupper(trim((string)$value)) . PHP_EOL;' 2>&1)"
        provider_config_exit=$?
        default_file_engine=""
        if [ "$provider_config_exit" -eq 0 ]; then
            default_file_engine="$(printf '%s\n' "$provider_config_output" | awk -F= '$1 == "default_file_engine" { sub(/^[^=]*=/, ""); print; exit }')"
        fi

        if [ "$provider_config_exit" -ne 0 ]; then
            conditional_production_issue "Default file engine provider policy" "unable to read dev_config SNOWY_SYS_DEFAULT_FILE_ENGINE"
        elif [ -z "$default_file_engine" ] || [ "$default_file_engine" = "LOCAL" ]; then
            ok "Default file engine provider policy" "LOCAL"
        else
            conditional_production_issue "Default file engine provider policy" "$default_file_engine configured; cloud provider storage remains deferred"
        fi
    else
        conditional_production_issue "Default file engine provider policy" "$PHP_BIN or vendor/autoload.php unavailable; cannot confirm dynamic upload default engine"
    fi
fi

if [ "$CHECK_DATABASE_SCHEMA" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    database_column_tables="sys_user sys_role sys_resource sys_relation sys_org sys_position tenants dev_config dev_file dev_email dev_sms act_ru_task act_hi_procinst biz_sale_project biz_sale_project_product_item biz_sale_project_invoice biz_sale_project_invoice_item biz_sale_project_invoicing biz_sale_project_reissue_order return_order return_order_item delivery_record inventory settlement_account settlement_account_statement biz_payment_record biz_expenditure_record biz_purchase_order biz_purchase_order_item biz_collection_receipt biz_debit_note biz_payroll biz_leave_application biz_file_relation customer supplier warehouses biz_product"

    if command_exists "$PHP_BIN" && [ -f "vendor/autoload.php" ]; then
        database_schema_output="$("$PHP_BIN" <<'PHP' 2>&1
<?php
require getcwd() . '/vendor/autoload.php';
$app = new think\App(getcwd());
$app->initialize();
think\facade\Db::query('SELECT 1');

$requiredTables = [
    'sys_user', 'sys_role', 'sys_resource', 'sys_relation', 'sys_org', 'sys_position', 'sys_user_process_config',
    'tenants', 'auth_third_user', 'client_user', 'client_relation', 'mobile_resource',
    'dev_config', 'dev_dict', 'dev_email', 'dev_file', 'dev_job', 'dev_log', 'dev_message', 'dev_relation', 'dev_sms',
    'gen_basic', 'gen_config',
    'act_ru_execution', 'act_ru_task', 'act_ru_variable', 'act_hi_procinst', 'act_hi_taskinst', 'act_hi_actinst',
    'act_hi_varinst', 'act_re_procdef', 'act_re_deployment',
    'biz_sale_project', 'biz_sale_project_product_item', 'biz_sale_project_invoice', 'biz_sale_project_invoice_item',
    'biz_sale_project_invoicing', 'biz_sale_project_reissue_order', 'return_order', 'return_order_item',
    'delivery_record', 'inventory', 'settlement_account', 'settlement_account_statement', 'biz_payment_record',
    'biz_expenditure_record', 'biz_purchase_order', 'biz_purchase_order_item', 'biz_collection_receipt',
    'biz_debit_note', 'biz_payroll', 'biz_leave_application', 'biz_file_relation', 'customer', 'supplier',
    'warehouses', 'biz_product',
];

$columnRequirements = [
    'sys_user' => ['ID', 'ACCOUNT', 'PASSWORD', 'DELETE_FLAG', 'TENANT_ID'],
    'sys_role' => ['ID', 'CODE', 'DELETE_FLAG', 'TENANT_ID'],
    'sys_resource' => ['ID', 'CATEGORY', 'MODULE', 'MENU_TYPE', 'PATH'],
    'sys_relation' => ['ID', 'OBJECT_ID', 'TARGET_ID', 'CATEGORY'],
    'sys_org' => ['ID', 'PARENT_ID', 'NAME', 'DELETE_FLAG', 'TENANT_ID'],
    'sys_position' => ['ID', 'ORG_ID', 'NAME', 'DELETE_FLAG', 'TENANT_ID'],
    'tenants' => ['Tenant_ID', 'Tenant_Name', 'DELETE_FLAG'],
    'dev_config' => ['ID', 'CONFIG_KEY', 'CONFIG_VALUE', 'DELETE_FLAG'],
    'dev_file' => ['ID', 'ENGINE', 'STORAGE_PATH', 'DOWNLOAD_PATH', 'DELETE_FLAG', 'TENANT_ID'],
    'dev_email' => ['ID', 'ENGINE', 'SUBJECT', 'DELETE_FLAG', 'TENANT_ID'],
    'dev_sms' => ['ID', 'ENGINE', 'PHONE_NUMBERS', 'DELETE_FLAG', 'TENANT_ID'],
    'act_ru_task' => ['ID_', 'PROC_INST_ID_', 'TASK_DEF_KEY_', 'ASSIGNEE_'],
    'act_hi_procinst' => ['ID_', 'PROC_INST_ID_', 'PROC_DEF_KEY_', 'END_TIME_'],
    'biz_sale_project' => ['ID', 'DELETE_FLAG', 'TENANT_ID', 'VERSION', 'PROCESS_ID', 'ACCOUNT_ID'],
    'biz_sale_project_product_item' => ['ID', 'PROJECT_ID', 'PRODUCT_ID', 'CATEGORY', 'STATE', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_invoice' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_invoice_item' => ['ID', 'INVOICE_ID', 'PROJECT_PRODUCT_ITEM_ID', 'WAREHOUSES_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_invoicing' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'AMOUNT', 'INVOICING_STATE', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_sale_project_reissue_order' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'DELETE_FLAG', 'TENANT_ID'],
    'return_order' => ['ID', 'PROJECT_ID', 'PROCESS_ID', 'DELETE_FLAG', 'TENANT_ID'],
    'return_order_item' => ['ID', 'RETURN_ORDER_ID', 'PROJECT_PRODUCT_ITEM_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'delivery_record' => ['ID', 'OBJECT_ID', 'PROCESS_ID', 'PRODUCT_ID', 'WAREHOUSES_ID', 'CATEGORY', 'DELETE_FLAG', 'TENANT_ID'],
    'inventory' => ['ID', 'PRODUCT_ID', 'WAREHOUSES_ID', 'CURRENT_COUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'settlement_account' => ['ID', 'ACCOUNT_NAME', 'ACCOUNT_NUMBER', 'CURRENT_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'settlement_account_statement' => ['ID', 'ACCOUNT_ID', 'PROCESS_ID', 'SETTLEMENT_CATEGORY', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_payment_record' => ['ID', 'OBJECT_ID', 'PROCESS_ID', 'SETTLEMENT_CATEGORY', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_expenditure_record' => ['ID', 'OBJECT_ID', 'PROCESS_ID', 'SETTLEMENT_CATEGORY', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_purchase_order' => ['ID', 'SUPPLIER_ID', 'INSTANCE_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_purchase_order_item' => ['ID', 'PURCHASE_ORDER_ID', 'PRODUCT_ID', 'AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_collection_receipt' => ['ID', 'PAYMENT_RECORD_ID', 'AMOUNT', 'SETTLEMENT_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_debit_note' => ['ID', 'EXPENDITURE_RECORD_ID', 'AMOUNT', 'SETTLEMENT_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_payroll' => ['ID', 'USER', 'ORG', 'PAYABLE_AMOUNT', 'ACTUAL_AMOUNT', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_leave_application' => ['ID', 'USER_ID', 'PROCESS_ID', 'AMOUNT', 'START_TIME', 'END_TIME', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_file_relation' => ['ID', 'OBJECT_ID', 'TARGET_ID', 'CATEGORY', 'FILE_NAME', 'DELETE_FLAG', 'TENANT_ID'],
    'customer' => ['ID', 'NAME', 'PHONE', 'DELETE_FLAG', 'TENANT_ID'],
    'supplier' => ['ID', 'NAME', 'PHONE', 'DELETE_FLAG', 'TENANT_ID'],
    'warehouses' => ['ID', 'NAME', 'CODE', 'DELETE_FLAG', 'TENANT_ID'],
    'biz_product' => ['ID', 'PRODUCT_NAME', 'PRODUCT_CATEGORY', 'DELETE_FLAG', 'TENANT_ID'],
];

$tableRows = think\facade\Db::query('SHOW TABLES');
$tables = [];
foreach ($tableRows as $row) {
    $values = array_values($row);
    if (isset($values[0])) {
        $tables[] = (string)$values[0];
    }
}

$tableLookup = [];
foreach ($tables as $table) {
    $tableLookup[strtolower($table)] = true;
}

$missingTables = [];
foreach ($requiredTables as $table) {
    if (!isset($tableLookup[strtolower($table)])) {
        $missingTables[] = $table;
    }
}

$missingColumns = [];
foreach ($columnRequirements as $table => $requiredColumns) {
    if (!isset($tableLookup[strtolower($table)])) {
        $missingColumns[$table] = ['table_missing'];
        continue;
    }

    $escapedTable = str_replace('`', '``', $table);
    $columnRows = think\facade\Db::query('SHOW COLUMNS FROM `' . $escapedTable . '`');
    $columnLookup = [];
    foreach ($columnRows as $row) {
        if (isset($row['Field'])) {
            $columnLookup[strtoupper((string)$row['Field'])] = true;
        }
    }

    foreach ($requiredColumns as $column) {
        if (!isset($columnLookup[strtoupper($column)])) {
            $missingColumns[$table][] = $column;
        }
    }
}

echo 'db.connected=yes' . PHP_EOL;
echo 'db.table_count=' . count($tables) . PHP_EOL;
echo 'db.required_table_count=' . count($requiredTables) . PHP_EOL;
echo 'db.missing_tables=' . (count($missingTables) === 0 ? 'none' : implode(',', $missingTables)) . PHP_EOL;
foreach (array_keys($columnRequirements) as $table) {
    echo 'db.column.' . $table . '.missing=' . (isset($missingColumns[$table]) ? implode(',', $missingColumns[$table]) : 'none') . PHP_EOL;
}
PHP
)"
        database_schema_exit=$?

        if [ "$database_schema_exit" -ne 0 ]; then
            conditional_production_issue "Database schema probe" "unable to boot ThinkPHP and inspect schema with read-only queries"
        else
            database_connected="$(printf '%s\n' "$database_schema_output" | awk -F= '$1 == "db.connected" { sub(/^[^=]*=/, ""); print; exit }')"
            if [ "$database_connected" = "yes" ]; then
                ok "Database connection" "SELECT 1 succeeded"
            else
                conditional_production_issue "Database connection" "schema probe did not confirm SELECT 1"
            fi

            database_table_count="$(printf '%s\n' "$database_schema_output" | awk -F= '$1 == "db.table_count" { sub(/^[^=]*=/, ""); print; exit }')"
            case "$database_table_count" in
                ''|*[!0-9]*)
                    conditional_production_issue "Database table count" "$database_table_count tables reported; expected at least 100"
                    ;;
                *)
                    if [ "$database_table_count" -ge 100 ]; then
                        ok "Database table count" "$database_table_count tables"
                    else
                        conditional_production_issue "Database table count" "$database_table_count tables reported; expected at least 100"
                    fi
                    ;;
            esac

            database_missing_tables="$(printf '%s\n' "$database_schema_output" | awk -F= '$1 == "db.missing_tables" { sub(/^[^=]*=/, ""); print; exit }')"
            if [ "$database_missing_tables" = "none" ]; then
                database_required_table_count="$(printf '%s\n' "$database_schema_output" | awk -F= '$1 == "db.required_table_count" { sub(/^[^=]*=/, ""); print; exit }')"
                if [ -z "$database_required_table_count" ]; then
                    database_required_table_count="curated"
                fi
                ok "Database required tables" "$database_required_table_count curated tables present"
            else
                conditional_production_issue "Database required tables" "missing table(s): $database_missing_tables"
            fi

            database_missing_column_groups=0
            database_column_table_count=0
            for table in $database_column_tables; do
                database_column_table_count=$((database_column_table_count + 1))
                key="db.column.$table.missing"
                missing_columns="$(printf '%s\n' "$database_schema_output" | awk -F= -v target="$key" '$1 == target { sub(/^[^=]*=/, ""); print; exit }')"
                if [ -z "$missing_columns" ]; then
                    database_missing_column_groups=$((database_missing_column_groups + 1))
                    conditional_production_issue "Database required columns $table" "schema probe did not report column result"
                elif [ "$missing_columns" != "none" ]; then
                    database_missing_column_groups=$((database_missing_column_groups + 1))
                    conditional_production_issue "Database required columns $table" "missing column(s): $missing_columns"
                fi
            done

            if [ "$database_missing_column_groups" -eq 0 ]; then
                ok "Database required columns" "$database_column_table_count table column groups checked"
            fi
        fi
    else
        conditional_production_issue "Database schema probe" "$PHP_BIN or vendor/autoload.php unavailable; cannot inspect schema"
    fi
fi

if [ "$CHECK_ARTIFACT_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    artifact_sample_append() {
        current_sample="$1"
        value="$2"
        if [ -z "$current_sample" ]; then
            printf '%s' "$value"
        else
            printf '%s, %s' "$current_sample" "$value"
        fi
    }

    source_metadata_count=0
    source_metadata_sample=""
    for path in .git .codex; do
        if [ -e "$path" ]; then
            source_metadata_count=$((source_metadata_count + 1))
            if [ "$source_metadata_count" -le 5 ]; then
                source_metadata_sample="$(artifact_sample_append "$source_metadata_sample" "$path")"
            fi
        fi
    done

    if [ "$source_metadata_count" -eq 0 ]; then
        ok "Deployment source metadata artifacts" "no .git/.codex directories in release root"
    else
        if [ "$source_metadata_count" -gt 5 ]; then
            source_metadata_sample="$source_metadata_sample, ..."
        fi
        conditional_production_issue "Deployment source metadata artifacts" "$source_metadata_count match(es): $source_metadata_sample"
    fi

    dependency_artifact_count=0
    dependency_artifact_sample=""
    for path in node_modules snowy-admin-web/node_modules; do
        if [ -e "$path" ]; then
            dependency_artifact_count=$((dependency_artifact_count + 1))
            if [ "$dependency_artifact_count" -le 5 ]; then
                dependency_artifact_sample="$(artifact_sample_append "$dependency_artifact_sample" "$path")"
            fi
        fi
    done

    if [ "$dependency_artifact_count" -eq 0 ]; then
        ok "Deployment dependency build artifacts" "no frontend node_modules directories in release root"
    else
        if [ "$dependency_artifact_count" -gt 5 ]; then
            dependency_artifact_sample="$dependency_artifact_sample, ..."
        fi
        conditional_production_issue "Deployment dependency build artifacts" "$dependency_artifact_count match(es): $dependency_artifact_sample"
    fi

    runtime_artifact_count=0
    runtime_artifact_sample=""
    runtime_artifact_seen=""
    if [ -d runtime ]; then
        for path in \
            runtime/codex-* \
            runtime/*.png \
            runtime/*import*.sql \
            runtime/probe-*.php \
            runtime/route_list.php \
            runtime/*-82*.log \
            runtime/vite-*.log \
            runtime/frontend-*.log \
            runtime/think-run*.log \
            runtime/mysql-import*.log
        do
            [ -e "$path" ] || continue
            case " $runtime_artifact_seen " in
                *" $path "*)
                    continue
                    ;;
            esac
            runtime_artifact_seen="$runtime_artifact_seen $path"
            runtime_artifact_count=$((runtime_artifact_count + 1))
            if [ "$runtime_artifact_count" -le 5 ]; then
                runtime_artifact_sample="$(artifact_sample_append "$runtime_artifact_sample" "$path")"
            fi
        done
    fi

    if [ "$runtime_artifact_count" -eq 0 ]; then
        ok "Deployment runtime local artifacts" "no known local smoke/import/build artifacts in runtime root"
    elif [ "$LIVE_ROOT" -eq 1 ]; then
        ok "Deployment runtime local artifacts" "$runtime_artifact_count runtime artifact signal(s) present in live root; public exposure and backup scope are checked separately"
    else
        if [ "$runtime_artifact_count" -gt 5 ]; then
            runtime_artifact_sample="$runtime_artifact_sample, ..."
        fi
        conditional_production_issue "Deployment runtime local artifacts" "$runtime_artifact_count match(es): $runtime_artifact_sample"
    fi
fi

if [ "$CHECK_FRONTEND_BUILD_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    frontend_sample_append() {
        current_sample="$1"
        value="$2"
        if [ -z "$current_sample" ]; then
            printf '%s' "$value"
        else
            printf '%s, %s' "$current_sample" "$value"
        fi
    }

    frontend_env_value() {
        file="$1"
        key_name="$2"
        [ -f "$file" ] || return 1
        awk -v target="$key_name" '
            function trim(value) {
                gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
                return value
            }
            {
                line = $0
                sub(/\r$/, "", line)
                if (NR == 1) {
                    sub(/^\357\273\277/, "", line)
                }
                if (line ~ /^[ \t]*($|#)/) {
                    next
                }
                separator = index(line, "=")
                if (separator == 0) {
                    next
                }
                key = trim(substr(line, 1, separator - 1))
                if (key == target) {
                    value = trim(substr(line, separator + 1))
                    gsub(/^["\047]|["\047]$/, "", value)
                    print value
                    exit
                }
            }
        ' "$file"
    }

    frontend_root="snowy-admin-web"
    if [ ! -d "$frontend_root" ]; then
        conditional_production_issue "Frontend build root" "$frontend_root missing"
    else
        ok "Frontend build root" "$frontend_root"

        frontend_package_path="$frontend_root/package.json"
        if [ -f "$frontend_package_path" ]; then
            if grep -F '"build"' "$frontend_package_path" >/dev/null 2>&1 && grep -F 'vite build --mode production' "$frontend_package_path" >/dev/null 2>&1; then
                ok "Frontend production build script" "vite build --mode production"
            else
                conditional_production_issue "Frontend production build script" "package.json scripts.build should run vite build --mode production"
            fi
        elif [ "$LIVE_ROOT" -eq 1 ]; then
            ok "Frontend package.json" "$frontend_package_path not deployed in live root; dist output is checked"
        else
            conditional_production_issue "Frontend package.json" "$frontend_package_path missing"
        fi

        frontend_lockfile_count=0
        frontend_lockfiles=""
        for lockfile in package-lock.json pnpm-lock.yaml yarn.lock; do
            if [ -f "$frontend_root/$lockfile" ]; then
                frontend_lockfile_count=$((frontend_lockfile_count + 1))
                if [ -z "$frontend_lockfiles" ]; then
                    frontend_lockfiles="$lockfile"
                else
                    frontend_lockfiles="$frontend_lockfiles, $lockfile"
                fi
            fi
        done

        if [ "$frontend_lockfile_count" -eq 1 ] && [ "$frontend_lockfiles" = "package-lock.json" ]; then
            ok "Frontend package lock policy" "package-lock.json"
        elif [ "$frontend_lockfile_count" -eq 0 ] && [ "$LIVE_ROOT" -eq 1 ]; then
            ok "Frontend package lock policy" "lockfile not deployed in live root; verify in source or staged release package"
        elif [ "$frontend_lockfile_count" -eq 0 ]; then
            conditional_production_issue "Frontend package lock policy" "no npm/pnpm/yarn lockfile found"
        else
            conditional_production_issue "Frontend package lock policy" "unexpected or mixed lockfiles: $frontend_lockfiles"
        fi

        frontend_production_env_path="$frontend_root/.env.production"
        if [ -f "$frontend_production_env_path" ]; then
            for key_name in NODE_ENV VITE_API_BASEURL VITE_API_PREFIX VITE_SET_DRAWER; do
                value="$(frontend_env_value "$frontend_production_env_path" "$key_name")"
                if [ -n "$value" ]; then
                    ok "Frontend .env.production key $key_name" "set"
                else
                    conditional_production_issue "Frontend .env.production key $key_name" "missing or empty"
                fi
            done

            frontend_public_key="$(frontend_env_value "$frontend_production_env_path" VITE_PUBLIC_KEY)"
            if [ -n "$frontend_public_key" ]; then
                ok "Frontend .env.production key VITE_PUBLIC_KEY" "set"
            else
                warn "Frontend .env.production key VITE_PUBLIC_KEY" "empty; password transport falls back to HTTPS plaintext unless AUTH_SM2_PRIVATE_KEY is configured"
            fi

            frontend_node_env="$(frontend_env_value "$frontend_production_env_path" NODE_ENV | tr '[:upper:]' '[:lower:]')"
            if [ "$frontend_node_env" = "production" ]; then
                ok "Frontend NODE_ENV" "production"
            else
                conditional_production_issue "Frontend NODE_ENV" "$frontend_node_env; expected production"
            fi

            for key_name in VITE_API_BASEURL VITE_API_PREFIX; do
                value="$(frontend_env_value "$frontend_production_env_path" "$key_name")"
                value_lc="$(printf '%s' "$value" | tr '[:upper:]' '[:lower:]')"
                case "$value_lc" in
                    '')
                        conditional_production_issue "Frontend $key_name" "empty production API setting"
                        ;;
                    http://localhost*|http://127.0.0.1*|http://0.0.0.0*)
                        conditional_production_issue "Frontend $key_name" "points to local HTTP host"
                        ;;
                    http://*)
                        conditional_production_issue "Frontend $key_name" "uses non-HTTPS absolute URL"
                        ;;
                    /*|https://*)
                        ok "Frontend $key_name" "production-safe URL shape"
                        ;;
                    *)
                        conditional_production_issue "Frontend $key_name" "not a root-relative path or HTTPS URL"
                        ;;
                esac
            done

            frontend_set_drawer="$(frontend_env_value "$frontend_production_env_path" VITE_SET_DRAWER | tr '[:upper:]' '[:lower:]')"
            if [ "$frontend_set_drawer" = "false" ]; then
                ok "Frontend production drawer switch" "false"
            else
                conditional_production_issue "Frontend production drawer switch" "$frontend_set_drawer; expected false"
            fi
        else
            if [ "$LIVE_ROOT" -eq 1 ]; then
                ok "Frontend .env.production" "$frontend_production_env_path not deployed in live root; dist output and backend CORS are checked"
            else
                conditional_production_issue "Frontend .env.production" "$frontend_production_env_path missing"
            fi
        fi

        frontend_vite_config_path="$frontend_root/vite.config.mjs"
        if [ -f "$frontend_vite_config_path" ]; then
            if grep -F 'build:' "$frontend_vite_config_path" >/dev/null 2>&1; then
                ok "Frontend Vite build config" "build block present"
            else
                conditional_production_issue "Frontend Vite build config" "build block missing"
            fi

            if grep -F 'manifest: true' "$frontend_vite_config_path" >/dev/null 2>&1; then
                ok "Frontend Vite manifest" "enabled"
            else
                conditional_production_issue "Frontend Vite manifest" "manifest output is not enabled"
            fi

            if grep -F 'sourcemap: false' "$frontend_vite_config_path" >/dev/null 2>&1; then
                ok "Frontend Vite sourcemap policy" "disabled"
            else
                conditional_production_issue "Frontend Vite sourcemap policy" "production sourcemaps should be disabled unless explicitly approved"
            fi

            if grep -F 'vite-plugin-compression' "$frontend_vite_config_path" >/dev/null 2>&1 || grep -F 'viteCompression' "$frontend_vite_config_path" >/dev/null 2>&1; then
                ok "Frontend Vite compression plugin" "configured"
            else
                warn "Frontend Vite compression plugin" "not configured"
            fi
        else
            if [ "$LIVE_ROOT" -eq 1 ]; then
                ok "Frontend Vite config" "$frontend_vite_config_path not deployed in live root; dist output is checked"
            else
                conditional_production_issue "Frontend Vite config" "$frontend_vite_config_path missing"
            fi
        fi

        frontend_dist_path="$frontend_root/dist"
        if [ -d "$frontend_dist_path" ]; then
            ok "Frontend dist directory" "present"

            if [ -f "$frontend_dist_path/index.html" ]; then
                ok "Frontend dist index.html" "present"
            else
                conditional_production_issue "Frontend dist index.html" "missing from production build output"
            fi

            if [ -d "$frontend_dist_path/assets" ]; then
                ok "Frontend dist assets" "present"
            else
                conditional_production_issue "Frontend dist assets" "missing from production build output"
            fi

            if [ -f "$frontend_dist_path/manifest.json" ] || [ -f "$frontend_dist_path/.vite/manifest.json" ]; then
                ok "Frontend dist manifest" "present"
            else
                conditional_production_issue "Frontend dist manifest" "missing from production build output"
            fi

            frontend_dist_sensitive_count=0
            frontend_dist_sensitive_sample=""
            for path in .env .env.production package.json package-lock.json src node_modules vite.config.mjs .git; do
                if [ -e "$frontend_dist_path/$path" ]; then
                    frontend_dist_sensitive_count=$((frontend_dist_sensitive_count + 1))
                    if [ "$frontend_dist_sensitive_count" -le 5 ]; then
                        frontend_dist_sensitive_sample="$(frontend_sample_append "$frontend_dist_sensitive_sample" "dist/$path")"
                    fi
                fi
            done

            if [ "$frontend_dist_sensitive_count" -eq 0 ]; then
                ok "Frontend dist sensitive source exposure" "no source/config/dependency entries in dist root"
            else
                if [ "$frontend_dist_sensitive_count" -gt 5 ]; then
                    frontend_dist_sensitive_sample="$frontend_dist_sensitive_sample, ..."
                fi
                conditional_production_issue "Frontend dist sensitive source exposure" "$frontend_dist_sensitive_count match(es): $frontend_dist_sensitive_sample"
            fi
        else
            conditional_production_issue "Frontend dist directory" "missing; run approved frontend production build before release packaging"
        fi

        frontend_temp_count=0
        frontend_temp_sample=""
        frontend_temp_seen=""
        for path in "$frontend_root"/vite.config.mjs.timestamp-* "$frontend_root"/stats.html; do
            [ -e "$path" ] || continue
            case " $frontend_temp_seen " in
                *" $path "*)
                    continue
                    ;;
            esac
            frontend_temp_seen="$frontend_temp_seen $path"
            frontend_temp_count=$((frontend_temp_count + 1))
            if [ "$frontend_temp_count" -le 5 ]; then
                frontend_temp_sample="$(frontend_sample_append "$frontend_temp_sample" "$path")"
            fi
        done

        if [ "$frontend_temp_count" -eq 0 ]; then
            ok "Frontend temporary build artifacts" "no Vite timestamp or visualizer files in frontend root"
        else
            if [ "$frontend_temp_count" -gt 5 ]; then
                frontend_temp_sample="$frontend_temp_sample, ..."
            fi
            conditional_production_issue "Frontend temporary build artifacts" "$frontend_temp_count match(es): $frontend_temp_sample"
        fi
    fi
fi

if [ "$CHECK_COMPOSER_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    if [ -f composer.json ]; then
        if command_exists "$PHP_BIN" && "$PHP_BIN" -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);' >/dev/null 2>&1; then
            ok "Composer manifest" "composer.json parseable"
        else
            conditional_production_issue "Composer manifest" "composer.json is not parseable JSON"
        fi
    else
        conditional_production_issue "Composer manifest" "composer.json missing"
    fi

    if [ -f composer.lock ]; then
        if command_exists "$PHP_BIN" && "$PHP_BIN" -r '$lock = json_decode(file_get_contents("composer.lock"), true, 512, JSON_THROW_ON_ERROR); exit(empty($lock["content-hash"]) ? 1 : 0);' >/dev/null 2>&1; then
            ok "Composer lock" "composer.lock parseable with content-hash"
        else
            conditional_production_issue "Composer lock" "composer.lock is not parseable or content-hash is missing"
        fi
    else
        conditional_production_issue "Composer lock" "composer.lock missing"
    fi

    if [ -f composer.json ]; then
        for package in php topthink/framework topthink/think-orm topthink/think-filesystem; do
            if command_exists "$PHP_BIN"; then
                package_version="$("$PHP_BIN" -r '$json = json_decode(file_get_contents("composer.json"), true); $package = $argv[1]; echo $json["require"][$package] ?? "";' "$package" 2>/dev/null)"
            else
                package_version=""
            fi

            if [ -n "$package_version" ]; then
                ok "Composer require $package" "$package_version"
            else
                conditional_production_issue "Composer require $package" "missing from composer.json require"
            fi
        done

        if command_exists "$PHP_BIN" && "$PHP_BIN" -r '$json = json_decode(file_get_contents("composer.json"), true); exit(($json["autoload"]["psr-4"]["app\\"] ?? null) === "app" ? 0 : 1);' >/dev/null 2>&1; then
            ok "Composer app autoload" "app\\ => app"
        else
            conditional_production_issue "Composer app autoload" "missing app\\ => app PSR-4 mapping"
        fi

        if command_exists "$PHP_BIN" && "$PHP_BIN" -r '$json = json_decode(file_get_contents("composer.json"), true); exit(($json["autoload"]["psr-0"][""] ?? null) === "extend/" ? 0 : 1);' >/dev/null 2>&1; then
            ok "Composer extend autoload" "extend/ mapping present"
        else
            warn "Composer extend autoload" "extend/ PSR-0 mapping missing or changed"
        fi

        if grep -F 'think service:discover' composer.json >/dev/null 2>&1; then
            ok "Composer post-autoload think service:discover" "registered"
        else
            conditional_production_issue "Composer post-autoload think service:discover" "missing from scripts.post-autoload-dump"
        fi

        if grep -F 'think vendor:publish' composer.json >/dev/null 2>&1; then
            ok "Composer post-autoload think vendor:publish" "registered"
        else
            conditional_production_issue "Composer post-autoload think vendor:publish" "missing from scripts.post-autoload-dump"
        fi
    fi

    if [ -f vendor/autoload.php ]; then
        ok "Composer vendor autoload policy" "vendor/autoload.php present"
    else
        conditional_production_issue "Composer vendor autoload policy" "vendor/autoload.php missing; run approved composer install before release"
    fi

    for path in vendor/composer/installed.php vendor/composer/installed.json vendor/composer/platform_check.php; do
        if [ -f "$path" ]; then
            ok "Composer vendor metadata $path" "present"
        else
            conditional_production_issue "Composer vendor metadata $path" "missing from vendor/composer"
        fi
    done

    composer_dev_dependency_matches=""
    for path in vendor/symfony/var-dumper vendor/topthink/think-trace; do
        if [ -e "$path" ]; then
            if [ -z "$composer_dev_dependency_matches" ]; then
                composer_dev_dependency_matches="$path"
            else
                composer_dev_dependency_matches="$composer_dev_dependency_matches, $path"
            fi
        fi
    done

    if [ -z "$composer_dev_dependency_matches" ]; then
        ok "Composer dev dependency exposure" "no known require-dev packages installed in vendor"
    else
        conditional_production_issue "Composer dev dependency exposure" "$composer_dev_dependency_matches installed; production release should use composer install --no-dev --optimize-autoloader"
    fi

    if command_exists "$COMPOSER_BIN"; then
        composer_validate_output="$("$COMPOSER_BIN" validate --no-check-publish --no-interaction 2>&1)"
        composer_validate_exit=$?
        if [ "$composer_validate_exit" -eq 0 ]; then
            ok "Composer validate" "composer.json and composer.lock accepted"
        else
            conditional_production_issue "Composer validate" "composer validate failed"
        fi
    else
        warn "Composer validate" "$COMPOSER_BIN not found; cannot run read-only composer validate"
    fi
fi

if { [ "$CHECK_RELEASE_PACKAGE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; } && [ "$LIVE_ROOT" -eq 1 ]; then
    ok "Release package policy" "skipped for live root; run without --live-root against a staged release package root"
elif [ "$CHECK_RELEASE_PACKAGE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    release_match_count=0
    release_match_sample=""

    add_release_match() {
        release_match_count=$((release_match_count + 1))
        if [ "$release_match_count" -le 8 ]; then
            if [ -z "$release_match_sample" ]; then
                release_match_sample="$1"
            else
                release_match_sample="$release_match_sample, $1"
            fi
        fi
    }

    summarize_release_matches() {
        if [ "$release_match_count" -gt 8 ]; then
            printf '%s (+%s more)' "$release_match_sample" "$((release_match_count - 8))"
        else
            printf '%s' "$release_match_sample"
        fi
    }

    check_release_file() {
        name="$1"
        relative="$2"
        if [ -f "$release_root_resolved/$relative" ]; then
            ok "$name" "$relative"
        else
            conditional_production_issue "$name" "$relative missing from release root"
        fi
    }

    check_release_dir() {
        name="$1"
        relative="$2"
        if [ -d "$release_root_resolved/$relative" ]; then
            ok "$name" "$relative"
        else
            conditional_production_issue "$name" "$relative missing from release root"
        fi
    }

    if [ -z "$RELEASE_ROOT" ]; then
        conditional_production_issue "Release package root" "empty release root"
    else
        release_root_path="$(configured_project_path "$RELEASE_ROOT")"
        release_root_resolved="$(resolve_path "$release_root_path")"
        if [ -z "$release_root_resolved" ] || [ ! -d "$release_root_resolved" ]; then
            conditional_production_issue "Release package root" "$RELEASE_ROOT missing"
        else
            ok "Release package root" "$release_root_resolved"

            check_release_file "Release backend entry think" "think"
            check_release_file "Release Composer manifest" "composer.json"
            check_release_file "Release Composer lock" "composer.lock"
            check_release_file "Release Composer autoload" "vendor/autoload.php"
            check_release_file "Release Composer installed metadata" "vendor/composer/installed.php"
            check_release_file "Release public index" "public/index.php"
            check_release_file "Release public router" "public/router.php"
            check_release_file "Release public htaccess" "public/.htaccess"
            check_release_file "Release frontend index" "snowy-admin-web/dist/index.html"

            check_release_dir "Release app source" "app"
            check_release_dir "Release config source" "config"
            check_release_dir "Release route source" "route"
            check_release_dir "Release extend source" "extend"
            check_release_dir "Release vendor directory" "vendor"
            check_release_dir "Release public directory" "public"
            check_release_dir "Release frontend assets" "snowy-admin-web/dist/assets"

            if [ -f "$release_root_resolved/snowy-admin-web/dist/.vite/manifest.json" ] || [ -f "$release_root_resolved/snowy-admin-web/dist/manifest.json" ]; then
                ok "Release frontend manifest" "present in dist"
            else
                conditional_production_issue "Release frontend manifest" "dist manifest missing from release root"
            fi

            release_match_count=0
            release_match_sample=""
            for path in \
                .env \
                .example.env \
                .git \
                .codex \
                .agents \
                .idea \
                .vscode \
                snowy-admin-web/node_modules \
                snowy-admin-web/src \
                snowy-admin-web/.env \
                snowy-admin-web/.env.development \
                snowy-admin-web/.env.production \
                snowy-admin-web/package.json \
                snowy-admin-web/package-lock.json \
                snowy-admin-web/pnpm-lock.yaml \
                snowy-admin-web/yarn.lock \
                snowy-admin-web/vite.config.mjs \
                snowy-admin-web/stats.html
            do
                if [ -e "$release_root_resolved/$path" ]; then
                    add_release_match "$path"
                fi
            done

            if [ "$release_match_count" -eq 0 ]; then
                ok "Release excluded entries" "no source-control, secret, frontend source, or dependency build entries found"
            else
                conditional_production_issue "Release excluded entries" "$(summarize_release_matches)"
            fi

            release_match_count=0
            release_match_sample=""
            if [ -d "$release_root_resolved/runtime" ]; then
                while IFS= read -r runtime_file; do
                    runtime_relative="${runtime_file#$release_root_resolved/}"
                    runtime_relative="$(printf '%s' "$runtime_relative" | tr '\\' '/')"
                    if [ "$runtime_relative" != "runtime/.gitignore" ]; then
                        add_release_match "$runtime_relative"
                    fi
                done < <(find "$release_root_resolved/runtime" -type f 2>/dev/null)
            fi

            if [ "$release_match_count" -eq 0 ]; then
                ok "Release runtime artifacts" "no runtime files found except optional placeholders"
            else
                conditional_production_issue "Release runtime artifacts" "$(summarize_release_matches)"
            fi

            release_match_count=0
            release_match_sample=""
            for path in \
                .env \
                .git \
                composer.json \
                composer.lock \
                vendor \
                app \
                config \
                route \
                extend \
                docs \
                scripts \
                snowy-admin-web
            do
                if [ -e "$release_root_resolved/public/$path" ]; then
                    add_release_match "public/$path"
                fi
            done

            if [ "$release_match_count" -eq 0 ]; then
                ok "Release public root exposure" "no project source/config/dependency entries under public"
            else
                conditional_production_issue "Release public root exposure" "$(summarize_release_matches)"
            fi
        fi
    fi
fi

if [ "$CHECK_CACHE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    if [ ! -f ".env" ]; then
        conditional_production_issue "Cache policy" ".env unavailable; cannot validate CACHE_DRIVER or Redis settings"
    else
        cache_driver="$(get_env_value CACHE_DRIVER | tr '[:upper:]' '[:lower:]')"
        if [ -z "$cache_driver" ]; then
            cache_driver="file"
        fi

        case "$cache_driver" in
            file|local)
                ok "Cache driver" "$cache_driver; no external cache probe needed"
                ;;
            redis)
                ok "Cache driver" "redis"

                redis_host="$(get_env_value REDIS_HOST)"
                if [ -n "$redis_host" ]; then
                    ok "Redis host" "set"
                else
                    conditional_production_issue "Redis host" "REDIS_HOST missing or empty while CACHE_DRIVER=redis"
                fi

                redis_port="$(get_env_value REDIS_PORT)"
                redis_port_valid=0
                case "$redis_port" in
                    ''|*[!0-9]*)
                        conditional_production_issue "Redis port" "REDIS_PORT missing, empty, or invalid while CACHE_DRIVER=redis"
                        ;;
                    *)
                        if [ "$redis_port" -gt 0 ] && [ "$redis_port" -le 65535 ]; then
                            redis_port_valid=1
                            ok "Redis port" "valid TCP port"
                        else
                            conditional_production_issue "Redis port" "REDIS_PORT missing, empty, or invalid while CACHE_DRIVER=redis"
                        fi
                        ;;
                esac

                redis_db="$(get_env_value REDIS_DB)"
                case "$redis_db" in
                    '')
                        ok "Redis database" "default 0"
                        ;;
                    *[!0-9]*)
                        warn "Redis database" "REDIS_DB is not a non-negative integer"
                        ;;
                    *)
                        ok "Redis database" "$redis_db"
                        ;;
                esac

                redis_timeout="$(get_env_value REDIS_TIMEOUT)"
                case "$redis_timeout" in
                    '')
                        ok "Redis timeout" "default 0"
                        ;;
                    *[!0-9.]*)
                        warn "Redis timeout" "REDIS_TIMEOUT is not a non-negative number"
                        ;;
                    *)
                        ok "Redis timeout" "$redis_timeout"
                        ;;
                esac

                if has_env_key REDIS_PASSWD || has_env_key REDIS_PASSWORD; then
                    ok "Redis password policy" "password value present"
                else
                    warn "Redis password policy" "password empty; verify Redis is protected by local binding, firewall, VPC, or equivalent controls"
                fi

                cache_probe_timeout="$CACHE_TCP_TIMEOUT_SECONDS"
                case "$cache_probe_timeout" in
                    ''|*[!0-9]*)
                        warn "Cache TCP timeout" "$CACHE_TCP_TIMEOUT_SECONDS is invalid; using 2 seconds"
                        cache_probe_timeout=2
                        ;;
                    0)
                        warn "Cache TCP timeout" "0 is invalid; using 1 second"
                        cache_probe_timeout=1
                        ;;
                esac

                if [ -n "$redis_host" ] && [ "$redis_port_valid" -eq 1 ]; then
                    if test_tcp_connection "$redis_host" "$redis_port" "$cache_probe_timeout"; then
                        ok "Redis TCP reachability" "$redis_host:$redis_port reachable"
                    else
                        conditional_production_issue "Redis TCP reachability" "$redis_host:$redis_port not reachable within $cache_probe_timeout seconds"
                    fi
                fi
                ;;
            *)
                conditional_production_issue "Cache driver" "$cache_driver is not one of file, local, or redis; verify ThinkPHP cache store support"
                ;;
        esac
    fi
fi

if { [ "$CHECK_COOKIE_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; } && command_exists "$PHP_BIN"; then
    cookie_policy_output="$("$PHP_BIN" -r '$cookie = is_file("config/cookie.php") ? require "config/cookie.php" : array(); $session = is_file("config/session.php") ? require "config/session.php" : array(); $keys = array("cookie.secure" => $cookie["secure"] ?? "", "cookie.httponly" => $cookie["httponly"] ?? "", "cookie.samesite" => $cookie["samesite"] ?? "", "cookie.domain" => $cookie["domain"] ?? "", "cookie.path" => $cookie["path"] ?? "", "session.name" => $session["name"] ?? "", "session.type" => $session["type"] ?? "", "session.expire" => $session["expire"] ?? ""); foreach ($keys as $key => $value) { if (is_bool($value)) { $value = $value ? "true" : "false"; } echo $key . "=" . $value . PHP_EOL; }' 2>&1)"
    cookie_policy_exit=$?
    cookie_ini_value_from_output() {
        key="$1"
        printf '%s\n' "$cookie_policy_output" | awk -F= -v target="$key" '$1 == target { sub(/^[^=]*=/, ""); print; exit }'
    }

    if [ "$cookie_policy_exit" -ne 0 ] || [ -z "$cookie_policy_output" ]; then
        conditional_production_issue "Cookie/session policy" "unable to read config/cookie.php and config/session.php"
    else
        cookie_secure="$(cookie_ini_value_from_output cookie.secure | tr '[:upper:]' '[:lower:]')"
        case "$cookie_secure" in
            1|true|on|yes)
                ok "Cookie secure flag" "$cookie_secure"
                ;;
            *)
                conditional_production_issue "Cookie secure flag" "$cookie_secure; enable secure cookies for HTTPS production"
                ;;
        esac

        cookie_httponly="$(cookie_ini_value_from_output cookie.httponly | tr '[:upper:]' '[:lower:]')"
        case "$cookie_httponly" in
            1|true|on|yes)
                ok "Cookie httponly flag" "$cookie_httponly"
                ;;
            *)
                conditional_production_issue "Cookie httponly flag" "$cookie_httponly; enable HttpOnly cookies before production"
                ;;
        esac

        cookie_samesite="$(cookie_ini_value_from_output cookie.samesite | tr '[:upper:]' '[:lower:]')"
        case "$cookie_samesite" in
            lax|strict|none)
                ok "Cookie SameSite policy" "$cookie_samesite"
                ;;
            *)
                conditional_production_issue "Cookie SameSite policy" "empty or unsupported; set lax, strict, or none before production"
                ;;
        esac

        cookie_path="$(cookie_ini_value_from_output cookie.path)"
        if [ "$cookie_path" = "/" ]; then
            ok "Cookie path" "$cookie_path"
        elif [ -z "$cookie_path" ]; then
            warn "Cookie path" "empty; verify default path before production"
        else
            ok "Cookie path" "$cookie_path"
        fi

        session_name="$(cookie_ini_value_from_output session.name)"
        if [ -z "$session_name" ]; then
            conditional_production_issue "Session name" "empty session cookie name"
        elif [ "$session_name" = "PHPSESSID" ]; then
            warn "Session name" "PHPSESSID default; consider app-specific session name before production"
        else
            ok "Session name" "$session_name"
        fi

        session_type="$(cookie_ini_value_from_output session.type)"
        if [ -z "$session_type" ]; then
            warn "Session type" "empty; verify framework default before production"
        else
            ok "Session type" "$session_type"
        fi

        session_expire="$(cookie_ini_value_from_output session.expire)"
        case "$session_expire" in
            ''|*[!0-9]*)
                warn "Session expire" "$session_expire is not a positive integer"
                ;;
            *)
                if [ "$session_expire" -gt 0 ]; then
                    ok "Session expire" "$session_expire seconds"
                else
                    warn "Session expire" "$session_expire is not a positive integer"
                fi
                ;;
        esac
    fi
fi

if [ "$CHECK_SCHEDULER_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    scheduler_policy_document_present=0
    if [ -z "$SCHEDULER_POLICY_DOCUMENT" ]; then
        conditional_production_issue "Scheduler/queue policy document" "empty scheduler policy document path"
    elif [ -f "$SCHEDULER_POLICY_DOCUMENT" ]; then
        scheduler_policy_document_present=1
        ok "Scheduler/queue policy document" "$SCHEDULER_POLICY_DOCUMENT present"
    elif [ "$LIVE_ROOT" -eq 1 ]; then
        scheduler_policy_document_present=1
        ok "Scheduler/queue policy document" "$SCHEDULER_POLICY_DOCUMENT not deployed in live root; policy verified in source or staged release package"
    else
        conditional_production_issue "Scheduler/queue policy document" "$SCHEDULER_POLICY_DOCUMENT missing; document whether workers/jobs are disabled or supervised before production"
    fi

    if [ -f "config/console.php" ]; then
        if command_exists "$PHP_BIN"; then
            console_command_count="$("$PHP_BIN" -r '$config = require "config/console.php"; $commands = $config["commands"] ?? array(); echo is_array($commands) ? count($commands) : "invalid";' 2>/dev/null)"
            case "$console_command_count" in
                ''|*[!0-9]*)
                    conditional_production_issue "ThinkPHP console commands" "unable to count config/console.php commands"
                    ;;
                0)
                    ok "ThinkPHP console commands" "none registered in config/console.php"
                    ;;
                *)
                    if [ "$scheduler_policy_document_present" -eq 1 ]; then
                        ok "ThinkPHP console commands" "$console_command_count registered; scheduler/queue policy documented"
                    else
                        conditional_production_issue "ThinkPHP console commands" "$console_command_count registered; document execution, restart, and log policy"
                    fi
                    ;;
            esac
        else
            warn "ThinkPHP console commands" "$PHP_BIN unavailable; cannot count config/console.php commands"
        fi
    else
        conditional_production_issue "ThinkPHP console config" "config/console.php missing"
    fi

    command_file_count=0
    if [ -d "app" ]; then
        command_file_count="$(find app -type f \( -path '*/command/*' -o -name '*Command.php' \) 2>/dev/null | wc -l | tr -d ' ')"
    fi

    if [ "$command_file_count" = "0" ]; then
        ok "App command classes" "no command files found under app"
    elif [ "$scheduler_policy_document_present" -eq 1 ]; then
        ok "App command classes" "$command_file_count found; scheduler/queue policy documented"
    else
        conditional_production_issue "App command classes" "$command_file_count found; document whether and how they run"
    fi

    queue_package_signals=""
    if [ -f "composer.json" ]; then
        for package in "topthink/think-queue" "workerman/" "php-amqplib/" "predis/predis"; do
            if grep -Fq "$package" composer.json; then
                queue_package_signals="${queue_package_signals}${queue_package_signals:+, }$package"
            fi
        done
    fi

    if [ -z "$queue_package_signals" ]; then
        ok "Queue worker dependencies" "no known queue/worker package signals in composer.json"
    elif [ "$scheduler_policy_document_present" -eq 1 ]; then
        ok "Queue worker dependencies" "$queue_package_signals present; scheduler/queue policy documented"
    else
        conditional_production_issue "Queue worker dependencies" "$queue_package_signals present; document worker process policy"
    fi

    dev_job_signals=""
    if [ -f "app/controller/dev/JobController.php" ]; then
        dev_job_signals="${dev_job_signals}${dev_job_signals:+, }app/controller/dev/JobController.php"
    fi
    if [ -f "app/service/dev/JobService.php" ]; then
        dev_job_signals="${dev_job_signals}${dev_job_signals:+, }app/service/dev/JobService.php"
    fi
    if [ -f "route/app.php" ] && grep -Fq "dev/job" route/app.php; then
        dev_job_signals="${dev_job_signals}${dev_job_signals:+, }route/app.php dev/job"
    fi

    if [ -z "$dev_job_signals" ]; then
        ok "Dev job runtime controls" "no dev/job control signals found"
    elif [ "$scheduler_policy_document_present" -eq 1 ]; then
        ok "Dev job runtime controls" "present; documented as non-executed by readiness"
    else
        conditional_production_issue "Dev job runtime controls" "dev/job controls present; document auth, execution, and disabled/enabled policy before production"
    fi
fi

if [ "$CHECK_BACKUP_TOOLS" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    if command_exists "$MYSQL_DUMP_BIN"; then
        ok "Backup dump command" "$MYSQL_DUMP_BIN found"
    else
        conditional_backup_issue "Backup dump command" "$MYSQL_DUMP_BIN not found; database backup command must be available before production writes"
    fi

    if command_exists "$MYSQL_CLIENT_BIN"; then
        ok "Backup restore client command" "$MYSQL_CLIENT_BIN found"
    else
        conditional_backup_issue "Backup restore client command" "$MYSQL_CLIENT_BIN not found; restore verification command must be available before production writes"
    fi

    if [ -f ".env" ]; then
        for key in DB_TYPE DB_HOST DB_NAME DB_USER DB_PASS DB_PORT; do
            if has_env_key "$key"; then
                ok "Backup DB env key $key" "set"
            else
                conditional_backup_issue "Backup DB env key $key" "missing or empty"
            fi
        done

        db_type="$(get_env_value DB_TYPE | tr '[:upper:]' '[:lower:]')"
        case "$db_type" in
            mysql|mysqli|mariadb)
                ok "Backup DB type" "$db_type"
                ;;
            *)
                conditional_backup_issue "Backup DB type" "$db_type is not a MySQL/MariaDB type; verify backup command strategy"
                ;;
        esac
    else
        conditional_backup_issue "Backup DB env keys" ".env unavailable; cannot validate backup connection inputs"
    fi

    if [ -z "$BACKUP_DIRECTORY" ]; then
        conditional_backup_issue "Backup directory" "empty backup directory"
    elif [ -d "$BACKUP_DIRECTORY" ]; then
        if [ "$SKIP_WRITABLE_PROBE" -eq 1 ]; then
            ok "Backup directory" "$BACKUP_DIRECTORY exists; probe skipped"
        elif test_writable_dir "$BACKUP_DIRECTORY"; then
            ok "Backup directory" "$BACKUP_DIRECTORY writable by current user"
        else
            conditional_backup_issue "Backup directory" "$BACKUP_DIRECTORY is not writable by current user; verify backup user permissions"
        fi
    else
        conditional_backup_issue "Backup directory" "$BACKUP_DIRECTORY missing; create and protect it before production writes"
    fi
fi

runtime_dirs="
runtime
runtime/log
runtime/cache
runtime/temp
runtime/storage
runtime/upload
public/storage
"

if [ "$CHECK_RUNTIME_PERMISSION_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    public_root_path="$repo_root/public"
    if [ -d "$public_root_path" ]; then
        ok "Runtime permission public root" "$public_root_path"
    else
        conditional_production_issue "Runtime permission public root" "$public_root_path missing"
    fi

    for path in .env config/database.php config/cache.php config/log.php config/filesystem.php composer.json composer.lock; do
        [ -f "$path" ] || continue
        if configured_path_under_root "$path" "$public_root_path"; then
            conditional_production_issue "Runtime sensitive path scope $path" "resolves under public root"
        else
            ok "Runtime sensitive path scope $path" "outside public root"
        fi
    done

    for path in $runtime_dirs; do
        if [ "$path" = "public/storage" ]; then
            if configured_path_under_root "$path" "$public_root_path"; then
                ok "Runtime writable path scope $path" "public upload/download path"
            else
                conditional_production_issue "Runtime writable path scope $path" "does not resolve under public root; verify upload URL mapping"
            fi
            continue
        fi

        if configured_path_under_root "$path" "$public_root_path"; then
            conditional_production_issue "Runtime writable path scope $path" "non-public runtime path resolves under public root"
        else
            ok "Runtime writable path scope $path" "outside public root"
        fi
    done

    if [ -z "$BACKUP_DIRECTORY" ]; then
        conditional_production_issue "Runtime backup path scope" "empty backup directory"
    elif configured_path_under_root "$BACKUP_DIRECTORY" "$public_root_path"; then
        conditional_production_issue "Runtime backup path scope" "$BACKUP_DIRECTORY resolves under public root"
    else
        ok "Runtime backup path scope" "$BACKUP_DIRECTORY outside public root"
    fi

    if [ -n "$BACKUP_DIRECTORY" ]; then
        if [ -d "$BACKUP_DIRECTORY" ]; then
            ok "Runtime backup directory existence" "$BACKUP_DIRECTORY exists"
        else
            conditional_production_issue "Runtime backup directory existence" "$BACKUP_DIRECTORY missing; create and protect it before production backups"
        fi
    fi

    if is_windows_host; then
        ok "Runtime Unix permission mode check" "skipped on Windows host"
    elif ! command_exists stat; then
        conditional_production_issue "Runtime Unix permission mode check" "stat command unavailable"
    else
        for path in .env config/database.php config/cache.php config/log.php config/filesystem.php; do
            [ -f "$path" ] || continue
            mode="$(unix_mode_for_path "$path" | head -n 1)"
            if [ -z "$mode" ]; then
                conditional_production_issue "Runtime Unix mode $path" "unable to read file mode"
                continue
            fi

            if mode_group_or_other_writable "$mode"; then
                conditional_production_issue "Runtime Unix mode $path" "mode $mode allows group/other write"
            else
                ok "Runtime Unix mode $path" "mode $mode not group/other writable"
            fi

            if [ "$path" = ".env" ]; then
                if mode_other_readable "$mode"; then
                    conditional_production_issue "Runtime Unix mode .env secrecy" "mode $mode allows other read"
                else
                    ok "Runtime Unix mode .env secrecy" "mode $mode not other-readable"
                fi
            fi
        done
    fi
fi

for path in $runtime_dirs; do
    if [ ! -d "$path" ]; then
        if [ "$CREATE_MISSING_WRITABLE_DIRS" -eq 1 ]; then
            if mkdir -p "$path" 2>/dev/null; then
                ok "Writable path $path" "created"
            else
                fail "Writable path $path" "missing and could not be created"
                continue
            fi
        else
            warn "Writable path $path" "missing; deployment must create it with web-user permissions"
            continue
        fi
    fi

    if [ "$SKIP_WRITABLE_PROBE" -eq 1 ]; then
        ok "Writable path $path" "exists; probe skipped"
    elif test_writable_dir "$path"; then
        ok "Writable path $path"
    else
        warn "Writable path $path" "current user cannot write; verify PHP-FPM/web user permissions"
    fi
done

if [ -n "$EXPECTED_PUBLIC_ROOT" ]; then
    expected_resolved="$(resolve_path "$EXPECTED_PUBLIC_ROOT")"
    project_public_resolved="$(resolve_path "$repo_root/public")"
    if [ -n "$expected_resolved" ] && [ "$expected_resolved" = "$project_public_resolved" ]; then
        ok "Expected public root" "$expected_resolved"
    else
        fail "Expected public root" "expected $project_public_resolved, got ${expected_resolved:-unresolved}"
    fi
fi

if [ -n "$PUBLIC_BASE_URL" ]; then
    if ! command_exists curl; then
        warn "HTTP public exposure guard" "curl not found; cannot probe $PUBLIC_BASE_URL"
    else
        for path in /.env /.example.env /composer.json /composer.lock /vendor/autoload.php /runtime /app /config /route /extend /docs /scripts /tests /PLANS.md /IMPLEMENT.md /STATUS.md; do
            url="$(join_url_path "$PUBLIC_BASE_URL" "$path")"
            curl_exit=0
            http_code="$(curl -k -sS -o /dev/null -w '%{http_code}' --max-time "$HTTP_PROBE_TIMEOUT_SECONDS" "$url" 2>/dev/null)" || curl_exit=$?
            if [ "$curl_exit" -ne 0 ] || [ "$http_code" = "000" ]; then
                warn "HTTP public exposure guard $path" "probe failed"
            elif [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
                fail "HTTP public exposure guard $path" "returned HTTP $http_code; sensitive project paths must not be web-readable"
            elif [ "$http_code" -ge 300 ] && [ "$http_code" -lt 400 ]; then
                warn "HTTP public exposure guard $path" "returned HTTP $http_code redirect; verify the redirect target is not web-readable"
            else
                ok "HTTP public exposure guard $path" "HTTP $http_code"
            fi
        done
    fi
fi

if [ "$CHECK_SECURITY_HEADERS_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    if [ -z "$PUBLIC_BASE_URL" ]; then
        conditional_production_issue "HTTP security headers probe" "empty PublicBaseUrl; cannot inspect response headers"
    elif ! command_exists curl; then
        conditional_production_issue "HTTP security headers probe" "curl not found; cannot inspect response headers"
    else
        scheme="$(printf '%s' "$PUBLIC_BASE_URL" | sed -n 's#^\([A-Za-z][A-Za-z0-9+.-]*\)://.*#\1#p' | tr '[:upper:]' '[:lower:]')"
        host="$(printf '%s' "$PUBLIC_BASE_URL" | sed -n 's#^[A-Za-z][A-Za-z0-9+.-]*://\([^/:?#]*\).*#\1#p')"
        if [ -z "$scheme" ] || [ -z "$host" ]; then
            conditional_production_issue "HTTP security headers probe" "PublicBaseUrl is not an absolute URL with scheme and host"
        elif [ "$scheme" != "http" ] && [ "$scheme" != "https" ]; then
            conditional_production_issue "HTTP security headers probe" "unsupported URL scheme $scheme"
        else
            security_header_output="$(curl -k -sS -o /dev/null -D - -w '\n__HTTP_CODE__:%{http_code}\n' --max-time "$HTTP_PROBE_TIMEOUT_SECONDS" "$PUBLIC_BASE_URL" 2>/dev/null)"
            security_header_exit=$?
            security_http_code="$(printf '%s\n' "$security_header_output" | awk -F: '$1 == "__HTTP_CODE__" { print $2; exit }')"
            if [ "$security_header_exit" -ne 0 ] || [ -z "$security_http_code" ] || [ "$security_http_code" = "000" ]; then
                conditional_production_issue "HTTP security headers probe" "probe failed"
            else
                if [ "$security_http_code" -ge 200 ] && [ "$security_http_code" -lt 400 ]; then
                    ok "HTTP security headers probe" "HTTP $security_http_code"
                else
                    warn "HTTP security headers probe" "HTTP $security_http_code; header policy should be verified on a normal frontend/backend entry response"
                fi

                hsts="$(security_header_value_from_output Strict-Transport-Security)"
                if [ "$scheme" = "https" ] && ! is_local_url_host "$host"; then
                    if [ -z "$hsts" ]; then
                        conditional_production_issue "HTTP security header Strict-Transport-Security" "missing on HTTPS public URL"
                    elif printf '%s' "$hsts" | grep -Eiq '(^|[ ;])max-age[[:space:]]*='; then
                        ok "HTTP security header Strict-Transport-Security" "max-age present"
                    else
                        conditional_production_issue "HTTP security header Strict-Transport-Security" "missing max-age directive"
                    fi
                elif [ "$scheme" = "http" ] && is_local_url_host "$host"; then
                    ok "HTTP security header Strict-Transport-Security" "skipped for local HTTP smoke URL"
                else
                    conditional_production_issue "HTTP security header Strict-Transport-Security" "requires HTTPS public URL before production"
                fi

                content_type_options="$(security_header_value_from_output X-Content-Type-Options | tr '[:upper:]' '[:lower:]')"
                if [ "$content_type_options" = "nosniff" ]; then
                    ok "HTTP security header X-Content-Type-Options" "nosniff"
                else
                    conditional_production_issue "HTTP security header X-Content-Type-Options" "missing nosniff"
                fi

                x_frame_options="$(security_header_value_from_output X-Frame-Options | tr '[:upper:]' '[:lower:]')"
                content_security_policy="$(security_header_value_from_output Content-Security-Policy)"
                case "$x_frame_options" in
                    deny|sameorigin)
                        ok "HTTP frame protection" "X-Frame-Options $x_frame_options"
                        ;;
                    *)
                        if printf '%s' "$content_security_policy" | grep -Eiq '(^|[ ;])frame-ancestors([ ;]|$)'; then
                            ok "HTTP frame protection" "CSP frame-ancestors present"
                        else
                            conditional_production_issue "HTTP frame protection" "missing X-Frame-Options deny/sameorigin or CSP frame-ancestors"
                        fi
                        ;;
                esac

                if [ -n "$content_security_policy" ]; then
                    ok "HTTP security header Content-Security-Policy" "present"
                else
                    conditional_production_issue "HTTP security header Content-Security-Policy" "missing"
                fi

                referrer_policy="$(security_header_value_from_output Referrer-Policy | tr '[:upper:]' '[:lower:]')"
                if [ -z "$referrer_policy" ]; then
                    conditional_production_issue "HTTP security header Referrer-Policy" "missing"
                elif [ "$referrer_policy" = "unsafe-url" ]; then
                    conditional_production_issue "HTTP security header Referrer-Policy" "unsafe-url is not release-safe"
                else
                    ok "HTTP security header Referrer-Policy" "$referrer_policy"
                fi

                permissions_policy="$(security_header_value_from_output Permissions-Policy)"
                if [ -n "$permissions_policy" ]; then
                    ok "HTTP security header Permissions-Policy" "present"
                else
                    conditional_production_issue "HTTP security header Permissions-Policy" "missing"
                fi
            fi
        fi
    fi
fi

if [ "$CHECK_CORS_POLICY" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
    cors_probe_confirmed=0
    frontend_api_requires_cors=0

    cors_source_matches="$(grep -RInE 'Access-Control-Allow-Origin|AllowCrossDomain|crossdomain|CORS' app config route public --include='*.php' --include='*.conf' --include='.htaccess' 2>/dev/null || true)"
    cors_source_count=0
    if [ -n "$cors_source_matches" ]; then
        cors_source_count="$(printf '%s\n' "$cors_source_matches" | awk 'END { print NR }')"
        ok "CORS source scan" "$cors_source_count signal(s)"
    else
        warn "CORS source scan" "no app/source CORS signal found; verify server/proxy policy for cross-origin deployments"
    fi

    has_global_cors_signal=0
    if [ -f "app/middleware.php" ] && grep -Eiq 'Access-Control-Allow-Origin|AllowCrossDomain|crossdomain|CORS' app/middleware.php; then
        has_global_cors_signal=1
        ok "CORS global middleware signal" "present in app/middleware.php"
    elif [ "$LIVE_ROOT" -eq 1 ] && [ -n "$CORS_PROBE_URL" ]; then
        ok "CORS global middleware signal" "not required in live-root mode; backend CORS is checked by CorsProbeUrl"
    else
        warn "CORS global middleware signal" "not found in app/middleware.php; cross-origin production should be handled by app middleware or server config"
    fi

    wildcard_origin_count=0
    if [ -n "$cors_source_matches" ]; then
        wildcard_origin_count="$(printf '%s\n' "$cors_source_matches" | awk '/Access-Control-Allow-Origin/ && /\*/ { count++ } END { print count + 0 }')"
    fi
    if [ "$wildcard_origin_count" -gt 0 ]; then
        conditional_production_issue "CORS wildcard origin source" "$wildcard_origin_count match(es); avoid '*' for credentialed/admin APIs"
    else
        ok "CORS wildcard origin source" "no wildcard Access-Control-Allow-Origin source matches"
    fi

    credential_source_count=0
    if [ -n "$cors_source_matches" ]; then
        credential_source_count="$(printf '%s\n' "$cors_source_matches" | awk '/Access-Control-Allow-Credentials/ && /true/ { count++ } END { print count + 0 }')"
    fi
    if [ "$wildcard_origin_count" -gt 0 ] && [ "$credential_source_count" -gt 0 ]; then
        conditional_production_issue "CORS wildcard credentials source" "wildcard origin and credential headers both appear in source; verify they cannot be emitted together"
    else
        ok "CORS wildcard credentials source" "no wildcard-origin plus credentials source combination detected"
    fi

    if [ -f "snowy-admin-web/.env.production" ]; then
        api_prefix="$(awk '
            function trim(value) {
                gsub(/^[ \t\r]+|[ \t\r]+$/, "", value)
                return value
            }
            {
                line = $0
                sub(/\r$/, "", line)
                if (line ~ /^[ \t]*($|#)/) {
                    next
                }
                separator = index(line, "=")
                if (separator == 0) {
                    next
                }
                key = trim(substr(line, 1, separator - 1))
                if (key == "VITE_API_PREFIX") {
                    value = trim(substr(line, separator + 1))
                    gsub(/^["\047]|["\047]$/, "", value)
                    print value
                    exit
                }
            }
        ' snowy-admin-web/.env.production)"
        if [ -z "$api_prefix" ]; then
            warn "Frontend CORS API prefix" "VITE_API_PREFIX empty in production env; verify same-origin /api or proxy policy"
        else
            case "$api_prefix" in
                /*)
                    ok "Frontend CORS API prefix" "relative API prefix; same-origin deployment can avoid browser CORS"
                    ;;
                http://*|https://*)
                    frontend_api_requires_cors=1
                    api_scheme="$(printf '%s' "$api_prefix" | sed -n 's#^\([A-Za-z][A-Za-z0-9+.-]*\)://.*#\1#p' | tr '[:upper:]' '[:lower:]')"
                    api_host="$(printf '%s' "$api_prefix" | sed -n 's#^[A-Za-z][A-Za-z0-9+.-]*://\([^/:?#]*\).*#\1#p')"
                    if [ "$api_scheme" = "https" ] || is_local_url_host "$api_host"; then
                        warn "Frontend CORS API prefix" "absolute API URL; cross-origin CORS must be verified"
                    else
                        conditional_production_issue "Frontend CORS API prefix" "absolute non-HTTPS API URL is not release-safe"
                    fi
                    ;;
                *)
                    conditional_production_issue "Frontend CORS API prefix" "VITE_API_PREFIX is neither relative nor an absolute HTTP(S) URL"
                    ;;
            esac
        fi
    else
        if [ "$LIVE_ROOT" -eq 1 ] && [ -n "$CORS_PROBE_URL" ]; then
            ok "Frontend CORS API prefix" "production env not deployed in live root; using CorsProbeUrl for backend CORS evidence"
        else
            warn "Frontend CORS API prefix" "snowy-admin-web/.env.production missing; cannot infer same-origin vs cross-origin API policy"
        fi
    fi

    cors_preflight_url="$CORS_PROBE_URL"
    if [ -z "$cors_preflight_url" ]; then
        cors_preflight_url="$PUBLIC_BASE_URL"
    fi

    if [ -z "$cors_preflight_url" ]; then
        warn "CORS preflight probe" "empty PublicBaseUrl/CorsProbeUrl; skip live CORS preflight"
    elif [ -z "$CORS_PROBE_ORIGIN" ]; then
        warn "CORS preflight probe" "empty CorsProbeOrigin; pass the frontend origin to inspect Access-Control-* headers"
    else
        cors_origin_scheme="$(printf '%s' "$CORS_PROBE_ORIGIN" | sed -n 's#^\([A-Za-z][A-Za-z0-9+.-]*\)://.*#\1#p' | tr '[:upper:]' '[:lower:]')"
        cors_origin_host="$(printf '%s' "$CORS_PROBE_ORIGIN" | sed -n 's#^[A-Za-z][A-Za-z0-9+.-]*://\([^/:?#]*\).*#\1#p')"
        if [ -z "$cors_origin_scheme" ] || [ -z "$cors_origin_host" ] || { [ "$cors_origin_scheme" != "http" ] && [ "$cors_origin_scheme" != "https" ]; }; then
            conditional_production_issue "CORS preflight origin" "CorsProbeOrigin must be an absolute HTTP(S) origin"
        elif ! command_exists curl; then
            conditional_production_issue "CORS preflight probe" "curl not found; cannot inspect CORS preflight"
        else
            cors_probe_output="$(curl -k -sS -o /dev/null -D - -X OPTIONS -H "Origin: $CORS_PROBE_ORIGIN" -H "Access-Control-Request-Method: GET" -H "Access-Control-Request-Headers: Authorization, Content-Type" -w '\n__HTTP_CODE__:%{http_code}\n' --max-time "$HTTP_PROBE_TIMEOUT_SECONDS" "$cors_preflight_url" 2>/dev/null)"
            cors_probe_exit=$?
            cors_http_code="$(printf '%s\n' "$cors_probe_output" | awk -F: '$1 == "__HTTP_CODE__" { print $2; exit }')"
            if [ "$cors_probe_exit" -ne 0 ] || [ -z "$cors_http_code" ] || [ "$cors_http_code" = "000" ]; then
                conditional_production_issue "CORS preflight probe" "probe failed"
            else
                if [ "$cors_http_code" -ge 200 ] && [ "$cors_http_code" -lt 400 ]; then
                    ok "CORS preflight probe" "HTTP $cors_http_code"
                else
                    conditional_production_issue "CORS preflight probe" "HTTP $cors_http_code"
                fi

                allow_origin="$(cors_header_value_from_output Access-Control-Allow-Origin)"
                origin_allowed=0
                if [ -z "$allow_origin" ]; then
                    conditional_production_issue "CORS Access-Control-Allow-Origin" "missing"
                elif [ "$allow_origin" = "*" ]; then
                    conditional_production_issue "CORS Access-Control-Allow-Origin" "wildcard for origin $CORS_PROBE_ORIGIN"
                elif [ "${allow_origin%/}" = "${CORS_PROBE_ORIGIN%/}" ]; then
                    origin_allowed=1
                    ok "CORS Access-Control-Allow-Origin" "matches probe origin"
                else
                    conditional_production_issue "CORS Access-Control-Allow-Origin" "does not match probe origin"
                fi

                allow_credentials="$(cors_header_value_from_output Access-Control-Allow-Credentials | tr '[:upper:]' '[:lower:]')"
                if [ "$allow_origin" = "*" ] && [ "$allow_credentials" = "true" ]; then
                    conditional_production_issue "CORS credentials policy" "wildcard origin cannot be combined with credentials"
                else
                    ok "CORS credentials policy" "no wildcard plus credentials combination in probe"
                fi

                vary_header="$(cors_header_value_from_output Vary)"
                if [ "$origin_allowed" -eq 1 ]; then
                    if printf '%s' "$vary_header" | grep -Eiq '(^|,[[:space:]]*)Origin([[:space:]]*,|$)'; then
                        ok "CORS Vary header" "Origin present"
                    else
                        conditional_production_issue "CORS Vary header" "reflected origin should include Vary: Origin"
                    fi
                fi

                allow_methods="$(cors_header_value_from_output Access-Control-Allow-Methods | tr '[:upper:]' '[:lower:]')"
                if printf '%s' "$allow_methods" | grep -Eiq '(^|[,[:space:]])(\*|get)([,[:space:]]|$)'; then
                    methods_allowed=1
                    ok "CORS Access-Control-Allow-Methods" "GET allowed"
                else
                    methods_allowed=0
                    conditional_production_issue "CORS Access-Control-Allow-Methods" "GET missing"
                fi

                allow_headers="$(cors_header_value_from_output Access-Control-Allow-Headers | tr '[:upper:]' '[:lower:]')"
                if printf '%s' "$allow_headers" | grep -Eiq '(^|[,[:space:]])(\*|authorization)([,[:space:]]|$)'; then
                    authorization_allowed=1
                else
                    authorization_allowed=0
                fi
                if printf '%s' "$allow_headers" | grep -Eiq '(^|[,[:space:]])(\*|content-type)([,[:space:]]|$)'; then
                    content_type_allowed=1
                else
                    content_type_allowed=0
                fi
                if [ "$authorization_allowed" -eq 1 ] && [ "$content_type_allowed" -eq 1 ]; then
                    ok "CORS Access-Control-Allow-Headers" "Authorization and Content-Type allowed"
                else
                    conditional_production_issue "CORS Access-Control-Allow-Headers" "Authorization or Content-Type missing"
                fi

                if [ "$origin_allowed" -eq 1 ] && [ "$methods_allowed" -eq 1 ] && [ "$authorization_allowed" -eq 1 ] && [ "$content_type_allowed" -eq 1 ]; then
                    cors_probe_confirmed=1
                fi
            fi
        fi
    fi

    if [ "$PRODUCTION" -eq 1 ] && [ "$frontend_api_requires_cors" -eq 1 ] && [ "$cors_probe_confirmed" -ne 1 ] && [ "$has_global_cors_signal" -ne 1 ]; then
        fail "CORS production evidence" "absolute frontend API URL needs app/server CORS evidence or a successful preflight probe"
    fi
fi

if command_exists "$NGINX_BIN"; then
    ok "Nginx command" "$NGINX_BIN found"
    if [ "$CHECK_NGINX_SYNTAX" -eq 1 ]; then
        nginx_output="$("$NGINX_BIN" -t 2>&1)"
        nginx_exit=$?
        if [ "$nginx_exit" -eq 0 ]; then
            ok "Nginx syntax" "nginx -t passed"
        else
            fail "Nginx syntax" "$(printf '%s\n' "$nginx_output" | tail -n 1)"
        fi
    fi
else
    if [ "$CHECK_WEB_SERVER_POLICY" -eq 1 ] || [ "$CHECK_NGINX_SYNTAX" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
        conditional_production_issue "Nginx command" "$NGINX_BIN not found; skip only if managed outside this host"
    else
        warn "Nginx command" "$NGINX_BIN not found; skip only if managed outside this host"
    fi
fi

if command_exists "$PHP_FPM_BIN"; then
    ok "PHP-FPM command" "$PHP_FPM_BIN found"
    if [ "$CHECK_PHP_FPM_SYNTAX" -eq 1 ]; then
        php_fpm_output="$("$PHP_FPM_BIN" -tt 2>&1)"
        php_fpm_exit=$?
        if [ "$php_fpm_exit" -eq 0 ]; then
            ok "PHP-FPM syntax" "php-fpm -tt passed"
        else
            fail "PHP-FPM syntax" "$(printf '%s\n' "$php_fpm_output" | tail -n 1)"
        fi
    fi
else
    if [ "$CHECK_WEB_SERVER_POLICY" -eq 1 ] || [ "$CHECK_PHP_FPM_SYNTAX" -eq 1 ] || [ "$PRODUCTION" -eq 1 ]; then
        conditional_production_issue "PHP-FPM command" "$PHP_FPM_BIN not found; skip only if managed outside this host"
    else
        warn "PHP-FPM command" "$PHP_FPM_BIN not found; skip only if managed outside this host"
    fi
fi

if [ "$SKIP_THINK_BOOT" -eq 1 ]; then
    ok "ThinkPHP route:list boot" "skipped"
elif command_exists "$PHP_BIN" && [ -f "vendor/autoload.php" ] && [ -f "think" ]; then
    route_output="$("$PHP_BIN" think route:list 2>&1)"
    route_exit=$?
    if [ "$route_exit" -eq 0 ]; then
        route_rows="$(printf '%s\n' "$route_output" | grep -c '^[[:space:]]*|')"
        ok "ThinkPHP route:list boot" "$route_rows table rows"
    else
        fail "ThinkPHP route:list boot" "$(printf '%s\n' "$route_output" | tail -n 1)"
    fi
fi

printf '\nDeployment readiness summary: %s failures, %s warnings\n' "$FAILURES" "$WARNINGS"

if [ "$FAILURES" -gt 0 ]; then
    exit 1
fi

if [ "$STRICT" -eq 1 ] && [ "$WARNINGS" -gt 0 ]; then
    printf 'Strict mode treats warnings as failures.\n'
    exit 1
fi

exit 0
