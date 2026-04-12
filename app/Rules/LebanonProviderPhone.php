<?php

namespace App\Rules;

use App\Support\LebanonMobilePhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LebanonProviderPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail($this->message());

            return;
        }

        if (LebanonMobilePhone::normalize($value) === null) {
            $fail($this->message());
        }
    }

    public function message(): string
    {
        return 'Enter 8 digits (e.g. 71234567) or 961 plus 8 digits (e.g. 96171234567). Spaces are allowed.';
    }
}
