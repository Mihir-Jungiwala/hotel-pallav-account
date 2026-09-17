<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\UserAuditLog;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user();

        return view('users.profile', [
            'user' => $user,
            'tab' => $request->query('tab', 'details'),
            'recentLogins' => ActivityLog::where('user_id', $user->id)->latest('id')->limit(8)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ];

        // Admins may rename their own login; others ask an administrator
        if ($user->isAdmin()) {
            $rules['username'] = [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ];
        }

        $data = $request->validate($rules, [
            'username.regex' => 'Username can only contain letters, numbers, dots and underscores.',
            'username.unique' => 'That username is already taken.',
        ]);

        if (isset($data['username'])) {
            $data['username'] = Str::lower($data['username']);
        }

        $before = $user->only(array_keys($data));
        $user->update($data);

        $changes = collect($data)
            ->filter(fn ($value, $key) => $value !== $before[$key])
            ->map(fn ($value, $key) => ['from' => $before[$key], 'to' => $value])
            ->all();

        if ($changes) {
            UserAuditLog::record('profile.updated', $user, $changes);
        }

        return back()->with('success', $changes ? 'Profile updated.' : 'No changes to save.');
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => array_merge(PasswordPolicy::rules(), ['different:current_password']),
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.regex' => PasswordPolicy::MESSAGE,
            'password.different' => 'The new password must be different from the current one.',
        ]);

        $user->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Sign out other devices still holding the old password
        Auth::logoutOtherDevices($request->password);

        UserAuditLog::record('profile.password_changed', $user);

        return redirect()->route('profile.show', ['tab' => 'password'])->with('success', 'Password changed.');
    }
}
