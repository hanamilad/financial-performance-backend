<?php

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
    foreach (array_keys(scrambleOpenApiDocument()['paths']) as $path) {
        expect($path)->not->toContain('api/v1')
            ->and($path)->not->toContain('/api/api');
    }
});

it('documents nothing outside /api/v1', function () {
    $paths = array_keys(scrambleOpenApiDocument()['paths']);

    expect($paths)->not->toContain('/up', '/horizon', '/pulse', '/docs/api', '/docs/api.json');

    foreach ($paths as $path) {
        expect($path)->not->toStartWith('/horizon')
            ->and($path)->not->toStartWith('/pulse')
            ->and($path)->not->toStartWith('/docs');
    }
});

it('publishes a bearer security scheme', function () {
    $schemes = scrambleOpenApiDocument()['components']['securitySchemes'];

    expect($schemes)->toHaveCount(1)
        ->and(collect($schemes)->first())
        ->type->toBe('http')
        ->scheme->toBe('bearer');
});

it('keeps the readiness endpoint public', function () {
    $document = scrambleOpenApiDocument();

    expect($document['paths']['/health']['get']['security'])->toBe([]);
});
