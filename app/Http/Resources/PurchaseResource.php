<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     purchase_no: string,
     *     date: string,
     *     subtotal: string,
     *     discount: string,
     *     total: string,
     *     paid: string,
     *     due: string,
     *     notes: ?string,
     *     supplier: array{id: int, code: string, name: string, type: string, phone: ?string}|null,
     *     user: array{id: int, name: string}|null,
     *     items: list<array{id: int, item_id: ?int, tag_no: string, name: string, karat: int, gross_weight: string, stone_weight: string, net_weight: string, rate: string, making_value: string, amount: string}>,
     *     payments: list<array{id: int, purchase_id: ?int, amount: string, date: string, method: string, reference: ?string, note: ?string, user: array{id: int, name: string}|null}>,
     *     created_at: ?string,
     *     updated_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_no' => $this->purchase_no,
            'date' => $this->date->toDateString(),
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'paid' => (string) $this->paid,
            'due' => (string) $this->due,
            'notes' => $this->notes,
            'supplier' => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
                'type' => $this->supplier->type->value,
                'phone' => $this->supplier->phone,
            ],
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'tag_no' => $item->tag_no,
                'name' => $item->name,
                'karat' => (int) $item->karat,
                'gross_weight' => (string) $item->gross_weight,
                'stone_weight' => (string) $item->stone_weight,
                'net_weight' => (string) $item->net_weight,
                'rate' => (string) $item->rate,
                'making_value' => (string) $item->making_value,
                'amount' => (string) $item->amount,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'purchase_id' => $payment->purchase_id,
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
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
