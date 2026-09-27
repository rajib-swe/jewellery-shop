<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSizeRequest;
use App\Models\Sale;
use App\Services\DocumentService;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __invoke(DocumentSizeRequest $request, Sale $sale, SaleService $sales, DocumentService $documents): Response
    {
        $sale = $sales->find($sale);

        $pdf = $documents->saleInvoice($sale, $request->validated('size') ?? 'a4');

        return $request->boolean('download')
            ? $pdf->download("invoice-{$sale->invoice_no}.pdf")
            : $pdf->stream("invoice-{$sale->invoice_no}.pdf");
    }
}
