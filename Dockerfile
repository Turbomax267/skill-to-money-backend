FROM richarvey/nginx-php-fpm:latest

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan config:clear
RUN php artisan route:clear
RUN php artisan view:clear

RUN chmod -R 775 storage bootstrap/cache

ENV WEBROOT=/var/www/html/public

CMD php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && /start.sh
