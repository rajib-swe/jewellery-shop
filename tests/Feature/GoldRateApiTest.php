<?php

namespace Tests\Feature;

use App\Models\GoldRate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoldRateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_endpoint_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/gold-rates/latest')->assertUnauthorized();
    }

    public function test_cashier_can_view_gold_rates(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');
        $goldRate = GoldRate::factory()->create([
            'karat' => 22,
            'effective_date' => '2026-09-24',
        ]);

        $this->actingAs($cashier)
            ->getJson('/api/v1/gold-rates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $goldRate->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_cashier_can_view_single_gold_rate(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');
        $goldRate = GoldRate::factory()->create([
            'karat' => 21,
            'effective_date' => '2026-09-24',
        ]);

        $this->actingAs($cashier)
            ->getJson("/api/v1/gold-rates/{$goldRate->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $goldRate->id)
            ->assertJsonPath('data.karat', 21);
    }

    public function test_cashier_cannot_create_gold_rate(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->postJson('/api/v1/gold-rates', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('gold_rates', 0);
    }

    public function test_manager_can_create_gold_rate_with_authenticated_user_as_creator(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $otherUser = User::factory()->create();

        $response = $this->actingAs($manager)
            ->postJson('/api/v1/gold-rates', [
                ...$this->validPayload(),
                'created_by' => $otherUser->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.karat', 22)
            ->assertJsonPath('data.rate_per_gram', '12500.50')
            ->assertJsonPath('data.created_by.id', $manager->id);

        $goldRate = GoldRate::query()->sole();

        $this->assertModelExists($goldRate);
        $this->assertSame($manager->id, $goldRate->created_by);
    }

    public function test_duplicate_karat_and_effective_date_returns_422(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        GoldRate::factory()->create([
            'karat' => 22,
            'effective_date' => '2026-09-24',
        ]);

        $this->actingAs($manager)
            ->postJson('/api/v1/gold-rates', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('karat');
    }

    public function test_create_returns_422_when_required_values_are_missing(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/api/v1/gold-rates')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['karat', 'rate_per_gram', 'effective_date']);
    }

    public function test_future_effective_date_returns_422(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/api/v1/gold-rates', [
                ...$this->validPayload(),
                'effective_date' => '2026-09-25',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_date');
    }

    public function test_manager_can_update_gold_rate_without_changing_creator(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $goldRate = GoldRate::factory()->create([
            'karat' => 22,
            'rate_per_gram' => '12000.00',
            'effective_date' => '2026-09-20',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->putJson("/api/v1/gold-rates/{$goldRate->id}", [
                'karat' => 22,
                'rate_per_gram' => '13000.00',
                'effective_date' => '2026-09-21',
            ])
            ->assertOk()
            ->assertJsonPath('data.rate_per_gram', '13000.00');

        $goldRate->refresh();

        $this->assertSame('13000.00', $goldRate->rate_per_gram);
        $this->assertSame('2026-09-21', $goldRate->effective_date->toDateString());
        $this->assertSame($manager->id, $goldRate->created_by);
    }

    public function test_manager_can_delete_gold_rate(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $goldRate = GoldRate::factory()->for($manager, 'createdBy')->create();

        $this->actingAs($manager)
            ->deleteJson("/api/v1/gold-rates/{$goldRate->id}")
            ->assertNoContent();

        $this->assertModelMissing($goldRate);
    }

    public function test_history_lists_older_rates_after_newer_rate(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $older = GoldRate::factory()->create([
            'karat' => 22,
            'rate_per_gram' => '11000.00',
            'effective_date' => '2026-09-20',
            'created_by' => $manager->id,
        ]);
        $newer = GoldRate::factory()->create([
            'karat' => 22,
            'rate_per_gram' => '12500.00',
            'effective_date' => '2026-09-24',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/gold-rates?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_latest_returns_newest_rate_per_karat_and_excludes_future_dates(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        GoldRate::factory()->create([
            'karat' => 22,
            'rate_per_gram' => '11000.00',
            'effective_date' => '2026-09-20',
            'created_by' => $manager->id,
        ]);
        $latestTwentyTwo = GoldRate::factory()->create([
            'karat' => 22,
            'rate_per_gram' => '12500.00',
            'effective_date' => '2026-09-24',
            'created_by' => $manager->id,
        ]);
        GoldRate::factory()->create([
            'karat' => 21,
            'rate_per_gram' => '12000.00',
            'effective_date' => '2026-09-23',
            'created_by' => $manager->id,
        ]);
        GoldRate::factory()->create([
            'karat' => 24,
            'rate_per_gram' => '14000.00',
            'effective_date' => '2026-09-25',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/gold-rates/latest')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.karat', 21)
            ->assertJsonPath('data.1.id', $latestTwentyTwo->id)
            ->assertJsonPath('data.1.effective_date', '2026-09-24');
    }

    public function test_history_search_filters_by_karat_and_paginates(): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        GoldRate::factory()
            ->count(2)
            ->sequence(
                ['effective_date' => '2026-09-20'],
                ['effective_date' => '2026-09-21'],
            )
            ->create([
                'karat' => 22,
                'created_by' => $manager->id,
            ]);
        GoldRate::factory()->create([
            'karat' => 18,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson('/api/v1/gold-rates?search=22&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.karat', 22)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
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
     * @return array{karat: int, rate_per_gram: string, effective_date: string}
     */
    private function validPayload(): array
    {
        return [
            'karat' => 22,
            'rate_per_gram' => '12500.50',
            'effective_date' => '2026-09-24',
        ];
    }
}
