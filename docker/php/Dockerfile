FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git \
        unzip \
        libpng-dev \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        dom \
        xml \
        zip \
        pcntl \
    && docker-php-ext-enable opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY www/composer.json www/composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY www/ .

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
