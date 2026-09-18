<?php

namespace App\Support;

/**
 * One password standard, and one lockout schedule, for every screen that asks
 * for a password or a one time code.
 */
class PasswordPolicy
{
    public const REGEX = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\\\|`~])\S{8,}$/';

    public const MESSAGE = 'Password must be at least 8 characters with an uppercase letter, a lowercase letter, a number and a special character.';

    /** Five tries is all anyone gets, for a password or for a code. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Each lock lasts longer than the last, so a patient attacker gains
     * nothing, while someone who simply mistyped waits a quarter of an hour.
     * Capped at a day.
     */
    public const LOCK_STEPS = [15, 30, 60, 180, 480, 1440];

    public const MAX_LOCK_MINUTES = 1440;

    /** How long the next lock lasts for an account that has been locked before. */
    public static function lockMinutes(int $level): int
    {
        $index = max(0, min($level, count(self::LOCK_STEPS) - 1));

        return min(self::LOCK_STEPS[$index], self::MAX_LOCK_MINUTES);
    }

    /** Written out, so the sign-in screen can explain the wait. */
    public static function lockLabel(int $minutes): string
    {
        if ($minutes >= self::MAX_LOCK_MINUTES) {
            return '24 hours';
        }

        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);

            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        return $minutes.' minutes';
    }

    public static function rules(bool $confirmed = true): array
    {
        return array_values(array_filter([
            'required', 'string', 'max:128', $confirmed ? 'confirmed' : null, 'regex:'.self::REGEX,
        ]));
    }
}
