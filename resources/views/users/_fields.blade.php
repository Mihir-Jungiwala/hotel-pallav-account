@php
    use App\Models\Role;

    $t = $target ?? null;
    // Roles come from the ladder, so one added on the Roles screen can be
    // handed out here straight away, with its own icon and colour.
    $roleRecords = collect($roles)->map(fn ($name) => Role::byKey($name))->filter()->values();
    $currentRole = old('role', $t->role ?? ($roleRecords->firstWhere('key', 'Editor')?->name ?? $roleRecords->first()?->name));
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Full name *</label>
        <input name="name" class="form-control" value="{{ old('name', $t->name ?? '') }}" maxlength="100" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Username *</label>
        <div class="input-icon">
            <i class="bi bi-at"></i>
            <input name="username" class="form-control" value="{{ old('username', $t->username ?? '') }}"
                   minlength="3" maxlength="50" pattern="[A-Za-z0-9._]+" autocapitalize="none" spellcheck="false" autocomplete="off" required
                   title="Letters, numbers, dots and underscores">
        </div>
        <div class="form-text">Used to sign in. Letters, numbers, dots and underscores.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Email <span class="wz-optional">optional</span></label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $t->email ?? '') }}" maxlength="254" autocomplete="off">
        <div class="form-text">Needed only for self-service password reset.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone <span class="wz-optional">optional</span></label>
        <input name="phone" class="form-control" value="{{ old('phone', $t->phone ?? '') }}" maxlength="20">
    </div>

    <div class="col-12">
        <label class="form-label">Role *</label>
        <div class="role-options">
            @foreach($roleRecords as $role)
                <label class="role-option" style="--role:{{ $role->accent }};">
                    <input type="radio" name="role" value="{{ $role->name }}" @checked($currentRole === $role->name) required>
                    <span class="ro-body">
                        <span class="ro-head"><i class="bi {{ $role->icon }}"></i>{{ $role->name }}</span>
                        <span class="ro-desc">{{ $role->description ?: 'No description yet.' }}</span>
                        <span class="ro-count">{{ count($role->effectivePermissions()) }} things allowed</span>
                    </span>
                </label>
            @endforeach
        </div>
        <div class="form-text">
            <a href="{{ route('access.index') }}"><i class="bi bi-diagram-3"></i> See what each role may do</a>
        </div>
    </div>
</div>
