<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{name?: list<string>, email?: list<string>, password?: list<string>, roles?: list<string>}
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            // Left unset to keep the current password, so the form never has to
            // send a hash back and forth.
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::min(8)],
            'roles' => ['sometimes', 'array', 'max:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
