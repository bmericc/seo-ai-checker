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

## Two levels of checks

The app runs checks at two independent levels, each with its own
orchestration service and history table:

- **Keyword-level** (`App\Services\CheckRunner`, table `checks`) — tied to a
  specific `Keyword` (domain + search term + optional custom target URL):
  SERP ranking, AI Overview, on-page SEO, Lighthouse for that URL.
- **Domain-level** (`App\Services\DomainCheckRunner`, table `domain_checks`)
  — tied to the whole `Domain`, independent of any single keyword: robots.txt
  AI-crawler access, sitemap.xml, llms.txt, security headers, canonical
  host, CrUX Core Web Vitals, Search Console/Analytics/Bing backlink data,
  and keyword suggestions.

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

Besides the on-demand check from the domain/keyword page, Lighthouse (and
on-page SEO) audits can also be queued per sitemap URL — see
[Sitemap URL queue jobs](#sitemap-url-queue-jobs) below.

## Database schema

- `users` — one row per Google account that has ever signed in; holds
  `approved_at`/`is_admin` (dashboard access), the stored Google OAuth
  tokens (for GSC/GA4), the stored Bing OAuth tokens (for Bing backlinks),
  and `locale` (auto-detected from the Google account on first login, kept
  in sync by `App\Http\Middleware\SetLocale`, see below).
- `domains` — tracked domains, owned by a `user_id`; stores the selected
  `ga4_property_id` and the list of dismissed keyword suggestions.
- `keywords` — keywords attached to a domain (with an optional custom URL).
- `checks` — the full result of every keyword-level "Check" run (SERP, AI
  Overview, on-page, Lighthouse).
- `domain_checks` — the full result of every domain-level check run
  (AI crawlers, sitemap, llms.txt, security headers, canonical host, CrUX,
  GSC, GA4, Bing backlinks, suggested keywords).
- `sitemap_urls` — URLs discovered in a domain's sitemap.xml (synced by
  `SitemapUrlSync`), each with its own optional queued Lighthouse/on-page
  scan results.

JSON columns are automatically cast to arrays by Eloquent. Migrations live
under `www/database/migrations/`; they're created during setup with
`php artisan migrate`.

## Services

All services live under `App\Services\` in their own namespace, one per
integration, and are deliberately framework-agnostic where practical (they
take plain values in, return typed result objects out).

### Orchestration

- `App\Services\CheckRunner` — combines SERP/AI Overview, on-page SEO, and
  Lighthouse into a `Check` record for a `Keyword`.
- `App\Services\DomainCheckRunner` — combines robots.txt, sitemap.xml,
  llms.txt, security headers, canonical host, CrUX, GSC, GA4, Bing
  backlinks, and keyword suggestions into a `DomainCheck` record for a
  `Domain`.
- `App\Services\SharedDomainCheckLookup` — for the domain-wide facts that
  don't depend on which user is asking (AI crawlers, llms.txt, security
  headers, canonical host, CrUX), reuses a recent result from *any* user
  who already checked the same domain instead of re-fetching. GSC/GA4/Bing
  (per-user OAuth) and keyword suggestions (per-user dismissal list) are
  never shared and always run fresh.
- `App\Services\Sitemap\SharedSitemapUrlLookup` — the sitemap-URL
  equivalent: shares queued Lighthouse/on-page scan results for a given URL
  across users.

### SEO / SERP

- `App\Services\Serp\GoogleSerpScraper` — Google SERP scraping + AI
  Overview detection (Guzzle + Symfony DomCrawler); detects and reports
  CAPTCHA/"unusual traffic" blocks. Paired with
  `Serp\GoogleRequestThrottle`, which enforces `REQUEST_DELAY_MS` between
  requests to Google.
- `App\Services\OnPage\OnPageSeoAnalyzer` — on-page analysis of the target
  page: title/meta description length, H1/H2 and heading-hierarchy checks,
  keyword density, image `alt` coverage, internal/external links,
  structured data (JSON-LD schema types, including deprecated ones),
  Open Graph/Twitter Card tags, canonical status, and hreflang tags/issues.
- `App\Services\CanonicalHost\CanonicalHostChecker` — resolves a domain's
  canonical host (e.g. does `example.com` redirect to `www.example.com`),
  used as the system-wide default host for the other domain-level checks.
- `App\Services\Robots\RobotsTxtChecker` — checks robots.txt for
  allow/disallow rules affecting known AI crawlers (GPTBot, ClaudeBot,
  Google-Extended, etc.).
- `App\Services\Llms\LlmsTxtChecker` — checks for the presence of an
  `llms.txt` file and returns a preview of its contents.
- `App\Services\Sitemap\SitemapChecker` — fetches and validates
  sitemap.xml (including sitemap index files) and counts URLs;
  `Sitemap\SitemapUrlSync` syncs the discovered URLs into the
  `sitemap_urls` table.
- `App\Services\Security\SecurityHeadersChecker` — checks for common
  security response headers and whether HTTP redirects to HTTPS.
- `App\Services\Keywords\KeywordSuggester` — suggests additional keywords
  for a domain (excluding ones already tracked or dismissed).
- `App\Services\Drift\DomainCheckDrift` — compares consecutive
  `DomainCheck` records to surface what changed between runs.

### Performance & real-user metrics

- `App\Services\Lighthouse\PageSpeedInsightsClient` — PSI v5 API client
  (see [above](#how-the-lighthouse-integration-works)).
- `App\Services\Crux\CrUxChecker` — Chrome UX Report API client; returns
  real-user Core Web Vitals (LCP, INP, CLS, FCP, TTFB) for an origin, using
  the same `PSI_API_KEY`.

### Third-party integrations (per-user OAuth)

- `App\Services\Google\GoogleTokenService` — refreshes/stores the Google
  OAuth access token used for both GSC and GA4 calls.
- `App\Services\Gsc\GscChecker` — Search Console clicks/impressions/CTR/
  average position for a verified property.
- `App\Services\Ga4\Ga4Checker` — Analytics 4 sessions/organic sessions/
  active users for the domain's selected property;
  `Ga4\Ga4PropertyLister` lists the accounts/properties available to the
  signed-in user (grouped by account, following the GA4 Admin API's
  pagination) so it can be picked from a dropdown instead of typed in.
- `App\Services\Bing\BingTokenService` — refreshes/stores the Bing OAuth
  access token; `Bing\BingBacklinksChecker` fetches backlink counts and top
  linked pages from Bing Webmaster Tools.

## Background jobs

### Sitemap URL queue jobs

Running Lighthouse (15-40s) or a full on-page fetch synchronously for every
URL in a sitemap would block the request/UI, so these run as queued jobs
instead, dispatched per `SitemapUrl`:

- `App\Jobs\RunSitemapUrlLighthouseCheck` — runs `PageSpeedInsightsClient`
  for one sitemap URL and writes the scores back onto that `SitemapUrl`.
- `App\Jobs\RunSitemapUrlOnPageCheck` — runs `OnPageSeoAnalyzer` for one
  sitemap URL and writes the result back onto that `SitemapUrl`.

The `queue` Docker/Compose worker (`php artisan queue:listen`, see
[docs/docker.md](docker.md)) must be running for these to actually process.

## Auth, access control & localization

- Google OAuth: `App\Http\Controllers\Auth\GoogleController` (Socialite),
  creates/finds a `User` record for any Google account and stores its
  refresh token for GSC/GA4 access.
- Bing OAuth: `App\Http\Controllers\Auth\BingController`, connected
  per-user (after Google sign-in) to enable Bing Webmaster backlink data;
  stores its own refresh token separately from the Google one.
- `App\Http\Middleware\EnsureUserApproved` — redirects users whose
  `approved_at` is empty (not yet approved) to `/pending-approval`.
- `App\Http\Middleware\EnsureUserIsAdmin` — blocks `/admin/*` routes for
  users who aren't `is_admin` (aliased as the `admin` middleware).
- `App\Http\Middleware\SetLocale` — applies the signed-in user's stored
  `locale` (or the Google account's language on first login) to the
  request; translation strings live under `www/lang/en` and `www/lang/tr`.

Both middleware are registered on the whole `web` group in
`bootstrap/app.php`, so they run on every request, before route-specific
`auth`/`admin` middleware narrows access further.
