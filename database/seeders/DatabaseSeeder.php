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
                'view customers',
                'manage customers',
                'view inventory',
                'manage inventory',
                'view sales',
                'manage sales',
                'void sales',
                'view pawns',
                'manage pawns',
                'forfeit pawns',
                'view suppliers',
                'manage suppliers',
                'view purchases',
                'manage purchases',
                'view accounts',
                'manage accounts',
                'close accounts',
                'view reports',
                'view users',
                'manage users',
                'view roles',
                'manage roles',
                'view activity log',
                'manage backups',
            ];

            foreach ($permissionNames as $permissionName) {
                Permission::findOrCreate($permissionName, 'web');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $rolePermissions = [
                'admin' => $permissionNames,
                // A manager runs the shop day to day, so they get the audit
                // trail and the backups, but handing out permissions is an
                // administrator's job: a role that can grant itself everything
                // makes "manage roles" meaningless.
                'manager' => array_values(array_diff($permissionNames, ['manage roles'])),
                'cashier' => [
                    'access api',
                    'view settings',
                    'view gold rates',
                    'view customers',
                    'view inventory',
                    'view sales',
                    'view pawns',
                    'view suppliers',
                    'view accounts',
                    'view reports',
                ],
            ];

            foreach ($rolePermissions as $roleName => $permissions) {
                Role::findOrCreate($roleName, 'web')->givePermissionTo($permissions);
            }

            Role::findByName('cashier', 'web')->revokePermissionTo([
                'manage settings',
                'manage gold rates',
                'manage customers',
                'manage inventory',
                'manage sales',
                'void sales',
                'manage pawns',
                'forfeit pawns',
                'manage suppliers',
                'view purchases',
                'manage accounts',
                'close accounts',
                'view users',
                'manage users',
                'view roles',
                'manage roles',
                'view activity log',
                'manage backups',
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
            $this->call(CategorySeeder::class);

            if (app()->environment('local')) {
                $this->call(CustomerSeeder::class);
                $this->call(ItemSeeder::class);
                $this->call(SaleSeeder::class);
                $this->call(PawnSeeder::class);
                $this->call(SupplierSeeder::class);
                $this->call(PurchaseSeeder::class);
                $this->call(AccountSeeder::class);
                // After the other modules, so the demo staff it creates can
                // audit records that already exist.
                $this->call(AdminSeeder::class);
            }
        });
    }
}
