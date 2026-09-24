<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CustomerResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, phone: string, nid: ?string, address: ?string, photo: ?string, photo_url: ?string, opening_balance: string, notes: ?string, created_at: ?string, updated_at: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'nid' => $this->nid,
            'address' => $this->address,
            'photo' => $this->photo,
            'photo_url' => $this->photo === null
                ? null
                : Storage::disk('public')->url($this->photo),
            'opening_balance' => (string) $this->opening_balance,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
