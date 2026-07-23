<?php

use Illuminate\Support\Facades\Gate;

it('forbids the documentation UI outside a local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->get('/docs/api')->assertForbidden();
});

it('forbids the JSON specification outside a local environment', function () {
    $this->get('/docs/api.json')->assertForbidden();
});

it('serves the documentation UI on a local machine', function () {
    app()->instance('env', 'local');

    $this->get('/docs/api')
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});

it('serves the JSON specification on a local machine', function () {
    app()->instance('env', 'local');

    $this->get('/docs/api.json')
        ->assertOk()
        ->assertHeader('content-type', 'application/json');
});

it('leaves the documentation closed to everyone when no gate allows it', function () {
    expect(Gate::allows('viewApiDocs'))->toBeFalse();
});
