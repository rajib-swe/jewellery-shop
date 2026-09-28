<?php

use App\Http\Controllers\Documents\InvoiceController;
use App\Http\Controllers\Documents\ReceiptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('app'))->name('login');

Route::middleware(['auth:sanctum', 'permission:view sales'])->group(function (): void {
    Route::get('/sales/{sale}/invoice', InvoiceController::class)
        ->name('sales.invoice');
    Route::get('/sales/{sale}/pdf', InvoiceController::class)
        ->name('sales.pdf');
    Route::get('/payments/{salePayment}/receipt', ReceiptController::class)
        ->name('payments.receipt');
    Route::get('/payments/{salePayment}/pdf', ReceiptController::class)
        ->name('payments.pdf');
});

Route::fallback(function (Request $request) {
    if ($request->is('api', 'api/*', 'storage', 'storage/*', 'build', 'build/*')) {
        return response()->json([
            'message' => 'Not found.',
        ], 404);
    }

    return view('app');
})->name('app');
