<?php

namespace App\Http\Requests;

use App\ExpenseCategory;
use App\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{category?: list<string>, title?: list<string>, amount?: list<string>, date?: list<string>, method?: list<string>, reference?: list<string>, note?: list<string>}
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'required', 'string', Rule::enum(ExpenseCategory::class)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['sometimes', 'required', 'string', Rule::enum(PaymentMethod::class)],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
