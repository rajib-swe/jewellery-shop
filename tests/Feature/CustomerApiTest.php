<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_manager_can_create_customer_with_normalized_phone_and_photo(): void
    {
        Storage::fake('public');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $photo = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->actingAs($manager)->post('/api/v1/customers', [
            'name' => '  Rahima Islam  ',
            'phone' => '+8801712-345678',
            'nid' => '1234567890',
            'address' => 'Dhaka',
            'opening_balance' => '1250.50',
            'notes' => 'Preferred customer',
            'photo' => $photo,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Rahima Islam')
            ->assertJsonPath('data.phone', '01712345678')
            ->assertJsonPath('data.opening_balance', '1250.50');

        $customer = Customer::query()->sole();

        $this->assertStringStartsWith('CUS-', $customer->code);
        $this->assertSame('01712345678', $customer->phone);
        $this->assertNotNull($customer->photo);
        Storage::disk('public')->assertExists($customer->photo);
    }

    public function test_duplicate_normalized_phone_returns_validation_error(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        Customer::factory()->create(['phone' => '01712345678']);

        $this->actingAs($manager)
            ->postJson('/api/v1/customers', [
                'name' => 'Another Customer',
                'phone' => '01712-345678',
                'opening_balance' => '0',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_cashier_can_view_customers_and_history(): void
    {
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');
        $customer = Customer::factory()->create([
            'name' => 'History Customer',
            'opening_balance' => '500.25',
        ]);

        $this->actingAs($cashier)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($cashier)
            ->getJson("/api/v1/customers/{$customer->id}/history")
            ->assertOk()
            ->assertJsonPath('data.sales', [])
            ->assertJsonPath('data.pawns', [])
            ->assertJsonPath('data.payments', [])
            ->assertJsonPath('data.due_balance', '500.25');
    }

    public function test_cashier_cannot_mutate_customers(): void
    {
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');
        $customer = Customer::factory()->create();

        $this->actingAs($cashier)
            ->postJson('/api/v1/customers', $this->validPayload())
            ->assertForbidden();

        $this->actingAs($cashier)
            ->putJson("/api/v1/customers/{$customer->id}", $this->validPayload())
            ->assertForbidden();

        $this->actingAs($cashier)
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertForbidden();
    }

    public function test_manager_can_search_and_paginate_customers(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        Customer::factory()->create([
            'name' => 'Searchable Person',
            'phone' => '01711111111',
            'nid' => 'NID-123',
        ]);
        Customer::factory()->create([
            'name' => 'Other Person',
            'phone' => '01822222222',
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/customers?search=017111&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Searchable Person')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_manager_can_update_customer_and_remove_photo(): void
    {
        Storage::fake('public');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $oldPath = 'customers/photos/old.jpg';
        Storage::disk('public')->put($oldPath, 'old-photo');
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'photo' => $oldPath,
        ]);

        $this->actingAs($manager)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'name' => 'New Name',
                'phone' => '01712345678',
                'remove_photo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.photo', null);

        $customer->refresh();

        $this->assertSame('New Name', $customer->name);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_manager_can_delete_customer(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $customer = Customer::factory()->create();

        $this->actingAs($manager)
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertNoContent();

        $this->assertModelMissing($customer);
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
     * @return array{name: string, phone: string, opening_balance: string}
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Test Customer',
            'phone' => '01712345678',
            'opening_balance' => '0.00',
        ];
    }
}
