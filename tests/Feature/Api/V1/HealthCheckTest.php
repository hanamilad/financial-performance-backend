<?php

use Illuminate\Support\Facades\DB;

function withUnreachableHealthDatabase(Closure $callback): void
{
    $original = config('database.connections.health_mysql');

    try {
        config([
            'database.connections.health_mysql.host' => '127.0.0.1',
            'database.connections.health_mysql.port' => 1,
        ]);
        DB::purge('health_mysql');

        $callback();
    } finally {
        config(['database.connections.health_mysql' => $original]);
        DB::purge('health_mysql');
    }
}

function withUnreachableHealthRedis(Closure $callback): void
{
    $original = config('database.redis.health_redis');

    try {
        config([
            'database.redis.health_redis.host' => '127.0.0.1',
            'database.redis.health_redis.port' => 1,
        ]);
        app()->forgetInstance('redis');
        app('redis')->purge('health_redis');

        $callback();
    } finally {
        config(['database.redis.health_redis' => $original]);
        app()->forgetInstance('redis');
        app('redis')->purge('health_redis');
    }
}

test('the readiness endpoint returns 200 when every service is reachable', function () {
    $this->get('/api/v1/health')->assertOk();
});

test('the readiness endpoint returns the agreed json contract', function () {
    $response = $this->get('/api/v1/health');

    $response->assertOk()->assertExactJson([
        'status' => 'healthy',
        'services' => [
            'database' => 'healthy',
            'redis' => 'healthy',
        ],
    ]);
});

test('the readiness endpoint responds as application/json', function () {
    $this->get('/api/v1/health')->assertHeader('Content-Type', 'application/json');
});

test('the readiness response is never cached', function () {
    $response = $this->get('/api/v1/health');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('mysql is reported healthy when reachable', function () {
    $this->get('/api/v1/health')->assertJsonPath('services.database', 'healthy');
});

test('redis is reported healthy when reachable', function () {
    $this->get('/api/v1/health')->assertJsonPath('services.redis', 'healthy');
});

test('the health connection targets the isolated test database under phpunit', function () {
    $connection = DB::connection('health_mysql');

    expect($connection->getDatabaseName())->toBe('financial_performance_test')
        ->and((string) $connection->selectOne('SELECT DATABASE() AS db')->db)
        ->toBe('financial_performance_test')
        ->not->toBe('financial_performance');
});

test('an unreachable database returns 503 and marks only the database unhealthy', function () {
    withUnreachableHealthDatabase(function () {
        $this->get('/api/v1/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'unhealthy',
                'services' => [
                    'database' => 'unhealthy',
                    'redis' => 'healthy',
                ],
            ]);
    });
});

test('an unreachable redis returns 503 and marks only redis unhealthy', function () {
    withUnreachableHealthRedis(function () {
        $this->get('/api/v1/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'unhealthy',
                'services' => [
                    'database' => 'healthy',
                    'redis' => 'unhealthy',
                ],
            ]);
    });
});

test('both services unreachable returns 503 with both marked unhealthy', function () {
    withUnreachableHealthDatabase(function () {
        withUnreachableHealthRedis(function () {
            $this->get('/api/v1/health')
                ->assertStatus(503)
                ->assertExactJson([
                    'status' => 'unhealthy',
                    'services' => [
                        'database' => 'unhealthy',
                        'redis' => 'unhealthy',
                    ],
                ]);
        });
    });
});

test('a failing readiness response discloses no internal detail', function () {
    withUnreachableHealthDatabase(function () {
        withUnreachableHealthRedis(function () {
            $body = $this->get('/api/v1/health')->assertStatus(503)->getContent();

            expect($body)
                ->not->toContain('SQLSTATE')
                ->not->toContain('Exception')
                ->not->toContain('PDO')
                ->not->toContain('Connection refused')
                ->not->toContain('vendor/')
                ->not->toContain('127.0.0.1')
                ->not->toContain('3306')
                ->not->toContain('6379')
                ->not->toContain('mysql')
                ->not->toContain('financial_performance');
        });
    });
});

test('the readiness endpoint returns json without an accept header', function () {
    $response = $this->get('/api/v1/health');

    $response->assertHeader('Content-Type', 'application/json');

    expect(json_decode($response->getContent(), true))->toBeArray();
});

test('an unknown api route returns a json 404', function () {
    $this->get('/api/v1/missing')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

test('the liveness endpoint still succeeds', function () {
    $this->get('/up')->assertSuccessful();
});
