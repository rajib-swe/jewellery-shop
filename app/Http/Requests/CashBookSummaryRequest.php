<?php

namespace App\Http\Requests;

use App\CashDirection;
use App\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashBookSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{date?: list<string>, direction?: list<string>, method?: list<string>, source_type?: list<string>}
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'direction' => ['sometimes', 'string', Rule::enum(CashDirection::class)],
            'method' => ['sometimes', 'string', Rule::enum(PaymentMethod::class)],
            'source_type' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
