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
- [Development](docs/development.md) — local setup and running tests.

## License

Licensed under the [GNU General Public License v3.0 or later](LICENSE) (GPL-3.0-or-later).
