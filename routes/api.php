<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Modules\Clients\Http\Controllers\BranchController;
use App\Modules\Clients\Http\Controllers\ClientController;
use App\Modules\Clients\Http\Controllers\ClientUserController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MobileAuthController;
use App\Modules\Imports\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/health', HealthController::class)->name('health');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('/login', [AuthController::class, 'login'])
                    ->middleware('throttle:login')
                    ->name('login');

                Route::middleware('auth:sanctum')->group(function (): void {
                    Route::get('/me', [AuthController::class, 'me'])->name('me');
                    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                });

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

        Route::prefix('admin')
            ->name('admin.')
            ->middleware(['auth:sanctum', 'system_admin'])
            ->group(function (): void {
                Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
                Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
                Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
                Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');

                Route::post('/clients/{client}/branches', [BranchController::class, 'store'])->name('clients.branches.store');
                Route::patch('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

                Route::post('/clients/{client}/users', [ClientUserController::class, 'store'])->name('clients.users.store');
                Route::patch('/client-users/{clientUser}', [ClientUserController::class, 'update'])->name('client-users.update');

                Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
                Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
                Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');
                Route::post('/imports/{importBatch}/submit', [ImportController::class, 'submit'])->name('imports.submit');
                Route::post('/imports/{importBatch}/approve', [ImportController::class, 'approve'])->name('imports.approve');
                Route::post('/imports/{importBatch}/return-to-draft', [ImportController::class, 'returnToDraft'])->name('imports.return-to-draft');
                Route::post('/imports/{importBatch}/publish', [ImportController::class, 'publish'])->name('imports.publish');
                Route::delete('/imports/{importBatch}', [ImportController::class, 'destroy'])->name('imports.destroy');
            });
    });
