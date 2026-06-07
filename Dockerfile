FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN a2enmod rewrite

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

RUN mkdir -p storage/app/public \
    && (php artisan storage:link || true) \
    && chown -R www-data:www-data storage bootstrap/cache public/storage \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD php artisan config:cache && apache2-foreground
