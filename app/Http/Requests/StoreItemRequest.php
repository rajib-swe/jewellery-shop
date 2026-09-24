<?php

namespace App\Http\Requests;

use App\Karat;
use App\MakingType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{category_id: list<string>, name: list<string>, karat: list<string>, gross_weight: list<string>, stone_weight?: list<string>, making_type: list<string>, making_value: list<string>, stone_price?: list<string>, image?: list<string>, remove_image?: list<string>, barcode?: list<string>}
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'karat' => ['required', 'integer', Rule::enum(Karat::class)],
            'gross_weight' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999.999'],
            'stone_weight' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999.999'],
            'making_type' => ['required', 'string', Rule::enum(MakingType::class)],
            'making_value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'stone_price' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['sometimes', 'boolean'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('items', 'barcode')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $grossWeight = (float) $this->input('gross_weight');
            $stoneWeight = (float) ($this->input('stone_weight') ?? 0);

            if ($stoneWeight > $grossWeight) {
                $validator->errors()->add('stone_weight', 'Stone weight cannot exceed gross weight.');
            }
        });
    }
}
