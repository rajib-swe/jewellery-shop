<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewPawnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{date?: list<string>, term_days?: list<string>, note?: list<string>}
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'term_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
