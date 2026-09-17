@php
    $t = $target ?? null;
    $roleIcons = ['Admin' => 'bi-person-gear', 'Editor' => 'bi-pencil-square', 'Viewer' => 'bi-eye'];
    $currentRole = old('role', $t->role ?? (in_array('Editor', $roles, true) ? 'Editor' : ($roles[0] ?? null)));
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
            @foreach($roles as $role)
                <label class="role-option role-{{ strtolower($role) }}">
                    <input type="radio" name="role" value="{{ $role }}" @checked($currentRole === $role) required>
                    <span class="ro-body">
                        <span class="ro-head"><i class="bi {{ $roleIcons[$role] ?? 'bi-person' }}"></i>{{ $role }}</span>
                        <span class="ro-desc">{{ \App\Models\User::ROLE_DESCRIPTIONS[$role] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
</div>
