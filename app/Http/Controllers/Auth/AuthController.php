<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $username = Str::lower(trim($data['username']));

        // Per-IP brake against spraying many usernames
        $ipKey = 'login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $this->fail('Too many login attempts from this device. Try again in '.ceil(RateLimiter::availableIn($ipKey) / 60).' minute(s).');
        }
        RateLimiter::hit($ipKey, 900);

        $user = User::whereRaw('LOWER(username) = ?', [$username])->first();

        if ($user && $user->isLocked()) {
            $this->fail('This account is locked after too many failed attempts. Try again after '.$user->locked_until->format('H:i').' or ask an administrator to unlock it.');
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $this->registerFailure($user);
            }
            // Same message whether or not the username exists
            $this->fail('Incorrect username or password.');
        }

        if (! $user->is_active) {
            $this->fail('This account is deactivated. Contact your administrator.');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        RateLimiter::clear($ipKey);

        $now = now();
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => $now,
            'last_login_ip' => $request->ip(),
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'Login',
            'activity_time' => $now,
            'login_date' => $now->toDateString(),
            'login_time' => $now->toTimeString(),
        ]);

        if ($user->must_change_password) {
            return redirect()->route('profile.show', ['tab' => 'password'])
                ->with('error', 'Please set a new password before continuing.');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function registerFailure(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $lock = $attempts >= PasswordPolicy::MAX_ATTEMPTS;

        $user->forceFill([
            'failed_login_attempts' => $lock ? 0 : $attempts,
            'locked_until' => $lock ? now()->addMinutes(PasswordPolicy::LOCK_MINUTES) : $user->locked_until,
        ])->save();

        if ($lock) {
            UserAuditLog::record('login.locked', $user, ['minutes' => PasswordPolicy::LOCK_MINUTES], $user);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['username' => $message]);
    }

    public function logout(Request $request)
    {
        $this->closeActivityLog();

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
     | Forgotten password — requested by username, mailed to the account
     |------------------------------------------------------------------ */

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['username' => ['required', 'string', 'max:50']]);

        $key = 'reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Too many requests. Please wait a few minutes.');
        }
        RateLimiter::hit($key, 600);

        $user = User::whereRaw('LOWER(username) = ?', [Str::lower(trim($request->username))])->first();

        if ($user && $user->is_active && $user->email) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // Never reveal whether the username exists or has an email on file
        return back()->with('success', 'If that account has an email address on file, a reset link has been sent. Otherwise, ask your administrator to reset the password.');
    }

    public function showReset(string $token, Request $request)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => PasswordPolicy::rules(),
        ], ['password.regex' => PasswordPolicy::MESSAGE]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password changed. You can sign in now.');
        }

        return back()->withErrors(['password' => __($status)]);
    }
}
