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
            'body' => ['required', 'string', 'max:10000'],
            'type' => ['nullable', 'in:text,image'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');
        if (($body === null || $body === '') && ($alt = $this->input('message') ?? $this->input('message_text'))) {
            $this->merge(['body' => $alt]);
        }
    }
}
