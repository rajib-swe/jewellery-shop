<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransactionResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     source_type: string,
     *     source_id: ?string,
     *     direction: string,
     *     amount: string,
     *     method: string,
     *     date: string,
     *     reference: ?string,
     *     note: ?string,
     *     user: array{id: int, name: string}|null,
     *     created_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type->value,
            'source_id' => $this->source_id,
            'direction' => $this->direction->value,
            'amount' => (string) $this->amount,
            'method' => $this->method->value,
            'date' => $this->date->toDateString(),
            'reference' => $this->reference,
            'note' => $this->note,
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
