<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /**
     * @param  array{search?: ?string, type?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->withTotals(Supplier::query())->withCount(['purchases', 'payments']);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $supplierQuery) use ($search): void {
                $supplierQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('nid', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array{name: string, type: string, phone?: ?string, address?: ?string, nid?: ?string, notes?: ?string}  $data
     */
    public function create(array $data): Supplier
    {
        return DB::transaction(fn (): Supplier => Supplier::query()->create($data));
    }

    /**
     * @param  array{name?: string, type?: string, phone?: ?string, address?: ?string, nid?: ?string, notes?: ?string}  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            $supplier->update($data);

            return $supplier->refresh();
        });
    }

    /**
     * A supplier with recorded purchases is part of the audit trail, so it can
     * only be removed while nothing points at it.
     */
    public function delete(Supplier $supplier): void
    {
        if ($supplier->purchases()->exists() || $supplier->payments()->exists()) {
            throw ValidationException::withMessages([
                'supplier' => 'This supplier has purchases or payments recorded against it.',
            ]);
        }

        DB::transaction(function () use ($supplier): void {
            $supplier->delete();
        });
    }

    public function find(Supplier $supplier): Supplier
    {
        return $this->withDetails($supplier);
    }

    /**
     * Attach the purchases and payments totals as subselects.
     *
     * The supplier list shows what the shop owes every supplier and karigor, and
     * replaying a full ledger per row would be two extra queries per supplier.
     * The sum is display only; {@see ledger()} remains the authority.
     *
     * `select()` replaces any columns already on the query, so this must run
     * before `withCount()` or the count subselects are silently dropped.
     */
    private function withTotals(Builder $query): Builder
    {
        $purchases = Purchase::query()
            ->selectRaw('COALESCE(SUM(total), 0)')
            ->whereColumn('supplier_id', 'suppliers.id');

        $payments = SupplierPayment::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('supplier_id', 'suppliers.id');

        return $query
            ->select('suppliers.*')
            ->selectSub($purchases, 'purchases_total')
            ->selectSub($payments, 'payments_total');
    }

    /**
     * The supplier ledger: every purchase and every payment on one running
     * balance, which is exactly "purchases minus payments".
     *
     * A purchase is a debit because the shop owes the supplier, a payment is a
     * credit. Sorting is stable, so on a shared date the purchase is listed
     * before the payment that settles it, which is how the paper ledger reads.
     *
     * @return array{balance: string, total_purchases: string, total_payments: string, transactions: list<array<string, mixed>>}
     */
    public function ledger(Supplier $supplier): array
    {
        $purchases = $supplier->purchases()
            ->with('items:id,purchase_id,net_weight')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $payments = $supplier->payments()
            ->with('purchase:id,purchase_no')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $transactions = $purchases
            ->map(fn (Purchase $purchase): array => [
                'kind' => 'purchase',
                'id' => $purchase->id,
                'reference' => $purchase->purchase_no,
                'purchase_id' => $purchase->id,
                'date' => $purchase->date->toDateString(),
                'debit' => (string) $purchase->total,
                'credit' => (string) $purchase->paid,
                'weight' => number_format($purchase->items->sum(
                    fn (PurchaseItem $item): float => (float) $item->net_weight,
                ), 3, '.', ''),
                'method' => null,
                'note' => $purchase->notes,
            ])
            ->concat($payments->map(fn (SupplierPayment $payment): array => [
                'kind' => 'payment',
                'id' => $payment->id,
                'reference' => $payment->purchase?->purchase_no,
                'purchase_id' => $payment->purchase_id,
                'date' => $payment->date->toDateString(),
                'debit' => '0.00',
                'credit' => (string) $payment->amount,
                'weight' => null,
                'method' => $payment->method->value,
                'note' => $payment->note,
            ]))
            ->sortBy('date')
            ->values()
            ->all();

        $totalPurchasesCents = $purchases->sum(
            fn (Purchase $purchase): int => (int) round((float) $purchase->total * 100),
        );
        $totalPaymentsCents = $payments->sum(
            fn (SupplierPayment $payment): int => (int) round((float) $payment->amount * 100),
        );

        return [
            'balance' => $this->fromCents($totalPurchasesCents - $totalPaymentsCents),
            'total_purchases' => $this->fromCents($totalPurchasesCents),
            'total_payments' => $this->fromCents($totalPaymentsCents),
            'transactions' => $transactions,
        ];
    }

    private function withDetails(Supplier $supplier): Supplier
    {
        return $this->withTotals(Supplier::query()->whereKey($supplier->getKey()))
            ->withCount(['purchases', 'payments'])
            ->firstOrFail();
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
