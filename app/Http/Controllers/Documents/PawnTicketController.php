<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSizeRequest;
use App\Models\Pawn;
use App\Services\DocumentService;
use Symfony\Component\HttpFoundation\Response;

class PawnTicketController extends Controller
{
    public function __invoke(DocumentSizeRequest $request, Pawn $pawn, DocumentService $documents): Response
    {
        $pawn->loadMissing(['customer', 'user:id,name', 'items']);

        $size = $request->validated('size') ?? 'a4';

        if ($request->query('format') === 'html' || $request->query('view') === 'html' || $request->boolean('html') || $request->has('print')) {
            return response()
                ->view("pdf.pawn-ticket-{$size}", $documents->pawnTicketViewData($pawn, $size))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $pdf = $documents->pawnTicket($pawn, $size);

        return $request->boolean('download')
            ? $pdf->download("pawn-ticket-{$pawn->pawn_no}.pdf")
            : $pdf->stream("pawn-ticket-{$pawn->pawn_no}.pdf");
    }
}
