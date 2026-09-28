<?php

namespace App\Http\Requests;

use App\Karat;
use App\PawnInterestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePawnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'principal' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'interest_rate' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
            'interest_type' => ['sometimes', 'string', Rule::enum(PawnInterestType::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.karat' => ['required', 'integer', Rule::enum(Karat::class)],
            'items.*.gross_weight' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999.999'],
            'items.*.stone_weight' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999.999'],
            'items.*.estimated_value' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'items.*.category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
            'items.*.photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A pawn needs at least one pledged item.',
        ];
    }
}
