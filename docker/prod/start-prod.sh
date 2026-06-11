#!/bin/sh
set -e

PUBLIC_DISK_ROOT="${PUBLIC_DISK_ROOT:-/var/www/html/storage/app/public}"

mkdir -p \
  "$PUBLIC_DISK_ROOT" \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/bootstrap/cache

php artisan storage:link || true

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache "$PUBLIC_DISK_ROOT" /var/www/html/public/storage || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

php artisan config:cache

exec apache2-foreground
