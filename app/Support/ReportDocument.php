<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\ReportService;
use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a report into a printable payload.
 *
 * The printable report carries no queries of its own: it is handed the same
 * figures `ReportService` gave the screen, laid out as heading rows and
 * pre-formatted strings. That is what keeps a printout from disagreeing with
 * the page it was printed from.
 */
final class ReportDocument
{
    /**
     * The report slugs, with their printable titles.
     *
     * A slug is one string shared by the screen, the spreadsheet and the
     * printout, so a report can never be fetched under one name and printed
     * under another.
     *
     * @var array<string, array{bn: string, en: string}>
     */
    public const TITLES = [
        'sales' => ['bn' => 'বিক্রয়ের হিসাব', 'en' => 'Sales Report'],
        'stock' => ['bn' => 'স্টকের হিসাব', 'en' => 'Stock Report'],
        'pawn-outstanding' => ['bn' => 'বন্ধকের বকেয়া হিসাব', 'en' => 'Pawn Outstanding'],
        'overdue-pawns' => ['bn' => 'মেয়াদোত্তীর্ণ বন্ধক', 'en' => 'Overdue Pawns'],
        'interest-earned' => ['bn' => 'আয়কৃত সুদের হিসাব', 'en' => 'Interest Earned'],
        'customer-ledger' => ['bn' => 'গ্রাহকের খতিয়ান', 'en' => 'Customer Ledger'],
        'supplier-ledger' => ['bn' => 'সাপ্লায়ারের খতিয়ান', 'en' => 'Supplier Ledger'],
        'profit' => ['bn' => 'মুনাফার হিসাব', 'en' => 'Profit Summary'],
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ReportService $reports,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(string $report, array $context = []): array
    {
        $title = self::TITLES[$report] ?? ['bn' => 'হিসাব', 'en' => 'Report'];

        $built = match ($report) {
            'sales' => $this->sales($context),
            'stock' => $this->stock(),
            'pawn-outstanding' => $this->pawnOutstanding(),
            'overdue-pawns' => $this->overduePawns(),
            'interest-earned' => $this->interestEarned($context),
            'customer-ledger' => $this->customerLedger($context),
            'supplier-ledger' => $this->supplierLedger($context),
            'profit' => $this->profit($context),
            default => null,
        };

        if ($built === null) {
            // A mistyped report name is a bad request, not a server fault.
            abort(404, "Unknown report [{$report}].");
        }

        $shop = $this->settings->all();

        return [
            'report' => $report,
            'shop' => $shop,
            'currency' => $shop['currency_symbol'],
            'shopLogo' => $this->logoPath($shop['shop_logo']),
            'title_bn' => $title['bn'],
            'title_en' => $title['en'],
            'empty_bn' => 'এই সময়ের মধ্যে কোনো তথ্য পাওয়া যায়নি।',
            'is_table' => true,
            'printed_at' => now()->format('d M Y, h:i A'),
            ...$built,
        ];
    }

    /**
     * @param  array{from: CarbonInterface, to: CarbonInterface}  $context
     * @return array<string, mixed>
     */
    private function sales(array $context): array
    {
        $report = $this->reports->sales($context['from'], $context['to']);

        return [
            'period' => $this->periodLabel($context['from'], $context['to']),
            'summary' => [
                'মোট চালান' => number_format($report['invoice_count']),
                'মোট বিক্রয়' => $this->money($report['total_sales']),
                'মোট পরিশোধ' => $this->money($report['total_paid']),
                'বকেয়া' => $this->money($report['total_due']),
                'মোট ওজন (গ্রাম)' => $report['total_weight'],
            ],
            'headings' => ['সময়কাল', 'চালান', 'মোট', 'পরিশোধ', 'বকেয়া', 'ওজন'],
            'rows' => array_map(fn (array $row): array => [
                $row['period'],
                $row['invoice_count'],
                $this->money($row['total']),
                $this->money($row['paid']),
                $this->money($row['due']),
                $row['weight'],
            ], $report['rows']),
            'totals' => [
                'মোট',
                $report['invoice_count'],
                $this->money($report['total_sales']),
                $this->money($report['total_paid']),
                $this->money($report['total_due']),
                $report['total_weight'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stock(): array
    {
        $report = $this->reports->stock();

        $rows = array_map(fn (array $row): array => [
            "{$row['karat']}K",
            $row['item_count'],
            $row['total_weight'],
            $this->money($row['total_value']),
        ], $report['by_karat']);

        $categoryRows = array_map(fn (array $row): array => [
            $row['category'],
            $row['item_count'],
            $row['total_weight'],
            $this->money($row['total_value']),
        ], $report['by_category']);

        return [
            'period' => null,
            'summary' => [
                'মোট পণ্য' => number_format($report['total_items']),
                'মোট নিট ওজন (গ্রাম)' => $report['total_weight'],
                'আনুমানিক মূল্য' => $this->money($report['total_value']),
            ],
            'headings' => ['ক্যারেট / ক্যাটাগরি', 'পণ্য', 'ওজন (গ্রাম)', 'মূল্য'],
            'rows' => array_merge($rows, $categoryRows),
            'totals' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pawnOutstanding(): array
    {
        $report = $this->reports->pawnOutstanding();

        return [
            'period' => null,
            'summary' => [
                'চলমান বন্ধক' => number_format($report['active_count']),
                'মোট মূলধন' => $this->money($report['total_principal']),
                'অবশিষ্ট মূলধন' => $this->money($report['outstanding_principal']),
                'বকেয়া সুদ' => $this->money($report['interest_due']),
                'মোট পরিশোধযোগ্য' => $this->money($report['total_payable']),
            ],
            'headings' => ['বন্ধক নং', 'গ্রাহক', 'পরিশোধের তারিখ', 'মূলধন', 'অবশিষ্ট', 'সুদ', 'মোট'],
            'rows' => array_map(fn (array $row): array => [
                $row['pawn_no'],
                $row['customer']['name'],
                $row['due_date'],
                $this->money($row['principal']),
                $this->money($row['outstanding_principal']),
                $this->money($row['interest_due']),
                $this->money($row['total_payable']),
            ], $report['rows']),
            'totals' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overduePawns(): array
    {
        $report = $this->reports->overduePawns();

        return [
            'period' => null,
            'summary' => [
                'মেয়াদোত্তীর্ণ বন্ধক' => number_format($report['count']),
            ],
            'headings' => ['বন্ধক নং', 'গ্রাহক', 'মোবাইল', 'পরিশোধের তারিখ', 'দিন', 'মোট পরিশোধযোগ্য'],
            'rows' => array_map(fn (array $row): array => [
                $row['pawn_no'],
                $row['customer']['name'],
                $row['customer']['phone'],
                $row['due_date'],
                $row['days_overdue'],
                $this->money($row['total_payable']),
            ], $report['rows']),
            'totals' => [],
        ];
    }

    /**
     * @param  array{from: CarbonInterface, to: CarbonInterface}  $context
     * @return array<string, mixed>
     */
    private function interestEarned(array $context): array
    {
        $report = $this->reports->interestEarned($context['from'], $context['to']);

        return [
            'period' => $this->periodLabel($context['from'], $context['to']),
            'summary' => [
                'আয়কৃত সুদ' => $this->money($report['interest_collected']),
                'আয়কৃত মূলধন' => $this->money($report['principal_collected']),
                'ফেরতকৃত' => $this->money($report['redemption_collected']),
                'অবহারিত বকেয়া সুদ' => $this->money($report['interest_accrued_outstanding']),
            ],
            'headings' => ['তারিখ', 'বন্ধক নং', 'গ্রাহক', 'ধরন', 'পরিমাণ', 'মাধ্যম'],
            'rows' => array_map(fn (array $row): array => [
                $row['date'],
                $row['pawn_no'],
                $row['customer'],
                $row['type'],
                $this->money($row['amount']),
                $row['method'],
            ], array_values($report['rows'])),
            'totals' => [],
        ];
    }

    /**
     * @param  array{customer: Customer, from: CarbonInterface, to: CarbonInterface}  $context
     * @return array<string, mixed>
     */
    private function customerLedger(array $context): array
    {
        $report = $this->reports->customerLedger($context['customer'], $context['from'], $context['to']);

        return [
            'period' => $this->periodLabel($context['from'], $context['to']),
            'summary' => [
                'গ্রাহক' => $report['customer']['name'],
                'মোবাইল' => $report['customer']['phone'],
                'প্রারম্ভিক ব্যালেন্স' => $this->money($report['opening_balance']),
                'মোট বিক্রয়' => $this->money($report['total_sales']),
                'মোট পরিশোধ' => $this->money($report['total_paid']),
                'বকেয়া' => $this->money($report['closing_balance']),
            ],
            'headings' => ['তারিখ', 'ধরন', 'রেফারেন্স', 'ডেবিট', 'ক্রেডিট'],
            'rows' => array_map(fn (array $row): array => [
                $row['date'],
                $row['kind'],
                $row['reference'],
                $this->money($row['debit']),
                $this->money($row['credit']),
            ], $report['transactions']),
            'totals' => [
                'মোট',
                '',
                '',
                $this->money($report['total_sales']),
                $this->money($report['total_paid']),
            ],
        ];
    }

    /**
     * @param  array{supplier: Supplier}  $context
     * @return array<string, mixed>
     */
    private function supplierLedger(array $context): array
    {
        $report = $this->reports->supplierLedger($context['supplier']);

        return [
            'period' => null,
            'summary' => [
                'সাপ্লায়ার' => $context['supplier']->name,
                'মোট ক্রয়' => $this->money($report['total_purchases']),
                'মোট পরিশোধ' => $this->money($report['total_payments']),
                'বকেয়া' => $this->money($report['balance']),
            ],
            'headings' => ['তারিখ', 'ধরন', 'রেফারেন্স', 'ডেবিট', 'ক্রেডিট'],
            'rows' => array_map(fn (array $row): array => [
                $row['date'],
                $row['kind'],
                $row['reference'],
                $this->money($row['debit']),
                $this->money($row['credit']),
            ], $report['transactions']),
            'totals' => [
                'মোট',
                '',
                '',
                $this->money($report['total_purchases']),
                $this->money($report['total_payments']),
            ],
        ];
    }

    /**
     * @param  array{from: CarbonInterface, to: CarbonInterface}  $context
     * @return array<string, mixed>
     */
    private function profit(array $context): array
    {
        $report = $this->reports->profitSummary($context['from'], $context['to']);

        return [
            'period' => $this->periodLabel($context['from'], $context['to']),
            'summary' => [
                'মোট বিক্রয়' => $this->money($report['sales_total']),
                'মোট পরিশোধ' => $this->money($report['sales_paid']),
                'ক্রয়ের খরচ' => $this->money($report['purchase_cost']),
                'খরচ' => $this->money($report['expenses']),
                'মোট মুনাফা' => $this->money($report['gross_profit']),
                'হার (%)' => $report['margin_percentage'],
                'ক্যাশ আয়' => $this->money($report['cash_in']),
                'ক্যাশ ব্যয়' => $this->money($report['cash_out']),
            ],
            // A statement of measures rather than a grid: the summary block above
            // is the whole report, so there is deliberately no table.
            'headings' => [],
            'rows' => [],
            'totals' => [],
            'is_table' => false,
        ];
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    private function periodLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        return $from->toDateString().' — '.$to->toDateString();
    }

    private function logoPath(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }

        $path = Storage::disk('public')->path($logo);

        return is_file($path) ? $path : null;
    }
}
