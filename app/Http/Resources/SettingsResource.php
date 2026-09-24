<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SettingsResource extends JsonResource
{
    /**
     * @return array{shop_name: string, shop_address: string, shop_phone: string, shop_logo: ?string, shop_logo_url: ?string, vat_percentage: string, currency_symbol: string, weight_unit: string, default_pawn_interest_rate: string, invoice_footer: string}
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource,
            'shop_logo_url' => $this->resource['shop_logo'] === null
                ? null
                : Storage::disk('public')->url($this->resource['shop_logo']),
        ];
    }
}
