<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('api.v1.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])
            ->middleware('permission:access api')
            ->name('api.v1.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.logout');
    });
});
