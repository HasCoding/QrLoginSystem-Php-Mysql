# Aşama 1: Composer ile bağımlılıkları kur
FROM composer:2.7 AS builder

WORKDIR /app
COPY ./src /app
RUN composer install --no-dev --optimize-autoloader

# Aşama 2: PHP-FPM
FROM php:8.2-fpm-alpine

# Gerekli sistem paketlerini kur
RUN apk add --no-cache libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS autoconf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip \
    && apk del .build-deps

WORKDIR /var/www/html

# Kodları kopyala
COPY --from=builder /app /var/www/html

# Dosya sahipliklerini ayarla
RUN chown -R www-data:www-data /var/www/html