#!/bin/sh
set -e

PUBLIC_DISK_ROOT="${PUBLIC_DISK_ROOT:-/var/www/html/storage/app/public}"

mkdir -p \
  "$PUBLIC_DISK_ROOT" \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/bootstrap/cache

rm -f \
  /var/www/html/bootstrap/cache/packages.php \
  /var/www/html/bootstrap/cache/services.php \
  /var/www/html/bootstrap/cache/config.php \
  /var/www/html/bootstrap/cache/routes-v7.php \
  /var/www/html/bootstrap/cache/events.php

if [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "Installing backend dependencies..."
  composer install --optimize-autoloader
fi

php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan package:discover || true
php artisan storage:link || true

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache "$PUBLIC_DISK_ROOT" /var/www/html/public/storage || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

echo "Starting backend in development mode..."
exec apache2-foreground
