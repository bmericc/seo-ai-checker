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

# www/.env: dotenv'in "dosya yok" PHPUnit uyarisini onlemek icin bos da
# olsa var olmasi gerekir (bkz. README). APP_KEY icin kalici depolama
# olarak da kullanilir (asagida). ONEMLI: APP_KEY kasitli olarak
# docker-compose'un "environment:" blogunda YOK (bkz. docker-compose.yml
# yorumu) - container'a gercek (bos da olsa) bir env var olarak
# tanimlanirsa, Laravel'in kendi dotenv yuklemesi bu dosyadaki degeri asla
# okumaz (zaten "set" sayilir) ve hem php-fpm hem de her "docker exec"
# oturumu (test, tinker, vb.) MissingAppKeyException verir. APP_KEY sadece
# bu dosyada tutuldugu icin Laravel'in kendisi (her SAPI/CLI cagrisinda)
# guvenilir sekilde okuyabiliyor.
touch .env

if ! grep -q '^APP_KEY=.\+' .env; then
    KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    if grep -q '^APP_KEY=' .env; then
        sed -i "s#^APP_KEY=.*#APP_KEY=${KEY}#" .env
    else
        printf 'APP_KEY=%s\n' "$KEY" >> .env
    fi
fi

exec docker-php-entrypoint "$@"
