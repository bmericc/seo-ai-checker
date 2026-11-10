🇬🇧 English | [🇹🇷 Türkçe](MCP.tr.md)

# MCP server and agent skills

SEO AI Checker runs a Model Context Protocol (MCP) server. Assistants such as Claude, ChatGPT, Cursor and Claude Code can read your domain data and start actions.

Server address: `https://seo-checker.tarti.com/mcp`

## Connecting

You don't need to copy a token. The connection uses OAuth, and you sign in with the Google account you use for the dashboard.

1. Add the server address to your assistant:
   - **Claude (claude.ai / desktop):** Settings → Connectors → Add custom connector → address `https://seo-checker.tarti.com/mcp`.
   - **ChatGPT:** Settings → Connectors → Create → same address, authentication "OAuth".
   - **Claude Code:**

     ```bash
     claude mcp add --transport http seo-ai-checker https://seo-checker.tarti.com/mcp
     ```

     Then authorize the connection inside Claude Code with the `/mcp` command.
   - **Cursor (`mcp.json`):**

     ```json
     {
       "mcpServers": {
         "seo-ai-checker": { "url": "https://seo-checker.tarti.com/mcp" }
       }
     }
     ```

2. The assistant opens the consent page in your browser. If you are not signed in, you are sent straight to Google sign-in.
3. Review the requested permissions and press **Allow**.

You can see the access you granted on the **Connections → AI assistants (MCP)** card and remove it at any time.

## Permissions

- Each member sees only their own domains. Admins see every member's domains.
- Accounts waiting for approval cannot use any tool.
- There are two scopes:
  - `read`: read tools.
  - `write`: adding, starting checks, IndexNow submissions and report links.
- Tools you have no permission for are not listed either.
- Nothing can be deleted through MCP.
- `explore_prompt` and `get_prompt_exploration` are admin-only.

## Tools

| Tool | Scope | What it does |
| --- | --- | --- |
| `list_domains` | read | Domains, health score, keyword count, AI Overview citation rate |
| `get_domain` | read | Health score, priority issues and fixes, checks, domain age |
| `list_keywords` | read | Keywords: Google rank, AI Overview, Lighthouse |
| `get_indexnow_status` | read | IndexNow key file, verification status, recent submissions |
| `add_domain` | write | Adds a domain and starts the first site check in the background |
| `add_keyword` | write | Adds a keyword to a domain |
| `run_domain_check` | write | Runs the site check again in the background |
| `run_keyword_check` | write | Runs the keyword check in the background (1-3 min) |
| `submit_indexnow` | write | Submits URLs or the sitemap to IndexNow |
| `create_report_link` | write | Shareable report link that needs no login |
| `explore_prompt` | write, admin | Asks a question to ChatGPT, Claude, Gemini and Perplexity |
| `get_prompt_exploration` | read, admin | Returns the `explore_prompt` answers and the sites they mention |

Checks run in the queue. The tool returns at once; read the result a minute or two later with the read tools.

## Agent skills

The `skills/` folder holds ready-made workflows. To use them in Claude Code, copy the folders to `~/.claude/skills/` (or the project's `.claude/skills/`):

- `seo-site-audit`: prioritized fix plan
- `keyword-rank-report`: ranking and AI Overview report
- `ai-visibility-research`: brand visibility in AI assistants (admin)
- `client-report`: summary and report link to send to a client
- `indexnow-publish`: announcing new pages with IndexNow

## Technical notes

- The server uses [laravel/mcp](https://github.com/laravel/mcp); OAuth uses [laravel/passport](https://github.com/laravel/passport).
- Endpoint: `POST /mcp`, Streamable HTTP. `GET` and `DELETE` return 405.
- Discovery addresses:
  - `/.well-known/oauth-protected-resource/mcp`
  - `/.well-known/oauth-authorization-server`
- Dynamic client registration: `POST /oauth/register`. Authorization is at `/oauth/authorize`, tokens at `/oauth/token`. PKCE (S256) is required.
- Token lifetimes: access token 15 days, refresh token 90 days.
- Rate limit: 120 requests per minute per user.
- Setup and deploy:
  - Run `composer install`, `php artisan migrate` and `php artisan passport:keys`.
  - The key files `storage/oauth-*.key` must belong to the php-fpm user (`www-data` in Docker, mode `600`).
