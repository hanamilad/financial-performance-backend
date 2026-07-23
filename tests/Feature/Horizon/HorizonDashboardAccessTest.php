<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

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
    expect(Gate::check('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(new User)->check('viewHorizon'))->toBeFalse();
});
