<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\GoldRateController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockController;
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

        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware('permission:view customers')
            ->name('api.v1.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])
            ->middleware('permission:manage customers')
            ->name('api.v1.customers.store');
        Route::get('/customers/{customer}/history', [CustomerController::class, 'history'])
            ->middleware('permission:view customers')
            ->name('api.v1.customers.history');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->middleware('permission:view customers')
            ->name('api.v1.customers.show');
        Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])
            ->middleware('permission:manage customers')
            ->name('api.v1.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
            ->middleware('permission:manage customers')
            ->name('api.v1.customers.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->middleware('permission:view inventory')
            ->name('api.v1.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.categories.store');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])
            ->middleware('permission:view inventory')
            ->name('api.v1.categories.show');
        Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.categories.destroy');

        Route::get('/items', [ItemController::class, 'index'])
            ->middleware('permission:view inventory')
            ->name('api.v1.items.index');
        Route::post('/items', [ItemController::class, 'store'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.items.store');
        Route::post('/items/{item}/stock-adjustments', [StockController::class, 'adjust'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.items.stock-adjustments.store');
        Route::get('/stock/summary', [StockController::class, 'summary'])
            ->middleware('permission:view inventory')
            ->name('api.v1.stock.summary');
        Route::get('/items/{item}', [ItemController::class, 'show'])
            ->middleware('permission:view inventory')
            ->name('api.v1.items.show');
        Route::match(['put', 'patch'], '/items/{item}', [ItemController::class, 'update'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.items.update');
        Route::delete('/items/{item}', [ItemController::class, 'destroy'])
            ->middleware('permission:manage inventory')
            ->name('api.v1.items.destroy');
    });
});
