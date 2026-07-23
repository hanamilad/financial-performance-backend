<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
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
