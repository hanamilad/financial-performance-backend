<?php

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
    expect(config('horizon.defaults.supervisor-1.timeout'))
        ->toBeLessThan(config('queue.connections.redis.retry_after'));
});
