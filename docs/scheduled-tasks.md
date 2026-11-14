🇬🇧 English | [🇹🇷 Türkçe](scheduled-tasks.tr.md)

[← Back to README](../README.md)

# Scheduled tasks

The `scheduler` container runs `php artisan schedule:work` and starts these
tasks on time. Most of them only put jobs on the queue; the `worker`
container runs the jobs. **Both containers must be running**, otherwise
nothing happens automatically.

Times are **UTC** (the app timezone). For Türkiye (UTC+3) add 3 hours.

| Task | When (UTC) | What it does | Cost |
|---|---|---|---|
| `domains:run-daily-checks` | every day 03:00 | Site check for every domain: robots.txt, sitemap, llms.txt, security headers, CrUX, Search Console, GA4, Bing backlinks | free (Google/Bing quotas) |
| `backlinks:sync-crawlgraph` | every day 05:00 | CrawlGraph referring domains, only for domains without data for the newest Common Crawl archive | free, 15 lookups a month |
| `keywords:run-weekly-checks` | Sunday 02:00 | Full check of every keyword: SERP desktop + mobile, on-page, Lighthouse; keyword metrics | DataForSEO, if set |
| `keywords:collect-queued-serp` | every 5 minutes | Collects finished DataForSEO queue results and starts the keyword checks that are ready | free |
| `reports:send-weekly` | Monday 08:00 | Weekly SEO summary email to every approved user with a checked domain | mail server |
| `backlinks:run-monthly-fetch` | 1st of the month 04:00 | DataForSEO backlink data for every domain | about $0.13 per domain |

## Details

### Daily site check

One job per domain, so a slow or failing site does not hold up the others.
One domain takes about 30–60 seconds. Same as clicking **Run Site Check**.

### Weekly keyword check

- **With DataForSEO:** desktop and mobile SERP tasks go to the **Standard
  queue**, about 3 times cheaper than live requests. Results are collected
  every 5 minutes by `keywords:collect-queued-serp`; when both devices are
  ready, the full keyword check is queued. Tasks not ready after
  `SCHEDULED_SERP_QUEUE_TIMEOUT_HOURS` (default 6) are retried live.
- **Without DataForSEO:** each keyword is checked by direct scraping, with
  `REQUEST_DELAY_MS` between requests.
- AI assistant checks (your own OpenAI/Anthropic/Gemini/Perplexity keys
  and DataForSEO LLM Scraper) are **not** called by default, because they
  cost money. Turn them on with `SCHEDULED_KEYWORD_CHECKS_INCLUDE_LLM=true`.

### Monthly backlinks

Runs only when DataForSEO is configured. You can also fetch any domain's
backlinks on demand with **Refresh now** on the Backlinks tab.

### CrawlGraph

Runs daily but sends a request only when a domain has no data for the
newest archive yet (archives come out about every three months). When the
free quota runs out (HTTP 429) it stops; the remaining domains are queried
after the quota resets.

### Weekly email

Users with no checked domain get no email. Mail needs a real mailer:
`MAIL_MAILER=smtp` with `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. The default `log` only writes the
email to the log.

## Turning tasks off

`SCHEDULED_KEYWORD_CHECKS=false` turns off the weekly keyword check and the
monthly backlink fetch (the paid tasks). Daily site checks and CrawlGraph
keep running.

## Running a task by hand

Prebuilt image:

```bash
docker compose -f docker-compose.release.yml exec app php artisan domains:run-daily-checks
```

Built from source:

```bash
docker compose exec app php artisan keywords:run-weekly-checks
```

List all tasks and their next run:

```bash
docker compose exec app php artisan schedule:list
```
