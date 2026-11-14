🇹🇷 Türkçe | [🇬🇧 English](development.md)

[← README'ye dön](../README.tr.md)

# Geliştirme

Docker olmadan yerel geliştirme için (Docker'lı alternatif için bkz.
[Docker ile çalıştırma](docker.tr.md)):

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

php artisan serve
```

## Testler

```bash
cd www
composer test
# veya Docker ile:
docker compose exec app php artisan test
```

Katkı kuralları için bkz. [Katkıda bulunma](../CONTRIBUTING.tr.md).
