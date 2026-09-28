<?php

namespace Tests\Feature;

use App\CashDirection;
use App\CashSourceType;
use App\ExpenseCategory;
use App\ItemStatus;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\GoldRate;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PawnService;
use App\Services\PurchaseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashBookApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin.password' => 'test-password-123']);

        $this->seed(DatabaseSeeder::class);

        $this->setGoldRate(22, '12800.00');
    }

    public function test_a_sale_payment_writes_an_in_cash_row(): void
    {
        $user = $this->userWithRole('admin');
        $sale = $this->recordSale($user, paid: '100000.00');
        $payment = $sale->payments()->firstOrFail();

        $row = CashTransaction::query()
            ->where('source_type', CashSourceType::SalePayment->value)
            ->where('source_id', (string) $payment->id)
            ->firstOrFail();

        $this->assertSame(CashDirection::In, $row->direction);
        $this->assertSame('100000.00', (string) $row->amount);
        $this->assertStringContainsString($sale->invoice_no, (string) $row->note);
    }

    public function test_a_due_payment_also_writes_a_cash_row(): void
    {
        $user = $this->userWithRole('admin');
        $sale = $this->recordSale($user, paid: '50000.00');

        $this->actingAs($user)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'amount' => '30000.00',
                'method' => 'bkash',
            ])
            ->assertOk();

        $this->assertSame(2, CashTransaction::query()
            ->where('source_type', CashSourceType::SalePayment->value)
            ->count());
    }

    public function test_voiding_a_sale_removes_its_cash_rows(): void
    {
        $user = $this->userWithRole('admin');
        $sale = $this->recordSale($user, paid: '100000.00');

        $this->assertSame(1, CashTransaction::query()
            ->where('source_type', CashSourceType::SalePayment->value)
            ->count());

        $this->actingAs($user)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'গ্রাহক অজুহাত করেছেন'])
            ->assertOk();

        // The customer handed the money back, so the drawer must be as it was.
        $this->assertSame(0, CashTransaction::query()
            ->where('source_type', CashSourceType::SalePayment->value)
            ->count());
    }

    public function test_a_pawn_books_the_disbursement_out_and_collections_in(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create();

        $pawn = app(PawnService::class)->create([
            'customer_id' => $customer->id,
            'date' => today()->toDateString(),
            'principal' => '50000.00',
            'interest_rate' => '3.00',
            'items' => [[
                'description' => 'স্বর্ণের চুড়ি',
                'karat' => 22,
                'gross_weight' => '6.000',
                'estimated_value' => '80000.00',
            ]],
        ], $user);

        $disbursement = CashTransaction::query()
            ->where('source_type', CashSourceType::PawnDisbursement->value)
            ->where('source_id', (string) $pawn->id)
            ->firstOrFail();

        $this->assertSame(CashDirection::Out, $disbursement->direction);
        $this->assertSame('50000.00', (string) $disbursement->amount);

        app(PawnService::class)->addPayment($pawn, [
            'amount' => '5000.00',
            'date' => today()->toDateString(),
        ], $user);

        $this->assertSame(1, CashTransaction::query()
            ->where('source_type', CashSourceType::PawnPayment->value)
            ->where('direction', CashDirection::In->value)
            ->count());
    }

    public function test_a_supplier_payment_is_booked_out(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = Supplier::factory()->create(['type' => 'supplier']);

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'items' => [[
                'name' => 'স্বর্ণের চুড়ি',
                'category_id' => Category::query()->value('id'),
                'karat' => 22,
                'gross_weight' => '10.000',
            ]],
        ], $user);

        app(PurchaseService::class)->addPayment($supplier, [
            'amount' => '50000.00',
            'date' => today()->toDateString(),
        ], $user);

        $row = CashTransaction::query()
            ->where('source_type', CashSourceType::SupplierPayment->value)
            ->firstOrFail();

        $this->assertSame(CashDirection::Out, $row->direction);
        $this->assertSame('50000.00', (string) $row->amount);
        $this->assertNotNull($purchase);
    }

    public function test_recording_an_expense_writes_an_out_cash_row(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'rent',
            'title' => 'মাসিক ভাড়া',
            'amount' => '20000.00',
        ]);

        $response->assertCreated();
        $expenseId = $response->json('data.id');

        $row = CashTransaction::query()
            ->where('source_type', CashSourceType::Expense->value)
            ->where('source_id', (string) $expenseId)
            ->firstOrFail();

        $this->assertSame(CashDirection::Out, $row->direction);
        $this->assertSame('20000.00', (string) $row->amount);
        $this->assertSame('মাসিক ভাড়া', $row->note);
    }

    public function test_updating_an_expense_replaces_its_cash_row(): void
    {
        $user = $this->userWithRole('admin');

        $expenseId = $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'other',
            'title' => 'পরিবহন',
            'amount' => '1000.00',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user)
            ->putJson("/api/v1/expenses/{$expenseId}", ['amount' => '1500.00'])
            ->assertOk()
            ->assertJsonPath('data.amount', '1500.00');

        // One row, carrying the corrected amount, not two rows.
        $rows = CashTransaction::query()
            ->where('source_type', CashSourceType::Expense->value)
            ->where('source_id', (string) $expenseId)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('1500.00', (string) $rows->first()->amount);
    }

    public function test_deleting_an_expense_removes_its_cash_row(): void
    {
        $user = $this->userWithRole('admin');

        $expenseId = $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'other',
            'title' => 'ভুল করে যোগ হয়েছিল',
            'amount' => '900.00',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user)->deleteJson("/api/v1/expenses/{$expenseId}")->assertNoContent();

        $this->assertSame(0, CashTransaction::query()
            ->where('source_type', CashSourceType::Expense->value)
            ->where('source_id', (string) $expenseId)
            ->count());
        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_an_expense_requires_a_known_category_and_a_positive_amount(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'yacht',
            'title' => 'অজানা',
            'amount' => '100.00',
        ])->assertJsonValidationErrors('category');

        $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'other',
            'title' => 'শূন্য',
            'amount' => '0.00',
        ])->assertJsonValidationErrors('amount');

        $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'other',
            'title' => '',
            'amount' => '100.00',
        ])->assertJsonValidationErrors('title');
    }

    public function test_the_day_summary_reconciles_with_opening_plus_in_minus_out(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '12000.00',
            'date' => $day,
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'out',
            'amount' => '4000.00',
            'date' => $day,
        ])->assertCreated();

        $summary = $this->actingAs($user)
            ->getJson("/api/v1/cash-book/summary?date={$day}")
            ->assertOk()
            ->json('data');

        $opening = (float) $summary['opening_balance'];
        $in = (float) $summary['total_in'];
        $out = (float) $summary['total_out'];
        $closing = (float) $summary['closing_balance'];

        $this->assertSame(12000.00, $in);
        $this->assertSame(4000.00, $out);
        $this->assertEqualsWithDelta(
            $opening + $in - $out,
            $closing,
            0.01,
            'The closing balance must be the opening balance plus money in, less money out.',
        );
    }

    public function test_the_day_summary_splits_by_method(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '3000.00',
            'date' => $day,
            'method' => 'cash',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '7000.00',
            'date' => $day,
            'method' => 'bkash',
        ])->assertCreated();

        $byMethod = collect($this->actingAs($user)
            ->getJson("/api/v1/cash-book/summary?date={$day}")
            ->assertOk()
            ->json('data.by_method'))
            ->keyBy('method');

        $this->assertSame('3000.00', $byMethod['cash']['net']);
        $this->assertSame('7000.00', $byMethod['bkash']['net']);
    }

    public function test_a_manual_cash_entry_must_say_which_way_the_money_moves(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/cash-book', ['amount' => '100.00'])
            ->assertJsonValidationErrors('direction');

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'sideways',
            'amount' => '100.00',
        ])->assertJsonValidationErrors('direction');
    }

    public function test_a_manual_cash_entry_is_kept_as_an_adjustment_with_no_source(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'out',
            'amount' => '5000.00',
            'note' => 'ব্যাংক থেকে টাকা উত্তোলন',
        ])->assertCreated()->assertJsonPath('data.source_type', 'cash_adjustment');

        $row = CashTransaction::query()
            ->where('source_type', CashSourceType::CashAdjustment->value)
            ->firstOrFail();

        $this->assertNull($row->source_id);
    }

    public function test_closing_a_day_stores_the_figures_and_locks_it(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '10000.00',
            'date' => $day,
        ])->assertCreated();

        $closing = $this->actingAs($user)
            ->postJson('/api/v1/daily-closings', ['date' => $day])
            ->assertCreated()
            ->json('data');

        $this->assertTrue($closing['is_locked']);
        $this->assertSame('10000.00', $closing['total_in']);
        $this->assertNotNull($closing['closed_by']);

        $this->assertDatabaseHas('daily_closings', [
            'date' => $day,
            'closing_balance' => $closing['closing_balance'],
        ]);
    }

    public function test_a_locked_day_refuses_further_money(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();

        $this->actingAs($user)->postJson('/api/v1/daily-closings', ['date' => $day])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '100.00',
            'date' => $day,
        ])->assertJsonValidationErrors('date');

        // An expense is money leaving the shop too, so it is locked out as well.
        $this->actingAs($user)->postJson('/api/v1/expenses', [
            'category' => 'other',
            'title' => 'বন্ধ দিনে খরচ',
            'amount' => '100.00',
            'date' => $day,
        ])->assertJsonValidationErrors('date');
    }

    public function test_a_day_cannot_be_closed_twice(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();

        $this->actingAs($user)->postJson('/api/v1/daily-closings', ['date' => $day])->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/v1/daily-closings', ['date' => $day])
            ->assertJsonValidationErrors('date');

        $this->assertSame(1, DailyClosing::query()->whereDate('date', $day)->count());
    }

    public function test_a_manager_can_reopen_a_closed_day_and_the_lock_lifts(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();

        $closingId = $this->actingAs($user)
            ->postJson('/api/v1/daily-closings', ['date' => $day])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/daily-closings/{$closingId}/reopen")
            ->assertOk()
            ->assertJsonPath('data.is_locked', false);

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '250.00',
            'date' => $day,
        ])->assertCreated();
    }

    public function test_a_future_day_cannot_be_closed(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->postJson('/api/v1/daily-closings', ['date' => today()->addDay()->toDateString()])
            ->assertJsonValidationErrors('date');
    }

    public function test_the_cash_book_filters_by_direction_method_and_date(): void
    {
        $user = $this->userWithRole('admin');
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '100.00',
            'date' => $today,
            'method' => 'cash',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/cash-book', [
            'direction' => 'out',
            'amount' => '200.00',
            'date' => $yesterday,
            'method' => 'bank',
        ])->assertCreated();

        $this->assertCount(1, $this->actingAs($user)
            ->getJson('/api/v1/cash-book?direction=in')->assertOk()->json('data'));

        $this->assertCount(1, $this->actingAs($user)
            ->getJson("/api/v1/cash-book?date_from={$today}")->assertOk()->json('data'));

        $this->assertCount(1, $this->actingAs($user)
            ->getJson('/api/v1/cash-book?method=bank')->assertOk()->json('data'));

        $this->assertCount(2, $this->actingAs($user)
            ->getJson('/api/v1/cash-book')->assertOk()->json('data'));
    }

    public function test_a_cashier_can_read_the_cash_book_but_not_post_to_it(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->getJson('/api/v1/cash-book')->assertOk();
        $this->actingAs($cashier)->getJson('/api/v1/cash-book/summary')->assertOk();
        $this->actingAs($cashier)->getJson('/api/v1/expenses')->assertOk();
        $this->actingAs($cashier)->getJson('/api/v1/daily-closings')->assertOk();

        $this->actingAs($cashier)->postJson('/api/v1/cash-book', [
            'direction' => 'in',
            'amount' => '100.00',
        ])->assertForbidden();

        $this->actingAs($cashier)->postJson('/api/v1/expenses', [
            'category' => 'other',
            'title' => 'খরচ',
            'amount' => '100.00',
        ])->assertForbidden();

        $this->actingAs($cashier)->postJson('/api/v1/daily-closings', [
            'date' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_the_expense_service_refuses_a_non_positive_amount(): void
    {
        $this->expectException(ValidationException::class);

        app(ExpenseService::class)->create([
            'category' => ExpenseCategory::Other->value,
            'title' => 'শূন্য',
            'amount' => '0.00',
        ], $this->userWithRole('admin'));
    }

    public function test_voiding_a_sale_returns_the_money_and_leaves_no_phantom_sale_row(): void
    {
        $user = $this->userWithRole('admin');
        $day = today()->toDateString();
        $sale = $this->recordSale($user, paid: '25000.00');

        $before = (float) $this->actingAs($user)
            ->getJson("/api/v1/cash-book/summary?date={$day}")
            ->json('data.closing_balance');

        $this->actingAs($user)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'রদ'])
            ->assertOk();

        $after = (float) $this->actingAs($user)
            ->getJson("/api/v1/cash-book/summary?date={$day}")
            ->json('data.closing_balance');

        // The customer handed the money back, so the drawer is back where it
        // started rather than still counting a sale that no longer exists.
        $this->assertEqualsWithDelta($before - 25000.00, $after, 0.01);

        $this->assertSame(0, CashTransaction::query()
            ->where('source_type', CashSourceType::SalePayment->value)
            ->count());
    }

    private function setGoldRate(int $karat, string $rate): void
    {
        GoldRate::query()->updateOrCreate(
            ['karat' => $karat, 'effective_date' => today()->toDateString()],
            ['rate_per_gram' => $rate, 'created_by' => User::role('admin')->value('id')],
        );
    }

    private function recordSale(User $user, string $paid = '0.00'): Sale
    {
        $item = Item::factory()
            ->for(Category::query()->firstOrFail(), 'category')
            ->create([
                'status' => ItemStatus::InStock,
                'name' => 'স্বর্ণের চুড়ি',
                'karat' => 22,
                'gross_weight' => '10.000',
                'stone_weight' => '0.000',
                'net_weight' => '10.000',
                'making_type' => 'fixed',
                'making_value' => '0.00',
                'stone_price' => '0.00',
            ]);

        $response = $this->actingAs($user)->postJson('/api/v1/sales', [
            'items' => [['item_id' => $item->id]],
            'payments' => $paid === '0.00'
                ? []
                : [['method' => 'cash', 'amount' => $paid]],
        ]);

        $response->assertCreated();

        return Sale::query()->findOrFail($response->json('data.id'));
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create()->assignRole($roleName);
    }
}
