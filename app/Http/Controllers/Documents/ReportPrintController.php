<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSizeRequest;
use App\Models\Customer;
use App\Models\Supplier;
use App\Support\PdfDocument;
use App\Support\ReportDocument;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The printable report.
 *
 * `?print=1` (or `?format=html`) opens the in-browser view with the print
 * dialog, and dropping it streams the PDF. The same controller serves both so
 * a report can never be printed from a different set of figures than the page
 * it was opened from.
 */
class ReportPrintController extends Controller
{
    public function __invoke(
        DocumentSizeRequest $request,
        string $report,
        ReportDocument $documents,
    ): Response {
        $data = $documents->payload($report, $this->context($request, $report));

        if ($request->boolean('print')
            || $request->query('format') === 'html'
            || $request->query('view') === 'html') {
            return response()
                ->view('pdf.report-a4', $data)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $pdf = Pdf::loadView('pdf.report-a4', $data, [], [
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);
        $pdf->getMpdf()->shrink_tables_to_fit = 0;

        // The wrapper is what turns the rendered PDF into a response; the raw
        // mPDF facade sends the file itself and returns nothing.
        $document = new PdfDocument($pdf);
        $filename = "{$report}-report.pdf";

        return $request->boolean('download')
            ? $document->download($filename)
            : $document->stream($filename);
    }

    /**
     * The report's subject: a period for the date-based reports, a customer or a
     * supplier for the two ledgers.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, string $report): array
    {
        if ($report === 'customer-ledger') {
            return [
                'customer' => Customer::query()->findOrFail($request->integer('customer')),
                ...ReportPeriod::from($request),
            ];
        }

        if ($report === 'supplier-ledger') {
            return [
                'supplier' => Supplier::query()->findOrFail($request->integer('supplier')),
            ];
        }

        return ReportPeriod::from($request);
    }
}
