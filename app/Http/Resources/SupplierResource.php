<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * The balance is a subquery total, not a per-row ledger replay, so the list
     * stays a single query. It is absent unless the caller selected it.
     *
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     type: string,
     *     phone: ?string,
     *     address: ?string,
     *     nid: ?string,
     *     notes: ?string,
     *     purchases_count: int,
     *     payments_count: int,
     *     balance: ?string,
     *     created_at: ?string,
     *     updated_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'phone' => $this->phone,
            'address' => $this->address,
            'nid' => $this->nid,
            'notes' => $this->notes,
            'purchases_count' => (int) ($this->purchases_count ?? 0),
            'payments_count' => (int) ($this->payments_count ?? 0),
            'balance' => $this->resource->getAttribute('purchases_total') === null
                ? null
                : number_format(
                    ((float) $this->resource->getAttribute('purchases_total'))
                    - ((float) $this->resource->getAttribute('payments_total')),
                    2,
                    '.',
                    '',
                ),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
