<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::fallback(function (Request $request) {
    if ($request->is('api', 'api/*', 'storage', 'storage/*', 'build', 'build/*')) {
        return response()->json([
            'message' => 'Not found.',
        ], 404);
    }

    return view('app');
})->name('app');
