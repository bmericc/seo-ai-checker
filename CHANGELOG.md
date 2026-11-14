🇬🇧 English | [🇹🇷 Türkçe](CHANGELOG.tr.md)

# Changelog

All notable changes are listed here. Versions follow
[Semantic Versioning](https://semver.org/). Docker images are published for
every release tag (see [Docker installation steps](docs/docker-install.md)).

## [Unreleased]

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

[Unreleased]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bmericc/seo-ai-checker/releases/tag/v1.0.0
