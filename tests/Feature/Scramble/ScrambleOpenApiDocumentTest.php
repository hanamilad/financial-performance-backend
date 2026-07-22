<?php

/*
|--------------------------------------------------------------------------
| Generated OpenAPI document
|--------------------------------------------------------------------------
|
| These tests read the document Scramble actually serves on /docs/api.json —
| not a fixture and not a snapshot of the whole file, which would break on
| every unrelated endpoint added later. They pin only the decisions taken in
| FOUNDATION-007:
|
|   - the document covers /api/v1 and nothing else;
|   - the version prefix lives in the server URL, so paths are never
|     duplicated into /api/api/v1;
|   - a bearer scheme is published, ready for the tokens the authentication
|     slice will issue;
|   - the readiness endpoint is public and must stay public.
|
| No RefreshDatabase: generating the document reads routes and source code,
| never a table.
|
*/

/**
 * Fetch the document Scramble serves, from a local environment.
 *
 * Test files share one global function namespace, so the name is qualified
 * rather than something as reusable as `openApiDocument`.
 *
 * @return array<string, mixed>
 */
function scrambleOpenApiDocument(): array
{
    app()->instance('env', 'local');

    return test()->get('/docs/api.json')->assertOk()->json();
}

it('generates a document instead of an error page', function () {
    $document = scrambleOpenApiDocument();

    expect($document)->toBeArray()
        ->and($document)->not->toHaveKey('message')
        ->and($document)->not->toHaveKey('exception');
});

it('declares the OpenAPI version Scramble generates', function () {
    expect(scrambleOpenApiDocument()['openapi'])->toBe('3.1.0');
});

it('names and versions the API', function () {
    expect(scrambleOpenApiDocument()['info'])
        ->title->toBe('Financial Performance Platform API')
        ->version->toBe('1.0.0');
});

it('serves every documented path from the /api/v1 base URL', function () {
    $servers = scrambleOpenApiDocument()['servers'];

    expect($servers)->toHaveCount(1)
        ->and($servers[0]['url'])->toEndWith('/api/v1');
});

it('documents the readiness endpoint', function () {
    expect(scrambleOpenApiDocument()['paths'])->toHaveKey('/health');
});

it('never repeats the prefix already carried by the server URL', function () {
    // Guards against the /api/api/v1 shape a second prefix would produce.
    foreach (array_keys(scrambleOpenApiDocument()['paths']) as $path) {
        expect($path)->not->toContain('api/v1')
            ->and($path)->not->toContain('/api/api');
    }
});

it('documents nothing outside /api/v1', function () {
    // /up is the liveness probe, /horizon and /pulse are dashboards, and
    // /docs/api* is this document itself — none of them are part of the API.
    $paths = array_keys(scrambleOpenApiDocument()['paths']);

    expect($paths)->not->toContain('/up', '/horizon', '/pulse', '/docs/api', '/docs/api.json');

    foreach ($paths as $path) {
        expect($path)->not->toStartWith('/horizon')
            ->and($path)->not->toStartWith('/pulse')
            ->and($path)->not->toStartWith('/docs');
    }
});

it('publishes a bearer security scheme', function () {
    // Registered by App\Providers\ScrambleServiceProvider so the scheme exists
    // before the first protected route does.
    $schemes = scrambleOpenApiDocument()['components']['securitySchemes'];

    expect($schemes)->toHaveCount(1)
        ->and(collect($schemes)->first())
        ->type->toBe('http')
        ->scheme->toBe('bearer');
});

it('keeps the readiness endpoint public', function () {
    $document = scrambleOpenApiDocument();

    // AUTH-001 added the first auth:sanctum route, so MiddlewareAuthSecurityStrategy
    // now publishes a document-wide bearer default and marks every unauthenticated
    // operation with an explicit empty `security` override. GET /api/v1/health must
    // carry that empty override, so no token is ever required of or sent to it
    // (DEC-039, DEC-043).
    expect($document['paths']['/health']['get']['security'])->toBe([]);
});
