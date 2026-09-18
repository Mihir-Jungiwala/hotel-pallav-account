<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Services\Auth\OneTimeCode;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in takes two steps: the username and password, then a six digit code
 * sent to the account's email. Five wrong tries of either lock the account, and
 * each lock lasts longer than the last, up to a day. Only one device may hold a
 * session at a time.
 */
class AuthController extends Controller
{
    /** Where the half finished sign-in is kept between the two steps. */
    private const PENDING = 'auth.pending';

    /** Identifies the one device allowed to hold this account's session. */
    public const DEVICE = 'auth.device';

    public function __construct(private OneTimeCode $codes) {}

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /* --------------------------------------------------------- step one */

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $username = Str::lower(trim($data['username']));

        // Per device brake against spraying many usernames
        $ipKey = 'login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $this->fail('Too many sign-in attempts from this device. Try again in '.ceil(RateLimiter::availableIn($ipKey) / 60).' minutes.');
        }
        RateLimiter::hit($ipKey, 900);

        $user = User::whereRaw('LOWER(username) = ?', [$username])->first();

        if ($user && $user->isLocked()) {
            $this->fail($this->lockedMessage($user));
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $this->registerFailure($user);
            }

            // The same message whether or not the username exists
            $this->fail('Incorrect username or password.');
        }

        if (! $user->is_active) {
            $this->fail('This account is deactivated. Contact your administrator.');
        }

        $user->forceFill(['failed_login_attempts' => 0])->save();
        RateLimiter::clear($ipKey);

        // Without an email address there is nowhere to send a code, so the
        // password is all we can ask for
        if (! $user->two_factor_enabled || ! $user->email) {
            return $this->completeSignIn($request, $user, false);
        }

        if (! $this->codes->send($user, OneTimeCode::LOGIN)) {
            $this->fail($user->fresh()->isLocked()
                ? $this->lockedMessage($user->fresh())
                : 'A code could not be sent. Ask your administrator to sign you in.');
        }

        $request->session()->put(self::PENDING, ['id' => $user->id, 'at' => now()->timestamp]);

        return redirect()->route('login.verify')
            ->with('success', 'We sent a six digit code to '.$this->maskEmail($user->email).'.');
    }

    /* --------------------------------------------------------- step two */

    public function showVerify(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Start again, that sign-in timed out.');
        }

        return view('auth.verify', [
            'maskedEmail' => $this->maskEmail($user->email),
            'secondsUntilResend' => $this->codes->secondsUntilResend($user),
            'triesLeft' => PasswordPolicy::MAX_ATTEMPTS - $user->otp_attempts,
        ]);
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Start again, that sign-in timed out.');
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        if ($user->isLocked()) {
            $request->session()->forget(self::PENDING);
            $this->fail($this->lockedMessage($user));
        }

        [$passed, $why] = $this->codes->check($user, OneTimeCode::LOGIN, $request->string('code')->toString());

        if (! $passed) {
            if ($user->fresh()->isLocked()) {
                $request->session()->forget(self::PENDING);
            }

            throw ValidationException::withMessages(['code' => $why]);
        }

        $request->session()->forget(self::PENDING);

        return $this->completeSignIn($request, $user, true);
    }

    public function resend(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Start again, that sign-in timed out.');
        }

        if (! $this->codes->canResend($user)) {
            return back()->with('error', 'Wait '.$this->codes->secondsUntilResend($user).' seconds before asking for another code.');
        }

        if (! $this->codes->send($user, OneTimeCode::LOGIN)) {
            $request->session()->forget(self::PENDING);

            return redirect()->route('login')->with('error', $this->lockedMessage($user->fresh()));
        }

        return back()->with('success', 'A new code is on its way.');
    }

    /** Signs the user in and ends any session they had elsewhere. */
    private function completeSignIn(Request $request, User $user, bool $withCode)
    {
        Auth::login($user);
        $request->session()->regenerate();

        // The device is identified by a token kept in the session, which
        // survives a session id change and is easy to compare
        $device = Str::random(40);
        $request->session()->put(self::DEVICE, $device);

        $now = now();
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'lock_level' => 0,
            'last_login_at' => $now,
            'last_login_ip' => $request->ip(),
            'current_session_id' => $device,
            'session_started_at' => $now,
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'Login',
            'activity_time' => $now,
            'login_date' => $now->toDateString(),
            'login_time' => $now->toTimeString(),
        ]);

        UserAuditLog::record($withCode ? 'login.verified' : 'login.password_only', $user, [
            'ip' => $request->ip(),
        ], $user);

        if ($user->must_change_password) {
            return redirect()->route('profile.show', ['tab' => 'password'])
                ->with('error', 'Please set a new password before continuing.');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Signed in. Any other device using this account has been signed out.');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING);

        // A half finished sign-in is only good for ten minutes
        if (! $pending || now()->timestamp - ($pending['at'] ?? 0) > OneTimeCode::VALID_MINUTES * 60) {
            $request->session()->forget(self::PENDING);

            return null;
        }

        return User::find($pending['id']);
    }

    private function registerFailure(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        if ($attempts < PasswordPolicy::MAX_ATTEMPTS) {
            $user->forceFill(['failed_login_attempts' => $attempts])->save();

            return;
        }

        $this->codes->lock($user, 'Too many wrong passwords');
    }

    private function lockedMessage(User $user): string
    {
        $minutes = max(1, (int) ceil(now()->diffInMinutes($user->locked_until, false)));
        $wait = $minutes >= 60
            ? intdiv($minutes, 60).' hours '.($minutes % 60).' minutes'
            : $minutes.' minutes';

        return 'This account is locked after too many failed attempts. Try again in '.$wait.', or ask an administrator to unlock it.';
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'your email';
        }

        [$name, $domain] = explode('@', $email, 2);
        $shown = mb_substr($name, 0, 2);

        return $shown.str_repeat('*', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['username' => $message]);
    }

    /* ---------------------------------------------------------- sign out */

    public function logout(Request $request)
    {
        $this->closeActivityLog();

        if ($user = Auth::user()) {
            $user->forceFill(['current_session_id' => null, 'session_started_at' => null])->save();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out.');
    }

    public static function closeActivityLog(): void
    {
        $log = ActivityLog::where('user_id', Auth::id())
            ->where('activity_type', 'Login')
            ->whereNull('logout_time')
            ->latest('id')->first();

        if (! $log) {
            return;
        }

        $now = now();
        $minutes = $log->login_time ? (int) abs($now->diffInMinutes($log->login_date->format('Y-m-d').' '.$log->login_time)) : 0;

        $log->forceFill([
            'logout_date' => $now->toDateString(),
            'logout_time' => $now->toTimeString(),
            'minutes_logged_in' => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
        ])->save();
    }

    /* ------------------------------------------------------------------
     | Forgotten password, by code rather than by link
     |------------------------------------------------------------------ */

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendResetCode(Request $request)
    {
        $request->validate(['username' => ['required', 'string', 'max:50']]);

        $key = 'reset-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Too many requests from this device. Wait a few minutes.');
        }
        RateLimiter::hit($key, 900);

        $user = User::whereRaw('LOWER(username) = ?', [Str::lower(trim($request->string('username')->toString()))])->first();

        if ($user && $user->is_active && ! $user->isLocked() && $user->email) {
            if ($this->codes->send($user, OneTimeCode::RESET)) {
                $request->session()->put('auth.reset', ['id' => $user->id, 'at' => now()->timestamp]);

                return redirect()->route('password.code')
                    ->with('success', 'We sent a six digit code to '.$this->maskEmail($user->email).'.');
            }
        }

        // Never reveal whether the username exists or has an email on file
        return back()->with('success', 'If that account has an email address on file, a code is on its way. Otherwise ask your administrator to reset the password.');
    }

    public function showResetCode(Request $request)
    {
        $user = $this->resetUser($request);

        if (! $user) {
            return redirect()->route('password.request')->with('error', 'Start again, that request timed out.');
        }

        return view('auth.reset-password', [
            'maskedEmail' => $this->maskEmail($user->email),
            'secondsUntilResend' => $this->codes->secondsUntilResend($user),
        ]);
    }

    public function reset(Request $request)
    {
        $user = $this->resetUser($request);

        if (! $user) {
            return redirect()->route('password.request')->with('error', 'Start again, that request timed out.');
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'password' => PasswordPolicy::rules(),
        ], ['password.regex' => PasswordPolicy::MESSAGE]);

        if ($user->isLocked()) {
            $request->session()->forget('auth.reset');

            return redirect()->route('login')->with('error', $this->lockedMessage($user));
        }

        [$passed, $why] = $this->codes->check($user, OneTimeCode::RESET, $request->string('code')->toString());

        if (! $passed) {
            if ($user->fresh()->isLocked()) {
                $request->session()->forget('auth.reset');
            }

            return back()->withErrors(['code' => $why])->withInput($request->except('password', 'password_confirmation'));
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'lock_level' => 0,
            // Any session held elsewhere is no longer valid
            'current_session_id' => null,
            'session_started_at' => null,
            'remember_token' => null,
        ])->save();

        UserAuditLog::record('password.reset_by_code', $user, [], $user);
        $request->session()->forget('auth.reset');

        return redirect()->route('login')->with('success', 'Password changed. You can sign in now.');
    }

    private function resetUser(Request $request): ?User
    {
        $pending = $request->session()->get('auth.reset');

        if (! $pending || now()->timestamp - ($pending['at'] ?? 0) > OneTimeCode::VALID_MINUTES * 60) {
            $request->session()->forget('auth.reset');

            return null;
        }

        return User::find($pending['id']);
    }
}
