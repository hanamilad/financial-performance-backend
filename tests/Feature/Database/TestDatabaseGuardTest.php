<?php

use Tests\TestCase;

test('the guard rejects a connection pointed at the development database', function () {
    expect(fn () => TestCase::assertSafeTestDatabase('testing', 'mysql', 'financial_performance'))
        ->toThrow(RuntimeException::class, 'financial_performance_test');
});

test('the guard rejects a non-testing environment', function () {
    expect(fn () => TestCase::assertSafeTestDatabase('production', 'mysql', 'financial_performance_test'))
        ->toThrow(RuntimeException::class, 'APP_ENV');
});

test('the guard rejects a non-mysql driver (sqlite fallback)', function () {
    expect(fn () => TestCase::assertSafeTestDatabase('testing', 'sqlite', 'financial_performance_test'))
        ->toThrow(RuntimeException::class, 'mysql');
});

test('the guard accepts the isolated test database', function () {
    TestCase::assertSafeTestDatabase('testing', 'mysql', 'financial_performance_test');

    expect(true)->toBeTrue();
});
