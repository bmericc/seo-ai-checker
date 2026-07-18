🇬🇧 English | [🇹🇷 Türkçe](README.tr.md)

# SEO / AI Overview Checker

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

A **Laravel** application that combines Google search results (SERP)
ranking tracking via web scraping, visibility checks in Google's **AI
Overview** box, basic **on-page SEO** analysis, and **Lighthouse** (Google
PageSpeed Insights) scores. It's used as a **web dashboard**, hosted on a
remote server (or locally with Docker), that keeps a history in a database
and is protected by Google account login.

## Features

1. **SERP ranking** — fetches Google's first ~20 organic results and
   reports where your target domain ranks.
2. **AI Overview check** — reports whether an AI Overview box is present
   on the results page, which domains are cited as sources inside it if
   so, and whether your own domain is among those sources.
3. **On-page SEO analysis** — fetches the target page and runs basic
   checks: title/meta description length, H1/H2 count, keyword density,
   images missing `alt` attributes, internal/external link count, and
   presence of structured data (JSON-LD).
4. **Lighthouse (Google PageSpeed Insights API)** — retrieves performance,
   SEO, accessibility, and best-practices scores. **No** Chrome/Node.js
   installation is required on the server; the audit runs on Google's own
   infrastructure.
5. **Web dashboard** — add a domain/keyword and run all checks at once
   with "Check"; results and history are stored in a database (SQLite by
   default, MySQL also supported). Access requires signing in with Google
   OAuth and being on the allowed email list.

## Important limitations and legal notice

- Uses **direct HTTP scraping** (not a SERP API). This is a deliberate
  design choice, but Google frequently blocks automated requests with a
  CAPTCHA/"unusual traffic" page or a redirect requiring JavaScript
  verification (`/httpservice/retry/enablejs`). The app detects these
  cases and reports them as "blocked" — check this first if you're not
  getting results.
- **AI Overview is usually rendered client-side (via JavaScript)** and can
  vary by account/location/device. This tool only inspects the static
  HTML response, so detection is **best-effort**; an AI Overview that's
  actually visible to a real user may not be captured here.
- Google's SERP HTML structure changes frequently; the parsing logic
  relies on general heuristic rules and may need updating over time (see
  `ai_overview_markers` and `ai_overview_selectors` in `config/seo.php`).
- Only use this tool to audit **your own sites, or sites you have explicit
  permission for**, at a reasonable request rate (`REQUEST_DELAY_MS`).
  Respect Google's terms of service and robots.txt rules; do not configure
  it for heavy or mass scraping.
- The PageSpeed Insights API has a very low quota when used **without an
  API key** and you can quickly hit "Quota exceeded"; getting a
  `PSI_API_KEY` is recommended for real usage (see below).

## Project structure

```
.
├── docker/              # Dockerfile (PHP-FPM) and nginx configuration
├── docker-compose.yml   # Single file; dev/prod are separated via --env-file
├── .env.docker.example  # Environment variable template for docker-compose
└── www/                 # The entire Laravel application (document root: www/public)
    ├── app/
    ├── config/
    ├── database/
    ├── public/
    ├── resources/
    ├── routes/
    └── ...
```

All of the application's source code lives under `www/`; `docker-compose.yml`
and `docker/` belong only to the runtime environment.

**Important:** under Docker, Laravel does **not** read `www/.env` —
configuration is injected as real environment variables via the
`environment:` block in `docker-compose.yml`, sourced from the file passed
with `--env-file` (dev: `.env`, prod: `.env.prod`). `www/.env.example` only
applies to the "manual, without Docker" setup path below.

Because dev and prod are **not expected to run on the same machine at the
same time**, `docker-compose.yml` is a single file and volume/service names
are deliberately identical (`vendor`, `bootstrap_cache`, etc.) — the
separation is entirely driven by which `--env-file` is passed.

## Running with Docker — dev (recommended)

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

## Running with Docker — production

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
# APP_ENV=production, APP_DEBUG=false, the real APP_URL/GOOGLE_REDIRECT_URI,
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

## Web dashboard

The web dashboard stores domain/keyword records and the result of every
"Check" run (SERP + AI Overview + on-page + Lighthouse) in the database, so
you can view changes over time as history.

### 1. Create a Google OAuth application

Any Google account can sign in and create an account in the dashboard, but
new accounts can't access the dashboard until an **admin approves** them
(see "User management and admin panel" below) — Google-based
authentication + admin approval is used instead of a plain password.

1. Create a new **OAuth client ID** on the
   [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials)
   page, type: **Web application**.
2. Add your server's address as the **Authorized redirect URI**, e.g.:
   `https://your-server.com/auth/google/callback`
3. Write the resulting **Client ID** and **Client Secret** values into the
   relevant `.env` file (Docker: `.env`/`.env.prod` at the repo root;
   manual setup: `www/.env`) — `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
   `GOOGLE_REDIRECT_URI`.
4. Put your own Google email into `ADMIN_EMAILS`. This email(s) are
   **automatically treated as admin + approved** on first login, so you
   get access to yourself on initial setup. All subsequent user
   approvals/admin assignments are done from the `/admin/users` panel, no
   further `.env` editing needed.

### 2. `.env` settings (web-specific)

| Variable | Description |
|---|---|
| `APP_URL` | The dashboard's public address |
| `DB_CONNECTION`, `DB_DATABASE`/`DB_HOST`/... | SQLite (default) or MySQL/PostgreSQL |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth app credentials |
| `ADMIN_EMAILS` | Emails automatically treated as admin+approved on first login (comma-separated) |
| `PSI_API_KEY` | (optional but recommended) PageSpeed Insights API key |
| `PSI_STRATEGY` | `mobile` or `desktop` |

### 3. Deploying to a server (without Docker)

Point the document root at the `www/public/` folder. Example Nginx:

```nginx
server {
    listen 443 ssl;
    server_name your-server.com;
    root /path/to/seo-ai-checker/www/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        # A "Check" run (SERP+on-page+Lighthouse) can take up to 60 seconds;
        # raise the default timeout:
        fastcgi_read_timeout 180;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Notes:

- No folder **outside** `www/public/` (`www/app/`, `www/config/`,
  `www/.env`, the database file) should be directly reachable from the web
  server — only `www/public/` should be set as the document root.
- Google OAuth requires **HTTPS** in production (the redirect URI must
  start with `https://`); the `localhost` exception only applies to local
  development.
- Raise PHP's `max_execution_time` (in php.ini or the php-fpm pool config)
  to at least 120 seconds; "Check" runs SERP + on-page + Lighthouse
  sequentially in a single request and can take 20-60 seconds.
- Standard Laravel production steps:
  `composer install --no-dev --optimize-autoloader`,
  `php artisan migrate --force`, `php artisan config:cache`,
  `php artisan route:cache`, and making sure `storage/` and
  `bootstrap/cache/` are writable by the web server.

### 4. Usage

1. Go to `https://your-server.com/` and sign in with "Sign in with
   Google". If you're one of the accounts in `ADMIN_EMAILS`, you land
   directly in the dashboard; otherwise your account is created but you'll
   see a "pending approval" page until an admin approves you.
2. Add a domain, then add keyword(s) for that domain (optionally
   specifying a separate target page URL for each keyword).
3. Click "Check"; SERP ranking, AI Overview status, on-page SEO, and
   Lighthouse scores are run and saved to the database.
4. On a keyword's detail page you can see past checks (the full result of
   every run) in chronological order.

## How AI Overview detection works

The text phrases in `ai_overview_markers` in `config/seo.php` (e.g. "AI
overview") are searched for in the page text. If a match is found, the
links inside the nearest ancestor container element are scanned to try to
extract the cited source domains. As Google's markup changes, these
phrases — and, if needed, the CSS selectors in `ai_overview_selectors` —
need to be updated.

## How the Lighthouse integration works

`App\Services\Lighthouse\PageSpeedInsightsClient` sends requests to
Google's
[PageSpeed Insights v5 API](https://developers.google.com/speed/docs/insights/v5/get-started),
which runs the Lighthouse audit on Google's own servers and returns the
result as JSON. This means you don't need to install Node.js/headless
Chrome on your own server. The audit typically takes 15-40 seconds and
returns 0-100 scores for the performance/SEO/accessibility/best-practices
categories.

## Database schema

- `domains` — tracked domains
- `keywords` — keywords attached to a domain (with an optional custom URL)
- `checks` — the full result of every "Check" run (SERP, AI Overview,
  on-page, Lighthouse — JSON columns are automatically cast to arrays by
  Eloquent)

Migrations live under `www/database/migrations/`; they're created during
setup with `php artisan migrate`.

## Architecture notes

- `App\Services\Serp\GoogleSerpScraper` — Google SERP scraping + AI
  Overview detection (framework-agnostic, uses Guzzle + Symfony
  DomCrawler).
- `App\Services\OnPage\OnPageSeoAnalyzer` — on-page analysis of the target
  page.
- `App\Services\Lighthouse\PageSpeedInsightsClient` — PSI API client.
- `App\Services\CheckRunner` — the orchestration service that combines the
  three above into a `Check` record for a `Keyword` model (used by the web
  dashboard).
- Google OAuth: `App\Http\Controllers\Auth\GoogleController` (Socialite),
  creates/finds a `User` record for any Google account.
- `App\Http\Middleware\EnsureUserApproved` — redirects users whose
  `approved_at` is empty (not yet approved) to `/pending-approval`;
  `App\Http\Middleware\EnsureUserIsAdmin` — blocks `/admin/*` routes for
  users who aren't `is_admin`.

## User management and admin panel

- Any Google account can sign in and create a `User` record; `approved_at`
  is empty by default (pending approval) and the dashboard is inaccessible
  until then.
- Emails in the `ADMIN_EMAILS` list (`.env`/`.env.prod`) are automatically
  set to `is_admin=true` + approved on their first login (to give yourself
  access on initial setup).
- Admin users can approve other users, revoke their approval, promote/demote
  admin status, or delete them from the `/admin/users` page (the "Users"
  link in the top menu). An admin cannot perform these actions on their own
  account (to prevent accidentally locking themselves out); this isn't an
  issue for an `ADMIN_EMAILS` account anyway, since it's automatically
  reset to admin+approved on every login.

## Development

For local development without Docker (see the "Setup" steps above):

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

php artisan serve
```

Tests:

```bash
cd www
composer test
# or via Docker:
docker compose exec app php artisan test
```

## License

Licensed under the [GNU General Public License v3.0 or later](LICENSE) (GPL-3.0-or-later).
