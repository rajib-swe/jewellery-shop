<?php

namespace App\Services;

use App\CashDirection;
use App\CashSourceType;
use App\Models\CashTransaction;
use App\Models\DailyClosing;
use App\Models\User;
use App\PaymentMethod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place money entering or leaving the shop is recorded.
 *
 * Every sale payment, due payment, pawn disbursement, pawn collection, supplier
 * payment and expense calls {@see record()} so the cash book is a complete,
 * replayable ledger rather than a set of totals derived from five different
 * tables. Nothing else in the application writes `cash_transactions`, and money
 * that leaves the shop without a source document (a bank withdrawal, a float
 * top-up) is recorded as a `cash_adjustment` so the day still balances.
 *
 * A locked day refuses new entries. That is the point of the daily closing:
 * once a manager has counted the drawer the day's money is frozen, and a
 * mistake is fixed by reopening the day rather than by quietly editing history.
 */
class CashBookService
{
    /**
     * @param  array{search?: ?string, source_type?: ?string, direction?: ?string, method?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = CashTransaction::query()->with('user:id,name');

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Opening and closing cash for a day, plus the per-method breakdown the
     * counter reconciles against the drawer.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     date: string,
     *     opening_balance: string,
     *     total_in: string,
     *     total_out: string,
     *     closing_balance: string,
     *     transaction_count: int,
     *     by_method: list<array{method: string, total_in: string, total_out: string, net: string}>,
     *     locked: bool,
     * }
     */
    public function summaryFor(CarbonInterface $date, array $filters = []): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $base = $this->filtered(CashTransaction::query(), $filters)
            ->whereDate('date', $day->toDateString());

        $directionRows = (clone $base)
            ->select('direction')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('direction')
            ->get();

        $totalInCents = $this->sumCents($directionRows, CashDirection::In);
        $totalOutCents = $this->sumCents($directionRows, CashDirection::Out);

        $methodRows = (clone $base)
            ->select('method', 'direction')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('method', 'direction')
            ->get();

        $byMethod = collect(PaymentMethod::cases())
            ->map(function (PaymentMethod $method) use ($methodRows): array {
                $in = $this->sumCents($methodRows, CashDirection::In, $method);
                $out = $this->sumCents($methodRows, CashDirection::Out, $method);

                return [
                    'method' => $method->value,
                    'total_in' => $this->fromCents($in),
                    'total_out' => $this->fromCents($out),
                    'net' => $this->fromCents($in - $out),
                ];
            })
            ->filter(fn (array $row): bool => $row['total_in'] !== '0.00' || $row['total_out'] !== '0.00')
            ->values()
            ->all();

        $closing = DailyClosing::query()->whereDate('date', $day->toDateString())->first();
        $openingCents = $this->openingBalanceCents($day);

        return [
            'date' => $day->toDateString(),
            'opening_balance' => $this->fromCents($openingCents),
            'total_in' => $this->fromCents($totalInCents),
            'total_out' => $this->fromCents($totalOutCents),
            'closing_balance' => $this->fromCents($openingCents + $totalInCents - $totalOutCents),
            'transaction_count' => (clone $base)->count(),
            'by_method' => $byMethod,
            'locked' => $closing?->isLocked() ?? false,
        ];
    }

    /**
     * Record money entering the shop. A sale, a pawn collection or a manual
     * float top-up all land here as an `in` row.
     *
     * @param  array{date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function recordIn(
        CashSourceType $sourceType,
        string|int|null $sourceId,
        float $amount,
        ?User $user = null,
        array $data = [],
    ): CashTransaction {
        return $this->record($sourceType, $sourceId, CashDirection::In, $amount, $user, $data);
    }

    /**
     * Record money leaving the shop, either against a document or as a manual
     * withdrawal that has no document behind it.
     *
     * @param  array{date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function recordOut(
        CashSourceType $sourceType,
        string|int|null $sourceId,
        float $amount,
        ?User $user = null,
        array $data = [],
    ): CashTransaction {
        return $this->record($sourceType, $sourceId, CashDirection::Out, $amount, $user, $data);
    }

    /**
     * @param  array{date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function record(
        CashSourceType $sourceType,
        string|int|null $sourceId,
        CashDirection $direction,
        float $amount,
        ?User $user = null,
        array $data = [],
    ): CashTransaction {
        $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
        $amountCents = $this->toCents($amount);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The cash amount must be greater than zero.',
            ]);
        }

        $this->guardOpenDay($date);

        return CashTransaction::query()->create([
            'source_type' => $sourceType,
            'source_id' => $sourceId === null ? null : (string) $sourceId,
            'direction' => $direction,
            'amount' => $this->fromCents($amountCents),
            'method' => $data['method'] ?? PaymentMethod::Cash->value,
            'date' => $date->toDateString(),
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'user_id' => $user?->getKey(),
        ]);
    }

    /**
     * Drop the ledger rows a source document wrote, so a voided sale, a
     * deleted expense or a reversed document cannot leave phantom money behind.
     */
    public function forgetSource(CashSourceType $sourceType, string|int $sourceId): void
    {
        CashTransaction::query()
            ->where('source_type', $sourceType->value)
            ->where('source_id', (string) $sourceId)
            ->delete();
    }

    /**
     * Cash carried into a day: everything banked before it, less what left.
     *
     * The figures are replayed from the ledger rather than read off the previous
     * day's closing row, so a day that was never closed still carries a correct
     * opening balance instead of an invented one.
     */
    public function openingBalanceCents(CarbonInterface $date): int
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        $rows = CashTransaction::query()
            ->whereDate('date', '<', $day->toDateString())
            ->select('direction')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('direction')
            ->get();

        return $this->sumCents($rows, CashDirection::In) - $this->sumCents($rows, CashDirection::Out);
    }

    /**
     * Everything a closing row needs, without committing to it.
     *
     * @param  array<string, mixed>  $filters
     * @return array{date: string, opening_balance: string, total_in: string, total_out: string, closing_balance: string, is_locked: bool}
     */
    public function previewClosing(CarbonInterface $date, array $filters = []): array
    {
        $summary = $this->summaryFor($date, $filters);

        return [
            'date' => $summary['date'],
            'opening_balance' => $summary['opening_balance'],
            'total_in' => $summary['total_in'],
            'total_out' => $summary['total_out'],
            'closing_balance' => $summary['closing_balance'],
            'is_locked' => $summary['locked'],
        ];
    }

    /**
     * Freeze a day. Closing an already locked day is rejected rather than
     * silently re-stamped, so the counted figure and its author stay honest.
     */
    public function close(CarbonInterface $date, User $user, array $filters = [], ?string $note = null): DailyClosing
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $preview = $this->previewClosing($day, $filters);

        if ($preview['is_locked']) {
            throw ValidationException::withMessages([
                'date' => "The day {$preview['date']} has already been closed.",
            ]);
        }

        return DB::transaction(fn (): DailyClosing => DailyClosing::query()->create([
            'date' => $day->toDateString(),
            'opening_balance' => $preview['opening_balance'],
            'total_in' => $preview['total_in'],
            'total_out' => $preview['total_out'],
            'closing_balance' => $preview['closing_balance'],
            'closed_by' => $user->getKey(),
            'closed_at' => now(),
            'note' => $note,
        ]));
    }

    /**
     * Unlock a day so a correction can be posted, keeping the reopen stamp.
     */
    public function reopen(DailyClosing $closing, User $user): DailyClosing
    {
        if (! $closing->isLocked()) {
            throw ValidationException::withMessages([
                'closing' => 'This day is not closed.',
            ]);
        }

        $closing->update([
            'reopened_by' => $user->getKey(),
            'reopened_at' => now(),
            'closed_by' => null,
            'closed_at' => null,
        ]);

        return $closing;
    }

    /**
     * Refuse to post money into a locked day.
     */
    public function guardOpenDay(CarbonInterface $date): void
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $closing = DailyClosing::query()
            ->whereDate('date', $day->toDateString())
            ->whereNotNull('closed_at')
            ->first();

        if ($closing !== null) {
            throw ValidationException::withMessages([
                'date' => "The day {$day->toDateString()} is closed. Reopen it before recording more money.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<CashTransaction>  $query
     */
    private function filtered(Builder $query, array $filters): Builder
    {
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<CashTransaction>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (isset($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (isset($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $transactionQuery) use ($search): void {
                $transactionQuery
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhere('source_id', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Sum one direction, optionally narrowed to a single method.
     *
     * The `CashTransaction` casts turn the grouped columns into enums, so the
     * rows are compared against the enums themselves.
     *
     * @param  Collection<int, CashTransaction>  $rows
     */
    private function sumCents(Collection $rows, CashDirection $direction, ?PaymentMethod $method = null): int
    {
        $row = $rows->firstWhere(
            fn (CashTransaction $row): bool => $row->direction === $direction
                && ($method === null || $row->method === $method),
        );

        return $this->toCents($row->total ?? 0);
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
