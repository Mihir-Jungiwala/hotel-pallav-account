<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Models\UserPermission;
use App\Support\Access;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The ladder of roles and what each rung may do.
 *
 * Reading it needs access.view; changing anything needs access.edit, and no
 * account may hand itself something it does not already have.
 */
class AccessController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::ladder();
        $counts = User::selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');
        $byName = User::selectRaw('role, count(*) as total')->whereNull('role_id')->groupBy('role')->pluck('total', 'role');

        return view('users.access', [
            'roles' => $roles,
            'userCounts' => $roles->mapWithKeys(fn (Role $r) => [
                $r->id => (int) ($counts[$r->id] ?? 0) + (int) ($byName[$r->key] ?? 0),
            ]),
            'modules' => Permissions::modules(),
            'abilities' => Permissions::abilities(),
            'actions' => Permissions::ACTIONS,
            'canEdit' => $request->user()->can('access.edit'),
            'selected' => Role::ladder()->firstWhere('key', $request->query('role')) ?? $roles->first(),
        ]);
    }

    private function roleRules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:40', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'inherits' => ['nullable', 'boolean'],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->roleRules());

        // A new rung starts under the one it was added below, never above the
        // top: nobody creates a role that outranks their own.
        $actor = $request->user();
        $ceiling = $actor->roleRecord()?->level ?? 1;
        $level = max(1, min((int) $request->integer('level', $ceiling - 1), $ceiling - 1));

        $role = DB::transaction(function () use ($data, $request, $level) {
            $this->makeRoom($level);

            return Role::create([
                'key' => $this->keyFor($data['name']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => ($data['icon'] ?? null) ?: 'bi-person',
                'accent' => ($data['accent'] ?? null) ?: '#7C3AED',
                'level' => $level,
                'inherits' => $request->boolean('inherits', true),
                'is_system' => false,
            ]);
        });

        Access::flush();
        UserAuditLog::record('role.created', $actor, ['role' => $role->name]);

        return redirect()->route('access.index', ['role' => $role->key])
            ->with('success', "Role {$role->name} created. Nothing is allowed until you tick it.");
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate($this->roleRules($role));

        $role->update([
            'name' => $role->is_system ? $role->name : $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => ($data['icon'] ?? null) ?: $role->icon,
            'accent' => ($data['accent'] ?? null) ?: $role->accent,
            'inherits' => $request->boolean('inherits'),
        ]);

        Access::flush();
        UserAuditLog::record('role.updated', $request->user(), ['role' => $role->name]);

        return back()->with('success', "{$role->name} updated.");
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'The four roles the system ships with cannot be deleted.');
        }

        $holders = User::where('role_id', $role->id)->count();
        if ($holders > 0) {
            return back()->with('error', "{$holders} ".Str::plural('account', $holders)." still use {$role->name}. Move them first.");
        }

        $name = $role->name;
        $role->delete();
        Access::flush();
        UserAuditLog::record('role.deleted', $request->user(), ['role' => $name]);

        return redirect()->route('access.index')->with('success', "Role {$name} deleted.");
    }

    /**
     * The ladder, reordered. The top rung stays on top: the account that owns
     * the system cannot be demoted by dragging.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $ids = array_reverse($data['order']);          // sent top first, stored low to high
        $top = Role::ladder()->first(fn (Role $r) => $r->protectsTheSystem());

        if ($top && (int) last($ids) !== $top->id) {
            return response()->json(['message' => "{$top->name} has to stay at the top of the ladder."], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                Role::whereKey($id)->update(['level' => $index + 1]);
            }
        });

        Access::flush();
        UserAuditLog::record('role.reordered', $request->user(), [
            'order' => Role::ladder()->pluck('name')->implode(' > '),
        ]);

        return response()->json([
            'message' => 'Ladder saved.',
            'ladder' => Role::ladder()->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'level' => $r->level,
                'inherited' => $r->effectivePermissions(),
            ])->values(),
        ]);
    }

    /** One permission ticked or unticked on one role. */
    public function togglePermission(Request $request, Role $role)
    {
        $data = $request->validate([
            'permission' => ['required', 'string', Rule::in(Permissions::all())],
            'granted' => ['required', 'boolean'],
        ]);

        $actor = $request->user();

        // Nobody may hand out what they do not hold themselves
        if ($data['granted'] && ! $actor->can($data['permission'])) {
            return response()->json(['message' => 'You cannot give away a permission you do not have.'], 422);
        }

        if ($role->protectsTheSystem() && ! $data['granted'] && in_array($data['permission'], ['access.view', 'access.edit'], true)) {
            return response()->json(['message' => "{$role->name} has to keep the keys to this screen."], 422);
        }

        if ($data['granted']) {
            RolePermission::firstOrCreate(['role_id' => $role->id, 'permission' => $data['permission']]);
        } else {
            RolePermission::where('role_id', $role->id)->where('permission', $data['permission'])->delete();
        }

        Access::flush();
        UserAuditLog::record($data['granted'] ? 'role.permission.granted' : 'role.permission.revoked', $actor, [
            'role' => $role->name, 'permission' => Permissions::label($data['permission']),
        ]);

        $role->refresh()->load('permissions');

        return response()->json([
            'role' => $role->name,
            'own' => $role->ownPermissions(),
            'effective' => $role->effectivePermissions(),
            'sensitive' => $role->sensitiveCount(),
        ]);
    }

    /** Something given to, or taken from, one person on top of their role. */
    public function userOverride(Request $request, User $user)
    {
        $data = $request->validate([
            'permission' => ['required', 'string', Rule::in(Permissions::all())],
            'state' => ['required', Rule::in(['grant', 'revoke', 'clear'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();

        if (! $actor->can($data['permission'])) {
            return response()->json(['message' => 'You cannot give away a permission you do not have.'], 422);
        }
        if ($user->isSuperAdmin() && ! $actor->is($user)) {
            return response()->json(['message' => 'The SuperAdmin account cannot be changed this way.'], 422);
        }
        if ($actor->is($user)) {
            return response()->json(['message' => 'You cannot change your own access.'], 422);
        }

        if ($data['state'] === 'clear') {
            UserPermission::where('user_id', $user->id)->where('permission', $data['permission'])->delete();
        } else {
            UserPermission::updateOrCreate(
                ['user_id' => $user->id, 'permission' => $data['permission']],
                ['granted' => $data['state'] === 'grant', 'reason' => $data['reason'] ?? null, 'granted_by' => $actor->id],
            );
        }

        Access::flush();
        UserAuditLog::record('user.permission.'.$data['state'], $user, [
            'permission' => Permissions::label($data['permission']),
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'state' => $data['state'],
            'effective' => Access::effective($user->fresh()),
        ]);
    }

    /** Everything known about one account, for the detail panel. */
    public function user(Request $request, User $user)
    {
        return response()->json($this->userDetail($user));
    }

    private function userDetail(User $user): array
    {
        $role = $user->roleRecord();
        $overrides = Access::overrides($user);

        $permissions = [];
        foreach (Permissions::all() as $permission) {
            $explained = Access::explain($user, $permission);
            $permissions[$permission] = [
                'allowed' => $explained['allowed'],
                'origin' => $explained['origin'],
                'from' => $explained['role']?->name,
                'reason' => $explained['reason'],
                'label' => Permissions::label($permission),
                'sensitive' => Permissions::isSensitive($permission),
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $role?->name ?? $user->role,
            'roleKey' => $role?->key ?? $user->role,
            'accent' => $role?->accent ?? '#7C3AED',
            'icon' => $role?->icon ?? 'bi-person',
            'initials' => $user->initials(),
            'status' => match (true) {
                $user->isBlocked() => 'Blocked',
                $user->isLocked() => 'Locked',
                ! $user->is_active => 'Inactive',
                default => 'Active',
            },
            'twoFactor' => (bool) $user->two_factor_enabled,
            'mustChangePassword' => (bool) $user->must_change_password,
            'lastLogin' => $user->last_login_at?->format('d M Y, H:i'),
            'lastLoginAgo' => $user->last_login_at?->diffForHumans(),
            'passwordChanged' => $user->password_changed_at?->format('d M Y'),
            'created' => $user->created_at?->format('d M Y'),
            'createdBy' => $user->creator?->name,
            'signedIn' => (bool) $user->current_session_id,
            'overrides' => $overrides,
            'overrideCount' => count($overrides),
            'permissions' => $permissions,
            'allowedCount' => count(Access::effective($user)),
        ];
    }

    /** Roles are keyed by a stable name so code can look them up. */
    private function keyFor(string $name): string
    {
        $base = Str::studly(Str::slug($name, ' ')) ?: 'Role';
        $key = $base;
        $n = 2;

        while (Role::where('key', $key)->exists()) {
            $key = $base.$n++;
        }

        return $key;
    }

    /** Push every rung at or above this level up one, to leave a gap. */
    private function makeRoom(int $level): void
    {
        Role::where('level', '>=', $level)->orderByDesc('level')->get()
            ->each(fn (Role $role) => $role->update(['level' => $role->level + 1]));
    }
}
