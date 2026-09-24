<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_endpoint_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
    }

    public function test_settings_update_returns_401_when_unauthenticated(): void
    {
        $this->putJson('/api/v1/settings', [
            'shop_name' => 'Unauthorized Shop',
        ])->assertUnauthorized();
    }

    public function test_manager_can_read_default_settings(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.shop_name', 'Jewellery Shop')
            ->assertJsonPath('data.currency_symbol', '৳')
            ->assertJsonPath('data.vat_percentage', '0.00');
    }

    public function test_cashier_can_read_settings(): void
    {
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.shop_name', 'Jewellery Shop');
    }

    public function test_manager_can_update_settings_without_resetting_omitted_values(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->putJson('/api/v1/settings', [
                'shop_name' => 'Golden Rose Jewellery',
                'weight_unit' => 'vori',
            ])
            ->assertOk()
            ->assertJsonPath('data.shop_name', 'Golden Rose Jewellery')
            ->assertJsonPath('data.weight_unit', 'vori')
            ->assertJsonPath('data.currency_symbol', '৳');

        $this->assertDatabaseHas('settings', [
            'key' => 'shop_name',
            'value' => 'Golden Rose Jewellery',
        ]);
    }

    public function test_cashier_cannot_update_settings(): void
    {
        $this->seedApplication();
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->putJson('/api/v1/settings', [
                'shop_name' => 'Cashier Shop',
            ])
            ->assertForbidden();
    }

    public function test_settings_update_rejects_invalid_business_values(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->putJson('/api/v1/settings', [
                'vat_percentage' => 101,
                'weight_unit' => 'kilo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vat_percentage', 'weight_unit']);
    }

    public function test_unexpected_setting_key_is_not_persisted(): void
    {
        $this->seedApplication();
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->putJson('/api/v1/settings', [
                'shop_name' => 'Safe Shop',
                'unexpected_key' => 'unsafe',
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.unexpected_key');

        $this->assertDatabaseMissing('settings', [
            'key' => 'unexpected_key',
        ]);
    }

    public function test_manager_can_upload_logo_to_public_disk(): void
    {
        Storage::fake('public');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $logo = UploadedFile::fake()->image('shop-logo.png', 200, 200);

        $response = $this->actingAs($manager)->put('/api/v1/settings', [
            'shop_logo' => $logo,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $path = Setting::query()->where('key', 'shop_logo')->value('value');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_manager_can_remove_existing_logo_after_database_update(): void
    {
        Storage::fake('public');
        $this->seedApplication();
        $manager = $this->userWithRole('manager');
        $oldPath = 'settings/logos/old-logo.png';
        Storage::disk('public')->put($oldPath, 'old-logo');
        Setting::query()->updateOrCreate(
            ['key' => 'shop_logo'],
            ['value' => $oldPath],
        );

        $this->actingAs($manager)
            ->putJson('/api/v1/settings', [
                'remove_shop_logo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.shop_logo', null);

        Storage::disk('public')->assertMissing($oldPath);
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
}
