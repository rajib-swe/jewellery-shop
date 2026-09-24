<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ItemResource extends JsonResource
{
    /**
     * @return array{id: int, tag_no: string, category: array{id: int, name: string}, name: string, karat: int, gross_weight: string, stone_weight: string, net_weight: string, making_type: string, making_value: string, stone_price: string, status: string, image: ?string, image_url: ?string, barcode: ?string, created_at: ?string, updated_at: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tag_no' => $this->tag_no,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'name' => $this->name,
            'karat' => $this->karat,
            'gross_weight' => (string) $this->gross_weight,
            'stone_weight' => (string) $this->stone_weight,
            'net_weight' => (string) $this->net_weight,
            'making_type' => $this->making_type->value,
            'making_value' => (string) $this->making_value,
            'stone_price' => (string) $this->stone_price,
            'status' => $this->status->value,
            'image' => $this->image,
            'image_url' => $this->image === null
                ? null
                : Storage::disk('public')->url($this->image),
            'barcode' => $this->barcode,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
