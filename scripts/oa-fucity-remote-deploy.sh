#!/usr/bin/env bash

set -Eeuo pipefail

ARCHIVE=""
TARGET_ROOT="/www/wwwroot/oa.fucity.cn"
RELEASE_ID="$(date +%Y%m%d-%H%M%S)"
PUBLIC_BASE_URL="https://oa.fucity.cn"
CORS_PROBE_ORIGIN="https://oa.fucity.cn"
OWNER="www:www"
PHP_BIN="php"
MYSQL_DUMP_BIN="mysqldump"
NGINX_BIN="nginx"
SKIP_DB_BACKUP=0
SKIP_READINESS=0
CHECK_CORS=0
CHECK_SECURITY_HEADERS=0
CONFIGURE_NGINX=0
RELOAD_NGINX=0
KEEP_BACKUPS=10
ROLLBACK_FILES=""
NGINX_BACKUP_DIR=""

log() {
    printf '[remote-deploy] %s\n' "$*"
}

die() {
    printf '[remote-deploy][ERROR] %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'USAGE'
Usage:
  scripts/oa-fucity-remote-deploy.sh --archive FILE [options]
  scripts/oa-fucity-remote-deploy.sh --rollback-files FILE [options]

Options:
  --archive FILE              Release zip uploaded to the server.
  --target DIR                Deployment root, default: /www/wwwroot/oa.fucity.cn.
  --release-id ID             Release identifier, default: current timestamp.
  --public-base-url URL       Public URL for readiness probes.
  --cors-probe-origin URL     Origin used when --check-cors is enabled.
  --owner USER:GROUP          chown target files after deploy, default: www:www.
  --no-owner                  Do not chown files.
  --php-bin PATH              PHP binary, default: php.
  --mysql-dump-bin PATH       mysqldump binary, default: mysqldump.
  --nginx-bin PATH            Nginx binary, default: nginx.
  --skip-db-backup            Do not dump the database before replacing files.
  --skip-readiness            Do not run deployment-readiness.sh after deploy.
  --check-cors                Run the live CORS OPTIONS probe.
  --check-security-headers    Run the live security header probe.
  --configure-nginx           Configure BaoTa Nginx for frontend dist and /backend.
  --reload-nginx              Run nginx -t and nginx -s reload after deploy.
  --keep-backups N            Keep the newest N file and DB backups, default: 10.
  --rollback-files FILE       Restore a previous file backup tar.gz.
  -h, --help                  Show this help.
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --archive)
            ARCHIVE="${2:-}"
            shift 2
            ;;
        --target)
            TARGET_ROOT="${2:-}"
            shift 2
            ;;
        --release-id)
            RELEASE_ID="${2:-}"
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
        --owner)
            OWNER="${2:-}"
            shift 2
            ;;
        --no-owner)
            OWNER=""
            shift
            ;;
        --php-bin)
            PHP_BIN="${2:-}"
            shift 2
            ;;
        --mysql-dump-bin)
            MYSQL_DUMP_BIN="${2:-}"
            shift 2
            ;;
        --nginx-bin)
            NGINX_BIN="${2:-}"
            shift 2
            ;;
        --skip-db-backup)
            SKIP_DB_BACKUP=1
            shift
            ;;
        --skip-readiness)
            SKIP_READINESS=1
            shift
            ;;
        --check-cors)
            CHECK_CORS=1
            shift
            ;;
        --check-security-headers)
            CHECK_SECURITY_HEADERS=1
            shift
            ;;
        --configure-nginx)
            CONFIGURE_NGINX=1
            shift
            ;;
        --reload-nginx)
            RELOAD_NGINX=1
            shift
            ;;
        --keep-backups)
            KEEP_BACKUPS="${2:-}"
            shift 2
            ;;
        --rollback-files)
            ROLLBACK_FILES="${2:-}"
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

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

require_command() {
    command_exists "$1" || die "required command not found: $1"
}

require_safe_target_root() {
    case "$TARGET_ROOT" in
        /*) ;;
        *) die "target root must be an absolute path: $TARGET_ROOT" ;;
    esac

    case "$TARGET_ROOT" in
        /|/www|/www/|/www/wwwroot|/www/wwwroot/)
            die "refusing unsafe target root: $TARGET_ROOT"
            ;;
    esac
}

env_value() {
    key="$1"
    env_file="$TARGET_ROOT/.env"
    [ -f "$env_file" ] || return 1
    awk -v target="$key" '
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
            if (key == target) {
                value = trim(substr(line, separator + 1))
                gsub(/^["\047]|["\047]$/, "", value)
                print value
                exit
            }
        }
    ' "$env_file"
}

lowercase() {
    printf '%s' "$1" | tr '[:upper:]' '[:lower:]'
}

require_production_env() {
    [ -f "$TARGET_ROOT/.env" ] || die "$TARGET_ROOT/.env missing; create production env before deployment"
    app_debug="$(lowercase "$(env_value APP_DEBUG || true)")"
    case "$app_debug" in
        false|0|off|no)
            log "APP_DEBUG is production-safe"
            ;;
        *)
            die "APP_DEBUG must be false for production"
            ;;
    esac
}

safe_remove_deploy_dir() {
    path="$1"
    case "$path" in
        "$DEPLOY_ROOT"/staging/*|"$DEPLOY_ROOT"/rollback/*)
            rm -rf -- "$path"
            ;;
        *)
            die "refusing to remove unsafe path: $path"
            ;;
    esac
}

create_file_backup() {
    backup_file="$BACKUPS_ROOT/files-$RELEASE_ID.tar.gz"
    log "backup files to $backup_file"
    tar -czf "$backup_file" --exclude='./.deploy' -C "$TARGET_ROOT" .
    chmod 600 "$backup_file" || true
}

create_db_backup() {
    [ "$SKIP_DB_BACKUP" -eq 0 ] || {
        log "database backup skipped"
        return
    }

    require_command "$MYSQL_DUMP_BIN"

    db_host="$(env_value DB_HOST || true)"
    db_name="$(env_value DB_NAME || true)"
    db_user="$(env_value DB_USER || true)"
    db_pass="$(env_value DB_PASS || true)"
    db_port="$(env_value DB_PORT || true)"
    db_port="${db_port:-3306}"

    [ -n "$db_host" ] || die "DB_HOST missing in .env"
    [ -n "$db_name" ] || die "DB_NAME missing in .env"
    [ -n "$db_user" ] || die "DB_USER missing in .env"

    db_backup_file="$BACKUPS_ROOT/db-$RELEASE_ID.sql.gz"
    log "backup database to $db_backup_file"
    MYSQL_PWD="$db_pass" "$MYSQL_DUMP_BIN" \
        --single-transaction \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        "$db_name" | gzip > "$db_backup_file"
    chmod 600 "$db_backup_file" || true
}

prune_backups_by_pattern() {
    pattern="$1"
    case "$KEEP_BACKUPS" in
        ''|*[!0-9]*)
            return
            ;;
    esac
    [ "$KEEP_BACKUPS" -gt 0 ] || return 0

    count="$(find "$BACKUPS_ROOT" -maxdepth 1 -type f -name "$pattern" | wc -l | awk '{print $1}')"
    remove_count=$((count - KEEP_BACKUPS))
    [ "$remove_count" -gt 0 ] || return 0

    find "$BACKUPS_ROOT" -maxdepth 1 -type f -name "$pattern" -printf '%T@ %p\n' \
        | sort -n \
        | head -n "$remove_count" \
        | cut -d' ' -f2- \
        | while IFS= read -r old_backup; do
        rm -f -- "$old_backup"
    done
}

prepare_staging() {
    require_command unzip
    require_command rsync

    [ -f "$ARCHIVE" ] || die "archive missing: $ARCHIVE"
    STAGING_DIR="$DEPLOY_ROOT/staging/$RELEASE_ID"
    safe_remove_deploy_dir "$STAGING_DIR"
    mkdir -p "$STAGING_DIR"

    log "extract archive to $STAGING_DIR"
    set +e
    unzip -q "$ARCHIVE" -d "$STAGING_DIR"
    unzip_status=$?
    set -e
    if [ "$unzip_status" -ne 0 ]; then
        [ "$unzip_status" -eq 1 ] || die "unzip failed with exit code $unzip_status"
        log "unzip completed with warnings; validating extracted release"
    fi

    [ -f "$STAGING_DIR/public/index.php" ] || die "release missing public/index.php"
    [ -f "$STAGING_DIR/vendor/autoload.php" ] || die "release missing vendor/autoload.php"
    [ -f "$STAGING_DIR/snowy-admin-web/dist/index.html" ] || die "release missing frontend dist/index.html"

    cp -p "$TARGET_ROOT/.env" "$STAGING_DIR/.env"

    log "verify ThinkPHP boot in staging"
    (cd "$STAGING_DIR" && "$PHP_BIN" think route:list >/dev/null)
    find "$STAGING_DIR/runtime" -type f -name 'route_list.php' -delete 2>/dev/null || true
}

apply_staging() {
    log "sync staging to live root"
    rsync -a --delete \
        --exclude='/.deploy/' \
        --exclude='/.env' \
        --exclude='/.user.ini' \
        --exclude='/runtime/upload/' \
        --exclude='/runtime/storage/' \
        --exclude='/runtime/backup/' \
        --exclude='/public/storage/' \
        --exclude='/public/upload/' \
        "$STAGING_DIR"/ "$TARGET_ROOT"/

    mkdir -p \
        "$TARGET_ROOT/runtime/log" \
        "$TARGET_ROOT/runtime/cache" \
        "$TARGET_ROOT/runtime/temp" \
        "$TARGET_ROOT/runtime/storage" \
        "$TARGET_ROOT/runtime/upload" \
        "$TARGET_ROOT/runtime/backup" \
        "$TARGET_ROOT/public/storage" \
        "$TARGET_ROOT/public/upload/dev_file"

    chmod -R u+rwX "$TARGET_ROOT/runtime" "$TARGET_ROOT/public/storage" "$TARGET_ROOT/public/upload" || true
    if [ -n "$OWNER" ]; then
        if id "${OWNER%%:*}" >/dev/null 2>&1; then
            chown_targets=()
            for relative_path in app config extend public route scripts snowy-admin-web vendor view runtime composer.json composer.lock think LICENSE.txt README.md; do
                if [ -e "$TARGET_ROOT/$relative_path" ]; then
                    chown_targets+=("$TARGET_ROOT/$relative_path")
                fi
            done
            if [ "${#chown_targets[@]}" -gt 0 ]; then
                chown -R "$OWNER" "${chown_targets[@]}" || true
            fi
        else
            log "owner user not found, skipped chown: $OWNER"
        fi
    fi
}

fix_writable_ownership() {
    chmod -R u+rwX "$TARGET_ROOT/runtime" "$TARGET_ROOT/public/storage" "$TARGET_ROOT/public/upload" || true

    if [ -z "$OWNER" ]; then
        return 0
    fi

    owner_user="${OWNER%%:*}"
    if ! id "$owner_user" >/dev/null 2>&1; then
        log "owner user not found, skipped writable chown: $OWNER"
        return 0
    fi

    for writable_path in "$TARGET_ROOT/runtime" "$TARGET_ROOT/public/storage" "$TARGET_ROOT/public/upload"; do
        if [ -e "$writable_path" ]; then
            chown -R "$OWNER" "$writable_path" || true
        fi
    done
}

harden_env_permissions() {
    env_file="$TARGET_ROOT/.env"
    [ -f "$env_file" ] || return 0

    env_group=""
    case "$OWNER" in
        *:*) env_group="${OWNER#*:}" ;;
    esac

    if [ -n "$env_group" ] && getent group "$env_group" >/dev/null 2>&1; then
        chown "root:$env_group" "$env_file" || true
        chmod 640 "$env_file" || true
    else
        chmod 600 "$env_file" || true
    fi
}

write_nginx_cors_report() {
    if command_exists "$NGINX_BIN"; then
        report_file="$DEPLOY_ROOT/nginx-cors-$RELEASE_ID.txt"
        "$NGINX_BIN" -T 2>&1 | grep -nE 'oa\.fucity\.cn|Access-Control-Allow-Origin|proxy_hide_header|fastcgi_hide_header|fucity' > "$report_file" || true
        chmod 600 "$report_file" || true
        log "wrote nginx CORS report to $report_file"
    fi
}

detect_fastcgi_pass() {
    fastcgi_pass=""
    for include_file in /www/server/nginx/conf/enable-php-83.conf /www/server/nginx/conf/enable-php-82.conf /www/server/nginx/conf/enable-php-81.conf /www/server/nginx/conf/enable-php-80.conf; do
        if [ -f "$include_file" ]; then
            fastcgi_pass="$(awk '/fastcgi_pass[[:space:]]+/ { gsub(/;/, "", $2); print $2; exit }' "$include_file")"
            if [ -n "$fastcgi_pass" ]; then
                printf '%s\n' "$fastcgi_pass"
                return 0
            fi
        fi
    done
    return 1
}

configure_nginx_site() {
    [ "$CONFIGURE_NGINX" -eq 1 ] || return 0

    vhost_file="/www/server/panel/vhost/nginx/oa.fucity.cn.conf"
    rewrite_file="/www/server/panel/vhost/rewrite/oa.fucity.cn.conf"
    NGINX_BACKUP_DIR="$BACKUPS_ROOT/nginx-$RELEASE_ID"

    [ -f "$vhost_file" ] || die "BaoTa vhost file missing: $vhost_file"
    mkdir -p "$NGINX_BACKUP_DIR"
    cp -p "$vhost_file" "$NGINX_BACKUP_DIR/oa.fucity.cn.conf"
    if [ -f "$rewrite_file" ]; then
        cp -p "$rewrite_file" "$NGINX_BACKUP_DIR/oa.fucity.cn.rewrite.conf"
    fi

    frontend_root="$TARGET_ROOT/snowy-admin-web/dist"
    backend_public_root="$TARGET_ROOT/public"
    fastcgi_pass="$(detect_fastcgi_pass)" || die "unable to detect PHP-FPM fastcgi_pass from BaoTa PHP config"

    log "configure BaoTa Nginx root to frontend dist and /backend to ThinkPHP"
    "$PHP_BIN" -r '$path = $argv[1]; $root = $argv[2]; $text = file_get_contents($path); if ($text === false) { fwrite(STDERR, "read failed\n"); exit(1); } $next = preg_replace("/^[ \t]*root[ \t]+[^;]+;/m", "    root " . $root . ";", $text, 1, $count); if ($count !== 1) { fwrite(STDERR, "root directive patch count=" . $count . "\n"); exit(1); } file_put_contents($path, $next);' "$vhost_file" "$frontend_root"

    cat > "$rewrite_file" <<EOF_NGINX
location = /backend {
    return 301 /backend/;
}

location ^~ /backend/ {
    set \$cors_origin "";
    if (\$http_origin ~* ^https://([a-z0-9-]+\.)*fucity\.cn(:[0-9]+)?\$) {
        set \$cors_origin \$http_origin;
    }

    add_header Access-Control-Allow-Origin \$cors_origin always;
    add_header Access-Control-Allow-Credentials "true" always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, PATCH, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With, Token, Satoken, X-Access-Token" always;
    add_header Vary "Origin" always;

    if (\$request_method = OPTIONS) {
        return 204;
    }

    set \$backend_path_info "";
    if (\$uri ~ ^/backend(/.*)\$) {
        set \$backend_path_info \$1;
    }

    include fastcgi.conf;
    fastcgi_param SCRIPT_FILENAME $backend_public_root/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param DOCUMENT_ROOT $backend_public_root;
    fastcgi_param DOCUMENT_URI \$backend_path_info;
    fastcgi_param REQUEST_URI \$backend_path_info\$is_args\$args;
    fastcgi_param PATH_INFO \$backend_path_info;
    fastcgi_index index.php;
    fastcgi_pass $fastcgi_pass;
}

location ~ ^/(composer\.(json|lock)|\.example\.env|runtime|app|config|route|extend|docs|scripts|tests|PLANS\.md|IMPLEMENT\.md|STATUS\.md)(/|\$) {
    return 404;
}

location ~ ^/vendor(/|\$) {
    return 404;
}

location ^~ /storage/ {
    alias $backend_public_root/storage/;
    access_log off;
    expires 30d;
}

location / {
    try_files \$uri \$uri/ /index.html;
}
EOF_NGINX

    "$NGINX_BIN" -t
    log "Nginx config backup written to $NGINX_BACKUP_DIR"
}

post_deploy_checks() {
    log "verify ThinkPHP boot in live root"
    (cd "$TARGET_ROOT" && "$PHP_BIN" think route:list >/dev/null)
    find "$TARGET_ROOT/runtime" -maxdepth 1 -type f -name 'route_list.php' -delete 2>/dev/null || true

    [ "$SKIP_READINESS" -eq 0 ] || {
        log "readiness check skipped"
        return
    }

    if [ -x "$TARGET_ROOT/scripts/deployment-readiness.sh" ] || [ -f "$TARGET_ROOT/scripts/deployment-readiness.sh" ]; then
        readiness_args=(
            --check-composer-policy
            --check-backup-tools
            --check-runtime-permission-policy
            --check-url-policy
            --backup-directory "$BACKUPS_ROOT"
            --public-base-url "$PUBLIC_BASE_URL"
        )
        if [ "$CHECK_CORS" -eq 1 ]; then
            readiness_args+=(--check-cors-policy --cors-probe-origin "$CORS_PROBE_ORIGIN")
        fi
        if [ "$CHECK_SECURITY_HEADERS" -eq 1 ]; then
            readiness_args+=(--check-security-headers-policy)
        fi

        log "run deployment readiness"
        (cd "$TARGET_ROOT" && bash scripts/deployment-readiness.sh "${readiness_args[@]}")
        find "$TARGET_ROOT/runtime" -maxdepth 1 -type f -name 'route_list.php' -delete 2>/dev/null || true
    fi
}

reload_nginx_if_requested() {
    [ "$RELOAD_NGINX" -eq 1 ] || return 0
    require_command "$NGINX_BIN"
    log "test and reload nginx"
    "$NGINX_BIN" -t
    "$NGINX_BIN" -s reload
}

restore_files_from_backup() {
    backup="$1"
    [ -f "$backup" ] || die "rollback file backup missing: $backup"
    require_command rsync

    rollback_dir="$DEPLOY_ROOT/rollback/$RELEASE_ID"
    safe_remove_deploy_dir "$rollback_dir"
    mkdir -p "$rollback_dir"

    safety_backup="$BACKUPS_ROOT/files-before-rollback-$RELEASE_ID.tar.gz"
    log "backup current files before rollback to $safety_backup"
    tar -czf "$safety_backup" --exclude='./.deploy' -C "$TARGET_ROOT" .
    chmod 600 "$safety_backup" || true

    log "extract rollback backup"
    tar -xzf "$backup" -C "$rollback_dir"
    log "restore files from $backup"
    rsync -a --delete --exclude='/.deploy/' --exclude='/.user.ini' "$rollback_dir"/ "$TARGET_ROOT"/
    safe_remove_deploy_dir "$rollback_dir"
}

restore_nginx_from_backup() {
    backup_dir="$1"
    [ -n "$backup_dir" ] || return 0
    [ -d "$backup_dir" ] || return 0

    vhost_file="/www/server/panel/vhost/nginx/oa.fucity.cn.conf"
    rewrite_file="/www/server/panel/vhost/rewrite/oa.fucity.cn.conf"
    if [ -f "$backup_dir/oa.fucity.cn.conf" ]; then
        log "restore Nginx vhost from $backup_dir"
        cp -p "$backup_dir/oa.fucity.cn.conf" "$vhost_file"
    fi
    if [ -f "$backup_dir/oa.fucity.cn.rewrite.conf" ]; then
        cp -p "$backup_dir/oa.fucity.cn.rewrite.conf" "$rewrite_file"
    fi
    if command_exists "$NGINX_BIN"; then
        if "$NGINX_BIN" -t; then
            "$NGINX_BIN" -s reload || true
        else
            log "restored Nginx config did not pass nginx -t"
        fi
    fi
}

rollback_on_failure() {
    status=$?
    if [ "$status" -ne 0 ] && [ -n "${backup_file:-}" ] && [ -f "${backup_file:-}" ]; then
        log "deployment failed; rolling files back from $backup_file"
        restore_files_from_backup "$backup_file" || true
    fi
    if [ "$status" -ne 0 ]; then
        restore_nginx_from_backup "$NGINX_BACKUP_DIR" || true
    fi
    exit "$status"
}

require_safe_target_root
TARGET_ROOT="${TARGET_ROOT%/}"
DEPLOY_ROOT="$TARGET_ROOT/.deploy"
BACKUPS_ROOT="$DEPLOY_ROOT/backups"
mkdir -p "$TARGET_ROOT" "$DEPLOY_ROOT/staging" "$DEPLOY_ROOT/rollback" "$BACKUPS_ROOT"
chmod 700 "$DEPLOY_ROOT" "$BACKUPS_ROOT" || true

require_command tar
require_command gzip
require_command "$PHP_BIN"

if [ -n "$ROLLBACK_FILES" ]; then
    restore_files_from_backup "$ROLLBACK_FILES"
    write_nginx_cors_report
    log "rollback completed"
    exit 0
fi

[ -n "$ARCHIVE" ] || die "--archive is required"
require_production_env
trap rollback_on_failure EXIT

create_file_backup
create_db_backup
prepare_staging
apply_staging
harden_env_permissions
configure_nginx_site
reload_nginx_if_requested
post_deploy_checks
fix_writable_ownership
write_nginx_cors_report

trap - EXIT

printf '%s\n' "$RELEASE_ID" > "$DEPLOY_ROOT/current-release"
printf '%s\n' "$backup_file" > "$DEPLOY_ROOT/last-file-backup"
if [ -n "${db_backup_file:-}" ]; then
    printf '%s\n' "$db_backup_file" > "$DEPLOY_ROOT/last-db-backup"
fi

safe_remove_deploy_dir "$STAGING_DIR" || true
prune_backups_by_pattern 'files-*.tar.gz' || true
prune_backups_by_pattern 'db-*.sql.gz' || true

log "deployment completed: $RELEASE_ID"
