<?php

use Illuminate\Support\Facades\DB;

test('the test suite runs on the mysql driver', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
});

test('the test suite targets the isolated test database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('financial_performance_test');
});

test('the test suite runs on MySQL 8.4', function () {
    $version = (string) DB::selectOne('select version() as version')->version;

    expect($version)->toStartWith('8.4');
});
