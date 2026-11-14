🇬🇧 English | [🇹🇷 Türkçe](SECURITY.tr.md)

# Security policy

## Supported versions

Security fixes are made for the latest release only. Use the newest image
tag or the `main` branch.

| Version | Supported |
|---|---|
| 1.0.x (latest 1.0.1) | yes |

## Reporting a vulnerability

**Do not open a public issue.** Report it privately through GitHub:
[Security → Report a vulnerability](https://github.com/bmericc/seo-ai-checker/security/advisories/new).

Please include:

- what the problem is and what an attacker could do with it
- steps to reproduce or a proof of concept
- the version (image tag or commit)

You should get a first reply within 7 days. Once a fix is released, the
advisory is published and you are credited unless you prefer otherwise.

## Scope

In scope: the application code in this repository and the published Docker
image.

Out of scope: problems in third-party services (Google, DataForSEO, OpenAI
and others), self-hosted installs that are misconfigured (for example
`APP_DEBUG=true` in production or a leaked `APP_KEY`), and denial of
service through heavy traffic.

## Running it safely

- Keep `APP_DEBUG=false` in production and serve the app over HTTPS.
- Keep `APP_KEY` and `.env` secret; anyone with `APP_KEY` can decrypt the
  stored OAuth tokens and API keys.
- Only accounts in `ADMIN_EMAILS` become admins automatically; approve other
  users only when you know them.
- Update to new image tags when they are released.
