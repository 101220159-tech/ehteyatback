#!/usr/bin/env bash
set -euo pipefail

echo "Deploying Service Platform..."

git pull origin main

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force

php artisan optimize:clear

php artisan optimize

php artisan queue:restart

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload php8.2-fpm 2>/dev/null || sudo systemctl reload php8.3-fpm 2>/dev/null || true
fi

echo "Deployment complete."
