<?php

namespace App\Http\Resources;

use App\Services\PawnInterestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PawnResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     pawn_no: string,
     *     date: string,
     *     due_date: string,
     *     status: string,
     *     principal: string,
     *     interest_rate: string,
     *     interest_type: string,
     *     notes: ?string,
     *     is_overdue: bool,
     *     customer: array{id: int, code: string, name: string, phone: string}|null,
     *     user: array{id: int, name: string}|null,
     *     items: list<array{id: int, description: string, karat: int, gross_weight: string, stone_weight: string, net_weight: string, estimated_value: string, photo_url: ?string, item_id: ?int}>,
     *     payments: list<array{id: int, type: string, amount: string, date: string, method: string, reference: ?string, note: ?string, user: array{id: int, name: string}|null}>,
     *     summary: array<string, mixed>,
     *     redeemed_at: ?string,
     *     forfeited_at: ?string,
     *     renewed_at: ?string,
     *     close_reason: ?string,
     *     created_at: ?string,
     *     updated_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        $summary = app(PawnInterestService::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'pawn_no' => $this->pawn_no,
            'date' => $this->date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'principal' => (string) $this->principal,
            'interest_rate' => (string) $this->interest_rate,
            'interest_type' => $this->interest_type->value,
            'notes' => $this->notes,
            'is_overdue' => $this->resource->isOverdue(),
            'customer' => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ],
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description,
                'karat' => (int) $item->karat,
                'gross_weight' => (string) $item->gross_weight,
                'stone_weight' => (string) $item->stone_weight,
                'net_weight' => (string) $item->net_weight,
                'estimated_value' => (string) $item->estimated_value,
                'photo_url' => $item->photoUrl(),
                'item_id' => $item->item_id,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'type' => $payment->type->value,
                'amount' => (string) $payment->amount,
                'date' => $payment->date->toDateString(),
                'method' => $payment->method->value,
                'reference' => $payment->reference,
                'note' => $payment->note,
                'user' => $payment->user === null ? null : [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                ],
            ])->all()),
            'summary' => $summary,
            'redeemed_at' => $this->redeemed_at?->toAtomString(),
            'forfeited_at' => $this->forfeited_at?->toAtomString(),
            'renewed_at' => $this->renewed_at?->toAtomString(),
            'close_reason' => $this->close_reason,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
