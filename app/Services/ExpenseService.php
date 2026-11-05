<?php

namespace App\Services;

use App\CashSourceType;
use App\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Day-to-day spending that is not tied to a purchase or a salary run.
 *
 * An expense and its cash book row are written in one transaction, so the
 * ledger and the expense list can never disagree. Deleting an expense takes its
 * ledger row with it for the same reason.
 */
class ExpenseService
{
    public function __construct(private readonly CashBookService $cashBook) {}

    /**
     * @param  array{search?: ?string, category?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Expense::query()->with('user:id,name');

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $expenseQuery) use ($search): void {
                $expenseQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function find(Expense $expense): Expense
    {
        return $expense->load('user:id,name');
    }

    /**
     * @param  array{category: string, title: string, amount: string|float|int, date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function create(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user): Expense {
            $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
            $amountCents = $this->toCents((float) $data['amount']);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The expense amount must be greater than zero.',
                ]);
            }

            $this->cashBook->guardOpenDay($date);

            $expense = Expense::query()->create([
                'category' => $data['category'],
                'title' => $data['title'],
                'amount' => $this->fromCents($amountCents),
                'date' => $date->toDateString(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $this->cashBook->recordOut(
                CashSourceType::Expense,
                $expense->getKey(),
                $expense->amount,
                $user,
                [
                    'date' => $expense->date->toDateString(),
                    'method' => $expense->method->value,
                    'reference' => $expense->reference,
                    'note' => $expense->title,
                ],
            );

            return $expense->load('user:id,name');
        });
    }

    /**
     * @param  array{category?: ?string, title?: ?string, amount?: ?string|float|int, date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function update(Expense $expense, array $data, User $user): Expense
    {
        return DB::transaction(function () use ($expense, $data, $user): Expense {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());
            $date = CarbonImmutable::parse($data['date'] ?? $lockedExpense->date)->startOfDay();
            $amount = $data['amount'] ?? $lockedExpense->amount;
            $amountCents = $this->toCents((float) $amount);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The expense amount must be greater than zero.',
                ]);
            }

            $this->cashBook->guardOpenDay($date);

            $changes = Arr::only($data, ['category', 'title', 'method', 'reference', 'note']);

            $lockedExpense->update([
                ...$changes,
                'amount' => $this->fromCents($amountCents),
                'date' => $date->toDateString(),
            ]);

            // The ledger row is replaced rather than edited, so a corrected
            // amount never leaves a stale row in the cash book.
            $this->cashBook->forgetSource(CashSourceType::Expense, $lockedExpense->getKey());

            $this->cashBook->recordOut(
                CashSourceType::Expense,
                $lockedExpense->getKey(),
                $lockedExpense->amount,
                $user,
                [
                    'date' => $lockedExpense->date->toDateString(),
                    'method' => $lockedExpense->method->value,
                    'reference' => $lockedExpense->reference,
                    'note' => $lockedExpense->title,
                ],
            );

            return $lockedExpense->load('user:id,name');
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());

            $this->cashBook->guardOpenDay($lockedExpense->date);

            $this->cashBook->forgetSource(CashSourceType::Expense, $lockedExpense->getKey());

            $lockedExpense->delete();
        });
    }

    /**
     * Spending per category over a range, for the expense breakdown.
     *
     * @return list<array{category: string, total: string, count: int}>
     */
    public function totalsByCategory(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Expense::query()
            ->select('category')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COUNT(*) as expense_count')
            ->groupBy('category');

        if ($dateFrom !== null) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query
            ->orderByDesc('total')
            ->get()
            ->map(fn (Expense $row): array => [
                'category' => $row->category->value,
                'total' => $this->fromCents($this->toCents((float) $row->getAttribute('total'))),
                'count' => (int) $row->getAttribute('expense_count'),
            ])
            ->all();
    }

    public static function categories(): array
    {
        return array_map(
            static fn (ExpenseCategory $category): string => $category->value,
            ExpenseCategory::cases(),
        );
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
