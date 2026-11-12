🇬🇧 English | [🇹🇷 Türkçe](README.tr.md)

# SEO / AI Overview Checker

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

A **Laravel** dashboard that shows where a site stands in **classic Google
search** and in **AI answers**: Google rankings, Google **AI Overview**
citations, and whether **ChatGPT, Claude, Gemini and Perplexity** mention
or cite the site. It finds technical problems, ranks them by impact and
says how to fix each one. It runs on your own server (or locally with
Docker), keeps the history in a database and is protected by Google sign-in.

The interface is available in **English and Turkish**. It follows the
browser language (Turkish browsers get Turkish, all others English), and
each user can change it later under **Settings**.

## Features

- **Google rankings** — fetches the top ~20 organic results for each
  tracked keyword (direct scraping or the DataForSEO API) and records your
  position on every check.
- **Google AI Overview** — detects the AI Overview box, lists the cited
  domains and tells you whether your site is one of them. A stability view
  shows, over the last 10 checks, how often an AI Overview appears, how
  persistently your site stays cited and how volatile the result is.
- **AI assistant visibility** — asks ChatGPT, Claude, Gemini and
  Perplexity a real customer question and records who is mentioned, who is
  cited and which competitors are recommended. **Prompt Explorer** lets
  admins ask any question across all four at once. A **Share of Model**
  matrix shows how often each assistant mentions your site versus your SERP
  competitors.
- **Site health** — AI bots in `robots.txt`, sitemap, `llms.txt`, HTTPS
  redirect, security headers, canonical host and WHOIS/domain age, with a
  health score and a prioritized issue list. Changes between two checks are
  highlighted. An **llms.txt generator** builds a draft from your sitemap
  pages, ready to review and upload.
- **Speed and page quality** — Lighthouse scores via the PageSpeed Insights
  API (no Chrome needed on the server), real-user CrUX data, and on-page SEO
  per page: title/meta, headings, images, structured data, hreflang.
- **Your own data** — connect Google Search Console, GA4 and Bing Webmaster
  Tools per user (OAuth) to see clicks, visitors and backlinks next to the
  checks.
- **IndexNow** — generates and verifies the key file and submits new or
  changed URLs (or the whole sitemap) to IndexNow search engines.
- **Sharing and reports** — read-only report links that need no login, and
  a weekly SEO summary email.
- **MCP server** — connect Claude, ChatGPT, Cursor or Claude Code with
  OAuth and your Google account; the assistant sees only your domains and
  cannot delete anything. See [MCP server](docs/MCP.md).
- **Access control** — invite-only Google sign-in, admin approval of new
  users, members see only their own domains.

## Important limitations and legal notice

- By default rankings use **direct HTTP scraping**; set
  `DATAFORSEO_LOGIN`/`DATAFORSEO_PASSWORD` to use the DataForSEO API
  instead for more reliable results. With `SERP_PROVIDER=fallback` the app
  scrapes first and only sends a query to DataForSEO when Google blocks it,
  so you pay only for blocked queries (mobile SERP is skipped in this mode). With scraping, Google frequently blocks automated requests with a
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

## Quick start (Docker)

```bash
docker network create npm_proxy   # one-time, see docs/docker.md
cp .env.docker.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan migrate
```

The dashboard runs at `http://localhost:8080` by default. Continue with
[Web dashboard](docs/web-dashboard.md) to set up Google login.

## Documentation

- [Running with Docker](docs/docker.md) — dev, production, behind
  nginx-proxy-manager, and manual (no Docker) setup.
- [Web dashboard](docs/web-dashboard.md) — Google OAuth setup, `.env`
  reference, deploying without Docker, and day-to-day usage.
- [Architecture](docs/architecture.md) — project structure, how AI
  Overview detection and the Lighthouse integration work, database
  schema, and key services.
- [User management and admin panel](docs/admin.md) — approving users,
  admin roles.
- [MCP server](docs/MCP.md) — connecting AI assistants, scopes and tools.
- [Development](docs/development.md) — local setup and running tests.

## License

Licensed under the [GNU General Public License v3.0 or later](LICENSE) (GPL-3.0-or-later).
