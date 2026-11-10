---
name: indexnow-publish
description: Notify Bing, Yandex, Seznam and Naver about new or updated pages through IndexNow using SEO AI Checker. Use after publishing or changing pages, or for "index my new page fast", "submit URLs to Bing".
---

# Publish URLs with IndexNow

Requires the `seo-ai-checker` MCP server with the "write" permission.

1. `get_indexnow_status` for the domain.
2. If `key_created` is false, ask the user to create a key on the "Hızlı indeksleme" (Instant indexing) tab of the domain page first. If `verified` is false, the key file is not confirmed. Tell the user to create a plain text file at `key_file_url` whose only content is `key_file_content` (or download it from the "Hızlı indeksleme" tab in the app). Wait for them to confirm before submitting.
3. `submit_indexnow` with the exact changed URLs. Only omit `urls` (which sends the whole sitemap) when the user asks for it or after a site-wide change.
4. Report the `message`. `status_code` 403 means the key file is missing or wrong; 422 means the URLs do not belong to the domain; 429 means too many submissions, try later.

Note for the user: Google does not support IndexNow. For Google, keep the sitemap up to date.
