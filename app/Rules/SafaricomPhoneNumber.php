<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Prizes, payouts and STK pushes all go through M-Pesa, so a phone number
 * must be on a Safaricom prefix. Accepts 07.., 01.., 254.. and +254..
 * formats, with spaces or dashes.
 */
class SafaricomPhoneNumber implements ValidationRule
{
    /**
     * Captures the 9-digit national number (without the 0 or 254).
     */
    public const PATTERN = '/^(?:\+?254|0)((?:7(?:[0129][0-9]|4[0123568]|5[789]|6[89])|1(?:1[01234589]|4[0123]|8[012]))[0-9]{6})$/';

    public const MESSAGE = 'Enter a Safaricom M-Pesa number, like 0712 345 678. Prizes and payouts are sent through M-Pesa, so Airtel, Telkom and other networks can\'t receive them.';

    /**
     * $phone in the local "07XXXXXXXX" format users are stored with, or
     * unchanged when it isn't a Safaricom number (so validation rejects it).
     */
    public static function normalize(string $phone): string
    {
        return Str::of($phone)
            ->replaceMatches('/[\s-]/', '')
            ->match(self::PATTERN)
            ->whenNotEmpty(
                fn ($national) => $national->prepend('0'),
                fn () => Str::of($phone),
            )
            ->toString();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Str::of($value)->replaceMatches('/[\s-]/', '')->isMatch(self::PATTERN)) {
            $fail(self::MESSAGE);
        }
    }
}
