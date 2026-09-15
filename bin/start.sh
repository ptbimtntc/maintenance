#!/usr/bin/env bash
# Convenience script for local development in this Codespace.
#
# This container has no init system, so MySQL does not start on its own
# after a Codespace restart/rebuild, and the dev server does not survive
# a restart either. Run this script each time you reopen the Codespace.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Starting MySQL..."
sudo service mysql start

echo "==> Waiting for MySQL to accept connections..."
until sudo mysqladmin ping --silent 2>/dev/null; do
    sleep 1
done

echo "==> Ensuring public storage symlink exists (for employee photos)..."
[ -L public/storage ] || php artisan storage:link

echo "==> Starting Laravel dev server on 0.0.0.0:8000..."
php artisan serve --host=0.0.0.0 --port=8000
