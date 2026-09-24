<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $password = config('app.admin.password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('ADMIN_PASSWORD must be set before seeding.');
        }

        if (config('app.env') !== 'local' && ($password === 'password' || strlen($password) < 12)) {
            throw new RuntimeException('ADMIN_PASSWORD must be at least 12 characters outside local environments.');
        }

        DB::transaction(function () use ($password): void {
            $permissionNames = [
                'access api',
                'view settings',
                'manage settings',
                'view gold rates',
                'manage gold rates',
            ];

            foreach ($permissionNames as $permissionName) {
                Permission::findOrCreate($permissionName, 'web');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $rolePermissions = [
                'admin' => $permissionNames,
                'manager' => $permissionNames,
                'cashier' => [
                    'access api',
                    'view settings',
                    'view gold rates',
                ],
            ];

            foreach ($rolePermissions as $roleName => $permissions) {
                Role::findOrCreate($roleName, 'web')->givePermissionTo($permissions);
            }

            Role::findByName('cashier', 'web')->revokePermissionTo([
                'manage settings',
                'manage gold rates',
            ]);

            $admin = User::firstOrNew([
                'email' => config('app.admin.email'),
            ]);

            $admin->name = config('app.admin.name');

            if (! $admin->exists || ! Hash::check($password, $admin->password)) {
                $admin->password = $password;
            }

            $admin->save();
            $admin->assignRole('admin');

            $this->call(SettingSeeder::class);
            $this->call(GoldRateSeeder::class);
        });
    }
}
