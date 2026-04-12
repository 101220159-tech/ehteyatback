<?php

namespace App\Http\Requests\Auth;

use App\Rules\LebanonProviderPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Frontends often send the provider mobile as `phone`; we validate and normalize it as `provider_phone`.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->isProviderRegistration()) {
            return;
        }

        if (! $this->filled('provider_phone') && $this->filled('phone')) {
            $this->merge(['provider_phone' => $this->input('phone')]);
        }
    }

    protected function isProviderRegistration(): bool
    {
        $role = strtolower((string) $this->input('role'));

        return $this->boolean('register_as_provider') || $role === 'provider';
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

        if ($this->isProviderRegistration()) {
            $rules['provider_phone'] = ['required', 'string', new LebanonProviderPhone];
        }

        return $rules;
    }
}
