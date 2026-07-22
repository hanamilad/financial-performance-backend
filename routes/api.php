<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MobileAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file is registered manually through withRouting(api: ...) in
| bootstrap/app.php, which already applies the "api" prefix. Only the version
| segment is added here, so routes resolve as /api/v1/... and never
| /api/api/v1/... (FOUNDATION-004).
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/health', HealthController::class)->name('health');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                // Public: system-admin cookie login, throttled per email+IP.
                Route::post('/login', [AuthController::class, 'login'])
                    ->middleware('throttle:login')
                    ->name('login');

                // Protected: resolved from the Sanctum stateful session. The
                // auth:sanctum middleware is also what makes Scramble document
                // these two operations as requiring authentication.
                Route::middleware('auth:sanctum')->group(function (): void {
                    Route::get('/me', [AuthController::class, 'me'])->name('me');
                    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                });

                // Mobile client_user authentication via Sanctum bearer tokens,
                // one token per device (AUTH-002).
                Route::prefix('mobile')
                    ->name('mobile.')
                    ->group(function (): void {
                        Route::post('/login', [MobileAuthController::class, 'login'])
                            ->middleware('throttle:mobile-login')
                            ->name('login');

                        Route::middleware('auth:sanctum')->group(function (): void {
                            Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
                            Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');
                        });
                    });
            });
    });
