<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerHistoryResource extends JsonResource
{
    /**
     * @return array{sales: list<mixed>, pawns: list<mixed>, payments: list<mixed>, due_balance: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'sales' => $this->resource['sales'] ?? [],
            'pawns' => $this->resource['pawns'] ?? [],
            'payments' => $this->resource['payments'] ?? [],
            'due_balance' => (string) ($this->resource['due_balance'] ?? '0.00'),
        ];
    }
}
