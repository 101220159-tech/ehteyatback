<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FcmTokenRequest extends FormRequest
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
        return [
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
