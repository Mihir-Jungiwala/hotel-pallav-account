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

    /**
     * A password for a new account. Nobody chooses it and nobody keeps it: it
     * is emailed once and has to be replaced before the account can be used.
     * Characters that are easy to misread (O/0, l/1) are left out, because
     * this one gets typed by hand from an email.
     */
    public static function generate(int $length = 12): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%^&*?';
        $all = $upper.$lower.$digits.$symbols;

        // One of each kind first, so the result always passes the policy
        $characters = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = count($characters); $i < max($length, 8); $i++) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffled with the same source of randomness, so the first four
        // positions do not give the pattern away
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    public static function rules(bool $confirmed = true): array
    {
        return array_values(array_filter([
            'required', 'string', 'max:128', $confirmed ? 'confirmed' : null, 'regex:'.self::REGEX,
        ]));
    }
}
