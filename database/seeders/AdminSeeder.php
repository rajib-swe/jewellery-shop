<?php

namespace Database\Seeders;

use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Pawn;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Demo staff and an audit trail for the admin screens.
 *
 * `DatabaseSeeder` runs with `WithoutModelEvents`, so the rows the other module
 * seeders create leave nothing in `activity_log`. This seeder writes the trail
 * explicitly against rows that already exist, which is what gives the activity
 * viewer and the "all money changes are logged" acceptance check something to
 * read without having to drive the app by hand first.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first();

        if ($admin === null) {
            return;
        }

        $this->createStaff($admin);
        $this->writeAuditTrail($admin);
    }

    /**
     * A manager and a cashier so the permission matrix can be checked by signing
     * in as each of them. Passwords come from config and default to the same
     * local value the admin account uses.
     */
    private function createStaff(User $admin): void
    {
        $password = config('app.admin.password');

        $staff = [
            'manager@example.com' => ['name' => 'ম্যানেজার', 'role' => 'manager'],
            'cashier@example.com' => ['name' => 'ক্যাশিয়ার', 'role' => 'cashier'],
        ];

        foreach ($staff as $email => $attributes) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $attributes['name'];
            $user->password = is_string($password) && $password !== '' ? $password : 'password';
            $user->save();
            $user->syncRoles([$attributes['role']]);
        }
    }

    /**
     * Writes one entry per money-moving model the audit screen can filter by,
     * plus a settings change, so every filter combination has a row behind it.
     */
    private function writeAuditTrail(User $admin): void
    {
        $sale = Sale::query()->orderBy('id')->first();
        $pawn = Pawn::query()->where('status', 'active')->orderBy('id')->first()
            ?? Pawn::query()->orderBy('id')->first();
        $expense = Expense::query()->orderBy('id')->first();
        $cash = CashTransaction::query()->where('direction', 'in')->orderBy('id')->first();
        $item = Item::query()->orderBy('id')->first();
        $setting = Setting::query()->where('key', 'shop_name')->first();

        $entries = [
            [$sale, 'created', 'Daily sale recorded'],
            [$pawn, 'created', 'Pawn disbursed'],
            [$expense, 'created', 'Expense posted'],
            [$cash, 'created', 'Cash received in the drawer'],
            [$item, 'updated', 'Stock adjusted'],
            [$setting, 'updated', 'Shop settings changed'],
        ];

        foreach ($entries as [$subject, $event, $description]) {
            if (! $subject instanceof Model) {
                continue;
            }

            activity()
                ->event($event)
                ->inLog($this->logNameFor($subject))
                ->performedOn($subject)
                ->causedBy($admin)
                ->withProperties(['seeded' => true])
                ->log($description);
        }
    }

    private function logNameFor(object $subject): string
    {
        return match (true) {
            $subject instanceof Sale => 'sales',
            $subject instanceof Pawn => 'pawns',
            $subject instanceof Expense => 'expenses',
            $subject instanceof CashTransaction => 'accounts',
            $subject instanceof Item => 'inventory',
            $subject instanceof Setting => 'settings',
            $subject instanceof User => 'users',
            default => 'default',
        };
    }
}
