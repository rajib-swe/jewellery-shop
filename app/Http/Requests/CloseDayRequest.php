<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{date?: list<string>, method?: list<string>, note?: list<string>}
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['sometimes', 'nullable', 'string', 'max:20'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
