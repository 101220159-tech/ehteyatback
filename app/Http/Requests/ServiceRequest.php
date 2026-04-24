<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('id') !== null;

        return [
            'name'        => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => [$isUpdate ? 'sometimes' : 'required', 'exists:service_categories,id'],
            'base_price'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'icon'        => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ];
    }
}
