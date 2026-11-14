🇬🇧 English | [🇹🇷 Türkçe](web-dashboard.tr.md)

[← Back to README](../README.md)

# Web dashboard

The web dashboard stores domain/keyword records and the result of every
"Check" run (SERP + AI Overview + on-page + Lighthouse) in the database, so
you can view changes over time as history. For a screen-by-screen tour see [Feature guide](features.md).

## 1. Create a Google OAuth application

Any Google account can sign in and create an account in the dashboard, but
new accounts can't access the dashboard until an **admin approves** them
(see [User management and admin panel](admin.md)) — Google-based
authentication + admin approval is used instead of a plain password.

1. Create a new **OAuth client ID** on the
   [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials)
   page, type: **Web application**.
2. Add an **Authorized redirect URI** for every address the dashboard is
   served from, e.g. `https://your-server.com/auth/google/callback`. Login
   returns to the address it started on, so a second domain (e.g.
   `https://your-server.com.tr`) needs its own
   `https://your-server.com.tr/auth/google/callback` entry.
3. Write the resulting **Client ID** and **Client Secret** values into the
   relevant `.env` file (Docker: `.env`/`.env.prod` at the repo root;
   manual setup: `www/.env`) — `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`.
4. Put your own Google email into `ADMIN_EMAILS`. This email(s) are
   **automatically treated as admin + approved** on first login, so you
   get access to yourself on initial setup. All subsequent user
   approvals/admin assignments are done from the `/admin/users` panel, no
   further `.env` editing needed.

## 2. `.env` settings (web-specific)

| Variable | Description |
|---|---|
| `APP_URL` | The dashboard's public address |
| `DB_CONNECTION`, `DB_DATABASE`/`DB_HOST`/... | SQLite (default) or MySQL/PostgreSQL |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google OAuth app credentials (the callback URL is derived from the request host) |
| `ADMIN_EMAILS` | Emails automatically treated as admin+approved on first login (comma-separated) |
| `PSI_API_KEY` | (optional but recommended) PageSpeed Insights API key |
| `PSI_STRATEGY` | `mobile` or `desktop` |
| `DATAFORSEO_LOGIN` / `DATAFORSEO_PASSWORD` | (optional) DataForSEO API credentials for SERP, keyword metrics and AI mention data |
| `SERP_PROVIDER` | `auto` (default: DataForSEO when credentials are set, otherwise direct scraping), `google`, `dataforseo` or `fallback` (scrape first, DataForSEO only when Google blocks; no mobile SERP, weekly checks run live). In Docker, if it is not in `docker-compose.yml`'s `environment:` block, add it to `www/.env` |
| `CRAWLGRAPH_API_KEY` | (optional) free CrawlGraph key for Common Crawl backlinks: `POST https://crawlgraph.com/api/v1/free-key` with `{"email": "..."}`. 15 lookups a month. In Docker it is already listed in `docker-compose.yml` |
| `SERP_FALLBACK_COOLDOWN_MINUTES` | In `fallback` mode, minutes to skip Google after a block (default `30`, `0` = off) |

All API keys: [Docker installation steps → Available APIs](docker-install.md#available-apis).

## 3. Deploying to a server (without Docker)

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

## 4. Usage

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
5. The domain page's **Backlink** tab shows the merged backlink profile.
   The scheduler runs `backlinks:run-monthly-fetch` (DataForSEO, 1st of the
   month 04:00) and `backlinks:sync-crawlgraph` (daily 05:00, queries only
   domains missing the latest Common Crawl archive and stops when the free
   quota runs out). Bing rows come with each site check for users who
   connected Bing Webmaster Tools.
   All scheduled tasks: [Scheduled tasks](scheduled-tasks.md).
