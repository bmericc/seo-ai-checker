<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
