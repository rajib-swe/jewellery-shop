<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashBookController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DailyClosingController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\GoldRateController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\PawnController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportExportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SupplierController;
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

        Route::get('/sales', [SaleController::class, 'index'])
            ->middleware('permission:view sales')
            ->name('api.v1.sales.index');
        Route::post('/sales', [SaleController::class, 'store'])
            ->middleware('permission:manage sales')
            ->name('api.v1.sales.store');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])
            ->middleware('permission:view sales')
            ->name('api.v1.sales.show');
        Route::post('/sales/{sale}/payments', [SaleController::class, 'addPayment'])
            ->middleware('permission:manage sales')
            ->name('api.v1.sales.payments.store');
        Route::post('/sales/{sale}/void', [SaleController::class, 'void'])
            ->middleware('permission:void sales')
            ->name('api.v1.sales.void');

        Route::get('/pawns', [PawnController::class, 'index'])
            ->middleware('permission:view pawns')
            ->name('api.v1.pawns.index');
        Route::post('/pawns', [PawnController::class, 'store'])
            ->middleware('permission:manage pawns')
            ->name('api.v1.pawns.store');
        Route::get('/pawns/{pawn}', [PawnController::class, 'show'])
            ->middleware('permission:view pawns')
            ->name('api.v1.pawns.show');
        Route::post('/pawns/{pawn}/payments', [PawnController::class, 'addPayment'])
            ->middleware('permission:manage pawns')
            ->name('api.v1.pawns.payments.store');
        Route::post('/pawns/{pawn}/redeem', [PawnController::class, 'redeem'])
            ->middleware('permission:manage pawns')
            ->name('api.v1.pawns.redeem');
        Route::post('/pawns/{pawn}/renew', [PawnController::class, 'renew'])
            ->middleware('permission:manage pawns')
            ->name('api.v1.pawns.renew');
        Route::post('/pawns/{pawn}/forfeit', [PawnController::class, 'forfeit'])
            ->middleware('permission:forfeit pawns')
            ->name('api.v1.pawns.forfeit');

        Route::get('/suppliers', [SupplierController::class, 'index'])
            ->middleware('permission:view suppliers')
            ->name('api.v1.suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])
            ->middleware('permission:manage suppliers')
            ->name('api.v1.suppliers.store');
        Route::get('/suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])
            ->middleware('permission:view suppliers')
            ->name('api.v1.suppliers.ledger');
        Route::post('/suppliers/{supplier}/payments', [SupplierController::class, 'addPayment'])
            ->middleware('permission:manage suppliers')
            ->name('api.v1.suppliers.payments.store');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
            ->middleware('permission:view suppliers')
            ->name('api.v1.suppliers.show');
        Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])
            ->middleware('permission:manage suppliers')
            ->name('api.v1.suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('permission:manage suppliers')
            ->name('api.v1.suppliers.destroy');

        Route::get('/purchases/rates', [PurchaseController::class, 'rates'])
            ->middleware('permission:view purchases')
            ->name('api.v1.purchases.rates');
        Route::get('/purchases', [PurchaseController::class, 'index'])
            ->middleware('permission:view purchases')
            ->name('api.v1.purchases.index');
        Route::post('/purchases', [PurchaseController::class, 'store'])
            ->middleware('permission:manage purchases')
            ->name('api.v1.purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])
            ->middleware('permission:view purchases')
            ->name('api.v1.purchases.show');

        Route::get('/reports/dashboard', [ReportController::class, 'dashboard'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.dashboard');
        Route::get('/reports/sales-trend', [ReportController::class, 'salesTrend'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.sales-trend');
        Route::get('/reports/sales', [ReportController::class, 'sales'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.sales');
        Route::get('/reports/stock', [ReportController::class, 'stock'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.stock');
        Route::get('/reports/pawn-outstanding', [ReportController::class, 'pawnOutstanding'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.pawn-outstanding');
        Route::get('/reports/overdue-pawns', [ReportController::class, 'overduePawns'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.overdue-pawns');
        Route::get('/reports/interest-earned', [ReportController::class, 'interestEarned'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.interest-earned');
        Route::get('/reports/customers/{customer}/ledger', [ReportController::class, 'customerLedger'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.customer-ledger');
        Route::get('/reports/suppliers/{supplier}/ledger', [ReportController::class, 'supplierLedger'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.supplier-ledger');
        Route::get('/reports/profit', [ReportController::class, 'profitSummary'])
            ->middleware('permission:view reports')
            ->name('api.v1.reports.profit');

        Route::get('/reports/{report}/export', ReportExportController::class)
            ->middleware('permission:view reports')
            ->name('api.v1.reports.export');

        Route::get('/expenses', [ExpenseController::class, 'index'])
            ->middleware('permission:view accounts')
            ->name('api.v1.expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])
            ->middleware('permission:manage accounts')
            ->name('api.v1.expenses.store');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])
            ->middleware('permission:view accounts')
            ->name('api.v1.expenses.show');
        Route::match(['put', 'patch'], '/expenses/{expense}', [ExpenseController::class, 'update'])
            ->middleware('permission:manage accounts')
            ->name('api.v1.expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
            ->middleware('permission:manage accounts')
            ->name('api.v1.expenses.destroy');

        Route::get('/cash-book', [CashBookController::class, 'index'])
            ->middleware('permission:view accounts')
            ->name('api.v1.cash-book.index');
        Route::get('/cash-book/summary', [CashBookController::class, 'summary'])
            ->middleware('permission:view accounts')
            ->name('api.v1.cash-book.summary');
        Route::post('/cash-book', [CashBookController::class, 'store'])
            ->middleware('permission:manage accounts')
            ->name('api.v1.cash-book.store');

        Route::get('/daily-closings', [DailyClosingController::class, 'index'])
            ->middleware('permission:view accounts')
            ->name('api.v1.daily-closings.index');
        Route::get('/daily-closings/show', [DailyClosingController::class, 'show'])
            ->middleware('permission:view accounts')
            ->name('api.v1.daily-closings.show');
        Route::post('/daily-closings', [DailyClosingController::class, 'store'])
            ->middleware('permission:close accounts')
            ->name('api.v1.daily-closings.store');
        Route::post('/daily-closings/{closing}/reopen', [DailyClosingController::class, 'reopen'])
            ->middleware('permission:close accounts')
            ->name('api.v1.daily-closings.reopen');
    });
});
