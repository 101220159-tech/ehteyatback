<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
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
            'day_of_week' => ['required', 'string', 'max:32'],
            'start_time' => ['required', 'string', 'max:16'],
            'end_time' => ['required', 'string', 'max:16'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
