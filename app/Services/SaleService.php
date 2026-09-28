<?php

namespace App\Services;

use App\ItemStatus;
use App\MakingType;
use App\Models\Item;
use App\Models\Sale;
use App\Models\User;
use App\SaleStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InvoiceNumberService $invoiceNumbers,
        private readonly StockService $stock,
        private readonly SettingsService $settings,
        private readonly GoldRateService $goldRates,
    ) {}

    /**
     * @param  array{search?: ?string, status?: ?string, customer_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Sale::query()->with('customer');

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $saleQuery) use ($search): void {
                $saleQuery
                    ->where('invoice_no', 'like', "%{$search}%")
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

    public function find(Sale $sale): Sale
    {
        return $this->withDetails($sale);
    }

    /**
     * @param  array{
     *     customer_id?: ?int,
     *     date?: ?string,
     *     discount?: string|float|int,
     *     notes?: ?string,
     *     items: list<array{
     *         item_id?: ?int,
     *         name?: ?string,
     *         karat?: ?int,
     *         weight?: string|float|null,
     *         rate?: string|float|null,
     *         making_type?: string|null,
     *         making_value?: string|float|null,
     *         stone_price?: string|float|null,
     *     }>,
     *     payments?: list<array{method: string, amount: string|float|int, reference?: ?string}>,
     *     exchanges?: list<array{description: string, karat: int, weight: string|float, rate?: string|float|null, category_id?: ?int}>,
     * }  $data
     */
    public function create(array $data, User $user, bool $canOverrideRate = false): Sale
    {
        $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
        $lines = Arr::get($data, 'items', []);
        $exchanges = Arr::get($data, 'exchanges', []);
        $payments = Arr::get($data, 'payments', []);

        return DB::transaction(function () use ($data, $user, $canOverrideRate, $date, $lines, $exchanges, $payments): Sale {
            $rateCache = [];
            $items = $this->lockSellableItems($lines);
            $sale = Sale::query()->create([
                'invoice_no' => $this->invoiceNumbers->nextInvoiceNumber($date),
                'customer_id' => $data['customer_id'] ?? null,
                'date' => $date->toDateString(),
                'subtotal' => 0,
                'discount' => 0,
                'vat' => 0,
                'exchange_amount' => 0,
                'total' => 0,
                'paid' => 0,
                'due' => 0,
                'status' => SaleStatus::Completed,
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $subtotalCents = 0;

            foreach ($lines as $line) {
                $item = $items->get($line['item_id'] ?? null);
                $karat = $item === null ? (int) $line['karat'] : $item->karat;
                $rate = $this->resolveRate(
                    $karat,
                    $date,
                    $line['rate'] ?? null,
                    $canOverrideRate,
                    $rateCache,
                );
                $weightGrams = $item === null ? (float) $line['weight'] : (float) $item->net_weight;
                $makingType = $item === null ? MakingType::from($line['making_type'] ?? 'fixed') : $item->making_type;
                $makingValue = $item === null ? (float) ($line['making_value'] ?? $line['making'] ?? 0) : (float) $item->making_value;
                $stonePriceGrams = $item === null ? (float) ($line['stone_price'] ?? 0) : (float) $item->stone_price;
                $goldValueCents = $this->toCents($weightGrams * $rate);
                $makingCents = $this->toCents(
                    $this->makingAmount($makingType, $makingValue, $weightGrams, $goldValueCents),
                );
                $stonePriceCents = $this->toCents($stonePriceGrams);
                $lineTotalCents = $goldValueCents + $makingCents + $stonePriceCents;

                $sale->items()->create([
                    'item_id' => $item?->getKey(),
                    'tag_no' => $item?->tag_no ?? '',
                    'name' => $item === null ? $line['name'] : $item->name,
                    'karat' => $karat,
                    'weight' => number_format($weightGrams, 3, '.', ''),
                    'rate' => $this->fromCents($this->toCents($rate)),
                    'gold_value' => $this->fromCents($goldValueCents),
                    'making' => $this->fromCents($makingCents),
                    'stone_price' => $this->fromCents($stonePriceCents),
                    'line_total' => $this->fromCents($lineTotalCents),
                ]);

                $subtotalCents += $lineTotalCents;

                if ($item !== null) {
                    $this->stock->markSold($item, $user, 'sale', (string) $sale->getKey(), $weightGrams);
                }
            }

            $exchangeCents = 0;

            foreach ($exchanges as $exchange) {
                $rate = $this->resolveRate(
                    (int) $exchange['karat'],
                    $date,
                    $exchange['rate'] ?? null,
                    $canOverrideRate,
                    $rateCache,
                );
                $weightGrams = (float) $exchange['weight'];
                $amountCents = $this->toCents($weightGrams * $rate);

                $exchangeCents += $amountCents;

                $scrapItemId = null;

                if (isset($exchange['category_id'])) {
                    $scrapItem = $this->stock->createInbound(
                        [
                            'category_id' => (int) $exchange['category_id'],
                            'name' => $exchange['description'],
                            'karat' => (int) $exchange['karat'],
                            'gross_weight' => number_format($weightGrams, 3, '.', ''),
                            'stone_weight' => '0.000',
                            'making_type' => MakingType::Fixed->value,
                            'making_value' => '0.00',
                        ],
                        $user,
                        null,
                        ItemStatus::Scrap,
                        'sale',
                        (string) $sale->getKey(),
                    );

                    $scrapItemId = $scrapItem->getKey();
                }

                $sale->exchanges()->create([
                    'description' => $exchange['description'],
                    'karat' => (int) $exchange['karat'],
                    'weight' => number_format($weightGrams, 3, '.', ''),
                    'rate' => $this->fromCents($this->toCents($rate)),
                    'amount' => $this->fromCents($amountCents),
                    'item_id' => $scrapItemId,
                ]);
            }

            $discountCents = $this->toCents((float) ($data['discount'] ?? 0));

            if ($discountCents < 0 || $discountCents > $subtotalCents) {
                throw ValidationException::withMessages([
                    'discount' => 'The discount cannot be negative or greater than the subtotal.',
                ]);
            }

            $vatPercentage = (float) $this->settings->all()['vat_percentage'];
            $vatCents = $this->toCents(($subtotalCents - $discountCents) / 100 * ($vatPercentage / 100));
            $totalCents = $subtotalCents - $discountCents + $vatCents - $exchangeCents;

            if ($totalCents < 0) {
                throw ValidationException::withMessages([
                    'exchanges' => 'The old gold exchange cannot exceed the invoice total.',
                ]);
            }

            $paidCents = 0;

            foreach ($payments as $payment) {
                $amountCents = $this->toCents((float) $payment['amount']);

                if ($amountCents <= 0) {
                    throw ValidationException::withMessages([
                        'payments' => 'Every payment amount must be greater than zero.',
                    ]);
                }

                $paidCents += $amountCents;
            }

            if ($paidCents > $totalCents) {
                throw ValidationException::withMessages([
                    'payments' => 'The paid amount cannot be greater than the invoice total.',
                ]);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $this->fromCents($this->toCents((float) $payment['amount'])),
                    'reference' => $payment['reference'] ?? null,
                    'user_id' => $user->getKey(),
                ]);
            }

            $sale->update([
                'subtotal' => $this->fromCents($subtotalCents),
                'discount' => $this->fromCents($discountCents),
                'vat' => $this->fromCents($vatCents),
                'exchange_amount' => $this->fromCents($exchangeCents),
                'total' => $this->fromCents($totalCents),
                'paid' => $this->fromCents($paidCents),
                'due' => $this->fromCents($totalCents - $paidCents),
            ]);

            return $this->withDetails($sale);
        });
    }

    /**
     * @param  array{method: string, amount: string|float|int, reference?: ?string}  $data
     */
    public function addPayment(Sale $sale, array $data, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $data, $user): Sale {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($lockedSale->status === SaleStatus::Void) {
                throw ValidationException::withMessages([
                    'sale' => 'A voided sale cannot receive further payments.',
                ]);
            }

            $amountCents = $this->toCents((float) $data['amount']);
            $dueCents = $this->toCents((float) $lockedSale->due);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment amount must be greater than zero.',
                ]);
            }

            if ($amountCents > $dueCents) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot be greater than the outstanding due.',
                ]);
            }

            $lockedSale->payments()->create([
                'method' => $data['method'],
                'amount' => $this->fromCents($amountCents),
                'reference' => $data['reference'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $paidCents = $this->toCents((float) $lockedSale->paid) + $amountCents;
            $totalCents = $this->toCents((float) $lockedSale->total);

            $lockedSale->update([
                'paid' => $this->fromCents($paidCents),
                'due' => $this->fromCents($totalCents - $paidCents),
            ]);

            return $this->withDetails($lockedSale);
        });
    }

    public function void(Sale $sale, User $user, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $user, $reason): Sale {
            $lockedSale = Sale::query()
                ->with(['items', 'exchanges', 'payments'])
                ->lockForUpdate()
                ->findOrFail($sale->getKey());

            if ($lockedSale->status === SaleStatus::Void) {
                throw ValidationException::withMessages([
                    'sale' => 'This sale has already been voided.',
                ]);
            }

            foreach ($lockedSale->items as $saleItem) {
                if ($saleItem->item_id === null) {
                    continue;
                }

                $item = Item::query()->find($saleItem->item_id);

                if ($item === null) {
                    continue;
                }

                $this->stock->restoreInStock(
                    $item,
                    $user,
                    'sale',
                    (string) $lockedSale->getKey(),
                    (float) $saleItem->weight,
                );
            }

            foreach ($lockedSale->exchanges as $exchange) {
                if ($exchange->item_id === null) {
                    continue;
                }

                $scrapItem = Item::query()->find($exchange->item_id);

                if ($scrapItem === null) {
                    continue;
                }

                $this->stock->delete($scrapItem);
            }

            $lockedSale->payments()->delete();

            $lockedSale->update([
                'status' => SaleStatus::Void,
                'paid' => '0.00',
                'due' => '0.00',
                'voided_by' => $user->getKey(),
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            return $this->withDetails($lockedSale);
        });
    }

    /**
     * @param  list<array{item_id?: ?int, name?: ?string, karat?: ?int, weight?: ?string|float}>  $lines
     * @return Collection<int, Item>
     */
    private function lockSellableItems(array $lines): Collection
    {
        $itemIds = array_values(array_filter(array_map(
            static fn (array $line): ?int => isset($line['item_id']) ? (int) $line['item_id'] : null,
            $lines,
        )));

        if (count($itemIds) !== count(array_unique($itemIds))) {
            throw ValidationException::withMessages([
                'items' => 'The same item cannot be added to a sale twice.',
            ]);
        }

        if ($itemIds === []) {
            return new Collection;
        }

        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($itemIds as $itemId) {
            $item = $items->get($itemId);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'items' => "Item {$itemId} does not exist.",
                ]);
            }

            if ($item->status !== ItemStatus::InStock) {
                throw ValidationException::withMessages([
                    'items' => "Item {$item->tag_no} is not available in stock.",
                ]);
            }
        }

        return $items;
    }

    /**
     * @param  array<string, float>  $rateCache
     */
    private function resolveRate(
        int $karat,
        CarbonInterface $date,
        string|float|null $override,
        bool $canOverrideRate,
        array &$rateCache,
    ): float {
        $shopRate = $rateCache[$karat] ??= $this->shopRateFor($karat, $date);

        if ($override === null) {
            if ($shopRate === null) {
                throw ValidationException::withMessages([
                    'items' => "No gold rate is configured for {$karat}K on {$date->toDateString()}.",
                ]);
            }

            return $shopRate;
        }

        if (! $canOverrideRate) {
            throw ValidationException::withMessages([
                'items' => 'You are not allowed to override the gold rate.',
            ]);
        }

        $overrideRate = (float) $override;

        if ($overrideRate <= 0) {
            throw ValidationException::withMessages([
                'items' => 'The rate override must be greater than zero.',
            ]);
        }

        return $overrideRate;
    }

    private function shopRateFor(int $karat, CarbonInterface $date): ?float
    {
        return $this->goldRates->rateFor($karat, $date);
    }

    private function makingAmount(
        MakingType $makingType,
        float $makingValue,
        float $weightGrams,
        int $goldValueCents,
    ): float {
        return match ($makingType) {
            MakingType::PerGram => $makingValue * $weightGrams,
            MakingType::Percent => ($goldValueCents / 100) * ($makingValue / 100),
            default => $makingValue,
        };
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    private function withDetails(Sale $sale): Sale
    {
        return $sale->load([
            'customer',
            'user:id,name',
            'voidedBy:id,name',
            'items',
            'payments.user:id,name',
            'exchanges',
        ]);
    }
}
