<?php

namespace App\Support;

class PhMobile
{
    /**
     * Normalize a PH mobile number to canonical E.164 form (+639XXXXXXXXX).
     * Returns null if the input cannot be coerced to a valid PH mobile.
     *
     * Accepted shapes (after stripping non-digits):
     *   09XXXXXXXXX  (11 digits, leading 0)  → +639XXXXXXXXX
     *   9XXXXXXXXX   (10 digits)              → +639XXXXXXXXX
     *   639XXXXXXXXX (12 digits, no +)        → +639XXXXXXXXX
     *   +639XXXXXXXXX (13 chars, with +)      → +639XXXXXXXXX
     *
     * Anything else (landlines, foreign numbers, junk) returns null.
     */
    public static function normalize(string $raw): ?string
    {
        $hasPlus = str_starts_with(trim($raw), '+');
        $digits  = preg_replace('/\D/', '', $raw);

        if ($hasPlus) {
            // Originally had +. Only +639XXXXXXXXX is a valid PH mobile.
            if (strlen($digits) === 12 && str_starts_with($digits, '63') && $digits[2] === '9') {
                return '+' . $digits;
            }
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return '+63' . substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '63') && $digits[2] === '9') {
            return '+' . $digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '+63' . $digits;
        }

        return null;
    }
}
