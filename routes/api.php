<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GoldRateController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.logout');
    });

    Route::middleware(['auth:sanctum', 'permission:access api'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.v1.me');

        Route::get('/settings', [SettingsController::class, 'show'])
            ->middleware('permission:view settings')
            ->name('api.v1.settings.show');
        Route::match(['put', 'patch'], '/settings', [SettingsController::class, 'update'])
            ->middleware('permission:manage settings')
            ->name('api.v1.settings.update');

        Route::get('/gold-rates/latest', [GoldRateController::class, 'latest'])
            ->middleware('permission:view gold rates')
            ->name('api.v1.gold-rates.latest');
        Route::get('/gold-rates', [GoldRateController::class, 'index'])
            ->middleware('permission:view gold rates')
            ->name('api.v1.gold-rates.index');
        Route::post('/gold-rates', [GoldRateController::class, 'store'])
            ->middleware('permission:manage gold rates')
            ->name('api.v1.gold-rates.store');
        Route::get('/gold-rates/{goldRate}', [GoldRateController::class, 'show'])
            ->middleware('permission:view gold rates')
            ->name('api.v1.gold-rates.show');
        Route::match(['put', 'patch'], '/gold-rates/{goldRate}', [GoldRateController::class, 'update'])
            ->middleware('permission:manage gold rates')
            ->name('api.v1.gold-rates.update');
        Route::delete('/gold-rates/{goldRate}', [GoldRateController::class, 'destroy'])
            ->middleware('permission:manage gold rates')
            ->name('api.v1.gold-rates.destroy');
    });
});
