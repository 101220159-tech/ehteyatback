<?php

namespace App\Support;

final class LebanonMobilePhone
{
    /**
     * Normalize provider input to E.164-style +961 followed by 8 digits.
     *
     * Accepts: 8 local digits (e.g. 71234567), or 961 + 8 digits, optional leading +,
     * arbitrary spaces (stripped before parsing).
     *
     * @return non-empty-string|null  null if empty/invalid
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        $compact = preg_replace('/\s+/', '', $trimmed) ?? $trimmed;
        $compact = ltrim($compact, '+');

        if (preg_match('/^(\d{8})$/', $compact, $m)) {
            return '+961'.$m[1];
        }

        if (preg_match('/^961(\d{8})$/', $compact, $m)) {
            return '+961'.$m[1];
        }

        return null;
    }
}
