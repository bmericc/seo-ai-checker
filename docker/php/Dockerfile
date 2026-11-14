FROM php:8.5-fpm-alpine AS app

# Prod'da COMPOSER_INSTALL_FLAGS=--no-dev gecirilir (.env.prod), dev deps
# (phpunit, pint, pail...) imaja dahil edilmez.
ARG COMPOSER_INSTALL_FLAGS=""

RUN apk add --no-cache \
        git \
        unzip \
        libpng-dev \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
        icu-dev \
    && docker-php-ext-install \
        zip \
        pcntl \
        intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY www/composer.json www/composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist ${COMPOSER_INSTALL_FLAGS}

COPY www/ .

RUN composer dump-autoload --optimize ${COMPOSER_INSTALL_FLAGS} \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# Hazir imaj (GHCR / Docker Hub, bkz. .github/workflows/docker.yml): tek
# imajda php-fpm + nginx. Gelistirme/prod compose'u "app" hedefini kullanir
# ve nginx'i ayri webserver servisi olarak calistirir.
FROM app AS release

RUN apk add --no-cache nginx

COPY docker/php/nginx-release.conf /etc/nginx/http.d/default.conf
COPY docker/php/serve.sh /usr/local/bin/serve
RUN chmod +x /usr/local/bin/serve

EXPOSE 80
CMD ["serve"]
