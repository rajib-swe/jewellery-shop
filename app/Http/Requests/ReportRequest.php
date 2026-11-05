<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{from?: list<string>, to?: list<string>, group_by?: list<string>, days?: list<string>}
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'group_by' => ['sometimes', 'string', 'in:day,month'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }
}
