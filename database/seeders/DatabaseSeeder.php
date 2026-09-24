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
            $accessApiPermission = Permission::findOrCreate('access api', 'web');

            foreach (['admin', 'manager', 'cashier'] as $roleName) {
                Role::findOrCreate($roleName, 'web')->givePermissionTo($accessApiPermission);
            }

            $admin = User::firstOrNew([
                'email' => config('app.admin.email'),
            ]);

            $admin->name = config('app.admin.name');

            if (! $admin->exists || ! Hash::check($password, $admin->password)) {
                $admin->password = $password;
            }

            $admin->save();
            $admin->assignRole('admin');
        });
    }
}
