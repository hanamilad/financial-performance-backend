<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run the test-database safety guard immediately after the application is
     * booted and before any test trait runs.
     *
     * refreshApplication() is called from setUp() BEFORE setUpTraits(), so this
     * fires before RefreshDatabase can start migrating — protecting the
     * development database from ever being reached by a misconfigured suite.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $default = (string) config('database.default');

        static::assertSafeTestDatabase(
            (string) config('app.env'),
            (string) config("database.connections.{$default}.driver"),
            (string) config("database.connections.{$default}.database"),
        );
    }

    /**
     * Pure, side-effect-free guard. Throws before any connection or migration
     * if the environment is not the isolated MySQL test database. Extracted as
     * a static method so it can be proven by a test without touching any
     * database (see TestDatabaseGuardTest).
     */
    public static function assertSafeTestDatabase(string $environment, string $driver, string $database): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException(
                "Refusing to run tests: APP_ENV is '{$environment}', expected 'testing'."
            );
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException(
                "Refusing to run tests: database driver is '{$driver}', expected 'mysql' (SQLite fallback detected?)."
            );
        }

        if ($database !== 'financial_performance_test') {
            throw new RuntimeException(
                "Refusing to run tests: database is '{$database}', expected 'financial_performance_test'. "
                .'Aborting before any migration to protect the development database.'
            );
        }
    }
}
