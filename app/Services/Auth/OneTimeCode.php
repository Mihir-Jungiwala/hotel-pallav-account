<?php

namespace App\Services\Auth;

use App\Mail\OneTimeCodeMail;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * The six digit code sent by email, used twice: as an optional second step of a
 * sign-in and as the way a forgotten password is reset.
 *
 * Two limits, and they are different things:
 *  - five wrong codes BLOCK the account, like five wrong passwords
 *  - too many emails start a WAIT that gets longer each run, up to 24 hours.
 *    The account is not blocked, it simply cannot ask for another code yet.
 */
class OneTimeCode
{
    public const LOGIN = 'login';

    public const RESET = 'reset';

    /** Proving the email works before the sign-in code is switched on. */
    public const ENABLE = 'enable';

    /** A code is good for ten minutes. */
    public const VALID_MINUTES = 10;

    /** A new code can be asked for once a minute. */
    public const RESEND_SECONDS = 60;

    /** Emails allowed before the wait starts. */
    public const MAX_SENDS = 5;

    /** Why a send was refused, so the screen can say something useful. */
    public ?string $refusal = null;

    /**
     * Issues a code and emails it. Returns false when there is no email on
     * file, or the account is inside its waiting period.
     */
    public function send(User $user, string $purpose): bool
    {
        $this->refusal = null;

        if (! $user->email) {
            $this->refusal = 'There is no email address on this account.';

            return false;
        }

        // The wait from a previous run has to pass first
        if ($this->waitingSeconds($user) > 0) {
            $this->refusal = 'Too many codes were requested. Try again in '.$this->waitLabel($user).'.';

            return false;
        }

        // A fresh run: the counter starts again once the wait has passed
        if ($user->otp_cooldown_until !== null && $user->otp_cooldown_until->isPast()) {
            $user->forceFill(['otp_sends' => 0, 'otp_cooldown_until' => null])->save();
            $user->refresh();
        }

        if ((int) $user->otp_sends >= self::MAX_SENDS) {
            $minutes = $this->startWait($user);

            $this->refusal = 'That is '.self::MAX_SENDS.' codes. For safety the next one can be asked for in '
                .PasswordPolicy::lockLabel($minutes).'.';

            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'otp_hash' => Hash::make($code),
            'otp_purpose' => $purpose,
            'otp_expires_at' => now()->addMinutes(self::VALID_MINUTES),
            'otp_attempts' => 0,
            'otp_sent_at' => now(),
            'otp_sends' => (int) $user->otp_sends + 1,
        ])->save();

        Mail::to($user->email)->send(new OneTimeCodeMail($user, $code, $purpose));

        return true;
    }

    /* ------------------------------------------------------------- the wait */

    /** Starts, or lengthens, the waiting period. Returns its length in minutes. */
    public function startWait(User $user): int
    {
        $minutes = PasswordPolicy::lockMinutes((int) $user->otp_cooldown_level);

        $user->forceFill([
            'otp_hash' => null,
            'otp_purpose' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_cooldown_until' => now()->addMinutes($minutes),
            'otp_cooldown_level' => min((int) $user->otp_cooldown_level + 1, count(PasswordPolicy::LOCK_STEPS) - 1),
        ])->save();

        UserAuditLog::record('otp.cooldown', $user, ['minutes' => $minutes], $user);

        return $minutes;
    }

    public function waitingSeconds(User $user): int
    {
        if ($user->otp_cooldown_until === null || $user->otp_cooldown_until->isPast()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInSeconds($user->otp_cooldown_until, false)));
    }

    public function waitLabel(User $user): string
    {
        $seconds = $this->waitingSeconds($user);

        if ($seconds <= 90) {
            return $seconds.' seconds';
        }

        return PasswordPolicy::lockLabel((int) ceil($seconds / 60));
    }

    public function canResend(User $user): bool
    {
        if ($this->waitingSeconds($user) > 0) {
            return false;
        }

        return $user->otp_sent_at === null || $user->otp_sent_at->lte(now()->subSeconds(self::RESEND_SECONDS));
    }

    public function secondsUntilResend(User $user): int
    {
        if ($this->waitingSeconds($user) > 0) {
            return $this->waitingSeconds($user);
        }

        if ($user->otp_sent_at === null) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) $user->otp_sent_at->diffInSeconds(now()));
    }

    /** How many emails are left in this run. */
    public function sendsLeft(User $user): int
    {
        return max(0, self::MAX_SENDS - (int) $user->otp_sends);
    }

    /* ------------------------------------------------------------ the code */

    /**
     * Checks a code. Five wrong ones block the account, exactly like five
     * wrong passwords.
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
                $this->block($user, 'Five wrong sign-in codes');

                return [false, 'That is '.PasswordPolicy::MAX_ATTEMPTS.' wrong codes, so the account is now blocked. An administrator can unblock it, or reset the password from the sign-in screen.'];
            }

            $left = PasswordPolicy::MAX_ATTEMPTS - $user->otp_attempts;

            return [false, 'That code is not right. '.$left.' '.($left === 1 ? 'try' : 'tries').' left before the account is blocked.'];
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
            'otp_cooldown_until' => null,
            'otp_cooldown_level' => 0,
        ])->save();
    }

    /**
     * Blocks the account. There is no timer on this: an administrator unblocks
     * it, or the owner proves the email is theirs by resetting the password.
     */
    public function block(User $user, string $reason): void
    {
        $user->forceFill([
            'otp_hash' => null,
            'otp_purpose' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'failed_login_attempts' => 0,
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ])->save();

        UserAuditLog::record('login.blocked', $user, ['reason' => $reason], $user);
    }
}
