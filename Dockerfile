FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip zip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan config:clear || true

EXPOSE 10000

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}