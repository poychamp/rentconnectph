<?php

namespace App\Rules;

use App\Support\PhMobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhMobile::normalize($value) === null) {
            $fail('Invalid PH mobile number.');
        }
    }
}
