#!/bin/sh
set -e

# www/database bind-mount edildigi icin host'ta olusturulan/degistirilen
# database.sqlite host kullanicisina ait olabilir; www-data yazamazsa
# uygulama sessizce (log bile yazamadan) 500 verir. Bu script root olarak
# calisir (php-fpm container'in normal baslangic sureci), bu yuzden
# sahiplikten bagimsiz sekilde chown/chmod yapabilir. chown SART: dosya
# root:root sahipliginde olusturulursa, chmod 664 "other" grubuna sadece
# okuma hakki birakir ve www-data (php-fpm worker) yine yazamaz.
#
# DIZIN de chown edilmeli: SQLite her yazma isleminde ayni dizinde
# gecici bir "-journal"/"-wal" dosyasi olusturur; sadece .sqlite dosyasi
# yazilabilir olsa bile dizin www-data'ya kapaliysa "attempt to write a
# readonly database" hatasi devam eder (sessions tablosuna hicbir yazma
# gerceklesmez, bu da oturumlarin/OAuth "state" kontrolunun her istekte
# sessizce sifirlanmasina yol acar).
mkdir -p database
chown www-data:www-data database
touch database/database.sqlite
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# Hazir imajla (docker-compose.release.yml) SQLite dosyasi kalici bir
# volume'de, mutlak DB_DATABASE yolunda durur (orn. storage/database/).
# Ayni sahiplik kurallariyla orada da olustur.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ "${DB_DATABASE#/}" != "${DB_DATABASE:-}" ]; then
    DB_DIR=$(dirname "$DB_DATABASE")
    mkdir -p "$DB_DIR"
    chown www-data:www-data "$DB_DIR"
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
    chmod 664 "$DB_DATABASE"
fi

# APP_KEY ortam degiskeni olarak verilip bos birakilirsa Laravel .env'deki
# degeri okumaz ve her istekte MissingAppKeyException verir - acik bir
# hatayla dur. (Gelistirme compose'u APP_KEY'i hic tanimlamaz.)
# Hazir imajda kaynak kod (ve www/.env) container icinde, kalici degil:
# otomatik uretilen anahtar her yeniden olusturmada degisir, oturumlar ve
# sifreli OAuth token'lari okunamaz hale gelir. Orada APP_KEY zorunlu.
if { [ -n "${APP_KEY+x}" ] || [ "${SEO_AI_CHECKER_RELEASE:-}" = "1" ]; } && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY ortam degiskeni bos. Bir anahtar olusturun:" >&2
    echo "  php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\"" >&2
    exit 1
fi

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

# Hazir imaj: app (serve = nginx + php-fpm) her baslangicta bekleyen migration'lari
# uygular; ilk kurulum ve surum yukseltmesi elle adim gerektirmez.
# RUN_MIGRATIONS=false ile kapatilir. www-data olarak calisir ki
# storage/logs altinda root'a ait dosya olusmasin.
if [ "${SEO_AI_CHECKER_RELEASE:-}" = "1" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ] && { [ "$1" = "serve" ] || [ "$1" = "php-fpm" ]; }; then
    su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction"
fi

exec docker-php-entrypoint "$@"
