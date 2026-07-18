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
     * Docker injects DB_DATABASE as a real container-level environment
     * variable (see docker-compose.yml's `environment:` block), which
     * populates $_ENV before phpunit.xml's <env name="DB_DATABASE"
     * value=":memory:" force="true"/> override gets a chance to apply -
     * so that override is silently ignored, and tests would otherwise run
     * against (and RefreshDatabase-wipe) the real dev database.sqlite file.
     * Force the in-memory database directly on the resolved config
     * instead, which always wins regardless of env-var precedence.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }
}
