<?php

namespace App\Http\Requests;

use App\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{page?: list<string>, per_page?: list<string>, search?: list<string>, type?: list<string>}
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type' => ['sometimes', 'string', Rule::enum(SupplierType::class)],
        ];
    }
}
