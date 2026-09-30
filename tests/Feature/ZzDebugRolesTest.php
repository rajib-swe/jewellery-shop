<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ZzDebugRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        config(['app.admin.password' => 'debug-password-1234']);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::role('admin')->firstOrFail();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum');

        $role = Role::findByName('admin');

        dump([
            'defaults.guard' => config('auth.defaults.guard'),
            'guards.web.provider' => config('auth.guards.web.provider'),
            'providers.users' => config('auth.providers.users'),
            'guard_attr_raw' => var_export($role->getAttributes()['guard_name'] ?? 'ABSENT', true),
            'guard_attr_key_exists' => array_key_exists('guard_name', $role->getAttributes()),
            'getModelForGuard(web)' => Spatie\Permission\Guard::getModelForGuard('web'),
            'morphKey' => Spatie\Permission\Helper::class ? config('permission.column_names.model_morph_key') : null,
        ]);

        $this->assertTrue(true);
    }
}
