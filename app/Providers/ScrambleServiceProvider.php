<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

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

    private static function bearerScheme(): SecurityScheme
    {
        return SecurityScheme::http('bearer');
    }
}
