<?php

namespace Tests\Feature;

use App\ItemStatus;
use App\Models\Category;
use App\Models\GoldRate;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPurchaseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin.password' => 'test-password-123']);

        $this->seed(DatabaseSeeder::class);

        $this->setGoldRate(22, '12800.00');
        $this->setGoldRate(21, '12200.00');
    }

    public function test_a_purchase_creates_a_catalogue_item_and_an_in_movement(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();
        $weightBefore = $this->totalNetWeight();

        $response = $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের নেকলেস', 'gross_weight' => '20.000', 'karat' => 22],
        ]));

        $response->assertCreated();
        $purchaseId = $response->json('data.id');
        $this->assertSame('PUR-'.now()->format('Y').'-000001', $response->json('data.purchase_no'));

        // Stock rises by the purchased weight, and the server does the pricing.
        $this->assertEqualsWithDelta(
            20.0,
            $this->totalNetWeight() - $weightBefore,
            0.001,
            'The purchased weight should land in stock.',
        );
        $this->assertSame('256000.00', $response->json('data.total'));
        $this->assertSame('256000.00', $response->json('data.items.0.amount'));

        $item = Item::query()
            ->where('name', 'স্বর্ণের নেকলেস')
            ->where('status', ItemStatus::InStock->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('20.000', (string) $item->net_weight);
        $this->assertSame(1, StockMovement::query()
            ->where('item_id', $item->id)
            ->where('type', 'in')
            ->where('ref_type', 'purchase')
            ->where('ref_id', (string) $purchaseId)
            ->count());
    }

    public function test_a_client_supplied_total_is_ignored(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->postJson('/api/v1/purchases', [
            ...$this->payload($this->supplier(), [
                ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
            ]),
            'subtotal' => '1.00',
            'total' => '1.00',
            'paid' => '0.00',
            'due' => '0.00',
        ]);

        $response->assertCreated();
        $this->assertSame('128000.00', $response->json('data.total'));
        $this->assertSame('128000.00', $response->json('data.due'));
    }

    public function test_a_discount_beyond_the_subtotal_is_rejected(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            ...$this->payload($this->supplier(), [
                ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
            ]),
            'discount' => '999999.00',
        ])->assertJsonValidationErrors('discount');
    }

    public function test_stone_weight_may_not_equal_the_gross_weight(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($this->supplier(), [
            [
                'name' => 'স্বর্ণের চুড়ি',
                'gross_weight' => '10.000',
                'stone_weight' => '10.000',
                'karat' => 22,
            ],
        ]))->assertJsonValidationErrors('items.0.stone_weight');
    }

    public function test_a_payment_at_the_counter_settles_the_purchase(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();

        $purchaseId = $this->actingAs($user)->postJson('/api/v1/purchases', [
            ...$this->payload($supplier, [
                ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
            ]),
            'payment' => ['method' => 'cash', 'amount' => '50000.00'],
        ])->assertCreated()->json('data.id');

        $purchase = Purchase::query()->findOrFail($purchaseId);

        $this->assertSame('50000.00', (string) $purchase->paid);
        $this->assertSame('78000.00', (string) $purchase->due);
        $this->assertSame('78000.00', $this->balance($supplier));
    }

    public function test_the_supplier_ledger_balance_is_purchases_minus_payments(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();

        $first = $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের নেকলেস', 'gross_weight' => '20.000', 'karat' => 22],
        ]))->assertCreated()->json('data.id');

        $second = $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
        ]))->assertCreated()->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => '100000.00',
            'purchase_id' => $first,
            'method' => 'cash',
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => '20000.00',
            'method' => 'bank',
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson("/api/v1/suppliers/{$supplier->id}/ledger");

        $response->assertOk();
        $this->assertSame('384000.00', $response->json('data.total_purchases'));
        $this->assertSame('120000.00', $response->json('data.total_payments'));
        $this->assertSame('264000.00', $response->json('data.balance'));
        $this->assertCount(4, $response->json('data.transactions'));

        // A purchase linked payment settles that purchase, an advance does not.
        $this->assertSame('156000.00', (string) Purchase::query()->findOrFail($first)->refresh()->due);
        $this->assertSame('128000.00', (string) Purchase::query()->findOrFail($second)->refresh()->due);
        $this->assertSame('264000.00', $this->balance($supplier));
    }

    public function test_the_ledger_lists_purchases_and_payments_in_date_order(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();
        $this->setGoldRate(22, '12600.00', today()->subDays(10)->toDateString());

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            ...$this->payload($supplier, [
                ['name' => 'প্রথম কেনা', 'gross_weight' => '10.000', 'karat' => 22],
            ]),
            'date' => today()->subDays(10)->toDateString(),
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => '5000.00',
            'date' => today()->subDays(5)->toDateString(),
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/purchases', [
            ...$this->payload($supplier, [
                ['name' => 'দ্বিতীয় কেনা', 'gross_weight' => '5.000', 'karat' => 22],
            ]),
            'date' => today()->toDateString(),
        ])->assertCreated();

        $rows = $this->actingAs($user)
            ->getJson("/api/v1/suppliers/{$supplier->id}/ledger")
            ->assertOk()
            ->json('data.transactions');

        $this->assertSame(['purchase', 'payment', 'purchase'], array_column($rows, 'kind'));
        $this->assertSame(['126000.00', '0.00', '64000.00'], array_column($rows, 'debit'));
        $this->assertSame(['0.00', '5000.00', '0.00'], array_column($rows, 'credit'));
    }

    public function test_a_payment_cannot_exceed_the_due_on_a_purchase(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();

        $purchaseId = $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
        ]))->assertCreated()->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
                'amount' => '999999.00',
                'purchase_id' => $purchaseId,
            ])
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_payment_cannot_target_another_suppliers_purchase(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();
        $other = $this->supplier();

        $purchaseId = $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
        ]))->assertCreated()->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/suppliers/{$other->id}/payments", [
                'amount' => '100.00',
                'purchase_id' => $purchaseId,
            ])
            ->assertJsonValidationErrors('purchase_id');
    }

    public function test_the_supplier_list_reports_counts_and_balance(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();

        $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
        ]))->assertCreated();

        $response = $this->actingAs($user)->getJson('/api/v1/suppliers');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $supplier->id);

        $this->assertNotNull($row);
        $this->assertSame('128000.00', $row['balance']);
        $this->assertSame(1, $row['purchases_count']);
        $this->assertSame('supplier', $row['type']);
    }

    public function test_a_supplier_with_history_cannot_be_deleted(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();

        $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
        ]))->assertCreated();

        $this->actingAs($user)
            ->deleteJson("/api/v1/suppliers/{$supplier->id}")
            ->assertJsonValidationErrors('supplier');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_an_unused_supplier_can_be_deleted(): void
    {
        $user = $this->userWithRole('admin');
        $supplier = $this->supplier();

        $this->actingAs($user)->deleteJson("/api/v1/suppliers/{$supplier->id}")->assertNoContent();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_a_karigor_can_be_created_and_filtered(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/suppliers', [
            'name' => 'আব্দুল খালেক কারিগর',
            'type' => 'karigor',
            'phone' => '01712345678',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'karigor')
            ->assertJsonPath('data.code', fn (string $code): bool => str_starts_with($code, 'SUP-'));

        $this->actingAs($user)
            ->getJson('/api/v1/suppliers?type=karigor')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson('/api/v1/suppliers?type=bogus')
            ->assertJsonValidationErrors('type');
    }

    public function test_a_purchase_for_a_karat_without_a_rate_is_rejected(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->postJson('/api/v1/purchases', $this->payload($this->supplier(), [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 24],
        ]))->assertJsonValidationErrors('items');
    }

    public function test_only_a_rate_manager_may_override_the_purchase_rate(): void
    {
        $override = [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22, 'rate' => '13000.00'],
        ];

        $this->actingAs($this->userWithRole('manager'))
            ->postJson('/api/v1/purchases', $this->payload($this->supplier(), $override))
            ->assertCreated()
            ->assertJsonPath('data.total', '130000.00');

        // A buyer without rate permission may not set their own rate.
        $buyer = User::factory()->create();
        $buyer->givePermissionTo(['access api', 'manage purchases']);

        $this->actingAs($buyer)
            ->postJson('/api/v1/purchases', $this->payload($this->supplier(), $override))
            ->assertJsonValidationErrors('items');

        // Without an override the same buyer still gets the shop rate.
        $this->actingAs($buyer)
            ->postJson('/api/v1/purchases', $this->payload($this->supplier(), [
                ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.total', '128000.00');
    }

    public function test_a_cashier_can_read_but_not_buy_or_pay(): void
    {
        $cashier = $this->userWithRole('cashier');
        $supplier = $this->supplier();

        $this->actingAs($cashier)->getJson('/api/v1/suppliers')->assertOk();
        $this->actingAs($cashier)->getJson("/api/v1/suppliers/{$supplier->id}/ledger")->assertOk();

        $this->actingAs($cashier)->postJson('/api/v1/suppliers', [
            'name' => 'নতুন',
            'type' => 'supplier',
        ])->assertForbidden();

        $this->actingAs($cashier)->postJson('/api/v1/purchases', $this->payload($supplier, [
            ['name' => 'স্বর্ণের চুড়ি', 'gross_weight' => '10.000', 'karat' => 22],
        ]))->assertForbidden();

        $this->actingAs($cashier)->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => '100.00',
        ])->assertForbidden();
    }

    public function test_the_rates_endpoint_prices_a_backdated_purchase(): void
    {
        $user = $this->userWithRole('admin');
        $date = today()->subDays(10)->toDateString();

        $this->setGoldRate(22, '13100.00', $date);

        $this->actingAs($user)
            ->getJson("/api/v1/purchases/rates?date={$date}")
            ->assertOk()
            ->assertJsonPath('data.22', '13100.00');
    }

    private function balance(Supplier $supplier): string
    {
        return app(SupplierService::class)->ledger($supplier)['balance'];
    }

    private function setGoldRate(int $karat, string $rate, ?string $date = null): void
    {
        GoldRate::query()->updateOrCreate(
            ['karat' => $karat, 'effective_date' => $date ?? today()->toDateString()],
            ['rate_per_gram' => $rate, 'created_by' => User::role('admin')->value('id')],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(Supplier $supplier, array $items): array
    {
        $categoryId = Category::query()->value('id');

        return [
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'items' => array_map(static fn (array $item): array => [
                ...$item,
                'category_id' => $categoryId,
            ], $items),
        ];
    }

    private function supplier(): Supplier
    {
        return Supplier::factory()->create(['type' => 'supplier']);
    }

    private function totalNetWeight(): float
    {
        return (float) Item::query()->where('status', ItemStatus::InStock->value)->sum('net_weight');
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create()->assignRole($roleName);
    }
}
