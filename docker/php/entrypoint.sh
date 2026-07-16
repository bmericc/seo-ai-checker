#!/bin/sh
set -e

# www/database bind-mount edildigi icin host'ta olusturulan/degistirilen
# database.sqlite host kullanicisina ait olabilir; www-data yazamazsa
# uygulama sessizce (log bile yazamadan) 500 verir. Bu script root olarak
# calisir (php-fpm container'in normal baslangic sureci), bu yuzden
# sahiplikten bagimsiz sekilde chown/chmod yapabilir. chown SART: dosya
# root:root sahipliginde olusturulursa, chmod 664 "other" grubuna sadece
# okuma hakki birakir ve www-data (php-fpm worker) yine yazamaz.
mkdir -p database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

exec docker-php-entrypoint "$@"
