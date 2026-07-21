<?php

use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| API documentation access
|--------------------------------------------------------------------------
|
| The approved rule (FOUNDATION-007) is the one Horizon and Pulse already
| follow: the documentation is reachable on a local machine and forbidden
| everywhere else until authentication exists (DEC-035).
|
| Scramble ships that rule itself. Its RestrictedDocsAccess middleware lets
| the request through in a local environment, otherwise falls back to the
| `viewApiDocs` gate, which nothing defines — so it denies. No gate, Basic
| Auth, custom middleware or secret is added by this project; these tests pin
| the built-in behaviour instead.
|
| The suite runs with APP_ENV=testing, which is already a non-local
| environment, so the "forbidden" case needs no setup. The local case flips
| the container's env binding only; config('app.env') stays 'testing', so the
| test-database guard in tests/TestCase.php is untouched.
|
| No RefreshDatabase: neither route reads a table.
|
*/

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
    // No user is involved: the application has no authentication yet, so the
    // environment is the only door. `viewApiDocs` is reopened and bound to
    // SYSTEM_ADMIN in the authentication slice.
    expect(Gate::allows('viewApiDocs'))->toBeFalse();
});
