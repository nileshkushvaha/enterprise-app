<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProductionDatabase();
    }

    /**
     * Abort if either the environment or the configured database looks wrong.
     *
     * Two independent checks:
     *   1. APP_ENV must be "testing" — catches running php artisan test without --env=testing.
     *   2. DB_DATABASE must not be the development database — catches the case where someone
     *      sets APP_ENV=testing but still points at enterprise_app (e.g. a misconfigured .env.testing).
     *
     * RefreshDatabase runs migrate:fresh, which drops all tables. enterprise_app is not recoverable.
     */
    private function guardAgainstProductionDatabase(): void
    {
        if (! app()->environment('testing')) {
            $this->fail(
                "SAFETY ABORT: APP_ENV is [" . app()->environment() . "], not [testing].\n" .
                ".env.testing was not loaded — RefreshDatabase could wipe the wrong database.\n" .
                "Run tests via: composer test   (php artisan test --env=testing)"
            );
        }

        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");

        if ($database === 'enterprise_app') {
            $this->fail(
                "SAFETY ABORT: tests are pointed at the development database [enterprise_app].\n" .
                "APP_ENV=testing was set but DB_DATABASE was not overridden.\n" .
                "Check .env.testing — it must set DB_DATABASE=enterprise_app_testing."
            );
        }
    }
}
