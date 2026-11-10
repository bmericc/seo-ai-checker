---
name: seo-site-audit
description: Audit a domain tracked in SEO AI Checker and produce a prioritized fix plan. Use when the user asks "what's wrong with my site", "audit example.com", "why is my SEO health low", or wants a to-do list for a domain.
---

# Site audit with SEO AI Checker

Requires the `seo-ai-checker` MCP server. Adding domains and running checks need the "write" permission.

1. Call `list_domains`. If the domain is not tracked, offer `add_domain` (it starts the first site check by itself).
2. If `last_site_check_at` is older than 7 days, or the user asks for fresh data, call `run_domain_check`. It runs in the background: tell the user, wait 1–2 minutes, then continue.
3. Call `get_domain`. Use `issues` (already sorted by impact: high → medium → low) as the backbone of the answer.
4. Write the report:
   - One line: health score and summary.
   - "Fix first": every `high` issue, each with its `fix` text rewritten as concrete steps for the user's platform if you know it (WordPress, Shopify, nginx...).
   - "Then": medium and low issues, one line each.
   - "Working well": checks with status `ok`, comma separated.
5. Offer next steps: re-run `run_domain_check` after fixes, `create_report_link` to share the result, `submit_indexnow` after publishing changed pages.

Rules:
- Never invent issues that are not in `issues` or `checks`. If a data source is missing (e.g. no Search Console), say so instead of guessing.
- Answer in the user's language.
