🇬🇧 English | [🇹🇷 Türkçe](docker-install.tr.md)

[← Back to README](../README.md)

# Docker installation steps

This guide walks through installing SEO / AI Overview Checker with Docker
from scratch. There are two ways:

- **A. Prebuilt image (recommended).** No source code needed. Pulls the
  `seo-ai-checker` image from GHCR or Docker Hub.
- **B. Docker, built from source.** If you want to develop or build the
  image yourself.

Dev/prod details: [docker.md](docker.md).

## Requirements

- Docker Engine 24+ and Docker Compose v2 (check with
  `docker compose version`).
- A linux/amd64 or linux/arm64 machine (Intel/AMD server, Apple Silicon,
  Raspberry Pi 4/5, etc.).
- For access from outside: a domain name and a reverse proxy in front
  (nginx-proxy-manager, Traefik, Caddy...). Google sign-in requires HTTPS.

---

## A. Prebuilt image

A single image is published: `seo-ai-checker`. It runs nginx and php-fpm
together (port 80). The queue worker (`worker`) and the scheduler
(`scheduler`) use the same image with a different command.

| Registry | Image |
|---|---|
| GitHub Container Registry | `ghcr.io/bmericc/seo-ai-checker` |
| Docker Hub | `bmericc/seo-ai-checker` |

Tags: `1.0.0`, `1.0`, `1`, `latest`.

### 1. Create the install folder

```bash
mkdir -p ~/seo-ai-checker
```

```bash
cd ~/seo-ai-checker
```

### 2. Download the compose file and the example settings

```bash
curl -O https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/docker-compose.release.yml
```

```bash
curl -o .env https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/.env.docker.example
```

### 3. Generate `APP_KEY`

The key is **required**. The code lives inside the image, so an
auto-generated key would change on every recreate and make sessions and
encrypted OAuth tokens unreadable. The container won't start without it.

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
```

Back this key up somewhere safe. If you lose it, saved OAuth connections
have to be set up again.

### 4. Edit `.env`

Fill in at least these values:

| Variable | Description |
|---|---|
| `APP_URL` | Public address, e.g. `https://seo.example.com` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `WEB_PORT` | Port opened on the host (default `8080`) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | OAuth client for Google sign-in and Search Console |
| `GOOGLE_LOGIN_ENABLED` | `false` hides "Sign in with Google" and leaves email + password sign-in (default `true`) |
| `ADMIN_EMAILS` | Emails that become admins, comma-separated |
| `MAIL_MAILER` and `MAIL_*` | Mail for email verification and password reset; `log` writes the links to `storage/logs/laravel.log` |
| `APP_LOCALE` | `tr` or `en` |

Also add the keys for the APIs you want to use (see
[Available APIs](#available-apis)). Other install settings:

| Variable | Purpose |
|---|---|
| `SEO_AI_CHECKER_VERSION` | Pin a version, e.g. `1.0.0` (default `latest`) |
| `SEO_AI_CHECKER_IMAGE` | `bmericc/seo-ai-checker` to use Docker Hub |
| `RUN_MIGRATIONS` | `false` skips migrations on start |

### 5. Register the OAuth redirect URIs

In Google Cloud Console, add this **Authorized redirect URI** to the OAuth
client (same host as `APP_URL`):

```
https://seo.example.com/auth/google/callback
```

If you use Bing, add this to the Azure app:

```
https://seo.example.com/auth/bing/callback
```

If you serve the app on more than one domain, add a callback for each host.

### 6. Start

```bash
docker compose -f docker-compose.release.yml up -d
```

On first start the `app` container creates the database and runs the
migrations itself. `worker` and `scheduler` start after `app`.

### 7. Check

```bash
docker compose -f docker-compose.release.yml ps
```

```bash
curl -I http://localhost:8080/
```

It should return `200`. If not, read the logs:

```bash
docker compose -f docker-compose.release.yml logs -f app
```

Open `APP_URL` in a browser and sign in with Google, or sign up with email
+ password, using an email from `ADMIN_EMAILS`. An email + password account
becomes admin once its email is verified.

---

## Available APIs

Every API key is optional. Without Google OAuth, people sign in with email
+ password and Search Console/GA4 data is not available. A feature without a key either
stays off or falls back to a free method. Add the keys to `.env` and
restart the containers:

```bash
docker compose -f docker-compose.release.yml up -d
```

### Google OAuth (sign-in and Search Console)

| Variable | Where to get it |
|---|---|
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → OAuth client ID (Web application) |

Used for sign-in and Search Console data. The **Google Search Console API**
must be enabled in the project. Callback: `https://YOUR-DOMAIN/auth/google/callback`.

### PageSpeed Insights and CrUX (recommended)

| Variable | Description |
|---|---|
| `PSI_API_KEY` | Lighthouse scores. Works without a key, but the quota is very low. |
| `PSI_STRATEGY` | `mobile` (default) or `desktop` |
| `CRUX_API_KEY` | Real-user Core Web Vitals. Falls back to `PSI_API_KEY` when empty. |

Create an API key in Google Cloud Console and enable the **PageSpeed
Insights API** and the **Chrome UX Report API** in the same project.

### DataForSEO (SERP, keyword metrics, AI visibility)

| Variable | Description |
|---|---|
| `DATAFORSEO_LOGIN`, `DATAFORSEO_PASSWORD` | [DataForSEO](https://app.dataforseo.com/api-access) API credentials |
| `DATAFORSEO_LOCATION_CODE` | Location code, default `2792` (Türkiye) |
| `DATAFORSEO_LANGUAGE_CODE` | Language code, defaults to `GOOGLE_HL` |
| `SERP_PROVIDER` | `auto` (default), `google`, `dataforseo`, `fallback` |
| `SERP_FALLBACK_COOLDOWN_MINUTES` | In `fallback` mode, minutes to skip Google after a block (default `30`) |

Without it, SERPs are scraped directly from Google, which often hits
CAPTCHAs. `fallback` tries free scraping first and switches to DataForSEO
when blocked. Competitor keywords, LLM Scraper and LLM Mentions need
DataForSEO.

### Bing Webmaster Tools (backlinks and Bing data)

| Variable | Where to get it |
|---|---|
| `BING_CLIENT_ID`, `BING_CLIENT_SECRET` | [Microsoft Entra / Azure](https://portal.azure.com/) → App registrations |

Callback: `https://YOUR-DOMAIN/auth/bing/callback`. Each user connects
their own Bing account inside the app.

### CrawlGraph (Common Crawl backlinks)

| Variable | Description |
|---|---|
| `CRAWLGRAPH_API_KEY` | CrawlGraph API key. The free tier gives 15 requests per month. |
| `CRAWLGRAPH_LIMIT` | Max referring domains per request (default `1000`) |

### AI visibility: ChatGPT, Claude, Gemini, Perplexity

These providers' API keys do **not** go into `.env`. They are entered per
domain on each domain's page in the app and stored encrypted in the
database. A check runs for each provider that has a key.

`.env` only changes which model is used:

| Variable | Default |
|---|---|
| `OPENAI_VISIBILITY_MODEL` | `gpt-5.6-luna` |
| `ANTHROPIC_VISIBILITY_MODEL` | `claude-haiku-5-5` |
| `GEMINI_VISIBILITY_MODEL` | `gemini-3.8-flash` |
| `PERPLEXITY_VISIBILITY_MODEL` | `sonar` |

### Scraping and scheduled checks

| Variable | Description |
|---|---|
| `GOOGLE_HL`, `GOOGLE_GL` | Search language and country, default `tr` |
| `REQUEST_DELAY_MS` | Delay between requests, default `4000` |
| `HTTP_PROXY` | Send SERP requests through a proxy |
| `SCRAPER_USER_AGENT` | Custom User-Agent |
| `SCHEDULED_KEYWORD_CHECKS` | Weekly automatic checks, on by default |
| `SCHEDULED_KEYWORD_CHECKS_INCLUDE_LLM` | Include AI visibility in weekly checks (costs money), off by default |
| `SCHEDULED_SERP_QUEUE_TIMEOUT_HOURS` | DataForSEO queue task timeout, default `6` |

---

## B. Docker, built from source

### 1. Clone the repository

```bash
git clone https://github.com/bmericc/seo-ai-checker.git
```

```bash
cd seo-ai-checker
```

### 2. Create the proxy network once

The `webserver` service joins an external Docker network. It must exist
even if you don't use it, otherwise you get `network not found`:

```bash
docker network create npm_proxy
```

You can change the network name with `PROXY_NETWORK` in `.env`.

### 3. Copy the settings file

For development:

```bash
cp .env.docker.example .env
```

For production use `.env.prod` and set:
`COMPOSER_INSTALL_FLAGS=--no-dev`, `RESTART_POLICY=unless-stopped`,
`APP_ENV=production`, `APP_DEBUG=false`, the real `APP_URL`.

```bash
cp .env.docker.example .env.prod
```

`APP_KEY` is not generated by hand here; it is written to `www/.env` on
first start and stays there.

Add the API keys as described in [Available APIs](#available-apis). Note:
`docker-compose.yml` only passes the variables in its own `environment:`
list into the container. Put the ones not on that list (`CRUX_API_KEY`,
`SERP_PROVIDER`, `DATAFORSEO_LANGUAGE_CODE`, `CRAWLGRAPH_LIMIT`,
`PERPLEXITY_VISIBILITY_MODEL`, `SCHEDULED_*`,
`SERP_FALLBACK_COOLDOWN_MINUTES`) into `www/.env`.

### 4. Build and start

Development:

```bash
docker compose build
```

```bash
docker compose up -d
```

Production:

```bash
docker compose --env-file .env.prod up -d --build
```

`app`, `worker` and `scheduler` share one local image
(`seo-ai-checker:local`); only `app` builds it.

### 5. Run migrations

```bash
docker compose exec app php artisan migrate
```

In production:

```bash
docker compose --env-file .env.prod exec app php artisan migrate --force
```

```bash
docker compose --env-file .env.prod exec app php artisan config:cache
```

The app runs at `http://localhost:8080` by default.

---

## Behind a reverse proxy (nginx-proxy-manager)

1. In `.env` / `.env.prod`:
   ```
   PROXY_NETWORK=npm_proxy
   APP_HOSTNAME=seo-ai-checker
   ```
   With the prebuilt image (way A), connect the `app` container to the NPM
   network:
   ```bash
   docker network connect npm_proxy seo-ai-checker-app-1
   ```
2. Add a new **Proxy Host** in NPM:
   - **Domain Names**: your domain
   - **Forward Hostname / IP**: the `APP_HOSTNAME` value (the container
     name for way A)
   - **Forward Port**: `80` (the container's internal port, not `WEB_PORT`)
   - **SSL**: Let's Encrypt certificate, "Force SSL" on
3. Add this under the **Advanced** tab (a check can take up to 60 seconds):
   ```
   proxy_read_timeout 180s;
   ```

---

## Upgrading

Prebuilt image:

```bash
docker compose -f docker-compose.release.yml pull
```

```bash
docker compose -f docker-compose.release.yml up -d
```

Migrations run automatically on start.

From source:

```bash
git pull
```

```bash
docker compose --env-file .env.prod up -d --build
```

```bash
docker compose --env-file .env.prod exec app php artisan migrate --force
```

## Backups

With the prebuilt image the SQLite database and logs live in the `storage`
volume (`/var/www/html/storage/database/database.sqlite`). Back up the
volume:

```bash
docker run --rm -v seo-ai-checker_storage:/data -v "$PWD":/backup alpine tar czf /backup/storage-backup.tgz -C /data .
```

The volume name depends on the folder name; check with `docker volume ls`.
When built from source the database is `www/database/database.sqlite`.
Include `APP_KEY` in the backup too.

## Common problems

| Symptom | Cause / fix |
|---|---|
| Container exits right away, log says `APP_KEY ortam degiskeni bos` | `APP_KEY` missing from `.env`. Do step 3. |
| `network npm_proxy not found` | Run `docker network create npm_proxy`. |
| `redirect_uri_mismatch` on Google sign-in | Callback not registered in Google Console, or `APP_URL` host differs. |
| 504 while a check runs | Reverse proxy timeout; add `proxy_read_timeout 180s;`. |
| Background jobs (bulk Lighthouse) don't progress | Is the `worker` container running: `docker compose ps`. |
| Scheduled checks don't run | Is the `scheduler` container running; check its logs. |
