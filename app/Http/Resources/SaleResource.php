<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     invoice_no: string,
     *     date: string,
     *     status: string,
     *     customer: array{id: int, code: string, name: string, phone: string}|null,
     *     user: array{id: int, name: string}|null,
     *     subtotal: string,
     *     discount: string,
     *     vat: string,
     *     exchange_amount: string,
     *     total: string,
     *     paid: string,
     *     due: string,
     *     notes: ?string,
     *     voided_at: ?string,
     *     void_reason: ?string,
     *     items: list<array{id: int, item_id: ?int, tag_no: string, name: string, karat: int, weight: string, rate: string, gold_value: string, making: string, stone_price: string, line_total: string}>,
     *     payments: list<array{id: int, method: string, amount: string, reference: ?string, user: array{id: int, name: string}|null, created_at: ?string}>,
     *     exchanges: list<array{id: int, description: string, karat: int, weight: string, rate: string, amount: string, item_id: ?int}>,
     *     created_at: ?string,
     *     updated_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'date' => $this->date->toDateString(),
            'status' => $this->status->value,
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
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'vat' => (string) $this->vat,
            'exchange_amount' => (string) $this->exchange_amount,
            'total' => (string) $this->total,
            'paid' => (string) $this->paid,
            'due' => (string) $this->due,
            'notes' => $this->notes,
            'voided_at' => $this->voided_at?->toAtomString(),
            'void_reason' => $this->void_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($saleItem): array => [
                'id' => $saleItem->id,
                'item_id' => $saleItem->item_id,
                'tag_no' => $saleItem->tag_no,
                'name' => $saleItem->name,
                'karat' => $saleItem->karat,
                'weight' => (string) $saleItem->weight,
                'rate' => (string) $saleItem->rate,
                'gold_value' => (string) $saleItem->gold_value,
                'making' => (string) $saleItem->making,
                'stone_price' => (string) $saleItem->stone_price,
                'line_total' => (string) $saleItem->line_total,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'amount' => (string) $payment->amount,
                'reference' => $payment->reference,
                'user' => $payment->user === null ? null : [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                ],
                'created_at' => $payment->created_at?->toAtomString(),
            ])->all()),
            'exchanges' => $this->whenLoaded('exchanges', fn () => $this->exchanges->map(fn ($exchange): array => [
                'id' => $exchange->id,
                'description' => $exchange->description,
                'karat' => $exchange->karat,
                'weight' => (string) $exchange->weight,
                'rate' => (string) $exchange->rate,
                'amount' => (string) $exchange->amount,
                'item_id' => $exchange->item_id,
            ])->all()),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
