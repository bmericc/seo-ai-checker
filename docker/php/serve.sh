#!/bin/sh
# Hazir imajin varsayilan komutu: php-fpm ve nginx'i ayni container'da
# calistirir. Biri durursa digeri de durdurulur ve container cikar; boylece
# Docker'in restart politikasi ikisini birlikte yeniden baslatir.
php-fpm -F &
FPM_PID=$!
nginx -g 'daemon off;' &
NGINX_PID=$!

# php:*-fpm imaji STOPSIGNAL SIGQUIT tanimlar (docker stop QUIT gonderir);
# QUIT her ikisinde de istekleri bitirip kapanma demek.
trap 'kill -QUIT "$FPM_PID" "$NGINX_PID" 2>/dev/null' TERM INT QUIT

while kill -0 "$FPM_PID" 2>/dev/null && kill -0 "$NGINX_PID" 2>/dev/null; do
    sleep 2
done

kill -TERM "$FPM_PID" "$NGINX_PID" 2>/dev/null
wait
exit 1
