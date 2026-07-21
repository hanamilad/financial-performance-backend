<?php

use Illuminate\Support\Collection;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders;
use Laravel\Pulse\Support\CacheStoreResolver;

/*
|--------------------------------------------------------------------------
| Pulse configuration
|--------------------------------------------------------------------------
|
| Pulse is deliberately kept to the smallest useful shape (FOUNDATION-006):
| it writes straight into the application's own MySQL database, keeps a week
| of data, and runs five recorders. Every one of those choices exists so the
| stack needs no extra process and no extra container on a small VPS
| (DEC-012).
|
| These tests fail the moment one of those decisions is changed silently —
| a new recorder switched on, a longer retention window, or an ingest driver
| that would require a `pulse:work` process nobody started.
|
| No database access is needed here, so no RefreshDatabase.
|
*/

it('stores entries in the application database', function () {
    expect(config('pulse.storage.driver'))->toBe('database')
        // Null means "the default connection" — Pulse shares the application's
        // MySQL database instead of a second connection or database.
        ->and(config('pulse.storage.database.connection'))->toBeNull();
});

it('ingests entries directly, without a pulse worker process', function () {
    // The `redis` driver would need a permanent `php artisan pulse:work`
    // process; `storage` writes at the end of the request instead.
    expect(config('pulse.ingest.driver'))->toBe('storage');
});

it('caches dashboard queries in a store that can hold objects', function () {
    // Laravel 13 ships cache.serializable_classes => false, so nothing that is
    // serialized into the shared cache can be read back as an object. Pulse
    // caches collections and stdClass rows per card, so it must use a store
    // that does not serialize at all — otherwise every card renders an error.
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
    // Mirrors Pulse's own rule for a disabled recorder (Pulse::register()).
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
    // phpunit.xml pins PULSE_ENABLED=false, so the rest of the suite can never
    // write Pulse rows into financial_performance_test.
    //
    // The value is compared loosely on purpose: PHPUnit parses value="false"
    // as a boolean and passes it to putenv(), which stringifies it to '', so
    // what reaches config() is a falsy empty string rather than false. Pulse
    // reads it the same way — a falsy switch means stopRecording() — and the
    // empty recorder collection below is the behaviour that actually matters.
    expect(config('pulse.enabled'))->toBeFalsy()
        ->and(Pulse::recorders())->toBeEmpty();
});
