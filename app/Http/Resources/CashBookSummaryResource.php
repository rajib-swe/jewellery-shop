<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashBookSummaryResource extends JsonResource
{
    /**
     * @return array{
     *     date: string,
     *     opening_balance: string,
     *     total_in: string,
     *     total_out: string,
     *     closing_balance: string,
     *     transaction_count: int,
     *     by_method: list<array{method: string, total_in: string, total_out: string, net: string}>,
     *     locked: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => (string) $this->resource['date'],
            'opening_balance' => (string) $this->resource['opening_balance'],
            'total_in' => (string) $this->resource['total_in'],
            'total_out' => (string) $this->resource['total_out'],
            'closing_balance' => (string) $this->resource['closing_balance'],
            'transaction_count' => (int) $this->resource['transaction_count'],
            'by_method' => $this->resource['by_method'] ?? [],
            'locked' => (bool) $this->resource['locked'],
        ];
    }
}
