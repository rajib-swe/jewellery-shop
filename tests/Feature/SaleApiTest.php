<?php

namespace Tests\Feature;

use App\ItemStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GoldRate;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/sales')->assertUnauthorized();
        $this->postJson('/api/v1/sales', [])->assertUnauthorized();
    }

    public function test_sale_totals_are_computed_on_the_server_and_match_the_preview(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $customer = Customer::factory()->create();
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $response = $this->actingAs($manager)->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'discount' => '4000.00',
            'items' => [['item_id' => $item->id]],
            'payments' => [['method' => 'cash', 'amount' => '10000.00']],
            'exchanges' => [
                ['description' => 'Old bangles', 'karat' => 22, 'weight' => '2.000'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '90500.00')
            ->assertJsonPath('data.discount', '4000.00')
            ->assertJsonPath('data.vat', '0.00')
            ->assertJsonPath('data.exchange_amount', '18000.00')
            ->assertJsonPath('data.total', '68500.00')
            ->assertJsonPath('data.paid', '10000.00')
            ->assertJsonPath('data.due', '58500.00')
            ->assertJsonPath('data.items.0.weight', '10.000')
            ->assertJsonPath('data.items.0.rate', '9000.00')
            ->assertJsonPath('data.items.0.gold_value', '90000.00')
            ->assertJsonPath('data.items.0.making', '500.00')
            ->assertJsonPath('data.items.0.line_total', '90500.00')
            ->assertJsonPath('data.exchanges.0.amount', '18000.00');

        $this->assertMatchesRegularExpression(
            '/^INV-'.now()->format('Y').'-\d{6}$/',
            $response->json('data.invoice_no'),
        );

        $this->assertSame(ItemStatus::Sold, $item->refresh()->status);
        $this->assertSame('90500.00', Sale::query()->with('items')->sole()->items->sole()->line_total);
    }

    public function test_invoice_numbers_increment_per_year(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->setGoldRate(22, '9000.00');

        $first = $this->actingAs($manager)
            ->postJson('/api/v1/sales', ['items' => [['item_id' => $this->tenGramTwentyTwoKItem()->id]]])
            ->assertCreated()
            ->json('data.invoice_no');

        $second = $this->actingAs($manager)
            ->postJson('/api/v1/sales', ['items' => [['item_id' => $this->tenGramTwentyTwoKItem()->id]]])
            ->assertCreated()
            ->json('data.invoice_no');

        $this->assertSame('INV-'.now()->format('Y').'-000001', $first);
        $this->assertSame('INV-'.now()->format('Y').'-000002', $second);
    }

    public function test_client_supplied_amounts_are_ignored(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->inStockItem([
            'karat' => 21,
            'gross_weight' => '5.000',
            'stone_weight' => '0.000',
            'net_weight' => '5.000',
            'making_type' => 'per_gram',
            'making_value' => '100.00',
            'stone_price' => '250.00',
        ]);

        $this->setGoldRate(21, '8000.00');

        $this->actingAs($manager)->postJson('/api/v1/sales', [
            'items' => [[
                'item_id' => $item->id,
                'weight' => '99.000',
                'line_total' => '1.00',
            ]],
            'subtotal' => '1.00',
            'total' => '1.00',
            'due' => '1.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '40750.00')
            ->assertJsonPath('data.total', '40750.00')
            ->assertJsonPath('data.items.0.weight', '5.000')
            ->assertJsonPath('data.items.0.gold_value', '40000.00')
            ->assertJsonPath('data.items.0.making', '500.00')
            ->assertJsonPath('data.items.0.stone_price', '250.00');
    }

    public function test_vat_is_applied_from_shop_settings_after_discount(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->inStockItem([
            'karat' => 24,
            'gross_weight' => '10.000',
            'stone_weight' => '0.000',
            'net_weight' => '10.000',
            'making_type' => 'fixed',
            'making_value' => '0.00',
            'stone_price' => '0.00',
        ]);

        $this->setGoldRate(24, '10000.00');
        Setting::query()->updateOrCreate(['key' => 'vat_percentage'], ['value' => '10.00']);

        $this->actingAs($manager)->postJson('/api/v1/sales', [
            'items' => [['item_id' => $item->id]],
            'discount' => '10000.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '100000.00')
            ->assertJsonPath('data.vat', '9000.00')
            ->assertJsonPath('data.total', '99000.00');
    }

    public function test_making_percent_is_applied_to_the_gold_value(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->inStockItem([
            'karat' => 22,
            'gross_weight' => '10.000',
            'stone_weight' => '0.000',
            'net_weight' => '10.000',
            'making_type' => 'percent',
            'making_value' => '5.00',
            'stone_price' => '0.00',
        ]);

        $this->setGoldRate(22, '10000.00');

        $this->actingAs($manager)->postJson('/api/v1/sales', [
            'items' => [['item_id' => $item->id]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.making', '5000.00')
            ->assertJsonPath('data.total', '105000.00');
    }

    public function test_rate_override_requires_permission(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $cashier = $this->cashier();
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($cashier)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id, 'rate' => '9500.00']],
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id, 'rate' => '9500.00']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.rate', '9500.00');
    }

    public function test_sale_requires_a_gold_rate_for_the_item_karat(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->inStockItem(['karat' => 18]);

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(ItemStatus::InStock, $item->refresh()->status);
    }

    public function test_an_item_cannot_be_sold_twice(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', ['items' => [['item_id' => $item->id]]])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', ['items' => [['item_id' => $item->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, Sale::query()->with('items')->sole()->items()->count());
        $this->assertSame(
            1,
            StockMovement::query()
                ->where('item_id', $item->id)
                ->where('type', StockMovementType::Out)
                ->count(),
        );
    }

    public function test_sale_is_rejected_when_an_item_is_not_in_stock(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->inStockItem(['karat' => 22, 'status' => ItemStatus::Sold]);

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_the_same_item_cannot_be_added_twice_in_one_request(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id], ['item_id' => $item->id]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_paid_amount_cannot_exceed_the_total(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id]],
                'payments' => [['method' => 'cash', 'amount' => '999999.00']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payments');
    }

    public function test_exchange_cannot_exceed_the_invoice_total(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($manager)
            ->postJson('/api/v1/sales', [
                'items' => [['item_id' => $item->id]],
                'exchanges' => [
                    ['description' => 'Heavy old gold', 'karat' => 22, 'weight' => '500.000'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exchanges');
    }

    public function test_old_gold_exchange_creates_a_scrap_item_and_stock_in_movement(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();
        $category = Category::query()->firstOrFail();

        $this->setGoldRate(22, '9000.00');

        $this->actingAs($manager)->postJson('/api/v1/sales', [
            'items' => [['item_id' => $item->id]],
            'exchanges' => [[
                'description' => 'Old chain',
                'karat' => 22,
                'weight' => '3.000',
                'category_id' => $category->id,
            ]],
        ])->assertCreated();

        $scrapItem = Item::query()->where('status', ItemStatus::Scrap->value)->sole();

        $this->assertSame('Old chain', $scrapItem->name);
        $this->assertSame('3.000', $scrapItem->net_weight);
        $this->assertSame(
            1,
            StockMovement::query()
                ->where('item_id', $scrapItem->id)
                ->where('type', StockMovementType::In)
                ->count(),
        );
    }

    public function test_due_payment_reduces_due_and_is_rejected_above_the_due(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $sale = $this->recordSale($manager, item: $item, paid: '20000.00');

        $this->actingAs($manager)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'method' => 'bkash',
                'amount' => '30000.00',
                'reference' => 'TXN123',
            ])
            ->assertOk()
            ->assertJsonPath('data.total', '90500.00')
            ->assertJsonPath('data.paid', '50000.00')
            ->assertJsonPath('data.due', '40500.00')
            ->assertJsonPath('data.payments.1.method', 'bkash');

        $this->actingAs($manager)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'method' => 'cash',
                'amount' => '40500.01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_voiding_a_sale_restores_stock_and_clears_the_customer_due(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $customer = Customer::factory()->create(['opening_balance' => '0.00']);
        $item = $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $sale = $this->recordSale($manager, $customer, $item, '10000.00');

        $this->actingAs($manager)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'Customer returned the ring'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.paid', '0.00')
            ->assertJsonPath('data.due', '0.00')
            ->assertJsonCount(0, 'data.payments');

        $this->assertSame(ItemStatus::InStock, $item->refresh()->status);
        $this->assertSame(
            1,
            StockMovement::query()
                ->where('item_id', $item->id)
                ->where('type', StockMovementType::In)
                ->where('ref_type', 'sale')
                ->count(),
        );

        $this->actingAs($manager)
            ->getJson("/api/v1/customers/{$customer->id}/history")
            ->assertOk()
            ->assertJsonPath('data.due_balance', '0.00');
    }

    public function test_voiding_a_sale_removes_the_scrap_items_it_created(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = $this->tenGramTwentyTwoKItem();
        $category = Category::query()->firstOrFail();

        $this->setGoldRate(22, '9000.00');

        $sale = $this->recordSale($manager, item: $item, exchanges: [[
            'description' => 'Old chain',
            'karat' => 22,
            'weight' => '3.000',
            'category_id' => $category->id,
        ]]);

        $this->assertSame(1, Item::query()->where('status', ItemStatus::Scrap->value)->count());

        $this->actingAs($manager)
            ->postJson("/api/v1/sales/{$sale->id}/void")
            ->assertOk();

        $this->assertSame(0, Item::query()->where('status', ItemStatus::Scrap->value)->count());
        $this->assertSame(ItemStatus::InStock, $item->refresh()->status);
    }

    public function test_voided_sale_rejects_further_payments(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $sale = $this->recordSale($manager);

        $this->actingAs($manager)
            ->postJson("/api/v1/sales/{$sale->id}/void")
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'method' => 'cash',
                'amount' => '100.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sale');
    }

    public function test_only_manager_can_void_a_sale(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $cashier = $this->cashier();
        $sale = $this->recordSale($manager);

        $this->actingAs($cashier)
            ->postJson("/api/v1/sales/{$sale->id}/void")
            ->assertForbidden();
    }

    public function test_cashier_can_view_sales_but_cannot_create_them(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $cashier = $this->cashier();
        $sale = $this->recordSale($manager);

        $this->actingAs($cashier)
            ->getJson('/api/v1/sales')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', $sale->invoice_no);

        $this->actingAs($cashier)
            ->getJson("/api/v1/sales/{$sale->id}")
            ->assertOk()
            ->assertJsonPath('data.invoice_no', $sale->invoice_no);

        $this->actingAs($cashier)
            ->postJson('/api/v1/sales', ['items' => [['item_id' => $this->tenGramTwentyTwoKItem()->id]]])
            ->assertForbidden();
    }

    public function test_sale_list_filters_and_searches(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $customer = Customer::factory()->create(['name' => 'Rahima Khatun', 'phone' => '01711000111']);
        $sale = $this->recordSale($manager, $customer);

        $this->actingAs($manager)
            ->getJson('/api/v1/sales?search=Rahima')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sale->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($manager)
            ->getJson('/api/v1/sales?search='.$sale->invoice_no)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($manager)
            ->getJson('/api/v1/sales?status=void')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_customer_history_exposes_sales_payments_and_due_balance(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $customer = Customer::factory()->create(['opening_balance' => '1000.00']);
        $sale = $this->recordSale($manager, $customer, $this->tenGramTwentyTwoKItem(), '10000.00');

        $this->actingAs($manager)
            ->getJson("/api/v1/customers/{$customer->id}/history")
            ->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.invoice_no', $sale->invoice_no)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.due_balance', '81500.00');
    }

    private function recordSale(
        User $user,
        ?Customer $customer = null,
        ?Item $item = null,
        string $paid = '0.00',
        array $exchanges = [],
    ): Sale {
        $item ??= $this->tenGramTwentyTwoKItem();

        $this->setGoldRate(22, '9000.00');

        $response = $this->actingAs($user)->postJson('/api/v1/sales', [
            'customer_id' => $customer?->id,
            'items' => [['item_id' => $item->id]],
            'payments' => $paid === '0.00' ? [] : [['method' => 'cash', 'amount' => $paid]],
            'exchanges' => $exchanges,
        ]);

        $response->assertCreated();

        return Sale::query()->findOrFail($response->json('data.id'));
    }

    private function tenGramTwentyTwoKItem(): Item
    {
        return $this->inStockItem([
            'karat' => 22,
            'gross_weight' => '10.000',
            'stone_weight' => '0.000',
            'net_weight' => '10.000',
            'making_type' => 'fixed',
            'making_value' => '500.00',
            'stone_price' => '0.00',
        ]);
    }

    private function inStockItem(array $overrides = []): Item
    {
        return Item::factory()->for(Category::query()->firstOrFail(), 'category')->create([
            'status' => ItemStatus::InStock,
            ...$overrides,
        ]);
    }

    private function setGoldRate(int $karat, string $rate): void
    {
        GoldRate::query()->updateOrCreate(
            [
                'karat' => $karat,
                'effective_date' => today()->toDateString(),
            ],
            [
                'rate_per_gram' => $rate,
                'created_by' => User::role('admin')->value('id'),
            ],
        );
    }

    private function seedApplication(): void
    {
        config(['app.admin.password' => 'test-password-123']);

        $this->seed(DatabaseSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create()->assignRole($roleName);
    }

    private function cashier(): User
    {
        return $this->userWithRole('cashier');
    }
}
