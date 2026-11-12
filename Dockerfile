# =====================================================================
# Dockerfile Backend Laravel (PHP 8.3 CLI/Server) — CV SIP Inventory ERP
# =====================================================================
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    unzip \
    git \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    pgsql \
    mbstring \
    zip \
    intl \
    bcmath \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

CMD ["sh", "-c", "php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080"]

