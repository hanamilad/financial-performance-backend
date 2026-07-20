#!/bin/sh
#
# Container entrypoint shared by the app, worker and scheduler services.
#
# Responsibilities:
#   1. Make sure the writable Laravel directories exist and are writable.
#   2. Wait for MySQL and Redis to accept connections.
#   3. Wait for vendor/ when the command needs it.
#   4. exec the requested command as PID 1.
#
# It deliberately does NOT run migrations, key:generate or any cache warming.
# Those stay explicit operator actions (see README.md).

set -eu

log() {
    echo "[entrypoint] $*" >&2
}

APP_ROOT="${APP_ROOT:-/var/www/html}"
cd "$APP_ROOT"

# ---------------------------------------------------------------------------
# Writable directories
#
# On Windows bind mounts ownership cannot be changed, so chown is best effort:
# those mounts are already world-writable and the failure is harmless. On a
# Linux host (or a repo moved inside WSL2) the chown is what actually matters.
# ---------------------------------------------------------------------------
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache 2>/dev/null || true

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

# ---------------------------------------------------------------------------
# Dependency readiness
# ---------------------------------------------------------------------------
wait_for_tcp() {
    label="$1"
    host="$2"
    port="$3"
    attempts="${4:-60}"

    [ -n "$host" ] || return 0

    i=1
    while [ "$i" -le "$attempts" ]; do
        if php -r 'exit(@fsockopen($argv[1], (int) $argv[2], $e, $s, 1) ? 0 : 1);' "$host" "$port" 2>/dev/null; then
            [ "$i" -eq 1 ] || log "$label is up at ${host}:${port}"
            return 0
        fi
        [ "$i" -eq 1 ] && log "waiting for $label at ${host}:${port} ..."
        i=$((i + 1))
        sleep 1
    done

    log "ERROR: $label never became reachable at ${host}:${port}"
    return 1
}

wait_for_vendor() {
    attempts="${1:-300}"

    i=1
    while [ "$i" -le "$attempts" ]; do
        if [ -f "$APP_ROOT/vendor/autoload.php" ]; then
            return 0
        fi
        [ "$i" -eq 1 ] && log "vendor/ is missing — run: docker compose exec app composer install"
        i=$((i + 1))
        sleep 2
    done

    log "ERROR: vendor/autoload.php never appeared"
    return 1
}

wait_for_tcp "MySQL" "${DB_HOST:-mysql}" "${DB_PORT:-3306}"
wait_for_tcp "Redis" "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}"

# Only artisan-based services are blocked on vendor/. php-fpm and composer
# must be able to boot without it, otherwise the very command that creates
# vendor/ could never run.
case "${1:-}" in
    php-fpm|composer) ;;
    *) wait_for_vendor ;;
esac

log "starting: $*"
exec "$@"
