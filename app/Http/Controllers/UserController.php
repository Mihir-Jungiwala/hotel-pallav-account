<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Services\UserHierarchy;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private UserHierarchy $hierarchy) {}

    public function index(Request $request)
    {
        $actor = Auth::user();

        $users = User::with('creator')
            ->orderByRaw("CASE role WHEN 'SuperAdmin' THEN 1 WHEN 'Admin' THEN 2 WHEN 'Editor' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get();

        return view('users.index', [
            'users' => $users,
            'actor' => $actor,
            'hierarchy' => $this->hierarchy,
            'assignableRoles' => $this->hierarchy->assignableRoles($actor),
            'stats' => [
                'total' => $users->count(),
                'active' => $users->where('is_active', true)->count(),
                'locked' => $users->filter->isLocked()->count(),
                'byRole' => collect(User::RANKS)->keys()->mapWithKeys(fn ($r) => [$r => $users->where('role', $r)->count()]),
            ],
        ]);
    }

    public function activity(Request $request)
    {
        return view('users.activity', [
            'logins' => ActivityLog::with('user')->latest('id')->paginate(15, ['*'], 'logins'),
            'audits' => UserAuditLog::with('actor')->latest('id')->paginate(15, ['*'], 'audits'),
        ]);
    }

    private function profileRules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._]+$/',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'email' => ['nullable', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, dots and underscores.',
            'username.unique' => 'That username is already taken.',
            'password.regex' => PasswordPolicy::MESSAGE,
        ];
    }

    public function store(Request $request)
    {
        $actor = Auth::user();

        $data = $request->validate(array_merge($this->profileRules(), [
            'role' => ['required', Rule::in($this->hierarchy->assignableRoles($actor))],
            'password' => PasswordPolicy::rules(),
            'must_change_password' => ['nullable', 'boolean'],
        ]), array_merge($this->messages(), [
            'role.in' => 'You are not allowed to create an account with that role.',
        ]));

        $user = User::create([
            'name' => $data['name'],
            'username' => Str::lower($data['username']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'must_change_password' => $request->boolean('must_change_password', true),
            'password_changed_at' => now(),
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        UserAuditLog::record('user.created', $user, ['role' => $user->role]);

        return back()->with('success', "Account @{$user->username} created as {$user->role}.");
    }

    public function update(Request $request, User $user)
    {
        $actor = Auth::user();
        $this->authorizeManage($actor, $user);

        $data = $request->validate(array_merge($this->profileRules($user), [
            'role' => ['required', Rule::in($this->hierarchy->assignableRoles($actor))],
        ]), array_merge($this->messages(), [
            'role.in' => 'You are not allowed to assign that role.',
        ]));

        $data['username'] = Str::lower($data['username']);

        $before = $user->only(['name', 'username', 'email', 'phone', 'role']);
        $user->update($data);
        $changes = collect($user->only(array_keys($before)))
            ->filter(fn ($value, $key) => $value !== $before[$key])
            ->map(fn ($value, $key) => ['from' => $before[$key], 'to' => $value])
            ->all();

        if ($changes) {
            UserAuditLog::record('user.updated', $user, $changes);
        }

        return back()->with('success', $changes ? "Account @{$user->username} updated." : 'No changes to save.');
    }

    public function toggleActive(User $user)
    {
        $actor = Auth::user();
        $this->authorizeManage($actor, $user);

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        UserAuditLog::record($user->is_active ? 'user.activated' : 'user.deactivated', $user);

        return back()->with('success', "@{$user->username} is now ".($user->is_active ? 'active' : 'deactivated').'.');
    }

    /**
     * An administrator can switch the sign-in code off for someone, which is
     * what helps a colleague who has lost access to their email. Switching it
     * on is the owner's own doing, because only they can read the code that
     * proves the address works.
     */
    public function toggleTwoFactor(User $user)
    {
        $this->authorizeManage(Auth::user(), $user);

        if (! $user->two_factor_enabled) {
            return back()->with('error', "Only @{$user->username} can switch this on, by entering a code we email them.");
        }

        $user->forceFill(['two_factor_enabled' => false])->save();
        UserAuditLog::record('two_factor.disabled', $user);

        return back()->with('success', "@{$user->username} will sign in with a password only.");
    }

    public function unlock(User $user)
    {
        $this->authorizeManage(Auth::user(), $user);

        // Clears the block, any administrator lock, and the wait on emailed codes
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'lock_level' => 0,
            'blocked_at' => null,
            'blocked_reason' => null,
            'otp_attempts' => 0,
            'otp_sends' => 0,
            'otp_cooldown_until' => null,
            'otp_cooldown_level' => 0,
        ])->save();

        UserAuditLog::record('user.unlocked', $user);

        return back()->with('success', "@{$user->username} can sign in again.");
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeManage(Auth::user(), $user);

        $data = $request->validate(['password' => PasswordPolicy::rules()], $this->messages());

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => $request->boolean('must_change_password', true),
            'password_changed_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            // Signs the user out of any "remember me" sessions
            'remember_token' => Str::random(60),
        ])->save();

        UserAuditLog::record('user.password_reset', $user, ['force_change' => $user->must_change_password]);

        return back()->with('success', "Password reset for @{$user->username}.");
    }

    public function destroy(User $user)
    {
        $actor = Auth::user();

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'The SuperAdmin account cannot be deleted. Transfer the SuperAdmin role to an Admin first.');
        }

        if (! $this->hierarchy->canDelete($actor, $user)) {
            return back()->with('error', 'You are not allowed to delete this account.');
        }

        // Closing an account never removes what it recorded: the entries keep
        // their numbers and still show the name, marked as deleted.
        $kept = $this->hierarchy->blockingRecords($user);

        UserAuditLog::record('user.deleted', $user, [
            'role' => $user->role, 'name' => $user->name, 'records_kept' => $kept,
        ]);

        $user->forceFill([
            'is_active' => false,
            'deleted_by' => $actor->id,
            'remember_token' => null,
        ])->save();

        $user->delete();

        $summary = collect($kept)->map(fn ($n, $label) => "$n $label")->implode(', ');

        return back()->with('success', $summary
            ? "Account closed. Their entries ($summary) are kept and now show as deleted."
            : 'Account closed. Any entries they recorded are kept.');
    }

    /**
     * Hands the single SuperAdmin role to an Admin. The outgoing SuperAdmin
     * becomes an Admin. Requires the current SuperAdmin's password.
     */
    public function transferSuperAdmin(Request $request, User $user)
    {
        $actor = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirm_username' => ['required', 'string'],
        ], [
            'current_password.current_password' => 'Your password is incorrect.',
        ]);

        if (! $this->hierarchy->canTransferSuperAdminTo($actor, $user)) {
            return back()->with('error', 'SuperAdmin can only be transferred by the SuperAdmin to an active Admin.');
        }

        if (Str::lower($request->confirm_username) !== Str::lower($user->username)) {
            return back()->with('error', 'The confirmation username did not match.');
        }

        DB::transaction(function () use ($actor, $user) {
            // Demote first: the database allows only one SuperAdmin at a time
            $actor->forceFill(['role' => User::ROLE_ADMIN])->save();
            $user->forceFill(['role' => User::ROLE_SUPERADMIN])->save();
        });

        UserAuditLog::record('superadmin.transferred', $user, ['from' => $actor->username, 'to' => $user->username], $actor);

        return redirect()->route('dashboard')
            ->with('success', "SuperAdmin transferred to @{$user->username}. You are now an Admin.");
    }

    private function authorizeManage(User $actor, User $target): void
    {
        if ($target->isSuperAdmin()) {
            abort(403, 'The SuperAdmin account can only be changed by transferring the role.');
        }

        abort_unless($this->hierarchy->canManage($actor, $target), 403, 'You are not allowed to manage this account.');
    }
}
