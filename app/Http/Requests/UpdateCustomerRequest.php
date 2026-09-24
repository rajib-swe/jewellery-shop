<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    /**
     * @return array{name: list<string>, phone: list<string>, nid?: list<string>, address?: list<string>, photo?: list<string>, remove_photo?: list<string>, opening_balance: list<string>, notes?: list<string>}
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^\d{10,15}$/',
                Rule::unique('customers', 'phone')->ignore($this->route('customer')),
            ],
            'nid' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'opening_balance' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
