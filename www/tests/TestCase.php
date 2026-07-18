<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Symfony's Request::create() (used internally by the HTTP test
     * client) synthesizes a default "Accept-Language: en-us,en;q=0.5"
     * header even when a test never sets one - unlike real production
     * requests (Request::createFromGlobals()), which only carry a
     * language header if the actual client sent one. Without this
     * override, every test would resolve to English via SetLocale's
     * Accept-Language detection, breaking existing Turkish-text
     * assertions. Individual tests can still override with
     * ->withHeaders(['Accept-Language' => 'en']).
     */
    protected $defaultHeaders = ['Accept-Language' => 'tr'];

    /**
     * Docker injects DB_DATABASE/SESSION_DRIVER as real container-level
     * environment variables (see docker-compose.yml's `environment:`
     * block), which populate $_ENV before phpunit.xml's <env ... force=
     * "true"/> overrides get a chance to apply - so those overrides are
     * silently ignored under Docker (config('session.driver') resolves to
     * 'database' here despite phpunit.xml forcing 'array', confirmed via a
     * scratch test). Force both directly on the resolved config instead,
     * which always wins regardless of env-var precedence. DB_DATABASE
     * matters so tests don't run against (and RefreshDatabase-wipe) the
     * real dev database.sqlite file; SESSION_DRIVER matters because the
     * 'database' driver needs a migrated sessions table this app doesn't
     * have, and is unnecessarily slow compared to 'array' for tests.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('session.driver', 'array');

        return $app;
    }
}
