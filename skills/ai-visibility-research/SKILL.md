---
name: ai-visibility-research
description: Find out whether ChatGPT, Claude, Gemini and Perplexity recommend a brand, and which competitors they mention instead, using SEO AI Checker's Prompt Explorer. Use for "does ChatGPT know my brand", "AI search visibility", "who do AI assistants recommend for X". Admin accounts only.
---

# AI assistant visibility research

Requires the `seo-ai-checker` MCP server, an admin account and the "write" permission (`explore_prompt` is admin-only, and the domain needs at least one AI provider API key in the app).

1. Clarify the audience and market (city/country, language). Draft 3–5 prompts a real customer would type, for example:
   - "best <service> in <city>"
   - "<product category> recommendations for <use case>"
   - "<brand> vs <competitor>"
   Show the prompts to the user before running them; each run calls paid APIs.
2. Run `explore_prompt` once per prompt. It runs in the background: wait about a minute, then read the answers with `get_prompt_exploration` (repeat while `complete` is false).
3. Summarize per prompt: which assistants mentioned the domain (`summary.mentioned` / `summary.answered`), and the top `other_sites_mentioned`.
4. Across all prompts, list the competitors that appear most often and the assistants where the brand is missing.
5. Recommend concrete actions: pages that answer these questions directly, an `llms.txt`, being listed on the sites the assistants cite (Perplexity `citations` are the strongest hint), allowing AI crawlers in robots.txt (check `get_domain`).

Answer in the user's language. Quote short fragments of answers, not whole responses.
