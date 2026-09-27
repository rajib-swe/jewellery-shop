<?php

namespace App\Http\Requests;

use App\Karat;
use App\MakingType;
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $catalogueItemExists = Rule::exists('items', 'id');

        return [
            'customer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('customers', 'id')],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'discount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_id' => ['nullable', 'integer', $catalogueItemExists],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.karat' => ['nullable', 'integer', Rule::enum(Karat::class)],
            'items.*.weight' => ['nullable', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999.999'],
            'items.*.rate' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'items.*.making_type' => ['sometimes', 'nullable', 'string', Rule::enum(MakingType::class)],
            'items.*.making_value' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'items.*.stone_price' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
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
            $itemIds = [];

            foreach ((array) $this->input('items', []) as $index => $line) {
                if (! is_array($line)) {
                    $validator->errors()->add("items.{$index}", 'Each line must be an object.');

                    continue;
                }

                $itemId = $line['item_id'] ?? null;
                $name = trim((string) ($line['name'] ?? ''));

                if ($itemId === null || $itemId === '') {
                    if ($name === '') {
                        $validator->errors()->add("items.{$index}.name", 'A hand-written line needs a product name.');
                    }

                    if (($line['karat'] ?? null) === null) {
                        $validator->errors()->add("items.{$index}.karat", 'A hand-written line needs a karat.');
                    }

                    if (! isset($line['weight']) || (float) $line['weight'] <= 0) {
                        $validator->errors()->add("items.{$index}.weight", 'A hand-written line needs a weight greater than zero.');
                    }

                    continue;
                }

                $itemIds[] = (int) $itemId;
            }

            if (count($itemIds) !== count(array_unique($itemIds))) {
                $validator->errors()->add('items', 'The same item cannot be added to a sale twice.');
            }
        });
    }
}
