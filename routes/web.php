<?php

use App\Http\Controllers\Documents\InvoiceController;
use App\Http\Controllers\Documents\PawnReceiptController;
use App\Http\Controllers\Documents\PawnTicketController;
use App\Http\Controllers\Documents\ReceiptController;
use App\Http\Controllers\Documents\ReportPrintController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

Route::middleware(['auth:sanctum', 'permission:view pawns'])->group(function (): void {
    Route::get('/pawns/{pawn}/ticket', PawnTicketController::class)
        ->name('pawns.ticket');
    Route::get('/pawns/{pawn}/pdf', PawnTicketController::class)
        ->name('pawns.pdf');
    Route::get('/pawn-payments/{pawnPayment}/receipt', PawnReceiptController::class)
        ->name('pawn-payments.receipt');
    Route::get('/pawn-payments/{pawnPayment}/pdf', PawnReceiptController::class)
        ->name('pawn-payments.pdf');
});

Route::middleware(['auth:sanctum', 'permission:view reports'])->group(function (): void {
    Route::get('/reports/{report}/print', ReportPrintController::class)
        ->name('reports.print');
});

Route::get('/migrate-db', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        return response("<h2>Database Migrated & Permissions Synced Successfully!</h2><pre>{$output}</pre>");
    } catch (Throwable $e) {
        return response("<h2>Error during migration</h2><pre>{$e->getMessage()}</pre>", 500);
    }
});

Route::fallback(function (Request $request) {
    if ($request->is('api', 'api/*', 'storage', 'storage/*', 'build', 'build/*')) {
        return response()->json([
            'message' => 'Not found.',
        ], 404);
    }

    return view('app');
})->name('app');
