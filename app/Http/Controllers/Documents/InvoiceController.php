<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSizeRequest;
use App\Models\Sale;
use App\Services\DocumentService;
use App\Services\SaleService;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __invoke(DocumentSizeRequest $request, Sale $sale, SaleService $sales, DocumentService $documents): Response
    {
        $sale = $sales->find($sale);

        $size = $request->validated('size') ?? 'a4';
        $template = $request->validated('template') ?? $request->query('template');

        if ($request->query('format') === 'html' || $request->query('view') === 'html' || $request->boolean('html') || $request->has('print')) {
            return response()
                ->view("pdf.sale-invoice-{$size}", $documents->saleViewData($sale, $size, $template))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $pdf = $documents->saleInvoice($sale, $size, $template);

        return $request->boolean('download')
            ? $pdf->download("invoice-{$sale->invoice_no}.pdf")
            : $pdf->stream("invoice-{$sale->invoice_no}.pdf");
    }
}
