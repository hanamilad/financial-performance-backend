<?php

function authApiDocument(): array
{
    app()->instance('env', 'local');

    return test()->get('/docs/api.json')->assertOk()->json();
}

it('applies a document-wide bearer requirement now that a protected route exists', function () {
    expect(authApiDocument()['security'])->toBe([['http' => []]]);
});

it('documents system-admin login as public', function () {
    expect(authApiDocument()['paths']['/auth/login']['post']['security'])->toBe([]);
});

it('documents the identity and logout endpoints as protected', function () {
    $paths = authApiDocument()['paths'];

    expect($paths['/auth/me']['get'])->not->toHaveKey('security')
        ->and($paths['/auth/logout']['post'])->not->toHaveKey('security');
});

it('keeps the readiness endpoint public alongside authentication', function () {
    expect(authApiDocument()['paths']['/health']['get']['security'])->toBe([]);
});

it('documents mobile login as public and mobile identity/logout as protected', function () {
    $paths = authApiDocument()['paths'];

    expect($paths['/auth/mobile/login']['post']['security'])->toBe([])
        ->and($paths['/auth/mobile/me']['get'])->not->toHaveKey('security')
        ->and($paths['/auth/mobile/logout']['post'])->not->toHaveKey('security');
});
