<?php

namespace App\Http\Requests;

use App\ItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForfeitPawnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forfeit pawns') ?? false;
    }

    /**
     * @return array{reason?: list<string>, move_to_inventory?: list<string>, item_status?: list<string>, date?: list<string>}
     */
    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'move_to_inventory' => ['sometimes', 'boolean'],
            'item_status' => ['sometimes', 'string', Rule::enum(ItemStatus::class)],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
