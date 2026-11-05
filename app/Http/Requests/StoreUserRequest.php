<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{name: list<string>, email: list<string>, password: list<string>, roles: list<string>}
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'roles' => ['sometimes', 'array', 'max:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
