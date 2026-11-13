# Hazir imajla (docker-compose.release.yml) kullanim icin: gelistirmede
# www/public ve default.conf host'tan bind-mount edilir, burada imaja
# kopyalanir. "app" servisi php-fpm'i app:9000'de sunar.
FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY www/public /var/www/html/public
