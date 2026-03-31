<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['nullable', 'string', 'in:customer,provider'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->boolean('register_as_provider') || $this->input('role') === 'provider') {
            $rules['provider_phone'] = ['required', 'string', 'max:50'];
        }

        return $rules;
    }
}
