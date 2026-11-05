<?php

namespace App\Exports;

use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Renders a report as a spreadsheet.
 *
 * Every report is already reduced to plain associative arrays by
 * {@see ReportService}, so one export class covers all of them:
 * the controller passes a title, a heading row, and the rows. Writing a class
 * per report would mean eight near-identical files.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
