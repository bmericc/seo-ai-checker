🇬🇧 English | [🇹🇷 Türkçe](CHANGELOG.tr.md)

# Changelog

All notable changes are listed here. Versions follow
[Semantic Versioning](https://semver.org/). Docker images are published for
every release tag (see [Docker installation steps](docs/docker-install.md)).

## [Unreleased]

## [1.1.3] - 2026-11-26

### Changed

- Dependency updates: `laravel/mcp` 1.2.1; development tools
  `laravel/pail` 1.3.0, `laravel/tinker` 3.2.0, `mockery/mockery` 1.6.15
  and `phpunit/phpunit` 13.4.8.
- The Docker image uses the latest `php:8.5-fpm-alpine` base image.
- GitHub Actions updated: `github/codeql-action` v4.39.4 and
  `ossf/scorecard-action` v2.6.0.
- The MCP server reports version 1.1.3.

## [1.1.2] - 2026-11-18

### Changed

- The footer shows the app version next to its name, linked to the release
  notes. The version lives in one place (`App\Support\AppVersion`), which
  the MCP server also reads.
- The MCP server reports version 1.1.2.

## [1.1.1] - 2026-11-18

### Changed

- Dependency updates: `laravel/mcp` 1.2.0, `symfony/dom-crawler` 8.1.10,
  `laravel/pao` 1.1.5; development tools `phpunit/phpunit` 13.4,
  `laravel/pint` 1.32.3 and `concurrently` 10.
- GitHub Actions updated: `actions/checkout` v7, `docker/setup-qemu-action`
  v4, `docker/setup-buildx-action` v4, `docker/login-action` v4,
  `docker/metadata-action` v6 and `docker/build-push-action` v7.
- The MCP server reports version 1.1.1.

## [1.1.0] - 2026-11-17

### Added

- **Email + password sign-in** — sign-up, login, email verification and
  password reset next to Google sign-in. New accounts still wait for admin
  approval. Users who signed up with Google can set a password with
  "Forgot password".
- `GOOGLE_LOGIN_ENABLED` turns "Sign in with Google" on or off (default
  on). Signed-in users can still connect Google for Search Console and GA4.
- **Mobile app support** — server endpoints for the "Seo Checker" Android
  and iOS app (separate repository): `/mobile/meta`, `/mobile/auth/start`
  and `/mobile/session`. The app asks for the server address and signs in
  on the site in the system browser (Google sign-in also finishes there).
  It then opens the dashboard with a one-time code that is valid for
  2 minutes. The session is remembered, so the app does not ask to sign in
  on every launch.
- **Native mobile screens** — a read-only API for the app:
  `POST /api/v1/auth/token` exchanges the one-time code for a token with the
  `read` scope (`DELETE` revokes it), and `/api/v1/domains`,
  `/api/v1/domains/{id}/screen` and `/api/v1/changes` return the domain
  list, every domain detail tab and recent changes. "Open in web" links go
  through `/mobile/web`. `/mobile/meta` reports `mobile_api` 3.
- **Account deletion** — users can delete their own account from Settings
  (and from the pending and verification pages); the app uses
  `DELETE /api/v1/me`. It removes the user's domains and their history,
  pending invites, tokens and sessions. The only admin cannot delete
  themselves.
- A GitHub Release is created for each version tag, with the notes from
  this changelog.
- Dependabot keeps GitHub Actions, Composer, npm and the Docker base image
  up to date; a CodeQL workflow and OpenSSF Scorecard run in CI.

### Changed

- `ADMIN_EMAILS` promotion and pending domain shares apply to an email +
  password account only after its email is verified.
- Connecting Google while signed in now links it to the current account,
  even when the Google email is different.
- Guests are sent to `/login` instead of the home page.
- Privacy policy and terms cover email + password sign-in, sharing,
  connected accounts, AI providers and other third-party services, the
  mobile app (Firebase Crashlytics/Analytics without the advertising ID)
  and account deletion, and list KVKK/GDPR rights.
- The Docker image is built on a `php:8.5-fpm-alpine` base pinned by
  digest; GitHub Actions are pinned to commit SHAs.
- The MCP server reports version 1.1.0.

## [1.0.1] - 2026-11-14

### Security

- Update dependencies to fix known vulnerabilities reported by Dependabot:
  `laravel/framework` 13.42.0 (TokenGuard authentication bypass, critical),
  `league/commonmark` 2.10.7, `guzzlehttp/guzzle` 7.16.1,
  `symfony/http-foundation` 8.1.11, `phpseclib/phpseclib` 3.0.58,
  `league/flysystem` 3.36.0.

### Added

- Documentation: feature guide with screenshots, scheduled tasks, Docker
  installation steps with all API keys, Turkish versions of every document,
  contributing guide and security policy.

## [1.0.0] - 2026-11-14

First public release.

### Added

- **Google rankings** — top ~20 organic results per keyword, desktop and
  mobile, by direct scraping or the DataForSEO API. `SERP_PROVIDER=fallback`
  scrapes first and pays DataForSEO only for blocked queries.
- **Google AI Overview** — detection, cited domains and a stability view
  over the last 10 checks.
- **AI assistant visibility** — ChatGPT, Claude, Gemini and Perplexity
  checks with per-domain API keys, Prompt Explorer with a question wizard,
  Share of Model matrix, DataForSEO LLM Scraper and LLM Mentions.
- **Site health** — AI bots in robots.txt, sitemap, llms.txt check and
  generator, HTTPS, security headers, canonical host, WHOIS, CrUX, drift
  between checks, prioritized issues with fixes.
- **Lighthouse and on-page SEO** — PageSpeed Insights per keyword and for
  every sitemap page in the background.
- **Keyword tools** — keyword metrics (volume, difficulty, CPC, intent) and
  site-wide keyword suggestions from sitemap pages.
- **Competitors** — SERP competitor discovery, competitor keyword gaps and
  a global competitor blacklist.
- **Backlinks** — merged DataForSEO, CrawlGraph (Common Crawl) and Bing
  Webmaster Tools data with trend and new/lost domains.
- **Your own data** — Google Search Console, GA4 (with channel breakdown)
  and Bing Webmaster Tools per user over OAuth.
- **IndexNow** — key file, verification and URL/sitemap submission.
- **Sharing and reports** — read-only report links and a weekly SEO email.
- **MCP server** — OAuth-protected `/mcp` endpoint for Claude, ChatGPT,
  Cursor and Claude Code, with read and optional write access.
- **Access control** — invite-only Google sign-in, admin approval, domain
  sharing between accounts, per-host login callback.
- **English and Turkish** interface.
- **Docker** — one multi-arch image (`linux/amd64`, `linux/arm64`) with
  nginx and php-fpm, published to GHCR (`ghcr.io/bmericc/seo-ai-checker`)
  and Docker Hub (`bmericc/seo-ai-checker`); queue worker and scheduler use
  the same image.

[Unreleased]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.3...HEAD
[1.1.3]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bmericc/seo-ai-checker/releases/tag/v1.0.0
