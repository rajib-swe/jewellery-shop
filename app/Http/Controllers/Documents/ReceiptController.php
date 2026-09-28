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

        $size = $request->validated('size') ?? 'a4';

        if ($request->query('format') === 'html' || $request->query('view') === 'html' || $request->boolean('html') || $request->has('print')) {
            return response()
                ->view("pdf.payment-receipt-{$size}", $documents->receiptViewData($salePayment, $size))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $pdf = $documents->paymentReceipt($salePayment, $size);

        return $request->boolean('download')
            ? $pdf->download("receipt-{$salePayment->id}.pdf")
            : $pdf->stream("receipt-{$salePayment->id}.pdf");
    }
}
