<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    private const COMPLEXITY_REGEX = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+])[A-Za-z\d!@#$%^&*()_+]{8,}$/';

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        $request->session()->regenerate();

        $now = now();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'Login',
            'activity_time' => $now,
            'login_date' => $now->toDateString(),
            'login_time' => $now->toTimeString(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $log = ActivityLog::where('user_id', Auth::id())->where('activity_type', 'Login')->latest('id')->first();

        if ($log) {
            $now = now();
            $log->logout_date = $now->toDateString();
            $log->logout_time = $now->toTimeString();

            if ($log->login_time) {
                $minutes = $now->diffInMinutes($log->login_date.' '.$log->login_time);
                $log->minutes_logged_in = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            }

            $log->save();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'regex:'.self::COMPLEXITY_REGEX],
            'role' => ['required', 'in:Admin,Editor,Viewer'],
        ], [
            'password.regex' => 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function index()
    {
        $users = User::when(! Auth::user()->isSuperAdmin(), fn ($q) => $q->where('name', '!=', 'SuperAdmin'))
            ->orderBy('name')
            ->get();

        return view('users.index', compact('users'));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return back()->with('success', 'User account deleted.');
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate(['role' => ['required', 'in:Admin,Editor,Viewer']]);
        $user->update(['role' => $request->role]);

        return back()->with('success', 'Role updated.');
    }

    public function toggleActive(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Status updated.');
    }

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return back()->with('success', __($status));
    }

    public function showReset(string $token, Request $request)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', 'regex:'.self::COMPLEXITY_REGEX],
        ], [
            'password.regex' => 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                if (Hash::check($password, $user->password)) {
                    return;
                }

                $user->forceFill(['password' => Hash::make($password)])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password changed successfully. Please log in.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
