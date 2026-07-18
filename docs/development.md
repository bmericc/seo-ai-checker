[← Back to README](../README.md)

# Development

For local development without Docker (see [Docker setup](docker.md) for
the Docker-based alternative):

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

php artisan serve
```

## Tests

```bash
cd www
composer test
# or via Docker:
docker compose exec app php artisan test
```
