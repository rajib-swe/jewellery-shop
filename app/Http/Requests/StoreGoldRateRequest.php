<?php

namespace App\Http\Requests;

use App\Karat;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoldRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{karat: list<string>, rate_per_gram: list<string>, effective_date: list<string>}
     */
    public function rules(): array
    {
        return [
            'karat' => [
                'required',
                Rule::enum(Karat::class),
                Rule::unique('gold_rates')->where(
                    fn (Builder $query): Builder => $query->where(
                        'effective_date',
                        $this->input('effective_date'),
                    ),
                ),
            ],
            'rate_per_gram' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
