<?php

namespace App\Http\Requests;

use App\Karat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGoldRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{page?: list<string>, per_page?: list<string>, search?: list<string>, karat?: list<string>, effective_date?: list<string>}
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'karat' => ['sometimes', 'integer', Rule::enum(Karat::class)],
            'effective_date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
