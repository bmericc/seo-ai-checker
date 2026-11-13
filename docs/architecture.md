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
  `ga4_property_id`, the list of dismissed keyword suggestions, and the
  latest WHOIS lookup (`whois_registrar`, `whois_registered_at`,
  `whois_expires_at`, the full raw response in `whois_raw`, `whois_error`,
  `whois_checked_at`).
- `keywords` — keywords attached to a domain (with an optional custom URL).
- `checks` — the full result of every keyword-level "Check" run (SERP, AI
  Overview, on-page, Lighthouse).
- `domain_checks` — the full result of every domain-level check run
  (AI crawlers, sitemap, llms.txt, security headers, canonical host, CrUX,
  GSC, GA4, Bing backlinks, suggested keywords).
- `backlink_snapshots` — one row per DataForSEO backlink fetch (summary
  counts, top referring domains, anchors, new/lost domains, cost, error);
  the latest 52 per domain are kept. `domains.backlinks_requested_at` marks
  a queued fetch.
- `crawlgraph_snapshots` — one row per domain and Common Crawl release
  (linking domains, authority, rank, added/removed domains vs. the previous
  release); unique on `(domain_id, release_id)`.
- `domain_competitors` — competitors a user marked as tracked or ignored
  (or added by hand) for a domain.
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

### Analytics (derived from existing data, no new queries)

Both of these are pure computation over `Check`/`DomainCheck` rows already
collected by the services above - no new API calls or database tables.

- `App\Services\Analytics\ScoreHistoryBuilder` — turns a domain's or
  keyword's `Check` history (and a domain's `DomainCheck` history) into
  day-bucketed time series for the domain/keyword pages' charts: AI
  Overview visibility %, Lighthouse scores, average SERP position, CrUX
  load-time metrics, Search Console clicks/impressions/CTR/position, GA4
  sessions/users.
- `App\Services\Analytics\CompetitorAnalyzer` — the "rakip analizi"
  (competitor analysis) card: looks at each tracked keyword's *latest*
  `Check.organic_results` and surfaces which domains recur most often
  alongside the tracked domain (ranked by number of distinct keywords
  shared, not raw appearance count, so one frequently-rechecked keyword
  can't dominate the ranking), with their average/best position.
- `App\Services\Analytics\AiOverviewVolatility` — AI Overview stability per
  keyword over the last 10 *unblocked* checks: trigger rate, citation rate
  (cited / AI Overview shown) and change rate (how often the
  absent/present/cited state flips between consecutive checks, bucketed
  stable/medium/volatile). Also exposed over MCP (`get_domain`,
  `list_keywords`).
- `App\Services\Analytics\LlmShareOfModel` — the "Share of Model" matrix:
  from the last 90 days of stored `PromptExploration` answers, the share of
  each assistant's answers that mention the domain, its top SERP competitors
  and other frequently mentioned hosts (matched by domain name via
  `Llm\LlmMentions`). Also exposed over MCP (`get_competitors`).
- `App\Services\Analytics\VisibilityChanges` — compares each keyword's
  latest unblocked check with the latest one before the window (default 7
  days), assistant mention rates with the previous window, and the site
  check / Search Console clicks; returns losses and gains sorted by impact.
  MCP `get_visibility_changes`.
- `App\Services\Llm\PromptSuggester` — Prompt Explorer's "suggest
  questions" wizard: one request to the domain's first available LLM key
  (Claude, ChatGPT, Gemini, then Perplexity) for brand-free customer
  questions built from the selected keywords and search intent.

### SEO / SERP

- `App\Services\Serp\GoogleSerpScraper` — Google SERP scraping + AI
  Overview detection (Guzzle + Symfony DomCrawler); detects and reports
  CAPTCHA/"unusual traffic" blocks. Paired with
  `Serp\GoogleRequestThrottle`, which enforces `REQUEST_DELAY_MS` between
  requests to Google.
- `App\Services\Serp\DataForSeoSerpScraper` — the same `SerpScraper`
  contract over the paid DataForSEO Google Organic API (live and Standard
  queue).
- `App\Services\Serp\FallbackSerpScraper` — `SERP_PROVIDER=fallback`:
  tries the scraper first and sends the query to DataForSEO only when the
  result is blocked, then skips Google for `SERP_FALLBACK_COOLDOWN_MINUTES`.
  Which implementation is bound is decided in `AppServiceProvider`.
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
- `App\Services\Llms\LlmsTxtValidator` — format check of a found
  `llms.txt` (run by `LlmsTxtChecker`, stored under `llms_txt.validation`):
  errors for HTML responses (SPA fallbacks), empty files or a missing
  `# Title`; warnings for a missing `> summary`, no `- [Title](URL)` links,
  links outside `## Section` headings, malformed list lines and mostly
  external links. Errors show as an "llms.txt invalid" health issue.
- `App\Services\Llms\LlmsTxtGenerator` — builds an llms.txt draft
  (llmstxt.org format) from the active `sitemap_urls`, grouped by first path
  segment, using page-report titles/meta descriptions when available.
  Nothing is saved; shown at `/domains/{id}/llms-txt` and over MCP
  (`generate_llms_txt`).
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
- `App\Services\Whois\WhoisChecker` — wraps the
  [`bahricanli/domainhunter`](https://github.com/bahricanli/laravel-domainhunter)
  package (WHOIS/RDAP lookup + domain-name parsing, requires the `intl` PHP
  extension) to resolve a domain's registrar, registration date and
  expiration date. Always run from `App\Jobs\RunDomainWhoisLookup` (see
  [Background jobs](#background-jobs)), never inline in a request - WHOIS
  lookups are direct TCP/RDAP connections to registrar servers and can be
  slow or unreachable.

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
  linked pages from Bing Webmaster Tools (`GetUserSites`, `GetLinkCounts`)
  and the sites linking to the 5 most-linked pages (`GetUrlLinks`).

### Backlinks

- `App\Services\DataForSeo\BacklinksFetcher` — DataForSEO Backlinks API
  (`summary`, `referring_domains`, `anchors` live endpoints): summary, top
  100 referring domains, anchors and new/lost domains of the last 30 days.
- `App\Services\CrawlGraph\CrawlGraphClient` — CrawlGraph API
  (`GET /api/v1/releases`, free; `POST /api/v1/backlinks`, counted against
  the free 15-a-month quota, HTTP 429 when exhausted).
- `App\Support\BacklinkMerger` — merges the DataForSEO, CrawlGraph and
  Bing lists into one row per registrable domain (subdomains fold into the
  CrawlGraph domain, `www.` is stripped) and records each row's sources.
- `App\Support\BacklinkOverview` — builds the data for the domain page's
  Backlink tab (latest/previous snapshot, trend, merged lists).

## Background jobs

### Sitemap URL queue jobs

Running Lighthouse (15-40s) or a full on-page fetch synchronously for every
URL in a sitemap would block the request/UI, so these run as queued jobs
instead, dispatched per `SitemapUrl`:

- `App\Jobs\RunSitemapUrlLighthouseCheck` — runs `PageSpeedInsightsClient`
  for one sitemap URL and writes the scores back onto that `SitemapUrl`.
- `App\Jobs\RunSitemapUrlOnPageCheck` — runs `OnPageSeoAnalyzer` for one
  sitemap URL and writes the result back onto that `SitemapUrl`.

The `worker` Docker/Compose service (`php artisan queue:work`, see
`docker-compose.yml`) must be running for these to actually process.

### Domain WHOIS lookup

`App\Jobs\RunDomainWhoisLookup` runs `WhoisChecker` for a `Domain` and
writes the result (or the error, if the lookup failed) back onto that
`Domain` — also via the `worker` service, for the same reason as the
sitemap jobs above (WHOIS lookups are slow/unreliable network calls).
It's dispatched automatically when a domain is added
(`DomainController::store()`), and can be re-dispatched at any time from
the domain page's "WHOIS Bilgisini Yenile" button
(`DomainController::refreshWhois()`) — unlike "Site Kontrolü Yap", it
never runs synchronously inside a web request.

### Backlink fetches

- `App\Jobs\FetchDomainBacklinks` — one DataForSEO backlink fetch per
  domain (unique, no retries since requests are paid). Dispatched by the
  "Refresh now" button and by `backlinks:run-monthly-fetch` (1st of the
  month, 04:00).
- `backlinks:sync-crawlgraph` (daily, 05:00) — checks the latest Common
  Crawl release and queries only domains that have no row for it yet;
  stops at the first quota error.

## Auth, access control & localization

- Google OAuth: `App\Http\Controllers\Auth\GoogleController` (Socialite),
  creates/finds a `User` record for any Google account and stores its
  refresh token for GSC/GA4 access. The callback URL is built from the
  request host, so login returns to the domain it started on; every host
  needs its `/auth/google/callback` in Google Console.
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
