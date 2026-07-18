[← Back to README](../README.md)

# Architecture

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
applies to the "manual, without Docker" setup path (see [Docker setup](docker.md)).

Because dev and prod are **not expected to run on the same machine at the
same time**, `docker-compose.yml` is a single file and volume/service names
are deliberately identical (`vendor`, `bootstrap_cache`, etc.) — the
separation is entirely driven by which `--env-file` is passed.

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

## Services

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
