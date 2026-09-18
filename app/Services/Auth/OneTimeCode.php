<?php

namespace App\Services\Auth;

use App\Mail\OneTimeCodeMail;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * The six digit code sent by email, used twice: as the second step of a sign-in
 * and as the way a forgotten password is reset. The code itself is never
 * stored, only a hash of it, and it expires on its own.
 */
class OneTimeCode
{
    public const LOGIN = 'login';

    public const RESET = 'reset';

    /** A code is good for ten minutes. */
    public const VALID_MINUTES = 10;

    /** A new code can be asked for once a minute. */
    public const RESEND_SECONDS = 60;

    /** How many codes may be sent before the account is locked out. */
    public const MAX_SENDS = 5;

    /**
     * Issues a code and emails it. Returns false when the account has asked
     * for too many, or has no email address to send to.
     */
    public function send(User $user, string $purpose): bool
    {
        if (! $user->email) {
            return false;
        }

        if ($user->otp_sends >= self::MAX_SENDS && $user->otp_purpose === $purpose && ! $this->staleRun($user)) {
            $this->lock($user, 'Too many codes requested');

            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'otp_hash' => Hash::make($code),
            'otp_purpose' => $purpose,
            'otp_expires_at' => now()->addMinutes(self::VALID_MINUTES),
            'otp_attempts' => 0,
            'otp_sent_at' => now(),
            'otp_sends' => $this->staleRun($user) ? 1 : $user->otp_sends + 1,
        ])->save();

        Mail::to($user->email)->send(new OneTimeCodeMail($user, $code, $purpose));

        return true;
    }

    /** True when the last code was long enough ago to start counting again. */
    private function staleRun(User $user): bool
    {
        return $user->otp_sent_at === null || $user->otp_sent_at->lt(now()->subMinutes(30));
    }

    public function canResend(User $user): bool
    {
        return $user->otp_sent_at === null || $user->otp_sent_at->lte(now()->subSeconds(self::RESEND_SECONDS));
    }

    public function secondsUntilResend(User $user): int
    {
        if ($this->canResend($user)) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) $user->otp_sent_at->diffInSeconds(now()));
    }

    /**
     * Checks a code. Wrong codes count towards the same five try limit as
     * passwords, and the fifth wrong code locks the account.
     *
     * @return array{0: bool, 1: ?string}  passed, and why not
     */
    public function check(User $user, string $purpose, string $code): array
    {
        if ($user->otp_hash === null || $user->otp_purpose !== $purpose) {
            return [false, 'Ask for a new code to continue.'];
        }

        if ($user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            return [false, 'That code has expired. Ask for a new one.'];
        }

        if (! Hash::check($code, $user->otp_hash)) {
            $user->increment('otp_attempts');
            $user->refresh();

            if ($user->otp_attempts >= PasswordPolicy::MAX_ATTEMPTS) {
                $minutes = $this->lock($user, 'Too many wrong codes');

                return [false, 'Too many wrong codes. This account is locked for '.PasswordPolicy::lockLabel($minutes).'.'];
            }

            $left = PasswordPolicy::MAX_ATTEMPTS - $user->otp_attempts;

            return [false, 'That code is not right. '.$left.' '.($left === 1 ? 'try' : 'tries').' left.'];
        }

        $this->clear($user);

        return [true, null];
    }

    /** Wipes the code, whether it was used or abandoned. */
    public function clear(User $user): void
    {
        $user->forceFill([
            'otp_hash' => null,
            'otp_purpose' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_sends' => 0,
        ])->save();
    }

    /** Locks the account for its next step up, and says for how long. */
    public function lock(User $user, string $reason): int
    {
        $minutes = PasswordPolicy::lockMinutes($user->lock_level);

        $user->forceFill([
            'otp_hash' => null,
            'otp_purpose' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_sends' => 0,
            'failed_login_attempts' => 0,
            'lock_level' => min($user->lock_level + 1, count(PasswordPolicy::LOCK_STEPS) - 1),
            'locked_until' => now()->addMinutes($minutes),
        ])->save();

        \App\Models\UserAuditLog::record('login.locked', $user, [
            'reason' => $reason,
            'minutes' => $minutes,
        ], $user);

        return $minutes;
    }
}
