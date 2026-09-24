<?php

namespace App\Http\Requests;

use App\ItemStatus;
use App\Karat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{page?: list<string>, per_page?: list<string>, search?: list<string>, category_id?: list<string>, karat?: list<string>, status?: list<string>}
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')],
            'karat' => ['sometimes', 'integer', Rule::enum(Karat::class)],
            'status' => ['sometimes', 'string', Rule::enum(ItemStatus::class)],
        ];
    }
}
