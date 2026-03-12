#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

exec "$@"
