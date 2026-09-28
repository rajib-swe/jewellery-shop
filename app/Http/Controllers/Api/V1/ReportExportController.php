<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel downloads for the reports.
 *
 * The figures come from `ReportService`, the same place the on-screen reports
 * and the printouts read them, so a spreadsheet can never disagree with the page
 * it was exported from. Only the column layout lives here.
 *
 * One entry point serves every report: an export is a table, and a table is a
 * heading row plus rows. Deciding what a report *means* is `ReportService`'s
 * job, not this controller's.
 */
class ReportExportController extends Controller
{
    public function __invoke(
        ReportRequest $request,
        string $report,
        ReportService $reports,
    ): BinaryFileResponse {
        $period = ReportPeriod::from($request);

        [$headings, $rows, $filename] = match ($report) {
            'sales' => $this->sales($reports, $period, $request->validated('group_by') ?? 'day'),
            'stock' => $this->stock($reports),
            'pawn-outstanding' => $this->pawnOutstanding($reports),
            'overdue-pawns' => $this->overduePawns($reports),
            'interest-earned' => $this->interestEarned($reports, $period),
            'customer-ledger' => $this->customerLedger($reports, $request, $period),
            'supplier-ledger' => $this->supplierLedger($reports, $request),
            'profit' => $this->profit($reports, $period),
            default => abort(404, "Unknown report [{$report}]."),
        };

        return Excel::download(new ReportExport($headings, $rows), $filename);
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function sales(ReportService $reports, array $period, string $groupBy): array
    {
        $report = $reports->sales($period['from'], $period['to'], $groupBy);

        return [
            ['Period', 'Invoices', 'Total', 'Paid', 'Due', 'Weight (g)'],
            array_map(fn (array $row): array => [
                $row['period'],
                $row['invoice_count'],
                $row['total'],
                $row['paid'],
                $row['due'],
                $row['weight'],
            ], $report['rows']),
            $this->filename('sales', $period),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function stock(ReportService $reports): array
    {
        $report = $reports->stock();

        $byKarat = array_map(fn (array $row): array => [
            "{$row['karat']}K",
            $row['item_count'],
            $row['total_weight'],
            $row['total_value'],
        ], $report['by_karat']);

        $byCategory = array_map(fn (array $row): array => [
            $row['category'],
            $row['item_count'],
            $row['total_weight'],
            $row['total_value'],
        ], $report['by_category']);

        return [
            ['Grouping', 'Item Count', 'Net Weight (g)', 'Value'],
            array_merge($byKarat, $byCategory),
            'stock-summary.xlsx',
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function pawnOutstanding(ReportService $reports): array
    {
        $report = $reports->pawnOutstanding();

        return [
            ['Pawn No', 'Customer', 'Date', 'Due Date', 'Principal', 'Outstanding', 'Interest Due', 'Total Payable', 'Overdue'],
            array_map(fn (array $row): array => [
                $row['pawn_no'],
                $row['customer']['name'],
                $row['date'],
                $row['due_date'],
                $row['principal'],
                $row['outstanding_principal'],
                $row['interest_due'],
                $row['total_payable'],
                $row['is_overdue'] ? 'Yes' : 'No',
            ], $report['rows']),
            'pawn-outstanding.xlsx',
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function overduePawns(ReportService $reports): array
    {
        $report = $reports->overduePawns();

        return [
            ['Pawn No', 'Customer', 'Phone', 'Due Date', 'Days Overdue', 'Total Payable'],
            array_map(fn (array $row): array => [
                $row['pawn_no'],
                $row['customer']['name'],
                $row['customer']['phone'],
                $row['due_date'],
                $row['days_overdue'],
                $row['total_payable'],
            ], $report['rows']),
            'overdue-pawns.xlsx',
        ];
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function interestEarned(ReportService $reports, array $period): array
    {
        $report = $reports->interestEarned($period['from'], $period['to']);

        return [
            ['Date', 'Pawn No', 'Customer', 'Type', 'Amount', 'Method'],
            array_map(fn (array $row): array => [
                $row['date'],
                $row['pawn_no'],
                $row['customer'],
                $row['type'],
                $row['amount'],
                $row['method'],
            ], $report['rows']),
            $this->filename('interest-earned', $period),
        ];
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function customerLedger(ReportService $reports, ReportRequest $request, array $period): array
    {
        $customer = Customer::query()->findOrFail($request->integer('customer'));
        $report = $reports->customerLedger($customer, $period['from'], $period['to']);

        return [
            ['Date', 'Type', 'Reference', 'Debit', 'Credit'],
            $this->ledgerRows($report['transactions']),
            "customer-ledger-{$customer->code}.xlsx",
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function supplierLedger(ReportService $reports, ReportRequest $request): array
    {
        $supplier = Supplier::query()->findOrFail($request->integer('supplier'));
        $report = $reports->supplierLedger($supplier);

        return [
            ['Date', 'Type', 'Reference', 'Debit', 'Credit'],
            $this->ledgerRows($report['transactions']),
            "supplier-ledger-{$supplier->code}.xlsx",
        ];
    }

    /**
     * The profit statement is a list of measures rather than a grid, so it is
     * exported as a two column table.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function profit(ReportService $reports, array $period): array
    {
        $report = $reports->profitSummary($period['from'], $period['to']);

        return [
            ['Measure', 'Amount'],
            [
                ['Sales total', $report['sales_total']],
                ['Sales collected', $report['sales_paid']],
                ['Purchase cost', $report['purchase_cost']],
                ['Expenses', $report['expenses']],
                ['Gross profit', $report['gross_profit']],
                ['Margin %', $report['margin_percentage']],
                ['Cash in', $report['cash_in']],
                ['Cash out', $report['cash_out']],
            ],
            $this->filename('profit-summary', $period),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<list<mixed>>
     */
    private function ledgerRows(array $transactions): array
    {
        return array_map(fn (array $row): array => [
            $row['date'],
            $row['kind'],
            $row['reference'],
            $row['debit'],
            $row['credit'],
        ], $transactions);
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     */
    private function filename(string $prefix, array $period): string
    {
        return "{$prefix}-{$period['from']->toDateString()}-to-{$period['to']->toDateString()}.xlsx";
    }
}
