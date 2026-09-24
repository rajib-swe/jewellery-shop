<?php

namespace Tests\Feature;

use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/items')->assertUnauthorized();
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->getJson('/api/v1/stock/summary')->assertUnauthorized();
    }

    public function test_manager_can_create_category_and_cashier_can_view_it(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $cashier = $this->userWithRole('cashier');

        $response = $this->actingAs($manager)->postJson('/api/v1/categories', [
            'name' => 'Anklet',
            'description' => 'Anklets and toe rings',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Anklet');

        $this->actingAs($cashier)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Anklet']);

        $this->actingAs($cashier)
            ->postJson('/api/v1/categories', ['name' => 'Forbidden'])
            ->assertForbidden();
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $category = Category::query()->firstOrFail();
        Item::factory()->for($category, 'category')->create();

        $this->actingAs($manager)
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_manager_can_create_item_with_server_computed_weight_and_inbound_movement(): void
    {
        Storage::fake('public');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $category = Category::query()->firstOrFail();
        $image = UploadedFile::fake()->image('item.jpg', 400, 400);

        $response = $this->actingAs($manager)->post('/api/v1/items', [
            'category_id' => $category->id,
            'name' => '22K Gold Ring',
            'karat' => 22,
            'gross_weight' => '10.000',
            'stone_weight' => '0.500',
            'net_weight' => '99.999',
            'making_type' => 'per_gram',
            'making_value' => '250.00',
            'stone_price' => '500.00',
            'status' => 'sold',
            'image' => $image,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.net_weight', '9.500')
            ->assertJsonPath('data.status', 'in_stock')
            ->assertJsonPath('data.category.id', $category->id);

        $item = Item::query()->with('category')->sole();
        $movement = StockMovement::query()->where('item_id', $item->id)->sole();

        $this->assertStringStartsWith('ITM-', $item->tag_no);
        $this->assertNotSame('99.999', $item->net_weight);
        $this->assertSame(ItemStatus::InStock, $item->status);
        $this->assertSame(StockMovementType::In, $movement->type);
        $this->assertSame('9.500', $movement->weight);
        $this->assertSame($manager->id, $movement->user_id);
        Storage::disk('public')->assertExists($item->image);
    }

    public function test_item_validation_rejects_stone_weight_over_gross_weight(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $category = Category::query()->firstOrFail();

        $this->actingAs($manager)
            ->postJson('/api/v1/items', [
                ...$this->validPayload($category->id),
                'stone_weight' => '11.000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stone_weight');
    }

    public function test_manager_can_update_item_and_weight_adjustment_is_recorded(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = Item::factory()->create([
            'gross_weight' => '10.000',
            'stone_weight' => '1.000',
            'net_weight' => '9.000',
        ]);

        $this->actingAs($manager)
            ->putJson("/api/v1/items/{$item->id}", [
                'gross_weight' => '12.000',
                'stone_weight' => '2.000',
                'net_weight' => '1.000',
                'status' => 'sold',
            ])
            ->assertOk()
            ->assertJsonPath('data.net_weight', '10.000')
            ->assertJsonPath('data.status', 'in_stock');

        $movement = StockMovement::query()->where('item_id', $item->id)->sole();

        $this->assertSame(StockMovementType::Adjust, $movement->type);
        $this->assertSame('1.000', $movement->weight);
    }

    public function test_stock_adjustment_changes_status_and_records_movement(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $item = Item::factory()->create([
            'gross_weight' => '10.000',
            'stone_weight' => '0.000',
            'net_weight' => '10.000',
        ]);

        $this->actingAs($manager)
            ->postJson("/api/v1/items/{$item->id}/stock-adjustments", [
                'type' => 'out',
                'weight' => '10.000',
                'status' => 'sold',
                'note' => 'Manual stock correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sold');

        $movement = StockMovement::query()->where('item_id', $item->id)->sole();

        $this->assertSame(StockMovementType::Out, $movement->type);
        $this->assertSame('Manual stock correction', $movement->note);
        $this->assertSame($manager->id, $movement->user_id);
    }

    public function test_item_list_filters_by_category_karat_status_and_search(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $category = Category::query()->firstOrFail();
        $otherCategory = Category::factory()->create(['name' => 'Test Other Category']);
        $matching = Item::factory()->for($category, 'category')->create([
            'name' => 'Matching Ring',
            'karat' => 22,
            'status' => ItemStatus::InStock,
            'barcode' => 'MATCH-001',
        ]);
        Item::factory()->for($otherCategory, 'category')->create([
            'name' => 'Other Ring',
            'karat' => 21,
            'status' => ItemStatus::Sold,
        ]);

        $this->actingAs($manager)
            ->getJson("/api/v1/items?category_id={$category->id}&karat=22&status=in_stock&search=Matching")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_stock_summary_sums_only_in_stock_items_by_karat(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        Item::factory()->count(2)->create([
            'karat' => 22,
            'status' => ItemStatus::InStock,
            'gross_weight' => '10.000',
            'stone_weight' => '1.000',
            'net_weight' => '9.000',
        ]);
        Item::factory()->create([
            'karat' => 22,
            'status' => ItemStatus::Sold,
            'gross_weight' => '5.000',
            'stone_weight' => '0.000',
            'net_weight' => '5.000',
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/stock/summary')
            ->assertOk()
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonPath('data.total_net_weight', '18.000')
            ->assertJsonPath('data.by_karat.2.karat', 22)
            ->assertJsonPath('data.by_karat.2.total_net_weight', '18.000')
            ->assertJsonPath('data.by_karat.2.item_count', 2);
    }

    public function test_cashier_can_view_but_cannot_manage_inventory(): void
    {
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');
        $item = Item::factory()->create();

        $this->actingAs($cashier)
            ->getJson('/api/v1/items')
            ->assertOk();

        $this->actingAs($cashier)
            ->postJson('/api/v1/items', $this->validPayload(Category::query()->value('id')))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->postJson("/api/v1/items/{$item->id}/stock-adjustments", [
                'type' => 'out',
                'weight' => '1.000',
                'status' => 'sold',
            ])
            ->assertForbidden();
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

    /**
     * @return array{category_id: int, name: string, karat: int, gross_weight: string, stone_weight: string, making_type: string, making_value: string, stone_price: string}
     */
    private function validPayload(int $categoryId): array
    {
        return [
            'category_id' => $categoryId,
            'name' => 'Test Gold Item',
            'karat' => 22,
            'gross_weight' => '10.000',
            'stone_weight' => '0.500',
            'making_type' => 'fixed',
            'making_value' => '100.00',
            'stone_price' => '0.00',
        ];
    }
}
