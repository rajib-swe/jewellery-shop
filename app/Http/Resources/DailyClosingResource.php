<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyClosingResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     date: string,
     *     opening_balance: string,
     *     total_in: string,
     *     total_out: string,
     *     closing_balance: string,
     *     is_locked: bool,
     *     closed_at: ?string,
     *     reopened_at: ?string,
     *     closed_by: array{id: int, name: string}|null,
     *     reopened_by: array{id: int, name: string}|null,
     *     note: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'opening_balance' => (string) $this->opening_balance,
            'total_in' => (string) $this->total_in,
            'total_out' => (string) $this->total_out,
            'closing_balance' => (string) $this->closing_balance,
            'is_locked' => $this->isLocked(),
            'closed_at' => $this->closed_at?->toAtomString(),
            'reopened_at' => $this->reopened_at?->toAtomString(),
            'closed_by' => $this->closedBy === null ? null : [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ],
            'reopened_by' => $this->reopenedBy === null ? null : [
                'id' => $this->reopenedBy->id,
                'name' => $this->reopenedBy->name,
            ],
            'note' => $this->note,
        ];
    }
}
