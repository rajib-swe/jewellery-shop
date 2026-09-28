<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     category: string,
     *     title: string,
     *     amount: string,
     *     date: string,
     *     method: string,
     *     reference: ?string,
     *     note: ?string,
     *     user: array{id: int, name: string}|null,
     *     created_at: ?string,
     *     updated_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'title' => $this->title,
            'amount' => (string) $this->amount,
            'date' => $this->date->toDateString(),
            'method' => $this->method->value,
            'reference' => $this->reference,
            'note' => $this->note,
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
