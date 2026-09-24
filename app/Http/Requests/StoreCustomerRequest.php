<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (! is_string($phone)) {
            return;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0088')) {
            $nationalNumber = substr($digits, 4);
            $digits = str_starts_with($nationalNumber, '0') ? $nationalNumber : '0'.$nationalNumber;
        } elseif (str_starts_with($digits, '88')) {
            $nationalNumber = substr($digits, 2);
            $digits = str_starts_with($nationalNumber, '0') ? $nationalNumber : '0'.$nationalNumber;
        }

        $this->merge(['phone' => $digits]);
    }

    /**
     * @return array{name: list<string>, phone: list<string>, nid?: list<string>, address?: list<string>, photo?: list<string>, remove_photo?: list<string>, opening_balance: list<string>, notes?: list<string>}
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\d{10,15}$/', Rule::unique('customers', 'phone')],
            'nid' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'opening_balance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
