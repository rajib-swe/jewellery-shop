<?php

namespace App\Services;

use App\CashSourceType;
use App\ItemStatus;
use App\MakingType;
use App\Models\Category;
use App\Models\Pawn;
use App\Models\User;
use App\PawnPaymentType;
use App\PawnStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PawnService
{
    public function __construct(
        private readonly PawnInterestService $interest,
        private readonly StockService $stock,
        private readonly SettingsService $settings,
        private readonly InvoiceNumberService $invoiceNumbers,
        private readonly CashBookService $cashBook,
    ) {}

    /**
     * @param  array{search?: ?string, status?: ?string, customer_id?: ?int, overdue?: ?bool, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        // The list needs the full rows: PawnResource replays the ledger for the
        // summary and serialises both collections, so a column subset here would
        // hand the resource null attributes.
        $query = Pawn::query()->with([
            'customer:id,code,name,phone',
            'items',
            'payments:id,pawn_id,type,amount,date,method,reference,note',
        ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (($filters['overdue'] ?? null) !== null) {
            $query->where('status', PawnStatus::Active->value);

            $filters['overdue']
                ? $query->whereDate('due_date', '<', today()->toDateString())
                : $query->whereDate('due_date', '>=', today()->toDateString());
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $pawnQuery) use ($search): void {
                $pawnQuery
                    ->where('pawn_no', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function find(Pawn $pawn): Pawn
    {
        return $this->withDetails($pawn);
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     date?: ?string,
     *     due_date?: ?string,
     *     principal: string|float|int,
     *     interest_rate?: string|float|null,
     *     notes?: ?string,
     *     items: list<array{
     *         description: string,
     *         karat: int,
     *         gross_weight: string|float,
     *         stone_weight?: string|float|null,
     *         estimated_value: string|float,
     *         category_id?: ?int,
     *     }>,
     * }  $data
     * @param  array<int, UploadedFile|null>  $photos  Item photos keyed by the same index as the item rows.
     */
    public function create(array $data, User $user, array $photos = []): Pawn
    {
        $shop = $this->settings->all();
        $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
        $termDays = max(1, (int) $shop['pawn_term_days']);
        $dueDate = isset($data['due_date'])
            ? CarbonImmutable::parse($data['due_date'])->startOfDay()
            : $date->addDays($termDays);
        $interestRate = (float) ($data['interest_rate'] ?? $shop['default_pawn_interest_rate']);
        $principalCents = $this->toCents((float) $data['principal']);
        $items = Arr::get($data, 'items', []);

        if ($interestRate <= 0) {
            throw ValidationException::withMessages([
                'interest_rate' => 'The monthly interest rate must be greater than zero.',
            ]);
        }

        if ($principalCents <= 0) {
            throw ValidationException::withMessages([
                'principal' => 'The principal must be greater than zero.',
            ]);
        }

        $this->guardLoanToValue($items, $principalCents, (float) $shop['pawn_max_ltv_percentage']);

        $storedPhotos = $this->storePhotos($photos);

        try {
            return DB::transaction(function () use ($data, $user, $date, $dueDate, $interestRate, $principalCents, $items, $storedPhotos): Pawn {
                $pawn = Pawn::query()->create([
                    'pawn_no' => $this->invoiceNumbers->nextPawnNumber($date),
                    'customer_id' => $data['customer_id'],
                    'date' => $date->toDateString(),
                    'principal' => $this->fromCents($principalCents),
                    'interest_rate' => number_format($interestRate, 2, '.', ''),
                    'due_date' => $dueDate->toDateString(),
                    'status' => PawnStatus::Active,
                    'notes' => $data['notes'] ?? null,
                    'user_id' => $user->getKey(),
                ]);

                foreach ($items as $index => $item) {
                    $grossWeight = (float) $item['gross_weight'];
                    $stoneWeight = (float) ($item['stone_weight'] ?? 0);

                    if ($stoneWeight < 0 || $stoneWeight > $grossWeight) {
                        throw ValidationException::withMessages([
                            "items.{$index}.stone_weight" => 'The stone weight cannot exceed the gross weight.',
                        ]);
                    }

                    $pawn->items()->create([
                        'description' => $item['description'],
                        'karat' => (int) $item['karat'],
                        'gross_weight' => number_format($grossWeight, 3, '.', ''),
                        'stone_weight' => number_format($stoneWeight, 3, '.', ''),
                        'net_weight' => number_format($grossWeight - $stoneWeight, 3, '.', ''),
                        'estimated_value' => $this->fromCents($this->toCents((float) $item['estimated_value'])),
                        'photo' => $storedPhotos[$index] ?? null,
                        'category_id' => $item['category_id'] ?? null,
                    ]);
                }

                // The principal leaves the shop as cash the moment the pawn is
                // taken, so it is booked out against the account it funds.
                $this->cashBook->recordOut(
                    CashSourceType::PawnDisbursement,
                    $pawn->getKey(),
                    (float) $pawn->principal,
                    $user,
                    [
                        'date' => $pawn->date->toDateString(),
                        'method' => 'cash',
                        'note' => "Pawn disbursement {$pawn->pawn_no}",
                    ],
                );

                return $this->withDetails($pawn);
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($storedPhotos);

            throw $exception;
        }
    }

    /**
     * Record a collection, allocated to the interest due first and the rest to
     * the principal, which is how the counter staff hand the money back.
     *
     * @param  array{amount: string|float|int, date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function addPayment(Pawn $pawn, array $data, User $user): Pawn
    {
        return DB::transaction(function () use ($pawn, $data, $user): Pawn {
            $lockedPawn = Pawn::query()
                ->with('payments')
                ->lockForUpdate()
                ->findOrFail($pawn->getKey());

            $this->guardOpen($lockedPawn);

            $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
            $summary = $this->interest->calculate($lockedPawn, $date);
            $amountCents = $this->toCents((float) $data['amount']);
            $payableCents = $this->toCents($summary['total_payable']);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment amount must be greater than zero.',
                ]);
            }

            if ($payableCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This pawn account is already fully settled.',
                ]);
            }

            if ($amountCents > $payableCents) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot be greater than the total payable amount.',
                ]);
            }

            $interestShare = min($amountCents, $this->toCents($summary['interest_due']));
            $principalShare = $amountCents - $interestShare;

            foreach ($this->shares($interestShare, $principalShare) as $share) {
                $createdPayment = $lockedPawn->payments()->create([
                    ...$share,
                    'date' => $date->toDateString(),
                    'method' => $data['method'] ?? 'cash',
                    'reference' => $data['reference'] ?? null,
                    'note' => $data['note'] ?? null,
                    'user_id' => $user->getKey(),
                ]);

                $this->cashBook->recordIn(
                    CashSourceType::PawnPayment,
                    $createdPayment->getKey(),
                    (float) $createdPayment->amount,
                    $user,
                    [
                        'date' => $createdPayment->date->toDateString(),
                        'method' => $createdPayment->method->value,
                        'reference' => $createdPayment->reference,
                        'note' => "Pawn {$lockedPawn->pawn_no} collection",
                    ],
                );
            }

            return $this->withDetails($lockedPawn);
        });
    }

    /**
     * Close the account once the whole payable amount is handed back.
     *
     * @param  array{amount?: string|float|int|null, date?: ?string, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function redeem(Pawn $pawn, array $data, User $user): Pawn
    {
        return DB::transaction(function () use ($pawn, $data, $user): Pawn {
            $lockedPawn = Pawn::query()
                ->with('payments')
                ->lockForUpdate()
                ->findOrFail($pawn->getKey());

            $this->guardOpen($lockedPawn);

            $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
            $summary = $this->interest->calculate($lockedPawn, $date);
            $payableCents = $this->toCents($summary['total_payable']);

            if ($payableCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This pawn account is already fully settled.',
                ]);
            }

            $amountCents = isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
                ? $this->toCents((float) $data['amount'])
                : $payableCents;

            if ($amountCents !== $payableCents) {
                throw ValidationException::withMessages([
                    'amount' => 'A redemption must clear the whole payable amount of the account.',
                ]);
            }

            $redeemPayment = $lockedPawn->payments()->create([
                'type' => PawnPaymentType::Redeem,
                'amount' => $this->fromCents($amountCents),
                'date' => $date->toDateString(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $this->cashBook->recordIn(
                CashSourceType::PawnPayment,
                $redeemPayment->getKey(),
                (float) $redeemPayment->amount,
                $user,
                [
                    'date' => $redeemPayment->date->toDateString(),
                    'method' => $redeemPayment->method->value,
                    'reference' => $redeemPayment->reference,
                    'note' => "Pawn {$lockedPawn->pawn_no} redemption",
                ],
            );

            $lockedPawn->update([
                'status' => PawnStatus::Redeemed,
                'redeemed_at' => now(),
                'redeemed_by' => $user->getKey(),
                'close_reason' => $data['note'] ?? null,
            ]);

            return $this->withDetails($lockedPawn);
        });
    }

    /**
     * Settle the interest already due and push the due date out by a fresh term,
     * which is the usual way a borrower extends a pawn. Simple interest keeps
     * accruing from the original disbursement date, so the renewal never erases
     * interest that was already owed.
     *
     * @param  array{date?: ?string, term_days?: ?int, note?: ?string}  $data
     */
    public function renew(Pawn $pawn, array $data, User $user): Pawn
    {
        return DB::transaction(function () use ($pawn, $data, $user): Pawn {
            $lockedPawn = Pawn::query()
                ->with('payments')
                ->lockForUpdate()
                ->findOrFail($pawn->getKey());

            $this->guardOpen($lockedPawn);

            $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
            $summary = $this->interest->calculate($lockedPawn, $date);
            $interestDueCents = $this->toCents($summary['interest_due']);

            if ($interestDueCents > 0) {
                $renewalPayment = $lockedPawn->payments()->create([
                    'type' => PawnPaymentType::Interest,
                    'amount' => $this->fromCents($interestDueCents),
                    'date' => $date->toDateString(),
                    'method' => 'cash',
                    'note' => 'Interest settled on renewal',
                    'user_id' => $user->getKey(),
                ]);

                $this->cashBook->recordIn(
                    CashSourceType::PawnPayment,
                    $renewalPayment->getKey(),
                    (float) $renewalPayment->amount,
                    $user,
                    [
                        'date' => $renewalPayment->date->toDateString(),
                        'method' => 'cash',
                        'note' => "Pawn {$lockedPawn->pawn_no} interest on renewal",
                    ],
                );
            }

            $termDays = max(1, (int) ($data['term_days'] ?? $this->settings->all()['pawn_term_days']));

            $lockedPawn->update([
                'due_date' => $date->addDays($termDays)->toDateString(),
                'renewed_at' => now(),
            ]);

            return $this->withDetails($lockedPawn);
        });
    }

    /**
     * Write the account off after the grace period. The pledged items can be
     * booked into the inventory so the shop keeps a record of what it holds.
     *
     * @param  array{reason?: ?string, move_to_inventory?: ?bool, item_status?: ?string, date?: ?string}  $data
     */
    public function forfeit(Pawn $pawn, array $data, User $user): Pawn
    {
        return DB::transaction(function () use ($pawn, $data, $user): Pawn {
            $lockedPawn = Pawn::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($pawn->getKey());

            $this->guardOpen($lockedPawn);

            $graceDays = max(0, (int) $this->settings->all()['pawn_grace_days']);

            if (! $lockedPawn->due_date->addDays($graceDays)->isBefore(today())) {
                throw ValidationException::withMessages([
                    'pawn' => "This pawn is still inside its {$graceDays} day grace period.",
                ]);
            }

            $itemStatus = ItemStatus::tryFrom((string) ($data['item_status'] ?? '')) ?? ItemStatus::Scrap;

            $lockedPawn->update([
                'status' => PawnStatus::Forfeited,
                'forfeited_at' => now(),
                'forfeited_by' => $user->getKey(),
                'close_reason' => $data['reason'] ?? null,
            ]);

            if (! ($data['move_to_inventory'] ?? false)) {
                return $this->withDetails($lockedPawn);
            }

            foreach ($lockedPawn->items as $pawnItem) {
                if ($pawnItem->item_id !== null) {
                    continue;
                }

                $categoryId = $pawnItem->category_id
                    ?? Category::query()->value('id');

                if ($categoryId === null) {
                    continue;
                }

                $item = $this->stock->createInbound(
                    [
                        'category_id' => (int) $categoryId,
                        'name' => $pawnItem->description,
                        'karat' => (int) $pawnItem->karat,
                        'gross_weight' => (string) $pawnItem->gross_weight,
                        'stone_weight' => (string) $pawnItem->stone_weight,
                        'making_type' => MakingType::Fixed->value,
                        'making_value' => '0.00',
                    ],
                    $user,
                    null,
                    $itemStatus,
                    'pawn',
                    (string) $lockedPawn->getKey(),
                );

                $pawnItem->update(['item_id' => $item->getKey()]);
            }

            return $this->withDetails($lockedPawn);
        });
    }

    /**
     * Split a collection into the interest and principal ledger rows it pays for.
     *
     * @return list<array{type: PawnPaymentType, amount: string}>
     */
    private function shares(int $interestCents, int $principalCents): array
    {
        $shares = [];

        if ($interestCents > 0) {
            $shares[] = [
                'type' => PawnPaymentType::Interest,
                'amount' => $this->fromCents($interestCents),
            ];
        }

        if ($principalCents > 0) {
            $shares[] = [
                'type' => PawnPaymentType::Principal,
                'amount' => $this->fromCents($principalCents),
            ];
        }

        return $shares;
    }

    /**
     * A pawn may never advance more than the configured share of the pledged
     * value, so the shop keeps a collateral margin.
     *
     * @param  list<array{estimated_value?: string|float|null}>  $items
     */
    private function guardLoanToValue(array $items, int $principalCents, float $maxLoanToValuePercentage): void
    {
        $allowedCents = (int) round(
            array_sum(array_map(static fn (array $item): float => (float) ($item['estimated_value'] ?? 0), $items))
            * $maxLoanToValuePercentage
            / 100
            * 100
        );

        if ($allowedCents > 0 && $principalCents > $allowedCents) {
            throw ValidationException::withMessages([
                'principal' => "The principal cannot exceed {$maxLoanToValuePercentage}% of the pledged value.",
            ]);
        }
    }

    private function guardOpen(Pawn $pawn): void
    {
        if ($pawn->status !== PawnStatus::Active) {
            throw ValidationException::withMessages([
                'pawn' => "A {$pawn->status->value} pawn account cannot receive further transactions.",
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile|null>  $photos
     * @return array<int, string>
     */
    private function storePhotos(array $photos): array
    {
        $stored = [];

        foreach ($photos as $index => $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            $path = $photo->store('pawns/items', 'public');

            if ($path === false) {
                $this->deletePhotos($stored);

                throw new RuntimeException('Unable to store the pawn item photo.');
            }

            $stored[$index] = $path;
        }

        return $stored;
    }

    /**
     * @param  array<int, string>  $photos
     */
    private function deletePhotos(array $photos): void
    {
        foreach ($photos as $photo) {
            Storage::disk('public')->delete($photo);
        }
    }

    private function withDetails(Pawn $pawn): Pawn
    {
        return $pawn->load([
            'customer',
            'user:id,name',
            'redeemedBy:id,name',
            'forfeitedBy:id,name',
            'items',
            'payments.user:id,name',
        ]);
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
