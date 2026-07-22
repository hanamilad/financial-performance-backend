<?php

/*
|--------------------------------------------------------------------------
| Authentication in the generated OpenAPI document (AUTH-001)
|--------------------------------------------------------------------------
|
| MiddlewareAuthSecurityStrategy (enabled since FOUNDATION-007) reacts to the
| first auth:sanctum route by publishing a document-wide bearer requirement and
| marking every public operation with an explicit empty `security` override.
| These tests pin that behaviour on the concrete auth routes: login stays
| public, /me and /logout become protected, and the readiness check stays
| public. No RefreshDatabase: the document is built from routes and source only.
|
*/

/**
 * @return array<string, mixed>
 */
function authApiDocument(): array
{
    app()->instance('env', 'local');

    return test()->get('/docs/api.json')->assertOk()->json();
}

it('applies a document-wide bearer requirement now that a protected route exists', function () {
    // The bearer scheme is the one published under the "http" name; the
    // document-level default is what protected operations inherit.
    expect(authApiDocument()['security'])->toBe([['http' => []]]);
});

it('documents system-admin login as public', function () {
    expect(authApiDocument()['paths']['/auth/login']['post']['security'])->toBe([]);
});

it('documents the identity and logout endpoints as protected', function () {
    $paths = authApiDocument()['paths'];

    // A protected operation carries no public `security: []` override, so it
    // inherits the document-wide bearer requirement asserted above.
    expect($paths['/auth/me']['get'])->not->toHaveKey('security')
        ->and($paths['/auth/logout']['post'])->not->toHaveKey('security');
});

it('keeps the readiness endpoint public alongside authentication', function () {
    expect(authApiDocument()['paths']['/health']['get']['security'])->toBe([]);
});
