<?php

namespace App\Http\Requests;

use App\ItemStatus;
use App\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{type: list<string>, weight: list<string>, status: list<string>, note?: list<string>}
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(StockMovementType::class)],
            'weight' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999.999'],
            'status' => ['required', 'string', Rule::enum(ItemStatus::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
