<?php

namespace App\Support;

class PasswordPolicy
{
    public const REGEX = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\\\|`~])\S{8,}$/';

    public const MESSAGE = 'Password must be at least 8 characters with an uppercase letter, a lowercase letter, a number and a special character.';

    /** Failed attempts before an account is locked. */
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public static function rules(bool $confirmed = true): array
    {
        return array_values(array_filter([
            'required', 'string', 'max:128', $confirmed ? 'confirmed' : null, 'regex:'.self::REGEX,
        ]));
    }
}
