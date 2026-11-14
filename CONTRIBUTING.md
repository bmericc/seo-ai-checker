🇬🇧 English | [🇹🇷 Türkçe](CONTRIBUTING.tr.md)

# Contributing

Thanks for helping. Bug reports, ideas and pull requests are welcome.

## Reporting a bug or asking for a feature

Open an [issue](https://github.com/bmericc/seo-ai-checker/issues) with:

- what you did, what you expected and what happened
- version (image tag or commit) and how you run it (prebuilt image, built
  from source, without Docker)
- the relevant lines from the logs (`docker compose logs app worker`)

Remove API keys, tokens, emails and other private data before posting.
**Security problems** go through the [security policy](SECURITY.md), not a
public issue.

## Setting up

Follow [Docker installation steps → B. Docker, built from source](docs/docker-install.md#b-docker-built-from-source)
or [Development](docs/development.md) for a setup without Docker. Code
structure: [Architecture](docs/architecture.md).

## Making a change

1. Fork the repository and create a branch from `main`.
2. Keep the change focused on one thing.
3. Add or update tests under `www/tests/`.
4. Run the tests:
   ```bash
   docker compose exec app php artisan test
   ```
5. Format PHP code with Laravel Pint:
   ```bash
   docker compose exec app ./vendor/bin/pint
   ```
6. Open a pull request against `main`. CI (`.github/workflows/tests.yml`)
   runs the test suite.

## Conventions

- **Commit messages and pull requests** are in English, written as a short
  imperative sentence ("Add backlink trend chart").
- **User-facing text** is written in Turkish inside `__()` and translated
  in `www/lang/en.json`. Add both when you add or change a string. Use the
  correct Turkish letters (ı, ş, ğ, ü, ö, ç).
- **Code comments** follow the existing style of the file.
- **Documentation** is bilingual: when you change `docs/x.md`, update
  `docs/x.tr.md` too.
- New settings go into `config/seo.php` with an `env()` default, and into
  `.env.docker.example` and the [API list](docs/docker-install.md#available-apis)
  when they are API keys.

## License

By contributing you agree that your work is released under the project's
[GPL-3.0 license](LICENSE).
