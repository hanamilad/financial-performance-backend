<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Facades\Pulse;

/*
|--------------------------------------------------------------------------
| Pulse dashboard access
|--------------------------------------------------------------------------
|
| The approved rule (FOUNDATION-006) is the same one Horizon already follows:
| /pulse is reachable on a local machine and forbidden everywhere else until
| authentication exists (DEC-035).
|
| Pulse ships that rule itself — its service provider defines
| `Gate::define('viewPulse', fn ($user = null) => $app->environment('local'))`
| and the dashboard routes run through its Authorize middleware. So no gate,
| provider, Basic Auth, custom middleware or secret is added by this project;
| these tests pin the built-in behaviour instead.
|
| The suite runs with APP_ENV=testing, which is already a non-local
| environment — the "forbidden" case needs no setup. The local case flips the
| container's env binding only; config('app.env') stays 'testing', so the
| test-database guard in tests/TestCase.php is untouched.
|
| RefreshDatabase is required here: the dashboard reads the pulse_* tables, so
| the migration published by this slice has to run against
| financial_performance_test.
|
*/

uses(RefreshDatabase::class);

it('forbids the dashboard outside a local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->get('/pulse')->assertForbidden();
});

it('serves the dashboard on a local machine', function () {
    app()->instance('env', 'local');

    $this->get('/pulse')->assertOk();
});

it('resolves the viewPulse gate from the environment alone', function () {
    // No user is involved: the application has no authentication yet, so the
    // environment is the only door. The gate is reopened and bound to
    // SYSTEM_ADMIN in the authentication slice.
    expect(Gate::check('viewPulse'))->toBeFalse();

    app()->instance('env', 'local');

    expect(Gate::check('viewPulse'))->toBeTrue();
});

it('records nothing while PULSE_ENABLED is false', function () {
    app()->instance('env', 'local');

    $this->get('/pulse')->assertOk();

    Pulse::record('test', 'entry')->count();
    Pulse::ingest();

    expect(DB::table('pulse_entries')->count())->toBe(0)
        ->and(DB::table('pulse_values')->count())->toBe(0)
        ->and(DB::table('pulse_aggregates')->count())->toBe(0);
});
