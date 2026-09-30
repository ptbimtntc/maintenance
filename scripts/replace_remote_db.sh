#!/usr/bin/env bash
#
# DANGEROUS: wipes ALL data in the production MySQL database on
# mpd.alfajarlogic.com and replaces it with the data currently in this
# machine's local SQLite database (database/database.sqlite).
#
# Run this from a machine/terminal that actually has network access to the
# Hostinger server (this failed from the Codespace sandbox due to blocked
# egress on ports 443/65002).
#
# Usage:
#   ./scripts/replace_remote_db.sh

set -euo pipefail

SSH_HOST="46.202.138.72"
SSH_PORT="65002"
SSH_USER="u627878615"
SSH_KEY="$HOME/.ssh/hostinger_deploy"
REMOTE_APP_DIR="/home/u627878615/laravel_apps/mpd"
LOCAL_SQLITE="database/database.sqlite"
TUNNEL_LOCAL_PORT="33061"

SSH_OPTS=(-p "$SSH_PORT" -i "$SSH_KEY" -o IdentitiesOnly=yes)
REMOTE="$SSH_USER@$SSH_HOST"

echo "==> Reading remote DB credentials from $REMOTE_APP_DIR/.env"
ENV_OUT=$(ssh "${SSH_OPTS[@]}" "$REMOTE" "grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' '$REMOTE_APP_DIR/.env'")

DB_HOST=$(echo "$ENV_OUT" | grep '^DB_HOST=' | cut -d= -f2-)
DB_PORT=$(echo "$ENV_OUT" | grep '^DB_PORT=' | cut -d= -f2-)
DB_DATABASE=$(echo "$ENV_OUT" | grep '^DB_DATABASE=' | cut -d= -f2-)
DB_USERNAME=$(echo "$ENV_OUT" | grep '^DB_USERNAME=' | cut -d= -f2-)
DB_PASSWORD=$(echo "$ENV_OUT" | grep '^DB_PASSWORD=' | cut -d= -f2-)
DB_PORT="${DB_PORT:-3306}"

echo "==> Wiping and recreating schema on remote (php artisan migrate:fresh --force)"
ssh "${SSH_OPTS[@]}" "$REMOTE" "cd '$REMOTE_APP_DIR' && php artisan migrate:fresh --force"

echo "==> Opening SSH tunnel to remote MySQL ($DB_HOST:$DB_PORT -> localhost:$TUNNEL_LOCAL_PORT)"
ssh "${SSH_OPTS[@]}" -L "${TUNNEL_LOCAL_PORT}:${DB_HOST}:${DB_PORT}" -N "$REMOTE" &
TUNNEL_PID=$!
trap 'kill "$TUNNEL_PID" 2>/dev/null || true' EXIT
sleep 3

echo "==> Copying data from local SQLite into remote MySQL"
php scripts/copy_sqlite_to_mysql.php \
    --sqlite="$LOCAL_SQLITE" \
    --host=127.0.0.1 \
    --port="$TUNNEL_LOCAL_PORT" \
    --db="$DB_DATABASE" \
    --user="$DB_USERNAME" \
    --pass="$DB_PASSWORD"

echo "==> Refreshing caches on the server"
ssh "${SSH_OPTS[@]}" "$REMOTE" "cd '$REMOTE_APP_DIR' && php artisan config:cache && php artisan route:cache && php artisan view:cache"

echo "==> Done."
