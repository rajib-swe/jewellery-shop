<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoldRateResource extends JsonResource
{
    /**
     * @return array{id: int, karat: int, rate_per_gram: string, effective_date: string, created_by: array{id: int, name: string}, created_at: ?string, updated_at: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'karat' => $this->karat,
            'rate_per_gram' => (string) $this->rate_per_gram,
            'effective_date' => $this->effective_date->toDateString(),
            'created_by' => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ],
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
