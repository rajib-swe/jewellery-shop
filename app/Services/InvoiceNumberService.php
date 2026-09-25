<?php

namespace App\Services;

use App\Models\DocumentCounter;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

class InvoiceNumberService
{
    public const INVOICE_SCOPE = 'invoice';

    public function nextInvoiceNumber(CarbonInterface $date): string
    {
        $year = (int) $date->format('Y');
        $counter = $this->lockCounter(self::INVOICE_SCOPE, $year);
        $counter->increment('value');

        return sprintf('INV-%d-%06d', $year, (int) $counter->fresh()?->value);
    }

    private function lockCounter(string $scope, int $year): DocumentCounter
    {
        $counter = DocumentCounter::query()
            ->where('scope', $scope)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($counter !== null) {
            return $counter;
        }

        try {
            DocumentCounter::query()->create([
                'scope' => $scope,
                'year' => $year,
                'value' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent transaction created the same counter first.
        }

        return DocumentCounter::query()
            ->where('scope', $scope)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
