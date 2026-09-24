<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
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
}
