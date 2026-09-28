<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Pawn;
use App\Models\PawnItem;
use App\Models\PawnPayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\PawnPaymentType;
use App\PawnStatus;
use App\SaleStatus;
use App\Support\DocumentFormat;
use App\Support\DocumentLabels;
use App\Support\PdfDocument;
use App\Support\Weight;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;

class DocumentService
{
    public const SIZES = ['a4', 'thermal'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly GoldRateService $goldRates,
        private readonly PawnInterestService $pawnInterest,
    ) {}

    /**
     * Render the printable sale memo in the requested paper size.
     *
     * The mPDF wrapper is returned rather than a response so the controller
     * can choose between streaming the memo in a new tab and downloading it.
     */
    public function saleInvoice(Sale $sale, string $size = 'a4', ?string $template = null): PdfDocument
    {
        $size = $this->normalizeSize($size);
        $data = $this->saleViewData($sale, $size, $template);
        $pdf = Pdf::loadView("pdf.sale-invoice-{$size}", $data, [], $this->mpdfConfig($size, $sale));
        $pdf->getMpdf()->shrink_tables_to_fit = 0;

        return new PdfDocument($pdf);
    }

    /**
     * Render the receipt for a single recorded payment.
     */
    public function paymentReceipt(SalePayment $payment, string $size = 'a4'): PdfDocument
    {
        $size = $this->normalizeSize($size);
        $pdf = Pdf::loadView("pdf.payment-receipt-{$size}", $this->receiptViewData($payment, $size), [], $this->mpdfReceiptConfig($size, $payment));
        $pdf->getMpdf()->shrink_tables_to_fit = 0;

        return new PdfDocument($pdf);
    }

    /**
     * Render the pawn agreement the customer signs and keeps a copy of.
     */
    public function pawnTicket(Pawn $pawn, string $size = 'a4'): PdfDocument
    {
        $size = $this->normalizeSize($size);
        $data = $this->pawnTicketViewData($pawn, $size);
        $pdf = Pdf::loadView("pdf.pawn-ticket-{$size}", $data, [], $this->mpdfPawnConfig($size, $pawn));
        $pdf->getMpdf()->shrink_tables_to_fit = 0;

        return new PdfDocument($pdf);
    }

    /**
     * Render the receipt for a single pawn ledger entry. A redemption gets its
     * own layout because it is the document that releases the pledged goods.
     */
    public function pawnPaymentReceipt(PawnPayment $payment, string $size = 'a4'): PdfDocument
    {
        $size = $this->normalizeSize($size);
        $isRedemption = $payment->type === PawnPaymentType::Redeem;
        $view = $isRedemption
            ? "pdf.redemption-receipt-{$size}"
            : "pdf.pawn-payment-receipt-{$size}";
        $data = $this->pawnReceiptViewData($payment, $size);

        $pdf = Pdf::loadView($view, $data, [], $this->mpdfReceiptConfig($size));
        $pdf->getMpdf()->shrink_tables_to_fit = 0;

        return new PdfDocument($pdf);
    }

    /**
     * Build the view payload for a pawn ticket.
     *
     * @return array<string, mixed>
     */
    public function pawnTicketViewData(Pawn $pawn, ?string $size = null): array
    {
        $shop = $this->settings->all();
        $summary = $this->pawnInterest->calculate($pawn);

        return [
            ...$this->documentShell('pawn_ticket', $size, $shop),
            'pawn' => [
                'pawn_no' => $pawn->pawn_no,
                'date' => $pawn->date->format('d M Y'),
                'term_start' => ($pawn->renewed_at ?? $pawn->date)->format('d M Y'),
                'due_date' => $pawn->due_date->format('d M Y'),
                'principal' => (string) $pawn->principal,
                'interest_rate' => (string) $pawn->interest_rate,
                'interest_type' => $pawn->interest_type->value,
                'status' => $pawn->status->value,
                'notes' => $pawn->notes,
                'is_forfeited' => $pawn->status === PawnStatus::Forfeited,
                'pledged_value' => $pawn->items->sum(fn (PawnItem $item): float => (float) $item->estimated_value),
                'loan_to_value' => $this->loanToValuePercentage($pawn),
            ],
            'customer' => $this->customerData($pawn->customer),
            'officer' => $pawn->user?->name ?? '',
            'items' => $pawn->items->map(fn (PawnItem $item, int $index): array => [
                'serial' => $index + 1,
                'description' => $item->description,
                'karat' => (int) $item->karat,
                'gross_weight' => DocumentFormat::weight($item->gross_weight, $shop['weight_unit']),
                'net_weight' => DocumentFormat::weight($item->net_weight, $shop['weight_unit']),
                'estimated_value' => (string) $item->estimated_value,
                'photo' => $this->photoUrl($item->photo),
            ])->all(),
            'summary' => $this->summaryData($summary),
            'terms' => $shop['pawn_terms'],
            'footer' => $shop['invoice_footer'],
        ];
    }

    /**
     * Build the view payload for a pawn payment or redemption receipt.
     *
     * @return array<string, mixed>
     */
    public function pawnReceiptViewData(PawnPayment $payment, ?string $size = null): array
    {
        $shop = $this->settings->all();
        $pawn = $payment->pawn;
        $summary = $this->pawnInterest->calculate($pawn);
        $isRedemption = $payment->type === PawnPaymentType::Redeem;

        return [
            ...$this->documentShell($isRedemption ? 'redemption' : 'pawn_receipt', $size, $shop),
            'document_title' => $isRedemption ? 'redemptionReceipt' : 'pawnPaymentReceipt',
            'receipt' => [
                'receipt_no' => 'PWN-RCP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                'type' => $payment->type->value,
                'amount' => (string) $payment->amount,
                'date' => $payment->date->format('d M Y'),
                'method' => $payment->method->value,
                'method_label' => DocumentLabels::paymentMethods()[$payment->method->value] ?? $payment->method->value,
                'reference' => $payment->reference,
                'note' => $payment->note,
                'received_by' => $payment->user?->name ?? '',
                'received_at' => $payment->created_at?->format('d M Y, h:i A') ?? '',
            ],
            'pawn' => [
                'pawn_no' => $pawn->pawn_no,
                'date' => $pawn->date->format('d M Y'),
                'due_date' => $pawn->due_date->format('d M Y'),
                'principal' => (string) $pawn->principal,
                'interest_rate' => (string) $pawn->interest_rate,
                'status' => $pawn->status->value,
                'is_redeemed' => $pawn->status === PawnStatus::Redeemed,
                'redeemed_at' => $pawn->redeemed_at?->format('d M Y'),
            ],
            'customer' => $this->customerData($pawn->customer),
            'items' => $pawn->items->map(fn (PawnItem $item): array => [
                'description' => $item->description,
                'karat' => (int) $item->karat,
                'net_weight' => DocumentFormat::weight($item->net_weight, $shop['weight_unit']),
                'estimated_value' => (string) $item->estimated_value,
            ])->all(),
            'summary' => $this->summaryData($summary),
            'amount_in_words' => DocumentFormat::amountInWords($payment->amount),
            'terms' => $shop['pawn_terms'],
            'footer' => $shop['invoice_footer'],
        ];
    }

    /**
     * Build the view payload for a sale memo.
     *
     * Money and weights are formatted here rather than in the views so both
     * paper sizes share one presentation and the shop's `weight_unit` setting
     * is honoured everywhere.
     *
     * @return array<string, mixed>
     */
    public function saleViewData(Sale $sale, ?string $size = null, ?string $template = null): array
    {
        $shop = $this->settings->all();
        $labels = DocumentLabels::all();
        $totalWeightGrams = self::totalWeight($sale);
        $selectedTemplate = in_array($template, ['demo1', 'demo2'], true) ? $template : ($shop['invoice_template'] ?? 'demo2');

        $latestRates = $this->goldRates->latest()->mapWithKeys(fn ($rate) => [
            (int) $rate->karat => number_format((float) $rate->rate_per_gram, 0, '.', ','),
        ])->all();

        $lines = $sale->items->values()->map(fn ($item, int $index): array => [
            'serial' => $index + 1,
            'tag_no' => $item->tag_no,
            'name' => $item->name,
            'karat' => (int) $item->karat,
            'is_handwritten' => $item->item_id === null,
            'weight' => DocumentFormat::weight($item->weight, $shop['weight_unit']),
            'weight_grams' => (string) $item->weight,
            'rate' => (string) $item->rate,
            'gold_value' => (string) $item->gold_value,
            'making' => (string) $item->making,
            'stone_price' => (string) $item->stone_price,
            'line_total' => (string) $item->line_total,
        ]);

        return [
            'document' => 'sale',
            'size' => $this->normalizeSize($size),
            'shop' => $shop,
            'labels' => $labels,
            'currency' => $shop['currency_symbol'],
            'weightUnitLabel' => DocumentFormat::weightUnitLabel($shop['weight_unit']),
            'shopLogo' => $this->logoPath($shop['shop_logo']),
            'invoice' => [
                'invoice_no' => $sale->invoice_no,
                'date' => $sale->date->format('d M Y'),
                'customer_name' => $sale->customer?->name ?? $labels['walkIn']['bn'],
                'customer_phone' => $sale->customer?->phone ?? '',
                'customer_code' => $sale->customer?->code ?? '',
                'customer_address' => $sale->customer?->address ?? '',
                'seller_name' => $sale->user?->name ?? '',
                'is_void' => $sale->status === SaleStatus::Void,
                'void_reason' => $sale->void_reason,
                'notes' => $sale->notes,
            ],
            'lines' => $lines,
            'exchanges' => $sale->exchanges->map(fn ($exchange): array => [
                'description' => $exchange->description,
                'karat' => (int) $exchange->karat,
                'weight' => DocumentFormat::weight($exchange->weight, $shop['weight_unit']),
                'weight_grams' => (string) $exchange->weight,
                'rate' => (string) $exchange->rate,
                'amount' => (string) $exchange->amount,
            ])->all(),
            'payments' => $sale->payments->map(fn (SalePayment $payment): array => [
                'method' => $payment->method->value,
                'method_label' => DocumentLabels::paymentMethods()[$payment->method->value] ?? $payment->method->value,
                'amount' => (string) $payment->amount,
                'reference' => $payment->reference,
                'received_by' => $payment->user?->name ?? '',
                'received_at' => $payment->created_at?->format('d M Y, h:i A') ?? '',
            ])->all(),
            'totals' => [
                'subtotal' => (string) $sale->subtotal,
                'discount' => (string) $sale->discount,
                'vat' => (string) $sale->vat,
                'vat_percentage' => $shop['vat_percentage'],
                'exchange_amount' => (string) $sale->exchange_amount,
                'total' => (string) $sale->total,
                'paid' => (string) $sale->paid,
                'due' => (string) $sale->due,
                'in_words' => DocumentFormat::amountInWords($sale->total),
                'due_in_words' => DocumentFormat::amountInWords($sale->due),
            ],
            'weight_summary' => [
                'total_grams' => number_format($totalWeightGrams, 3, '.', ''),
                'total_vori' => number_format(Weight::gramsToVori($totalWeightGrams), 4, '.', ''),
                'total_ana' => number_format(Weight::gramsToAna($totalWeightGrams), 2, '.', ''),
            ],
            'template' => $selectedTemplate,
            'latestRates' => $latestRates,
            'footer' => $shop['invoice_footer'],
            'printed_at' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * Build the view payload for a payment receipt.
     *
     * @return array<string, mixed>
     */
    public function receiptViewData(SalePayment $payment, ?string $size = null): array
    {
        $shop = $this->settings->all();
        $labels = DocumentLabels::all();
        $sale = $payment->sale;
        $receivedAt = $payment->created_at ?? now();

        return [
            'document' => 'receipt',
            'size' => $this->normalizeSize($size),
            'shop' => $shop,
            'labels' => $labels,
            'currency' => $shop['currency_symbol'],
            'weightUnitLabel' => DocumentFormat::weightUnitLabel($shop['weight_unit']),
            'shopLogo' => $this->logoPath($shop['shop_logo']),
            'receipt' => [
                'receipt_no' => 'RCP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                'date' => $receivedAt->format('d M Y'),
                'method' => $payment->method->value,
                'method_label' => DocumentLabels::paymentMethods()[$payment->method->value] ?? $payment->method->value,
                'amount' => (string) $payment->amount,
                'reference' => $payment->reference,
                'received_by' => $payment->user?->name ?? '',
                'received_at' => $payment->created_at?->format('d M Y, h:i A') ?? '',
            ],
            'sale' => [
                'invoice_no' => $sale->invoice_no,
                'date' => $sale->date->format('d M Y'),
                'total' => (string) $sale->total,
                'paid' => (string) $sale->paid,
                'due' => (string) $sale->due,
                'is_void' => $sale->status === SaleStatus::Void,
            ],
            'customer' => [
                'name' => $sale->customer?->name ?? $labels['walkIn']['bn'],
                'phone' => $sale->customer?->phone ?? '',
                'code' => $sale->customer?->code ?? '',
                'address' => $sale->customer?->address ?? '',
            ],
            'amount_in_words' => DocumentFormat::amountInWords($payment->amount),
            'footer' => $shop['invoice_footer'],
            'printed_at' => now()->format('d M Y, h:i A'),
        ];
    }

    public function normalizeSize(?string $size): string
    {
        $normalized = strtolower(trim((string) $size));

        return in_array($normalized, self::SIZES, true) ? $normalized : 'a4';
    }

    /**
     * @return array<string, mixed>
     */
    private function mpdfConfig(string $size, Sale $sale): array
    {
        if ($size === 'a4') {
            return [
                'format' => 'A4',
                'margin_left' => 6,
                'margin_right' => 6,
                'margin_top' => 6,
                'margin_bottom' => 6,
            ];
        }

        return [
            'format' => [80, $this->thermalHeight($sale)],
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mpdfReceiptConfig(string $size, ?SalePayment $payment = null): array
    {
        if ($size === 'a4') {
            return [
                'format' => 'A4',
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 8,
                'margin_bottom' => 8,
            ];
        }

        return [
            'format' => [80, $payment === null ? 160.0 : 160.0],
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mpdfPawnConfig(string $size, Pawn $pawn): array
    {
        if ($size === 'a4') {
            return [
                'format' => 'A4',
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 8,
                'margin_bottom' => 8,
            ];
        }

        return [
            'format' => [80, $this->pawnThermalHeight($pawn)],
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ];
    }

    /**
     * The shared shop header, labels and printed-at stamp for every document.
     *
     * @param  array<string, string>  $shop
     * @return array<string, mixed>
     */
    private function documentShell(string $document, ?string $size, array $shop): array
    {
        return [
            'document' => $document,
            'size' => $this->normalizeSize($size),
            'shop' => $shop,
            'labels' => DocumentLabels::all(),
            'currency' => $shop['currency_symbol'],
            'weightUnitLabel' => DocumentFormat::weightUnitLabel($shop['weight_unit']),
            'shopLogo' => $this->logoPath($shop['shop_logo']),
            'printed_at' => now()->format('d M Y, h:i A'),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, phone: string, nid: ?string, address: string}
     */
    private function customerData(?Customer $customer): array
    {
        return [
            'id' => (int) ($customer?->id ?? 0),
            'code' => $customer?->code ?? '',
            'name' => $customer?->name ?? DocumentLabels::all()['walkIn']['bn'],
            'phone' => $customer?->phone ?? '',
            'nid' => $customer?->nid,
            'address' => $customer?->address ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{outstanding_principal: string, principal_paid: string, interest_rate: string, interest_accrued: string, interest_paid: string, interest_due: string, total_paid: string, total_payable: string, as_of: string, partial_month_rule: string}
     */
    private function summaryData(array $summary): array
    {
        unset($summary['periods']);

        return $summary;
    }

    private function loanToValuePercentage(Pawn $pawn): string
    {
        $pledged = (float) $pawn->items->sum(fn (PawnItem $item): float => (float) $item->estimated_value);

        if ($pledged <= 0) {
            return '0.00';
        }

        return number_format((float) $pawn->principal / $pledged * 100, 2, '.', '');
    }

    private function photoUrl(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }

        return Storage::disk('public')->url($photo);
    }

    /**
     * Thermal rolls are continuous, so the page is sized to the content.
     */
    private function thermalHeight(Sale $sale): float
    {
        $height = 95.0
            + count($sale->items) * 10.0
            + count($sale->exchanges) * 8.0
            + count($sale->payments) * 8.0
            + ($sale->notes ? 10.0 : 0.0);

        return min(max($height, 130.0), 450.0);
    }

    /**
     * Thermal rolls are continuous, so the page is sized to the content.
     */
    private function pawnThermalHeight(Pawn $pawn): float
    {
        $height = 110.0
            + count($pawn->items) * 10.0
            + 45.0;

        return min(max($height, 180.0), 600.0);
    }

    private function logoPath(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }

        $path = Storage::disk('public')->path($logo);

        return is_file($path) ? $path : null;
    }

    /**
     * Total weight of the invoice in grams, for the memo's weight summary row.
     */
    public static function totalWeight(Sale $sale): float
    {
        return round((float) $sale->items->sum(fn ($item): float => (float) $item->weight), 3);
    }
}
