<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Horizon dashboard access
|--------------------------------------------------------------------------
|
| The approved rule (FOUNDATION-005): /horizon is reachable on a local machine
| and forbidden everywhere else until authentication exists (DEC-035).
|
| Horizon resolves this as `Gate::check('viewHorizon') || environment('local')`,
| so both halves are covered here: the gate itself must deny, and the
| environment must be the only thing that opens the dashboard.
|
| The suite runs with APP_ENV=testing, which is already a non-local
| environment — the "forbidden" case needs no setup. The local case flips the
| container's env binding only; config('app.env') stays 'testing', so the
| test-database guard in tests/TestCase.php is untouched.
|
*/

it('forbids the dashboard outside a local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->get('/horizon')->assertForbidden();
});

it('forbids the horizon api outside a local environment', function () {
    $this->getJson('/horizon/api/stats')->assertForbidden();
});

it('serves the dashboard on a local machine', function () {
    app()->instance('env', 'local');

    $this->get('/horizon')->assertOk();
});

it('never authorizes anyone through the viewHorizon gate', function () {
    // No user can be allowed yet: the application has no authentication, so
    // the gate stays closed and the environment is the only door.
    expect(Gate::check('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(new User)->check('viewHorizon'))->toBeFalse();
});
