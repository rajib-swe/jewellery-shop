<?php

namespace App\Services;

use App\CashSourceType;
use App\ItemStatus;
use App\Karat;
use App\MakingType;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly GoldRateService $goldRates,
        private readonly StockService $stock,
        private readonly InvoiceNumberService $invoiceNumbers,
        private readonly CashBookService $cashBook,
    ) {}

    /**
     * @param  array{search?: ?string, supplier_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Purchase::query()->with('supplier');

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $purchaseQuery) use ($search): void {
                $purchaseQuery
                    ->where('purchase_no', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                        $supplierQuery
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

    public function find(Purchase $purchase): Purchase
    {
        return $this->withDetails($purchase);
    }

    /**
     * Record stock bought in.
     *
     * Every line is priced on the server from the karat rate effective on the
     * purchase date, creates a catalogue item through `StockService` as an `in`
     * movement, and the discount is applied once to the invoice, exactly like a
     * sale. A payment made at the counter settles the purchase straight away so
     * the supplier ledger and `purchases.paid` can never disagree.
     *
     * @param  array{
     *     supplier_id: int,
     *     date?: ?string,
     *     discount?: string|float|int,
     *     notes?: ?string,
     *     items: list<array{
     *         name: string,
     *         karat: int,
     *         gross_weight: string|float,
     *         stone_weight?: string|float|null,
     *         rate?: string|float|null,
     *         making_value?: string|float|null,
     *         category_id?: ?int,
     *     }>,
     *     payment?: array{method?: ?string, amount?: string|float|int, reference?: ?string, date?: ?string}|null,
     * }  $data
     */
    public function create(array $data, User $user, bool $canOverrideRate = false): Purchase
    {
        $date = CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay();
        $lines = Arr::get($data, 'items', []);
        $payment = Arr::get($data, 'payment');
        $rateCache = [];

        return DB::transaction(function () use ($data, $user, $canOverrideRate, $date, $lines, $payment, $rateCache): Purchase {
            $purchase = Purchase::query()->create([
                'purchase_no' => $this->invoiceNumbers->nextPurchaseNumber($date),
                'supplier_id' => $data['supplier_id'],
                'date' => $date->toDateString(),
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'paid' => 0,
                'due' => 0,
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $subtotalCents = 0;

            foreach ($lines as $line) {
                $karat = (int) $line['karat'];
                $rate = $this->resolveRate($karat, $date, $line['rate'] ?? null, $canOverrideRate, $rateCache);
                $grossWeight = (float) $line['gross_weight'];
                $stoneWeight = (float) ($line['stone_weight'] ?? 0);
                $netWeight = $grossWeight - $stoneWeight;

                if ($netWeight <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'The stone weight cannot be greater than or equal to the gross weight.',
                    ]);
                }

                $makingCents = $this->toCents((float) ($line['making_value'] ?? 0));
                $amountCents = $this->toCents($netWeight * $rate) + $makingCents;

                $item = $this->stock->createInbound(
                    [
                        'category_id' => $line['category_id'],
                        'name' => $line['name'],
                        'karat' => $karat,
                        'gross_weight' => number_format($grossWeight, 3, '.', ''),
                        'stone_weight' => number_format($stoneWeight, 3, '.', ''),
                        'making_type' => MakingType::Fixed->value,
                        'making_value' => '0.00',
                    ],
                    $user,
                    null,
                    ItemStatus::InStock,
                    'purchase',
                    (string) $purchase->getKey(),
                );

                $purchase->items()->create([
                    'item_id' => $item->getKey(),
                    'tag_no' => $item->tag_no,
                    'name' => $line['name'],
                    'karat' => $karat,
                    'gross_weight' => number_format($grossWeight, 3, '.', ''),
                    'stone_weight' => number_format($stoneWeight, 3, '.', ''),
                    'net_weight' => number_format($netWeight, 3, '.', ''),
                    'rate' => $this->fromCents($this->toCents($rate)),
                    'making_value' => $this->fromCents($makingCents),
                    'amount' => $this->fromCents($amountCents),
                ]);

                $subtotalCents += $amountCents;
            }

            $discountCents = $this->toCents((float) ($data['discount'] ?? 0));

            if ($discountCents < 0 || $discountCents > $subtotalCents) {
                throw ValidationException::withMessages([
                    'discount' => 'The discount cannot be negative or greater than the subtotal.',
                ]);
            }

            $totalCents = $subtotalCents - $discountCents;
            $paidCents = 0;

            if (is_array($payment) && ($payment['amount'] ?? null) !== null && (float) $payment['amount'] > 0) {
                $paidCents = $this->toCents((float) $payment['amount']);

                if ($paidCents > $totalCents) {
                    throw ValidationException::withMessages([
                        'payment' => 'The paid amount cannot be greater than the purchase total.',
                    ]);
                }

                $paymentDate = isset($payment['date'])
                    ? CarbonImmutable::parse($payment['date'])->startOfDay()->toDateString()
                    : $date->toDateString();

                $createdPayment = $purchase->payments()->create([
                    'supplier_id' => $purchase->supplier_id,
                    'amount' => $this->fromCents($paidCents),
                    'date' => $paymentDate,
                    'method' => $payment['method'] ?? 'cash',
                    'reference' => $payment['reference'] ?? null,
                    'note' => null,
                    'user_id' => $user->getKey(),
                ]);

                $this->cashBook->recordOut(
                    CashSourceType::SupplierPayment,
                    $createdPayment->getKey(),
                    (float) $createdPayment->amount,
                    $user,
                    [
                        'date' => $paymentDate,
                        'method' => $createdPayment->method->value,
                        'reference' => $createdPayment->reference,
                        'note' => "Purchase {$purchase->purchase_no}",
                    ],
                );
            }

            $purchase->update([
                'subtotal' => $this->fromCents($subtotalCents),
                'discount' => $this->fromCents($discountCents),
                'total' => $this->fromCents($totalCents),
                'paid' => $this->fromCents($paidCents),
                'due' => $this->fromCents($totalCents - $paidCents),
            ]);

            return $this->withDetails($purchase);
        });
    }

    /**
     * Pay a supplier. With a `purchase_id` the money settles that purchase,
     * otherwise it is an advance on account and only moves the ledger balance.
     *
     * @param  array{amount: string|float|int, date?: ?string, method?: ?string, reference?: ?string, note?: ?string, purchase_id?: ?int}  $data
     */
    public function addPayment(Supplier $supplier, array $data, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data, $user): SupplierPayment {
            $amountCents = $this->toCents((float) $data['amount']);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment amount must be greater than zero.',
                ]);
            }

            $purchase = null;

            if (isset($data['purchase_id'])) {
                $purchase = Purchase::query()
                    ->where('supplier_id', $supplier->getKey())
                    ->lockForUpdate()
                    ->find($data['purchase_id']);

                if ($purchase === null) {
                    throw ValidationException::withMessages([
                        'purchase_id' => 'That purchase does not belong to this supplier.',
                    ]);
                }

                $dueCents = $this->toCents((float) $purchase->due);

                if ($dueCents <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'This purchase is already fully settled.',
                    ]);
                }

                if ($amountCents > $dueCents) {
                    throw ValidationException::withMessages([
                        'amount' => 'The payment cannot be greater than the outstanding due on this purchase.',
                    ]);
                }
            }

            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplier->getKey(),
                'purchase_id' => $purchase?->getKey(),
                'amount' => $this->fromCents($amountCents),
                'date' => CarbonImmutable::parse($data['date'] ?? 'today')->startOfDay()->toDateString(),
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $this->cashBook->recordOut(
                CashSourceType::SupplierPayment,
                $payment->getKey(),
                (float) $payment->amount,
                $user,
                [
                    'date' => $payment->date->toDateString(),
                    'method' => $payment->method->value,
                    'reference' => $payment->reference,
                    'note' => $data['note'] ?? "Payment to {$supplier->name}",
                ],
            );

            if ($purchase !== null) {
                $paidCents = $this->toCents((float) $purchase->paid) + $amountCents;
                $totalCents = $this->toCents((float) $purchase->total);

                $purchase->update([
                    'paid' => $this->fromCents($paidCents),
                    'due' => $this->fromCents($totalCents - $paidCents),
                ]);
            }

            return $payment;
        });
    }

    /**
     * The current per-gram rate for a karat, used by the purchase form to show
     * what a line will cost before it is saved.
     *
     * @return array<int, string>
     */
    public function previewRates(CarbonInterface $date): array
    {
        $rates = [];

        foreach (Karat::cases() as $karat) {
            $rate = $this->goldRates->rateFor($karat->value, $date);

            if ($rate !== null) {
                $rates[$karat->value] = number_format($rate, 2, '.', '');
            }
        }

        return $rates;
    }

    /**
     * @param  array<int, float>  $rateCache
     */
    private function resolveRate(
        int $karat,
        CarbonInterface $date,
        string|float|null $override,
        bool $canOverrideRate,
        array &$rateCache,
    ): float {
        $shopRate = $rateCache[$karat] ??= $this->goldRates->rateFor($karat, $date);

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

    private function withDetails(Purchase $purchase): Purchase
    {
        return $purchase->load([
            'supplier',
            'user:id,name',
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
