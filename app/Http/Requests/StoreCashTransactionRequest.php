<?php

namespace App\Http\Requests;

use App\CashDirection;
use App\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{direction?: list<string>, amount?: list<string>, date?: list<string>, method?: list<string>, reference?: list<string>, note?: list<string>}
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', Rule::enum(CashDirection::class)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['sometimes', 'required', 'string', Rule::enum(PaymentMethod::class)],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'direction.required' => 'Choose whether the money is coming in or going out.',
        ];
    }
}
