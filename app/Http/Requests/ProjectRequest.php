<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Frontends may send `name`; backend persists `title`.
        if (! $this->filled('title') && $this->filled('name')) {
            $this->merge(['title' => $this->input('name')]);
        }
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
            // Optional: projects can be created first, then images uploaded via /projects/{id}/images.
            'image_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
