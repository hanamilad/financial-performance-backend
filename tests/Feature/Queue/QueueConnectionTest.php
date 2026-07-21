<?php

/*
|--------------------------------------------------------------------------
| Queue transport — Redis, driven by Horizon
|--------------------------------------------------------------------------
|
| These assertions are about configuration, not about a running worker.
|
| config('queue.default') is NOT asserted at runtime: phpunit.xml forces
| QUEUE_CONNECTION=sync so tests never depend on a live worker. The shipped
| default for the real runtime therefore has to be proven from .env.example,
| which is what the first test does.
|
| Proof that Horizon actually consumes these jobs is manual (README, "Queue
| worker") — no test in this file claims it.
|
*/

it('ships redis as the queue connection used at runtime', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));

    expect($environmentExample)->toContain('QUEUE_CONNECTION=redis');
});

it('configures the redis queue connection', function () {
    expect(config('queue.connections.redis.driver'))->toBe('redis')
        ->and(config('queue.connections.redis.connection'))->toBe('default')
        ->and(config('queue.connections.redis.queue'))->toBe('default');
});

it('runs a single horizon supervisor on the redis default queue', function () {
    $supervisor = config('horizon.defaults.supervisor-1');

    expect(config('horizon.defaults'))->toHaveCount(1)
        ->and($supervisor['connection'])->toBe('redis')
        ->and($supervisor['queue'])->toBe(['default'])
        ->and($supervisor['balance'])->toBeFalse()
        ->and($supervisor['maxProcesses'])->toBe(1)
        ->and(config('horizon.environments.local.supervisor-1.maxProcesses'))->toBe(1);
});

it('keeps the job timeout below the queue retry_after', function () {
    // A timeout at or above retry_after lets Redis hand the same job to another
    // worker while it is still running, which processes it twice.
    expect(config('horizon.defaults.supervisor-1.timeout'))
        ->toBeLessThan(config('queue.connections.redis.retry_after'));
});
