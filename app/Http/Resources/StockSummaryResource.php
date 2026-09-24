<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockSummaryResource extends JsonResource
{
    /**
     * @return array{total_items: int, total_net_weight: string, by_karat: list<array{karat: int, total_net_weight: string, item_count: int}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'total_items' => (int) ($this->resource['total_items'] ?? 0),
            'total_net_weight' => (string) ($this->resource['total_net_weight'] ?? '0.000'),
            'by_karat' => $this->resource['by_karat'] ?? [],
        ];
    }
}
