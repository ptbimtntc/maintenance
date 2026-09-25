#!/usr/bin/env bash
#
# Deploy this app to the mpd.alfajarlogic.com hosting (Hostinger hPanel).
#
# Usage:
#   ./deploy.sh            # build assets + upload code + refresh caches
#   ./deploy.sh --migrate  # same, plus run pending migrations
#
# Requires the "hostinger_deploy" SSH key to already be imported and
# authorized in hPanel (Advanced -> SSH Access -> Manage SSH Keys).
# If you're on a fresh machine/Codespace without that key, generate one
# with `ssh-keygen -t ed25519 -f ~/.ssh/hostinger_deploy` and import the
# .pub into hPanel before running this script.

set -euo pipefail

SSH_HOST="46.202.138.72"
SSH_PORT="65002"
SSH_USER="u627878615"
SSH_KEY="$HOME/.ssh/hostinger_deploy"
REMOTE_APP_DIR="/home/u627878615/laravel_apps/mpd"

SSH_OPTS=(-p "$SSH_PORT" -i "$SSH_KEY" -o IdentitiesOnly=yes)
REMOTE="$SSH_USER@$SSH_HOST"

RUN_MIGRATE=false
for arg in "$@"; do
    case "$arg" in
        --migrate) RUN_MIGRATE=true ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

echo "==> Building front-end assets"
npm install
npm run build

echo "==> Installing production PHP dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Uploading changed files to $REMOTE:$REMOTE_APP_DIR"
rsync -az --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude='storage' \
    --exclude='.devcontainer' \
    --exclude='.claude' \
    -e "ssh ${SSH_OPTS[*]}" \
    ./ "$REMOTE:$REMOTE_APP_DIR/"

echo "==> Refreshing caches on the server"
ssh "${SSH_OPTS[@]}" "$REMOTE" "cd '$REMOTE_APP_DIR' && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    chmod -R 775 storage bootstrap/cache"

if [ "$RUN_MIGRATE" = true ]; then
    echo "==> Running database migrations"
    ssh "${SSH_OPTS[@]}" "$REMOTE" "cd '$REMOTE_APP_DIR' && php artisan migrate --force"
fi

echo "==> Done. https://mpd.alfajarlogic.com"
