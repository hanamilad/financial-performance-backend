<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

/**
 * Bearer token support in the generated OpenAPI document (FOUNDATION-007).
 *
 * Scramble derives `security` from route middleware through
 * `MiddlewareAuthSecurityStrategy`, which is enabled in `config/scramble.php`.
 * That strategy only publishes the bearer scheme once at least one documented
 * route actually carries `auth` / `auth:*` middleware. No route does yet —
 * authentication is a slice of its own (DEC-035) — so without this provider the
 * document would contain no `securitySchemes` at all and the documentation UI
 * would offer nowhere to paste a token.
 *
 * This provider therefore publishes the scheme, and only the scheme: it is
 * added to `components.securitySchemes` and never attached to the document-wide
 * `security` list. `GET /api/v1/health` is public and stays public — it carries
 * no security requirement, so pasting a token never sends an Authorization
 * header to it.
 *
 * No token, secret or credential is stored anywhere in this repository; the
 * value is typed into the UI by hand and lives only in the browser tab.
 */
class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::configure()->withDocumentTransformers(
            fn (OpenApi $openApi) => $openApi->components->addSecurityScheme(
                self::bearerScheme()->schemeName,
                self::bearerScheme(),
            ),
        );
    }

    /**
     * The scheme published here is built exactly like the one
     * `MiddlewareAuthSecurityStrategy` uses by default, under the same name.
     * `components.securitySchemes` is keyed by name, so when the authentication
     * slice adds the first protected route the strategy overwrites this entry
     * with an identical one instead of adding a second, duplicated scheme.
     */
    private static function bearerScheme(): SecurityScheme
    {
        return SecurityScheme::http('bearer');
    }
}
