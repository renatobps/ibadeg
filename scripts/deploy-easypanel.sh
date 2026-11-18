#!/bin/sh
set -eu

cd "$(dirname "$0")/.."

: "${APP_KEY:?Configure APP_KEY no ambiente do Easypanel.}"
: "${APP_URL:?Configure APP_URL no ambiente do Easypanel.}"

mkdir -p storage/app/private storage/app/public storage/fonts \
    storage/framework/cache/data storage/framework/cache/pdf \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan ibadeg:bootstrap --no-interaction
php artisan optimize --no-interaction

if [ "$(id -u)" = "0" ] && id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache
fi
chmod -R ug+rwX storage bootstrap/cache
