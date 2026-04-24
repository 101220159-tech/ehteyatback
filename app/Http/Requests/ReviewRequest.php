<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
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
            'booking_id'  => ['required', 'uuid', 'exists:bookings,id'],
            'rating'      => ['required', 'integer', 'min:1', 'max:5'],
            'comment'     => ['nullable', 'string', 'max:5000'],
        ];
    }
}
