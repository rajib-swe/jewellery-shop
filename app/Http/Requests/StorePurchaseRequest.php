<?php

namespace App\Http\Requests;

use App\Karat;
use App\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'discount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'items.*.karat' => ['required', 'integer', Rule::enum(Karat::class)],
            'items.*.gross_weight' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999.999'],
            'items.*.stone_weight' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999.999'],
            'items.*.rate' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'items.*.making_value' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'payment' => ['sometimes', 'nullable', 'array'],
            'payment.amount' => ['required_with:payment', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment.date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment.method' => ['sometimes', 'required', 'string', Rule::enum(PaymentMethod::class)],
            'payment.reference' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A purchase needs at least one item.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $line) {
                if (! is_array($line)) {
                    $validator->errors()->add("items.{$index}", 'Each line must be an object.');

                    continue;
                }

                $grossWeight = (float) ($line['gross_weight'] ?? 0);
                $stoneWeight = (float) ($line['stone_weight'] ?? 0);

                if ($stoneWeight >= $grossWeight) {
                    $validator->errors()->add("items.{$index}.stone_weight", 'The stone weight must be less than the gross weight.');
                }
            }
        });
    }
}
