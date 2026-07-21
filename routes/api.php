<?php

use App\Http\Controllers\Api\V1\HealthController;
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
    });
