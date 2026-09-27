<?php

namespace App\Http\Requests;

use App\Services\DocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{size?: list<string>, download?: list<string>}
     */
    public function rules(): array
    {
        return [
            'size' => ['sometimes', 'nullable', 'string', Rule::in(DocumentService::SIZES)],
            'download' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
