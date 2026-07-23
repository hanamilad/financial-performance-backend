<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Facades\Pulse;

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
