<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{shop_name?: list<string>, shop_address?: list<string>, shop_phone?: list<string>, shop_logo?: list<string>, remove_shop_logo?: list<string>, vat_percentage?: list<string>, currency_symbol?: list<string>, weight_unit?: list<string>, default_pawn_interest_rate?: list<string>, invoice_footer?: list<string>}
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['sometimes', 'required', 'string', 'max:255'],
            'shop_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'shop_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'shop_logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_shop_logo' => ['sometimes', 'boolean'],
            'vat_percentage' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'currency_symbol' => ['sometimes', 'required', 'string', 'max:10'],
            'weight_unit' => ['sometimes', 'required', Rule::in(['gram', 'vori'])],
            'default_pawn_interest_rate' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'invoice_footer' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
