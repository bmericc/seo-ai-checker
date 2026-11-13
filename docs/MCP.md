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

- Each member sees their own domains plus domains shared with them from the domain page's "Access" dialog. `list_domains` returns your `access` level per domain (`owner`, `write`, `read`); write tools fail on read-only shares. Admins see every member's domains.
- Accounts waiting for approval cannot use any tool.
- There are two scopes:
  - `read`: read tools.
  - `write`: adding, starting checks, IndexNow submissions and report links.
- Tools you have no permission for are not listed either.
- Nothing can be deleted through MCP.
- `explore_prompt` is admin-only (it uses the domain's AI API keys).

## Tools

| Tool | Scope | What it does |
| --- | --- | --- |
| `list_domains` | read | Domains, health score, keyword count, AI Overview citation rate |
| `get_domain` | read | Health score, priority issues and fixes, checks, domain age |
| `list_keywords` | read | Keywords: Google rank, AI Overview (incl. stability over the last 10 checks), Lighthouse |
| `get_visibility_changes` | read | What got worse or better in the last N days (rankings, AI Overview citations, assistant mentions, site checks), sorted by impact |
| `get_competitors` | read | SERP competitors and Share of Model: how often each AI assistant mentions the site and its competitors |
| `get_competitor_keyword_gap` | read | Cross-check: keywords the selected competitors rank for (DataForSEO) or feature on their site (crawl) that the site does not track yet |
| `generate_llms_txt` | read | llms.txt draft built from the sitemap pages (nothing is published) |
| `get_indexnow_status` | read | IndexNow key file, verification status, recent submissions |
| `add_domain` | write | Adds a domain and starts the first site check in the background |
| `add_keyword` | write | Adds a keyword to a domain |
| `set_competitor` | write | Marks a domain as a real competitor or "not a competitor", or adds one by hand; fetches its keywords in the background |
| `run_domain_check` | write | Runs the site check again in the background |
| `run_keyword_check` | write | Runs the keyword check in the background (1-3 min) |
| `submit_indexnow` | write | Submits URLs or the sitemap to IndexNow |
| `create_report_link` | write | Shareable report link that needs no login |
| `explore_prompt` | write, admin | Asks a question to ChatGPT, Claude, Gemini and Perplexity |
| `get_prompt_exploration` | read | Returns one saved AI check in full: answers, mention status and the sites they mention |
| `check_ai_answer` | write | Records a manual check of a tracked keyword: the assistant's own answers, whether the domain appears, other sites mentioned |
| `list_ai_checks` | read | History of saved AI checks (manual and API), filterable by keyword, assistant, prompt text and source |
| `get_ai_check_prompts` | read (write to add a keyword) | Questions for a manual check in the API format; adds a new keyword first, or lists every tracked keyword |

### Manual AI check (`check_ai_answer`)

Your assistant checks your keywords itself, without API keys, in the same format as the API checks: the same instruction, the same question (*"… konusunda hangi web sitelerine bakmalıyım?"*) and the same follow-up question about sites from Turkey. Free-form questions are not checked manually.

1. Ask your assistant, for example: *"Check example.com's keywords"* or *"Check the keyword 'e-fatura' for example.com"*.
2. The assistant calls `get_ai_check_prompts`. With a keyword the domain does not track yet, the keyword is added to its keywords first. Without a keyword, every tracked keyword is returned.
3. The assistant answers each question and the follow-up question, then records them with `check_ai_answer`. The assistant name and the exact model (for example `grok` / `grok-5`) are required, like the API checks record them. It only accepts keywords the domain already tracks.

The tool reports whether the domain appears in the answers or the cited URLs and lists the other sites mentioned. Claude, ChatGPT, Gemini and Perplexity are stored under the same names as the API checks; any other assistant (Grok, DeepSeek, Copilot…) is stored under its own name.

Every result is kept as history. Members see it on the keyword page and on the domain page under **AI visibility → AI check history**, marked "Manual (MCP)", and `list_ai_checks` returns it.

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
