<?php

namespace App\Http\Requests;

use App\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedeemPawnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{amount?: list<string>, date?: list<string>, method?: list<string>, reference?: list<string>, note?: list<string>}
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['sometimes', 'required', 'string', Rule::enum(PaymentMethod::class)],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
