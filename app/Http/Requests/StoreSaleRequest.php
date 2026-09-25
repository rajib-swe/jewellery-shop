<?php

namespace App\Http\Requests;

use App\Karat;
use App\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{customer_id?: list<string>, date?: list<string>, discount?: list<string>, notes?: list<string>, items: list<string>, payments?: list<string>, exchanges?: list<string>}
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'discount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'items.*.rate' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'payments' => ['sometimes', 'array', 'max:20'],
            'payments.*.method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payments.*.reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'exchanges' => ['sometimes', 'array', 'max:20'],
            'exchanges.*.description' => ['required', 'string', 'max:255'],
            'exchanges.*.karat' => ['required', 'integer', Rule::enum(Karat::class)],
            'exchanges.*.weight' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999.999'],
            'exchanges.*.rate' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'exchanges.*.category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $itemIds = array_map(
                static fn (mixed $item): mixed => is_array($item) ? ($item['item_id'] ?? null) : null,
                (array) $this->input('items', []),
            );

            if (count($itemIds) !== count(array_unique($itemIds))) {
                $validator->errors()->add('items', 'The same item cannot be added to a sale twice.');
            }
        });
    }
}
