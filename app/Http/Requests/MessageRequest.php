<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
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
            'message_text' => ['required', 'string', 'max:10000'],
            'message_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
