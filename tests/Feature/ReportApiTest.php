<?php

namespace Tests\Feature;

use App\CashSourceType;
use App\ItemStatus;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\GoldRate;
use App\Models\Item;
use App\Models\Pawn;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PawnInterestService;
use App\Services\PawnService;
use App\Services\PurchaseService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin.password' => 'test-password-123']);

        $this->seed(DatabaseSeeder::class);

        $this->setGoldRate(22, '12800.00');
    }

    public function test_the_sales_report_totals_only_completed_sales(): void
    {
        $user = $this->userWithRole('admin');
        $sale = $this->recordSale($user, paid: '100000.00');

        $this->actingAs($user)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'রদ'])
            ->assertOk();

        $fresh = $this->recordSale($user, paid: '50000.00');

        $report = $this->actingAs($user)
            ->getJson('/api/v1/reports/sales')
            ->assertOk()
            ->json('data');

        // The voided invoice is an audit record, not revenue.
        $this->assertSame(1, $report['invoice_count']);
        $this->assertSame('128000.00', $report['total_sales']);
        $this->assertNotNull($fresh);
    }

    public function test_the_sales_report_groups_by_day_and_by_month(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordSale($user, paid: '10000.00', date: today()->subDays(40));
        $from = today()->subDays(60)->toDateString();

        $byDay = $this->actingAs($user)
            ->getJson("/api/v1/reports/sales?from={$from}")
            ->assertOk()
            ->json('data');
        $this->assertSame('day', $byDay['group_by']);
        $this->assertCount(1, $byDay['rows']);

        $byMonth = $this->actingAs($user)
            ->getJson("/api/v1/reports/sales?from={$from}&group_by=month")
            ->assertOk()
            ->json('data');

        $this->assertSame('month', $byMonth['group_by']);
        $this->assertCount(1, $byMonth['rows']);
        // A month bucket is Y-m, a day bucket is Y-m-d.
        $this->assertSame(7, strlen($byMonth['rows'][0]['period']));
        $this->assertSame(10, strlen($byDay['rows'][0]['period']));
    }

    public function test_the_sales_report_honours_the_date_range(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordSale($user, paid: '10000.00', date: today()->subDays(40));

        $report = $this->actingAs($user)
            ->getJson('/api/v1/reports/sales')
            ->assertOk()
            ->json('data');

        // The default window is 30 days, so the older sale is outside it.
        $this->assertSame(0, $report['invoice_count']);

        $wider = $this->actingAs($user)
            ->getJson('/api/v1/reports/sales?from='.today()->subDays(60)->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $wider['invoice_count']);
    }

    public function test_the_stock_report_matches_the_in_stock_weight(): void
    {
        $user = $this->userWithRole('admin');
        $item = $this->inStockItem();
        $before = $this->inStockWeight();

        $this->actingAs($user)->postJson('/api/v1/sales', [
            'items' => [['item_id' => $item->id]],
            'payments' => [['method' => 'cash', 'amount' => '10000.00']],
        ])->assertCreated();

        $report = $this->actingAs($user)->getJson('/api/v1/reports/stock')->assertOk()->json('data');

        $this->assertEqualsWithDelta(
            $before - 10.0,
            (float) $report['total_weight'],
            0.001,
            'Selling an item must lower the reported stock weight.',
        );
    }

    public function test_the_stock_report_values_stock_at_the_latest_gold_rate(): void
    {
        $user = $this->userWithRole('admin');
        $this->inStockItem();

        $report = $this->actingAs($user)->getJson('/api/v1/reports/stock')->assertOk()->json('data');

        $karat22 = collect($report['by_karat'])->firstWhere('karat', 22);
        $weight = (float) $karat22['total_weight'];

        $this->assertGreaterThan(0, $weight);
        $this->assertEqualsWithDelta(
            $weight * 12800.00,
            (float) $karat22['total_value'],
            1.00,
            'Stock value is the net weight priced at the karat rate.',
        );
    }

    public function test_the_pawn_outstanding_report_uses_the_interest_engine(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create();
        $this->recordPawn($user, $customer, '50000.00', '3.00');

        $report = $this->actingAs($user)
            ->getJson('/api/v1/reports/pawn-outstanding')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $report['active_count']);
        $this->assertSame('50000.00', $report['total_principal']);
        $this->assertSame('50000.00', $report['outstanding_principal']);
        $this->assertCount(1, $report['rows']);
    }

    public function test_the_overdue_report_only_lists_pawns_past_their_due_date(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create();

        $this->recordPawn($user, $customer, '50000.00', '3.00', today()->subDays(60));
        $this->recordPawn($user, $customer, '30000.00', '3.00', today()->subDays(5));

        $outstanding = $this->actingAs($user)
            ->getJson('/api/v1/reports/pawn-outstanding')
            ->assertOk()
            ->json('data');

        $overdue = $this->actingAs($user)
            ->getJson('/api/v1/reports/overdue-pawns')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $outstanding['active_count']);
        $this->assertSame(1, $outstanding['overdue_count']);
        $this->assertSame(1, $overdue['count']);
        $this->assertTrue($overdue['rows'][0]['is_overdue']);
        $this->assertGreaterThan(0, $overdue['rows'][0]['days_overdue']);
    }

    public function test_a_redeemed_pawn_leaves_the_outstanding_report(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create();
        $pawn = $this->recordPawn($user, $customer, '50000.00', '3.00');

        $payable = app(PawnInterestService::class)->calculate($pawn, today())['total_payable'];

        app(PawnService::class)->redeem($pawn, ['amount' => $payable], $user);

        $this->actingAs($user)
            ->getJson('/api/v1/reports/pawn-outstanding')
            ->assertOk()
            ->assertJsonPath('data.active_count', 0);

        $this->assertNotNull($payable);
    }

    public function test_the_interest_report_separates_collected_from_accrued(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create();
        // Backdated a month so a full month's interest has accrued.
        $pawn = $this->recordPawn($user, $customer, '100000.00', '3.00', today()->subDays(30));

        app(PawnService::class)->addPayment($pawn, [
            'amount' => '5000.00',
            'date' => today()->toDateString(),
        ], $user);

        $report = $this->actingAs($user)
            ->getJson('/api/v1/reports/interest-earned?from='.today()->subDays(30)->toDateString())
            ->assertOk()
            ->json('data');

        // 3% of 100000 for a month is 3000, so 5000 settles 3000 interest and
        // 2000 of principal. The month's interest is then fully collected, so
        // nothing is left accrued on this account.
        $this->assertSame('3000.00', $report['interest_collected']);
        $this->assertSame(1, $report['interest_collection_count']);
        $this->assertSame('2000.00', $report['principal_collected']);
        $this->assertSame('0.00', $report['interest_accrued_outstanding']);
    }

    public function test_the_customer_ledger_balance_is_sales_minus_payments(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create(['opening_balance' => '1000.00']);
        $sale = $this->recordSale($user, paid: '50000.00', customer: $customer);

        $this->actingAs($user)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'amount' => '20000.00',
                'method' => 'cash',
            ])
            ->assertOk();

        $ledger = $this->actingAs($user)
            ->getJson("/api/v1/reports/customers/{$customer->id}/ledger")
            ->assertOk()
            ->json('data');

        $this->assertSame('128000.00', $ledger['total_sales']);
        $this->assertSame('70000.00', $ledger['total_paid']);
        $this->assertSame(
            '59000.00',
            $ledger['closing_balance'],
            'Opening 1000 plus 128000 sold less 70000 collected.',
        );
        // One sale debit plus the two collection credits.
        $this->assertCount(3, $ledger['transactions']);
    }

    public function test_the_supplier_ledger_reuses_the_purchases_minus_payments_rule(): void
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
            'amount' => '28000.00',
            'date' => today()->toDateString(),
        ], $user);

        $ledger = $this->actingAs($user)
            ->getJson("/api/v1/reports/suppliers/{$supplier->id}/ledger")
            ->assertOk()
            ->json('data');

        $this->assertSame('128000.00', $ledger['total_purchases']);
        $this->assertSame('28000.00', $ledger['total_payments']);
        $this->assertSame('100000.00', $ledger['balance']);
        $this->assertNotNull($purchase);
    }

    public function test_the_profit_summary_reconciles_with_the_cash_book(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordSale($user, paid: '128000.00');
        $this->recordExpense($user, '10000.00');

        $report = $this->actingAs($user)
            ->getJson('/api/v1/reports/profit')
            ->assertOk()
            ->json('data');

        $this->assertSame('128000.00', $report['sales_total']);
        $this->assertSame('10000.00', $report['expenses']);
        $this->assertSame('118000.00', $report['gross_profit']);

        // The expense left the drawer, so the cash book agrees with the report.
        $this->assertSame('10000.00', $report['cash_out']);
        $this->assertSame(1, CashTransaction::query()
            ->where('source_type', CashSourceType::Expense->value)
            ->count());
    }

    public function test_the_dashboard_reports_todays_figures_and_a_full_trend(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordSale($user, paid: '128000.00');

        $dashboard = $this->actingAs($user)
            ->getJson('/api/v1/reports/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertSame('128000.00', $dashboard['today']['sales_total']);
        $this->assertSame(1, $dashboard['today']['invoice_count']);
        $this->assertCount(30, $dashboard['sales_trend']);
        $this->assertSame(today()->toDateString(), end($dashboard['sales_trend'])['date']);
        $this->assertSame('128000.00', end($dashboard['sales_trend'])['total']);
    }

    public function test_the_sales_trend_keeps_every_day_in_the_window(): void
    {
        $user = $this->userWithRole('admin');

        $rows = $this->actingAs($user)
            ->getJson('/api/v1/reports/sales-trend?days=7')
            ->assertOk()
            ->json('data.rows');

        // Quiet days are present with a zero, so the chart is a continuous line.
        $this->assertCount(7, $rows);
        $this->assertSame('0.00', $rows[0]['total']);
    }

    public function test_an_unknown_report_is_rejected(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->getJson('/api/v1/reports/sales?from=not-a-date')
            ->assertJsonValidationErrors('from');

        $this->actingAs($user)
            ->getJson('/api/v1/reports/sales?group_by=decade')
            ->assertJsonValidationErrors('group_by');

        $this->assertNotNull($user);
    }

    public function test_a_reversed_date_range_is_corrected_rather_than_rejected(): void
    {
        $user = $this->userWithRole('admin');
        $today = today()->toDateString();
        $earlier = today()->subDays(10)->toDateString();

        $report = $this->actingAs($user)
            ->getJson("/api/v1/reports/sales?from={$today}&to={$earlier}")
            ->assertOk()
            ->json('data');

        // The two bounds are swapped, so they are corrected: from becomes the
        // earlier date and to the later one.
        $this->assertSame($earlier, $report['from']);
        $this->assertSame($today, $report['to']);
    }

    public function test_every_report_can_be_exported_as_a_spreadsheet(): void
    {
        $user = $this->userWithRole('admin');
        $customer = Customer::factory()->create();
        $supplier = Supplier::factory()->create(['type' => 'supplier']);
        $this->recordSale($user, paid: '10000.00', customer: $customer);

        $reports = [
            'sales' => '/api/v1/reports/sales/export',
            'stock' => '/api/v1/reports/stock/export',
            'pawn-outstanding' => '/api/v1/reports/pawn-outstanding/export',
            'overdue-pawns' => '/api/v1/reports/overdue-pawns/export',
            'interest-earned' => '/api/v1/reports/interest-earned/export',
            'profit' => '/api/v1/reports/profit/export',
            'customer-ledger' => "/api/v1/reports/customer-ledger/export?customer={$customer->id}",
            'supplier-ledger' => "/api/v1/reports/supplier-ledger/export?supplier={$supplier->id}",
        ];

        foreach ($reports as $name => $url) {
            $response = $this->actingAs($user)->get($url);

            $response->assertOk();
            $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse, $name);
        }
    }

    public function test_an_unknown_report_export_is_a_404(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->get('/api/v1/reports/nonsense/export')
            ->assertNotFound();
    }

    public function test_a_report_prints_to_pdf(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordSale($user, paid: '100000.00');

        $response = $this->actingAs($user)->get('/reports/sales/print');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $body = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString('%%EOF', $body);
    }

    public function test_a_report_prints_in_the_browser_with_bangla_headings(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordSale($user, paid: '100000.00');

        $html = $this->actingAs($user)
            ->get('/reports/sales/print?print=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('বিক্রয়ের হিসাব', $html);
        $this->assertStringContainsString('মোট বিক্রয়', $html);
        $this->assertStringContainsString('128,000.00', $html);
    }

    public function test_an_unknown_report_print_is_a_404(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->get('/reports/nonsense/print')->assertNotFound();
    }

    public function test_reports_require_the_view_reports_permission(): void
    {
        $this->actingAs($this->userWithoutPermissions())->getJson('/api/v1/reports/sales')->assertForbidden();
        $this->actingAs($this->userWithoutPermissions())->get('/reports/sales/print')->assertForbidden();
    }

    public function test_a_cashier_can_read_reports(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->getJson('/api/v1/reports/sales')->assertOk();
        $this->actingAs($cashier)->getJson('/api/v1/reports/dashboard')->assertOk();
        $this->actingAs($cashier)->getJson('/api/v1/reports/profit')->assertOk();
    }

    public function test_the_stock_report_ignores_items_that_are_not_in_stock(): void
    {
        $user = $this->userWithRole('admin');
        $this->inStockItem('5.000');
        $before = $this->inStockWeight();

        Item::factory()
            ->for(Category::query()->firstOrFail(), 'category')
            ->create([
                'status' => ItemStatus::Sold,
                'karat' => 22,
                'gross_weight' => '50.000',
                'stone_weight' => '0.000',
                'net_weight' => '50.000',
            ]);

        $report = $this->actingAs($user)->getJson('/api/v1/reports/stock')->assertOk()->json('data');

        $this->assertEqualsWithDelta($before, (float) $report['total_weight'], 0.001);
    }

    public function test_the_cash_book_and_the_profit_report_agree_on_cash_out(): void
    {
        $user = $this->userWithRole('admin');
        $this->recordExpense($user, '4500.00');

        $profit = $this->actingAs($user)->getJson('/api/v1/reports/profit')->json('data');
        $cashBook = $this->actingAs($user)
            ->getJson('/api/v1/cash-book/summary?date='.today()->toDateString())
            ->json('data');

        $this->assertSame($cashBook['total_out'], $profit['cash_out']);
        $this->assertSame(1, CashTransaction::query()
            ->where('source_type', CashSourceType::Expense->value)
            ->count());
    }

    private function inStockWeight(): float
    {
        return (float) Item::query()
            ->where('status', ItemStatus::InStock->value)
            ->sum('net_weight');
    }

    /**
     * The seeded demo inventory is nearly all sold, so a report test that needs
     * stock on the shelf adds its own.
     */
    private function inStockItem(string $weight = '10.000'): Item
    {
        return Item::factory()
            ->for(Category::query()->firstOrFail(), 'category')
            ->create([
                'status' => ItemStatus::InStock,
                'karat' => 22,
                'gross_weight' => $weight,
                'stone_weight' => '0.000',
                'net_weight' => $weight,
            ]);
    }

    private function recordExpense(User $user, string $amount): Expense
    {
        return app(ExpenseService::class)->create([
            'category' => 'other',
            'title' => 'পরীক্ষামূলক খরচ',
            'amount' => $amount,
            'date' => today()->toDateString(),
        ], $user);
    }

    private function recordPawn(
        User $user,
        Customer $customer,
        string $principal,
        string $rate,
        ?string $date = null,
    ): Pawn {
        return app(PawnService::class)->create([
            'customer_id' => $customer->id,
            'date' => $date ?? today()->toDateString(),
            'principal' => $principal,
            'interest_rate' => $rate,
            'items' => [[
                'description' => 'স্বর্ণের চুড়ি',
                'karat' => 22,
                'gross_weight' => '10.000',
                'estimated_value' => '200000.00',
            ]],
        ], $user);
    }

    private function recordSale(
        User $user,
        string $paid = '0.00',
        ?Customer $customer = null,
        ?string $date = null,
    ): Sale {
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

        $payload = [
            'customer_id' => $customer?->id,
            'items' => [['item_id' => $item->id]],
            'payments' => $paid === '0.00' ? [] : [['method' => 'cash', 'amount' => $paid]],
        ];

        if ($date !== null) {
            $payload['date'] = CarbonImmutable::parse($date)->toDateString();

            // A backdated sale is priced on the rate effective that day, so the
            // report tests have to configure one, exactly as the shop would.
            $this->setGoldRate(22, '12500.00', $payload['date']);
        }

        $response = $this->actingAs($user)->postJson('/api/v1/sales', $payload);

        $response->assertCreated();

        return Sale::query()->findOrFail($response->json('data.id'));
    }

    private function setGoldRate(int $karat, string $rate, ?string $date = null): void
    {
        GoldRate::query()->updateOrCreate(
            ['karat' => $karat, 'effective_date' => $date ?? today()->toDateString()],
            ['rate_per_gram' => $rate, 'created_by' => User::role('admin')->value('id')],
        );
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create()->assignRole($roleName);
    }

    private function userWithoutPermissions(): User
    {
        return User::factory()->create();
    }
}
