<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierLedgerResource extends JsonResource
{
    /**
     * @return array{
     *     supplier: array{id: int, code: string, name: string, type: string, phone: ?string},
     *     balance: string,
     *     total_purchases: string,
     *     total_payments: string,
     *     transactions: list<array<string, mixed>>,
     * }
     */
    public function toArray(Request $request): array
    {
        $supplier = $this->resource['supplier'];
        $ledger = $this->resource['ledger'];

        return [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'type' => $supplier->type->value,
                'phone' => $supplier->phone,
            ],
            'balance' => $ledger['balance'],
            'total_purchases' => $ledger['total_purchases'],
            'total_payments' => $ledger['total_payments'],
            'transactions' => $ledger['transactions'],
        ];
    }
}
