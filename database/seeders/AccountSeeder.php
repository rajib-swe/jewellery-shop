<?php

namespace Database\Seeders;

use App\CashSourceType;
use App\ExpenseCategory;
use App\Models\User;
use App\Services\CashBookService;
use App\Services\ExpenseService;
use Illuminate\Database\Seeder;

/**
 * Demo expenses and a day that has already been closed.
 *
 * The closed day is the interesting row: it gives the Daily Closing page a
 * locked day to reopen, and a day with nothing in it to close, without having
 * to post anything by hand.
 */
class AccountSeeder extends Seeder
{
    public function run(ExpenseService $expenses, CashBookService $cashBook): void
    {
        $admin = User::role('admin')->first();

        if ($admin === null) {
            return;
        }

        $expenses->create([
            'category' => ExpenseCategory::Rent->value,
            'title' => 'মাসিক দোকান ভাড়া',
            'amount' => '25000.00',
            'date' => today()->toDateString(),
            'method' => 'cash',
            'note' => 'এই মাসের দোকান ভাড়া পরিশোধ করা হয়েছে।',
        ], $admin);

        $expenses->create([
            'category' => ExpenseCategory::Electricity->value,
            'title' => 'বিদ্যুৎ বিল',
            'amount' => '4200.00',
            'date' => today()->toDateString(),
            'method' => 'bkash',
            'reference' => 'BK-88213',
        ], $admin);

        $expenses->create([
            'category' => ExpenseCategory::Transport->value,
            'title' => 'মাল আনার ভাড়া',
            'amount' => '1500.00',
            'date' => today()->toDateString(),
            'method' => 'cash',
        ], $admin);

        // A withdrawal with no document behind it, so the cash book has a row
        // that is not traced to a sale, a pawn or a supplier.
        $cashBook->recordOut(
            CashSourceType::CashAdjustment,
            null,
            5000.00,
            $admin,
            [
                'date' => today()->toDateString(),
                'method' => 'cash',
                'note' => 'ব্যাংক থেকে টাকা তুলে আনা হয়েছে',
            ],
        );

        $cashBook->recordIn(
            CashSourceType::CashAdjustment,
            null,
            2000.00,
            $admin,
            [
                'date' => today()->subDays(2)->toDateString(),
                'method' => 'cash',
                'note' => 'আগের দিনের ইকুইটি ক্যাশ ফেরত',
            ],
        );

        $this->closeYesterday($cashBook, $admin);
    }

    private function closeYesterday(CashBookService $cashBook, User $admin): void
    {
        $yesterday = today()->subDay();

        $cashBook->recordIn(
            CashSourceType::CashAdjustment,
            null,
            1500.00,
            $admin,
            [
                'date' => $yesterday->toDateString(),
                'method' => 'cash',
                'note' => 'আগের দিনের জমা',
            ],
        );

        $cashBook->close($yesterday, $admin, [], 'আগের দিনের ক্যাশ গোনা হয়েছে।');
    }
}
