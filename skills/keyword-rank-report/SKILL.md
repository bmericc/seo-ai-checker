---
name: keyword-rank-report
description: Report Google rankings and AI Overview citations for the keywords a domain tracks in SEO AI Checker, and suggest what to work on. Use for "how are my keywords doing", "rank report", "which keywords dropped", "am I in AI Overviews".
---

# Keyword ranking report

Requires the `seo-ai-checker` MCP server.

1. `list_keywords` for the domain. If there are none, ask for 3–10 target phrases and add them with `add_keyword`.
2. For keywords with `last_checked_at` older than 7 days (or none), ask before refreshing. `run_keyword_check` queues a check that takes 1–3 minutes; queue at most 5 per turn unless the user agrees to more, then call `list_keywords` again.
3. Group the results:
   - By `google_position`: top 3, page 1 (4–10), page 2 (11–20), beyond 20 / not found. `serp_blocked: true` means Google blocked the check; say so.
   - AI Overview (`ai_overview_present`, `ai_overview_cites_site`): present and cited / present but not cited / no AI Overview.
4. Highlight opportunities:
   - Positions 4–20: closest to gaining traffic. Suggest on-page improvements for the target URL.
   - "AI Overview present but not cited": the page should answer the query directly in the first paragraph, use clear headings and FAQ-style sections.
   - Low `lighthouse.performance` (< 50) on a target page: speed is likely holding it back.
5. End with a short table: keyword, position, AI Overview status.

Answer in the user's language. Do not claim ranking changes you have no earlier data for.
