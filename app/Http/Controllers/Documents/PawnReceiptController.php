<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSizeRequest;
use App\Models\PawnPayment;
use App\PawnPaymentType;
use App\Services\DocumentService;
use Symfony\Component\HttpFoundation\Response;

class PawnReceiptController extends Controller
{
    public function __invoke(DocumentSizeRequest $request, PawnPayment $pawnPayment, DocumentService $documents): Response
    {
        $pawnPayment->loadMissing(['pawn.customer', 'pawn.user:id,name', 'pawn.items', 'user:id,name']);

        $size = $request->validated('size') ?? 'a4';

        if ($request->query('format') === 'html' || $request->query('view') === 'html' || $request->boolean('html') || $request->has('print')) {
            return response()
                ->view($this->viewName($pawnPayment, $size), $documents->pawnReceiptViewData($pawnPayment, $size))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $pdf = $documents->pawnPaymentReceipt($pawnPayment, $size);

        return $request->boolean('download')
            ? $pdf->download("pawn-receipt-{$pawnPayment->id}.pdf")
            : $pdf->stream("pawn-receipt-{$pawnPayment->id}.pdf");
    }

    private function viewName(PawnPayment $payment, string $size): string
    {
        return $payment->type === PawnPaymentType::Redeem
            ? "pdf.redemption-receipt-{$size}"
            : "pdf.pawn-payment-receipt-{$size}";
    }
}
