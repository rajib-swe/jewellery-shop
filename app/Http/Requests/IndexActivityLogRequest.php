<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{page?: list<string>, per_page?: list<string>, user_id?: list<string>, log_name?: list<string>, event?: list<string>, date_from?: list<string>, date_to?: list<string>, search?: list<string>}
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'log_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'event' => ['sometimes', 'nullable', 'string', 'max:30'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
