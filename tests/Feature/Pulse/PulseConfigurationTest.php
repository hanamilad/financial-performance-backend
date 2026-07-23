<?php

use Illuminate\Support\Collection;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders;
use Laravel\Pulse\Support\CacheStoreResolver;

it('stores entries in the application database', function () {
    expect(config('pulse.storage.driver'))->toBe('database')
        ->and(config('pulse.storage.database.connection'))->toBeNull();
});

it('ingests entries directly, without a pulse worker process', function () {
    expect(config('pulse.ingest.driver'))->toBe('storage');
});

it('caches dashboard queries in a store that can hold objects', function () {
    expect(config('pulse.cache'))->toBe('array');

    $store = app(CacheStoreResolver::class)->store();
    $store->put('pulse-configuration-test', collect(['a']), 5);

    expect($store->get('pulse-configuration-test'))->toBeInstanceOf(Collection::class);
});

it('keeps one week of data', function () {
    expect(config('pulse.storage.trim.keep'))->toBe('7 days')
        ->and(config('pulse.ingest.trim.keep'))->toBe('7 days');
});

it('enables exactly the five approved recorders', function () {
    $enabled = collect(config('pulse.recorders'))
        ->filter(fn ($options) => $options !== false && ($options['enabled'] ?? true))
        ->keys()
        ->sort()
        ->values()
        ->all();

    $approved = collect([
        Recorders\Exceptions::class,
        Recorders\Queues::class,
        Recorders\SlowJobs::class,
        Recorders\SlowQueries::class,
        Recorders\SlowRequests::class,
    ])->sort()->values()->all();

    expect($enabled)->toBe($approved);
});

it('disables the server recorder that would require a pulse check process', function () {
    expect(config('pulse.recorders.'.Recorders\Servers::class.'.enabled'))->toBeFalse();
});

it('samples everything and treats one second as slow', function () {
    $recorders = [
        Recorders\Exceptions::class,
        Recorders\Queues::class,
        Recorders\SlowJobs::class,
        Recorders\SlowQueries::class,
        Recorders\SlowRequests::class,
    ];

    foreach ($recorders as $recorder) {
        expect(config("pulse.recorders.{$recorder}.sample_rate"))->toBe(1);
    }

    foreach ([Recorders\SlowJobs::class, Recorders\SlowQueries::class, Recorders\SlowRequests::class] as $recorder) {
        expect(config("pulse.recorders.{$recorder}.threshold"))->toBe(1000);
    }
});

it('runs no dedicated pulse process and no scheduled pulse task', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)->not->toContain('pulse:work')
        ->and($compose)->not->toContain('pulse:check');

    $this->artisan('schedule:list')
        ->expectsOutputToContain('No scheduled tasks have been defined.')
        ->assertSuccessful();
});

it('registers no recorder while the master switch is off', function () {
    expect(config('pulse.enabled'))->toBeFalsy()
        ->and(Pulse::recorders())->toBeEmpty();
});
