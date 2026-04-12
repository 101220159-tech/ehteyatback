<?php

namespace App\Http\Requests\Customer\Address;

use App\Rules\LebanonProviderPhone;
use App\Support\LebanonMobilePhone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $normalized = LebanonMobilePhone::normalize($this->input('phone'));
            $this->merge([
                // Important: don't convert invalid input to `null`, otherwise `nullable` would
                // let the rule pass. Keep the original value when normalization fails.
                'phone' => $normalized ?? $this->input('phone'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address' => ['sometimes', 'string', 'max:2000'],
            'latitude' => ['sometimes', 'numeric'],
            'longitude' => ['sometimes', 'numeric'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],

            'is_default' => ['sometimes', 'boolean'],
            'address_type' => ['sometimes', 'string', 'max:50'],

            'phone' => ['sometimes', 'nullable', 'string', 'max:50', new LebanonProviderPhone],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}

