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

php artisan migrate --force --path=database/migrations/2026_06_16_000009_create_subscription_tables.php
php artisan migrate --force --path=database/migrations/2026_06_17_000020_create_contract_escrow_tables.php

php artisan config:cache

exec apache2-foreground
