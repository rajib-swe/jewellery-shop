<?php

namespace App\Services;

use App\CashDirection;
use App\ItemStatus;
use App\Karat;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Pawn;
use App\Models\PawnPayment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\PawnPaymentType;
use App\PawnStatus;
use App\SaleStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only figures for management: sales, stock, pawn exposure, interest
 * earned, the two ledgers, and a profit summary.
 *
 * Every figure is derived from the transactional tables rather than from a
 * stored total, so a report can never disagree with the module it summarises.
 * Voided sales are excluded throughout: a voided invoice is an audit record, not
 * revenue. Profit is deliberately a cash-and-cost statement of the period
 * (sales received, less stock bought, less expenses) rather than an
 * accrual accounting one, because that is the number a shop owner reconciles
 * against the drawer.
 */
class ReportService
{
    public function __construct(
        private readonly PawnInterestService $pawnInterest,
        private readonly CashBookService $cashBook,
        private readonly GoldRateService $goldRates,
        private readonly SupplierService $suppliers,
    ) {}

    /**
     * Sales over a period, broken down by day or by month.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     group_by: string,
     *     invoice_count: int,
     *     total_sales: string,
     *     total_discount: string,
     *     total_exchange: string,
     *     total_paid: string,
     *     total_due: string,
     *     total_weight: string,
     *     rows: list<array{period: string, invoice_count: int, total: string, paid: string, due: string, weight: string}>,
     * }
     */
    public function sales(CarbonInterface $from, CarbonInterface $to, string $groupBy = 'day'): array
    {
        $groupBy = $groupBy === 'month' ? 'month' : 'day';
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        $sales = Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->with('items:id,sale_id,weight')
            ->orderBy('date')
            ->get();

        $grouped = $sales->groupBy(
            fn (Sale $sale): string => $groupBy === 'month'
                ? $sale->date->format('Y-m')
                : $sale->date->toDateString(),
        );

        $rows = $grouped
            ->map(function (Collection $daySales, string $period): array {
                $totalCents = $this->sumCents($daySales, 'total');
                $paidCents = $this->sumCents($daySales, 'paid');
                $weightGrams = round($daySales->sum(
                    fn (Sale $sale): float => $sale->items->sum(fn ($item): float => (float) $item->weight),
                ), 3);

                return [
                    'period' => $period,
                    'invoice_count' => $daySales->count(),
                    'total' => $this->fromCents($totalCents),
                    'paid' => $this->fromCents($paidCents),
                    'due' => $this->fromCents($totalCents - $paidCents),
                    'weight' => number_format($weightGrams, 3, '.', ''),
                ];
            })
            ->values()
            ->all();

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'group_by' => $groupBy,
            'invoice_count' => $sales->count(),
            'total_sales' => $this->fromCents($this->sumCents($sales, 'total')),
            'total_discount' => $this->fromCents($this->sumCents($sales, 'discount')),
            'total_exchange' => $this->fromCents($this->sumCents($sales, 'exchange_amount')),
            'total_paid' => $this->fromCents($this->sumCents($sales, 'paid')),
            'total_due' => $this->fromCents($this->sumCents($sales, 'due')),
            'total_weight' => number_format(round($sales->sum(
                fn (Sale $sale): float => $sale->items->sum(fn ($item): float => (float) $item->weight),
            ), 3), 3, '.', ''),
            'rows' => $rows,
        ];
    }

    /**
     * In-stock weight and piece count, by karat and by category.
     *
     * Stock value is the net weight priced at the latest gold rate for that
     * karat. An item carries no cost of its own — what the shop paid lives on
     * the purchase — so the rate is the only honest way to value the shelf, and
     * it is the same rate source every other module uses.
     *
     * @return array{
     *     total_items: int,
     *     total_weight: string,
     *     total_value: string,
     *     by_karat: list<array{karat: int, item_count: int, total_weight: string, total_value: string}>,
     *     by_category: list<array{category_id: int, category: string, item_count: int, total_weight: string, total_value: string}>,
     * }
     */
    public function stock(): array
    {
        $items = Item::query()
            ->where('status', ItemStatus::InStock->value)
            ->get(['category_id', 'karat', 'net_weight']);

        // Weight is accumulated per karat first, because the value depends on
        // the karat and a category can hold more than one of them.
        $karats = [];

        foreach ($items as $item) {
            $karat = (int) $item->karat;
            $categoryId = (int) $item->category_id;
            $weight = (float) $item->net_weight;

            $karats[$karat] ??= ['weight' => 0.0, 'count' => 0, 'categories' => []];
            $karats[$karat]['weight'] += $weight;
            $karats[$karat]['count']++;
            $karats[$karat]['categories'][$categoryId] ??= ['weight' => 0.0, 'count' => 0];
            $karats[$karat]['categories'][$categoryId]['weight'] += $weight;
            $karats[$karat]['categories'][$categoryId]['count']++;
        }

        $categoryNames = Category::query()->pluck('name', 'id');

        $byCategory = [];

        foreach (Karat::cases() as $karat) {
            foreach ($karats[$karat->value]['categories'] ?? [] as $categoryId => $categoryTotals) {
                $byCategory[$categoryId] ??= [
                    'category_id' => $categoryId,
                    'category' => (string) ($categoryNames->get($categoryId) ?? ''),
                    'item_count' => 0,
                    'total_weight' => 0.0,
                    'total_value' => 0,
                ];

                $byCategory[$categoryId]['item_count'] += $categoryTotals['count'];
                $byCategory[$categoryId]['total_weight'] += $categoryTotals['weight'];
                $byCategory[$categoryId]['total_value'] += $this->valueCents(
                    $karat->value,
                    $categoryTotals['weight'],
                );
            }
        }

        $byCategory = collect($byCategory)
            ->map(fn (array $row): array => [
                ...$row,
                'total_weight' => number_format($row['total_weight'], 3, '.', ''),
                'total_value' => $this->fromCents($row['total_value']),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['total_weight'])
            ->values()
            ->all();

        $byKarat = collect(Karat::cases())
            ->map(fn (Karat $karat): array => [
                'karat' => $karat->value,
                'item_count' => $karats[$karat->value]['count'] ?? 0,
                'total_weight' => number_format($karats[$karat->value]['weight'] ?? 0.0, 3, '.', ''),
                'total_value' => $this->fromCents($this->valueCents(
                    $karat->value,
                    $karats[$karat->value]['weight'] ?? 0.0,
                )),
            ])
            ->all();

        $totalWeightGrams = 0.0;
        $totalValueCents = 0;

        foreach (Karat::cases() as $karat) {
            $totalWeightGrams += $karats[$karat->value]['weight'] ?? 0.0;
            $totalValueCents += $this->valueCents($karat->value, $karats[$karat->value]['weight'] ?? 0.0);
        }

        return [
            'total_items' => $items->count(),
            'total_weight' => number_format($totalWeightGrams, 3, '.', ''),
            'total_value' => $this->fromCents($totalValueCents),
            'by_karat' => $byKarat,
            'by_category' => $byCategory,
        ];
    }

    /**
     * A karat's weight priced at today's rate for that karat, in cents.
     *
     * A karat with no configured rate contributes nothing rather than being
     * guessed, so the stock value is never inflated by an invented rate.
     */
    private function valueCents(int $karat, float $weightGrams): int
    {
        $rate = $this->goldRates->rateFor($karat, CarbonImmutable::today());

        if ($rate === null || $weightGrams <= 0) {
            return 0;
        }

        return $this->toCents($weightGrams * $rate);
    }

    /**
     * What the shop has lent out and what it is owed back.
     *
     * The interest figures come from `PawnInterestService`, so this report and
     * the pawn detail page can never quote different numbers.
     *
     * @return array{
     *     active_count: int,
     *     total_principal: string,
     *     outstanding_principal: string,
     *     interest_due: string,
     *     total_payable: string,
     *     overdue_count: int,
     *     rows: list<array<string, mixed>>,
     * }
     */
    public function pawnOutstanding(): array
    {
        $pawns = Pawn::query()
            ->where('status', PawnStatus::Active->value)
            ->with('customer:id,code,name,phone')
            ->with('payments:id,pawn_id,type,amount,date')
            ->orderBy('due_date')
            ->get();

        $today = CarbonImmutable::today();
        $rows = [];
        $principalCents = 0;
        $outstandingCents = 0;
        $interestDueCents = 0;
        $overdueCount = 0;

        foreach ($pawns as $pawn) {
            $summary = $this->pawnInterest->calculate($pawn, $today);
            $isOverdue = $pawn->due_date->startOfDay()->lessThan($today);
            $overdueCount += $isOverdue ? 1 : 0;

            $principalCents += $this->toCents($pawn->principal);
            $outstandingCents += $this->toCents($summary['outstanding_principal']);
            $interestDueCents += $this->toCents($summary['interest_due']);

            $rows[] = [
                'id' => $pawn->id,
                'pawn_no' => $pawn->pawn_no,
                'date' => $pawn->date->toDateString(),
                'due_date' => $pawn->due_date->toDateString(),
                'customer' => [
                    'id' => $pawn->customer?->id,
                    'code' => $pawn->customer?->code ?? '',
                    'name' => $pawn->customer?->name ?? '',
                    'phone' => $pawn->customer?->phone ?? '',
                ],
                'principal' => (string) $pawn->principal,
                'outstanding_principal' => $summary['outstanding_principal'],
                'interest_due' => $summary['interest_due'],
                'total_payable' => $summary['total_payable'],
                'days_overdue' => $isOverdue
                    ? (int) $pawn->due_date->startOfDay()->diffInDays($today)
                    : 0,
                'is_overdue' => $isOverdue,
            ];
        }

        return [
            'active_count' => count($rows),
            'total_principal' => $this->fromCents($principalCents),
            'outstanding_principal' => $this->fromCents($outstandingCents),
            'interest_due' => $this->fromCents($interestDueCents),
            'total_payable' => $this->fromCents($outstandingCents + $interestDueCents),
            'overdue_count' => $overdueCount,
            'rows' => $rows,
        ];
    }

    /**
     * Active pawns past their due date, worst first.
     *
     * @return array{count: int, rows: list<array<string, mixed>>}
     */
    public function overduePawns(): array
    {
        $rows = array_values(array_filter(
            $this->pawnOutstanding()['rows'],
            static fn (array $row): bool => $row['is_overdue'],
        ));

        usort($rows, static fn (array $left, array $right): int => $right['days_overdue'] <=> $left['days_overdue']);

        return [
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Interest the shop has actually collected on pawns in a period.
     *
     * This is cash basis on purpose: an `interest` ledger row is money handed
     * over, which is what the owner reconciles. Interest merely accrued but not
     * yet collected is reported separately so the two are never confused.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     interest_collected: string,
     *     interest_collection_count: int,
     *     principal_collected: string,
     *     redemption_collected: string,
     *     interest_accrued_outstanding: string,
     *     rows: list<array{date: string, pawn_no: string, customer: string, amount: string, method: string}>,
     * }
     */
    public function interestEarned(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        $payments = PawnPayment::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->with('pawn:id,pawn_no,customer_id')
            ->with('pawn.customer:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $byType = $payments->groupBy(fn (PawnPayment $payment): string => $payment->type->value);

        $sumType = function (string $type) use ($byType): int {
            return $byType->get($type, collect())->sum(
                fn (PawnPayment $payment): int => $this->toCents($payment->amount),
            );
        };

        $outstanding = $this->pawnOutstanding();

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'interest_collected' => $this->fromCents($sumType(PawnPaymentType::Interest->value)),
            'interest_collection_count' => $byType->get(PawnPaymentType::Interest->value, collect())->count(),
            'principal_collected' => $this->fromCents($sumType(PawnPaymentType::Principal->value)),
            'redemption_collected' => $this->fromCents($sumType(PawnPaymentType::Redeem->value)),
            'interest_accrued_outstanding' => $outstanding['interest_due'],
            'rows' => $payments->map(fn (PawnPayment $payment): array => [
                'id' => $payment->id,
                'pawn_id' => $payment->pawn_id,
                'date' => $payment->date->toDateString(),
                'pawn_no' => $payment->pawn->pawn_no,
                'customer' => $payment->pawn->customer?->name ?? '',
                'type' => $payment->type->value,
                'amount' => (string) $payment->amount,
                'method' => $payment->method->value,
            ])->all(),
        ];
    }

    /**
     * One customer's sales and collections on a running balance, which is what
     * the shop is owed or holds.
     *
     * @return array<string, mixed>
     */
    public function customerLedger(
        Customer $customer,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $fromDate = $from === null ? null : CarbonImmutable::parse($from)->startOfDay()->toDateString();
        $toDate = $to === null ? null : CarbonImmutable::parse($to)->startOfDay()->toDateString();

        $sales = $customer->sales()
            ->where('status', SaleStatus::Completed->value)
            ->when($fromDate !== null, fn (Builder $query): Builder => $query->whereDate('date', '>=', $fromDate))
            ->when($toDate !== null, fn (Builder $query): Builder => $query->whereDate('date', '<=', $toDate))
            ->with('items:id,sale_id,weight')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $saleIds = $sales->pluck('id')->all();

        $payments = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $salesById = $sales->keyBy('id');

        $transactions = $sales->map(fn (Sale $sale): array => [
            'kind' => 'sale',
            'id' => $sale->id,
            'date' => $sale->date->toDateString(),
            'reference' => $sale->invoice_no,
            'debit' => (string) $sale->total,
            'credit' => '0.00',
            'due' => (string) $sale->due,
            'weight' => number_format(round($sale->items->sum(
                fn (SaleItem $item): float => (float) $item->weight,
            ), 3), 3, '.', ''),
        ])->concat($payments->map(function (SalePayment $payment) use ($salesById): array {
            $sale = $salesById->get($payment->sale_id);

            return [
                'kind' => 'payment',
                'id' => $payment->id,
                'date' => $sale?->date->toDateString() ?? '',
                'reference' => $sale?->invoice_no,
                'debit' => '0.00',
                'credit' => (string) $payment->amount,
                'due' => null,
                'weight' => null,
            ];
        }))
            ->sortBy('date')
            ->values()
            ->all();

        $totalCents = $this->sumCents($sales, 'total');
        $paidCents = $payments->sum(fn ($payment): int => $this->toCents($payment->amount));
        $openingCents = $this->toCents($customer->opening_balance);

        return [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ],
            'from' => $fromDate,
            'to' => $toDate,
            'opening_balance' => $this->fromCents($openingCents),
            'total_sales' => $this->fromCents($totalCents),
            'total_paid' => $this->fromCents($paidCents),
            'closing_balance' => $this->fromCents($openingCents + $totalCents - $paidCents),
            'transactions' => $transactions,
        ];
    }

    /**
     * What the shop owes one supplier, reusing the single ledger definition
     * rather than re-deriving "purchases minus payments" a second time.
     *
     * @return array<string, mixed>
     */
    public function supplierLedger(Supplier $supplier): array
    {
        return $this->suppliers->ledger($supplier);
    }

    /**
     * A period statement: what came in, what the stock cost, what else was
     * spent, and what is left.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     sales_total: string,
     *     sales_paid: string,
     *     purchase_cost: string,
     *     expenses: string,
     *     gross_profit: string,
     *     margin_percentage: string,
     *     cash_in: string,
     *     cash_out: string,
     * }
     */
    public function profitSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $fromDate = $start->toDateString();
        $toDate = $end->toDateString();

        $salesCents = $this->toCents(Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('total'));

        $salesPaidCents = $this->toCents(Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('paid'));

        $purchaseCents = $this->toCents(Purchase::query()
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('total'));

        $expenseCents = $this->toCents(Expense::query()
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('amount'));

        $cashRows = CashTransaction::query()
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->select('direction')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('direction')
            ->get()
            ->keyBy(fn ($row): string => $row->direction->value);

        $cashInCents = $this->toCents($cashRows->get(CashDirection::In->value)->total ?? 0);
        $cashOutCents = $this->toCents($cashRows->get(CashDirection::Out->value)->total ?? 0);
        $costCents = $purchaseCents + $expenseCents;
        $profitCents = $salesCents - $costCents;

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'sales_total' => $this->fromCents($salesCents),
            'sales_paid' => $this->fromCents($salesPaidCents),
            'purchase_cost' => $this->fromCents($purchaseCents),
            'expenses' => $this->fromCents($expenseCents),
            'gross_profit' => $this->fromCents($profitCents),
            'margin_percentage' => $salesCents > 0
                ? number_format($profitCents / $salesCents * 100, 2, '.', '')
                : '0.00',
            'cash_in' => $this->fromCents($cashInCents),
            'cash_out' => $this->fromCents($cashOutCents),
        ];
    }

    /**
     * The dashboard: today's figures, exposure, and a 30 day sales trend.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $today = CarbonImmutable::today();
        $profit = $this->profitSummary($today, $today);
        $stock = $this->stock();
        $pawns = $this->pawnOutstanding();
        $summary = $this->cashBook->summaryFor($today);

        return [
            'date' => $today->toDateString(),
            'today' => [
                'invoice_count' => (int) Sale::query()
                    ->where('status', SaleStatus::Completed->value)
                    ->whereDate('date', $today->toDateString())
                    ->count(),
                'sales_total' => $profit['sales_total'],
                'sales_paid' => $profit['sales_paid'],
                'cash_balance' => $summary['closing_balance'],
                'cash_in' => $summary['total_in'],
                'cash_out' => $summary['total_out'],
            ],
            'stock' => [
                'total_items' => $stock['total_items'],
                'total_weight' => $stock['total_weight'],
            ],
            'pawns' => [
                'active_count' => $pawns['active_count'],
                'outstanding_principal' => $pawns['outstanding_principal'],
                'interest_due' => $pawns['interest_due'],
                'overdue_count' => $pawns['overdue_count'],
            ],
            'receivables' => $this->receivables(),
            'payables' => $this->payables(),
            'sales_trend' => $this->salesTrend(30),
        ];
    }

    /**
     * A run of daily sales totals ending today, for the dashboard chart.
     *
     * Every day in the window is present, including the ones with no sales, so
     * the chart shows a continuous line rather than skipping quiet days.
     *
     * @return list<array{date: string, total: string, paid: string, count: int}>
     */
    public function salesTrend(int $days = 30): array
    {
        $days = max(1, min($days, 365));
        $end = CarbonImmutable::today();
        $start = $end->subDays($days - 1);

        $rows = Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('DATE(date) as day')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(paid), 0) as paid')
            ->selectRaw('COUNT(*) as invoice_count')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset)->toDateString();
            $row = $rows->get($date);

            $series[] = [
                'date' => $date,
                'total' => $this->fromCents($this->toCents($row->total ?? 0)),
                'paid' => $this->fromCents($this->toCents($row->paid ?? 0)),
                'count' => (int) ($row->invoice_count ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Total the shop is owed: the unpaid part of every live sale.
     */
    public function receivables(): string
    {
        $dueCents = $this->toCents(Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->sum('due'));

        $openingCents = $this->toCents(
            Customer::query()->sum('opening_balance'),
        );

        return $this->fromCents($dueCents + $openingCents);
    }

    /**
     * Total the shop owes: unpaid purchases plus the pawn money still out.
     */
    public function payables(): string
    {
        $purchaseDueCents = $this->toCents(Purchase::query()->sum('due'));

        $principalCents = $this->toCents(Pawn::query()
            ->where('status', PawnStatus::Active->value)
            ->sum('principal'));

        return $this->fromCents($purchaseDueCents + $principalCents);
    }

    /**
     * @param  Collection<int, Sale>  $sales
     */
    private function sumCents(Collection $sales, string $column): int
    {
        return $sales->sum(fn (Sale $sale): int => $this->toCents($sale->{$column}));
    }

    private function toCents(float|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
