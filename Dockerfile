FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN echo "upload_max_filesize=500M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=550M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=1024M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_file_uploads=100" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time=600" >> /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/app/public/designs \
    && mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && php artisan storage:link || true \
    && php artisan config:clear || true

EXPOSE 10000

CMD chmod -R 777 storage bootstrap/cache && php artisan migrate --force && php artisan storage:link || true && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}