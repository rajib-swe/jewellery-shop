<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /**
     * @return array{name?: list<string>, description?: list<string>}
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
