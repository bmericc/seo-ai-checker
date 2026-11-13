[← Back to README](../README.md)

# Running with Docker

## Dev (recommended)

Requirement: Docker + Docker Compose.

The `webserver` service also joins an external Docker network named
`proxy`, defined in `docker-compose.yml` (see "Running behind
nginx-proxy-manager" below); even if you don't use it, you need to create
it **once**, otherwise `docker compose up` fails with a "network not
found" error:

```bash
docker network create npm_proxy   # or whatever PROXY_NETWORK is set to in .env
```

```bash
cp .env.docker.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan migrate
```

You don't need to do anything manually for `APP_KEY` — it is **not**
defined in `docker-compose.yml`; `docker/php/entrypoint.sh` generates one
automatically the first time the container starts (if `www/.env` doesn't
already have one) and persists it there. The same key keeps being used on
every subsequent restart/recreation (otherwise sessions would be
invalidated on every restart). If you want to use a specific key (e.g.
migrating from an old setup), edit the `APP_KEY=` line in `www/.env`
manually and restart the container.

**On write permissions:** `storage/`, `bootstrap/cache/`, and `vendor/` are
all separate Docker volumes (see `volumes:` below), so they're unaffected
by file ownership on the host — they stay chowned to the `www-data` user
as set during the image build. `database.sqlite` needs to live inside
`www/database/` (alongside the migrations) so it can't be a volume;
instead, `docker/php/entrypoint.sh` creates the file and makes it writable
for `www-data` every time the container starts — no manual `touch`/`chmod`
needed. (Without this automation, since `www/` is bind-mounted, files owned
by the host user would be inaccessible to `www-data` on real Linux
servers, and the app would fail with a bare 500 unable to even log the
error — this may go unnoticed in local development because Docker
Desktop's file sharing on macOS/Windows hides the issue.)

The dashboard runs at `http://localhost:8080` by default (change via
`WEB_PORT` in `.env`).

Services:

- `app` — PHP 8.4-FPM (Composer dependencies are installed during the
  image build; the locked Symfony 8.x packages in `composer.lock` require
  PHP 8.4+).
- `webserver` — Nginx, serves `www/public` as the document root via
  `docker/nginx/default.conf`, forwards PHP requests to the `app` service
  over fastcgi.

The `www/` folder is bind-mounted into the container for code changes; PHP
file changes don't require a rebuild. Rebuild the image
(`docker compose build app`) when `composer.json` or `composer.lock`
changes. When you change a value in `.env`, `docker compose up -d` is
enough (Compose automatically recreates the container for changed
`environment:` values).

## Production

The same `docker-compose.yml` is run with a different `--env-file`. Three
variables set in `.env.prod` change the behavior:

| Variable | Dev (`.env`) | Prod (`.env.prod`) |
|---|---|---|
| `COMPOSER_INSTALL_FLAGS` | empty (dev dependencies also installed) | `--no-dev` |
| `RESTART_POLICY` | `no` | `unless-stopped` |
| `WEB_PORT` | `8080` | `80` (or whatever port your reverse proxy forwards to) |

```bash
cp .env.docker.example .env.prod
# Fill .env.prod with production values (the 3 variables above, plus
# APP_ENV=production, APP_DEBUG=false, the real APP_URL,
# PSI_API_KEY, ADMIN_EMAILS, etc.)

docker compose --env-file .env.prod build
docker compose --env-file .env.prod up -d

docker compose --env-file .env.prod exec app php artisan migrate --force
```

`APP_KEY` isn't generated manually here either — see the note in the dev
section above (auto-generated and persisted in `www/.env`).

A typical **git-based deploy** flow on the server:

```bash
git pull
docker compose --env-file .env.prod up -d --build
docker compose --env-file .env.prod exec app php artisan migrate --force
```

Since `www/` is bind-mounted, `--build` only actually triggers a rebuild
when `composer.json`/`composer.lock` or `docker/` changes; it's safe to run
`up -d --build` even for code-only changes (an unnecessary build finishes
quickly thanks to Docker's layer cache).

**Always** pass `--env-file` explicitly (even in dev) — without a flag,
`docker compose` automatically looks for a file named `.env`, which is
already the correct behavior for dev; but if both `.env` and `.env.prod`
exist in the same directory, don't skip `--env-file .env.prod` on
production commands, or it will silently run with dev settings.

## Prebuilt images (no build)

Every release tag (`v1.0.0`, `v1.1.0`, ...) is built by GitHub Actions
(`.github/workflows/docker.yml`) for **linux/amd64 and linux/arm64** and
published to both registries:

| Image | GitHub Container Registry | Docker Hub |
|---|---|---|
| App (php-fpm, queue worker, scheduler) | `ghcr.io/bmericc/seo-ai-checker` | `bmericc/seo-ai-checker` |
| Web (nginx + `public/`) | `ghcr.io/bmericc/seo-ai-checker-web` | `bmericc/seo-ai-checker-web` |

Tags: `1.0.0`, `1.0`, `1` and `latest`.

```bash
curl -O https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/docker-compose.release.yml
curl -o .env https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/.env.docker.example
# Edit .env: add APP_KEY (required, see below), APP_URL, GOOGLE_CLIENT_ID,
# GOOGLE_CLIENT_SECRET, ADMIN_EMAILS and the optional API keys.
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"   # or: openssl rand -base64 32

docker compose -f docker-compose.release.yml up -d
```

- **`APP_KEY` is required.** The code lives inside the image, so a key
  generated at start would change on every container recreate and make
  sessions and stored OAuth tokens unreadable. The container refuses to
  start without it.
- The SQLite database and logs live in the `storage` volume
  (`/var/www/html/storage/database/database.sqlite`); back that volume up.
- The `app` container runs pending migrations on every start, so upgrades
  need no extra step. Set `RUN_MIGRATIONS=false` to turn this off.
- Pin a version with `SEO_AI_CHECKER_VERSION=1.0.0` in `.env` (default
  `latest`). To use Docker Hub instead of GHCR, set
  `SEO_AI_CHECKER_IMAGE=bmericc/seo-ai-checker` and
  `SEO_AI_CHECKER_WEB_IMAGE=bmericc/seo-ai-checker-web`.
- Upgrade: `docker compose -f docker-compose.release.yml pull && docker compose -f docker-compose.release.yml up -d`.

To publish a new release, push a tag: `git tag v1.1.0 && git push origin v1.1.0`.
The workflow needs the `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN`
repository secrets (Docker Hub personal access token with Read & Write).

## Running behind nginx-proxy-manager (or another reverse proxy)

In addition to the host port, the `webserver` service also joins an
external Docker network configured via `PROXY_NETWORK`/`APP_HOSTNAME`
(`.env`/`.env.prod`); this lets NPM reach the container directly over the
Docker network without ever using the host port.

1. Find the name of the external network used by the docker-compose setup
   that runs NPM (or create one, and use the same name with
   `external: true` in NPM's own compose file):
   ```bash
   docker network create npm_proxy
   ```
2. In `.env.prod`:
   ```
   PROXY_NETWORK=npm_proxy      # network name shared with NPM
   APP_HOSTNAME=seo-ai-checker  # the name you'll enter in NPM's "Forward Hostname/IP" field
   ```
3. When `docker compose --env-file .env.prod up -d` is (re)run, `webserver`
   automatically joins this network and becomes reachable via
   `APP_HOSTNAME` (verify from another container on the network with
   `getent hosts seo-ai-checker`).
4. Add a new **Proxy Host** in the NPM UI:
   - **Domain Names**: your real domain (e.g. `seo.example.com`)
   - **Forward Hostname / IP**: your `APP_HOSTNAME` value (e.g. `seo-ai-checker`)
   - **Forward Port**: `80` (the nginx container's internal port — not the
     `WEB_PORT` in `.env.prod`, which is only used when you want to
     publish a port directly to the host)
   - **SSL**: request a Let's Encrypt certificate, enable "Force SSL".
   - Under the **Advanced** tab, add the following line, since a "Check"
     run (SERP+on-page+Lighthouse) can take up to 60 seconds (NPM's default
     proxy timeout can cut this off):
     ```
     proxy_read_timeout 180s;
     ```

## Setup (manual, without Docker)

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # for the default SQLite setup
php artisan migrate
php artisan serve
```
