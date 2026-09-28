<?php

namespace App\Support;

use Mccarlosen\LaravelMpdf\LaravelMpdf;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

class PdfDocument
{
    public function __construct(private readonly LaravelMpdf $pdf) {}

    public function download(string $filename = 'document.pdf'): Response
    {
        return response($this->pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return response($this->pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function output(): string
    {
        return $this->pdf->output();
    }

    public function getMpdf(): Mpdf
    {
        return $this->pdf->getMpdf();
    }
}
