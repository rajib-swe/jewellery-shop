<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSizeRequest;
use App\Models\SalePayment;
use App\Services\DocumentService;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __invoke(DocumentSizeRequest $request, SalePayment $salePayment, DocumentService $documents): Response
    {
        $salePayment->loadMissing(['sale.customer', 'user:id,name']);

        $pdf = $documents->paymentReceipt($salePayment, $request->validated('size') ?? 'a4');

        return $request->boolean('download')
            ? $pdf->download("receipt-{$salePayment->id}.pdf")
            : $pdf->stream("receipt-{$salePayment->id}.pdf");
    }
}
