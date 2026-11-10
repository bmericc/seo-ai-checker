---
name: client-report
description: Prepare a client-ready SEO status update for a domain in SEO AI Checker with a shareable report link. Use for "send a report to my client", "monthly SEO report", "share the results".
---

# Client report

Requires the `seo-ai-checker` MCP server with the "write" permission (for `create_report_link`).

1. `get_domain` (and `list_keywords` if the client cares about rankings). Refresh with `run_domain_check` only if `last_site_check_at` is older than 7 days; it runs in the background, so wait 1–2 minutes before reading again.
2. Ask which sections the client should see: `summary`, `keywords`, `traffic`, `performance`, `ai`. Default to all. Ask how long the link should work: `expires_in_days` 7, 30, 90, or 0 for no expiry.
3. `create_report_link` with those choices.
4. Write a short message the user can send, in plain language without jargon:
   - Health score and what it means.
   - 2–3 most important findings (from `issues`), framed as next steps.
   - Ranking or traffic highlights only if the data exists.
   - The report link, noting that it always shows live data and can be closed at any time from the app.

Answer in the user's language. Do not send anything yourself; give the user the text.
