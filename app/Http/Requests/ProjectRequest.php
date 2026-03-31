<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
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
        $updating = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'title' => $updating ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'image_url' => $updating
                ? ['nullable', 'string', 'max:2048']
                : ['nullable', 'string', 'max:2048', 'required_without:image'],
        ];
    }
}
