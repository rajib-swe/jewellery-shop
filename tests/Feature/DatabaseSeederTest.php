<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_local_environment_rejects_the_default_admin_password(): void
    {
        config([
            'app.env' => 'production',
            'app.admin.password' => 'password',
        ]);

        $this->expectException(RuntimeException::class);

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_password_rotates_when_the_configured_value_changes(): void
    {
        config([
            'app.env' => 'local',
            'app.admin.email' => 'admin@example.com',
            'app.admin.password' => 'first-local-password',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('first-local-password', $admin->password));

        config(['app.admin.password' => 'rotated-local-password']);
        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('rotated-local-password', $admin->password));
    }

    public function test_seeder_assigns_step_two_permission_matrix(): void
    {
        config([
            'app.env' => 'local',
            'app.admin.password' => 'test-password-123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = Role::findByName('admin');
        $manager = Role::findByName('manager');
        $cashier = Role::findByName('cashier');

        $this->assertTrue($admin->hasPermissionTo('view settings'));
        $this->assertTrue($admin->hasPermissionTo('manage settings'));
        $this->assertTrue($manager->hasPermissionTo('view settings'));
        $this->assertTrue($manager->hasPermissionTo('manage settings'));
        $this->assertTrue($cashier->hasPermissionTo('view settings'));
        $this->assertFalse($cashier->hasPermissionTo('manage settings'));
        $this->assertTrue($cashier->hasPermissionTo('view gold rates'));
        $this->assertFalse($cashier->hasPermissionTo('manage gold rates'));
    }

    public function test_seeder_creates_defaults_without_overwriting_changed_settings(): void
    {
        config([
            'app.env' => 'local',
            'app.admin.password' => 'test-password-123',
        ]);

        $this->seed(DatabaseSeeder::class);
        Setting::query()->where('key', 'shop_name')->update([
            'value' => 'Administrator Shop Name',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('settings', 9);
        $this->assertDatabaseHas('settings', [
            'key' => 'shop_name',
            'value' => 'Administrator Shop Name',
        ]);
    }
}
