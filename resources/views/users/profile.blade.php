@extends('layouts.app')
@section('title', 'My Profile')
@section('content')

@php
    $roleIcons = ['SuperAdmin' => 'bi-shield-fill-check', 'Admin' => 'bi-person-gear', 'Editor' => 'bi-pencil-square', 'Viewer' => 'bi-eye'];
    $tab = in_array($tab, ['details', 'password'], true) ? $tab : 'details';
@endphp

<div class="profile-hero mb-3">
    <div class="avatar role-av-{{ strtolower($user->role) }}" style="width:64px;height:64px;font-size:20px;">{{ $user->initials() }}</div>
    <div class="flex-grow-1" style="min-width:0;">
        <h2 class="pms-title">{{ $user->name }}</h2>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
            <span class="text-muted">&#64;{{ $user->username }}</span>
            <span class="role-pill role-{{ strtolower($user->role) }}"><i class="bi {{ $roleIcons[$user->role] }}"></i>{{ $user->role }}</span>
        </div>
        <div class="text-muted mt-1" style="font-size:12.5px;">{{ \App\Models\User::ROLE_DESCRIPTIONS[$user->role] }}</div>
    </div>
</div>

@if($user->must_change_password)
    <div class="lock-banner unlocked mb-3">
        <i class="bi bi-key-fill"></i>
        <span>Your password was set by an administrator. Choose your own password to continue using the system.</span>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header p-2">
                <ul class="nav seg-tabs" role="tablist">
                    <li><button class="{{ $tab === 'details' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-details" type="button"><i class="bi bi-person"></i> Details</button></li>
                    <li><button class="{{ $tab === 'password' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-password" type="button"><i class="bi bi-key"></i> Password</button></li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade {{ $tab === 'details' ? 'show active' : '' }}" id="tab-details">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full name *</label>
                                <input name="name" class="form-control" value="{{ old('name', $user->name) }}" maxlength="100" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <div class="input-icon">
                                    <i class="bi bi-at"></i>
                                    @if($user->isAdmin())
                                        <input name="username" class="form-control" value="{{ old('username', $user->username) }}"
                                               minlength="3" maxlength="50" pattern="[A-Za-z0-9._]+" autocapitalize="none" required>
                                    @else
                                        <input class="form-control" value="{{ $user->username }}" disabled>
                                    @endif
                                </div>
                                @unless($user->isAdmin())
                                    <div class="form-text">Ask an administrator to change your username.</div>
                                @endunless
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="wz-optional">optional</span></label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" maxlength="254">
                                <div class="form-text">Lets you reset a forgotten password yourself.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="wz-optional">optional</span></label>
                                <input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" maxlength="20">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-p" data-busy-label="Saving…"><i class="bi bi-check-lg"></i> Save Profile</button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade {{ $tab === 'password' ? 'show active' : '' }}" id="tab-password">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Current password *</label>
                                <div class="input-icon">
                                    <i class="bi bi-lock"></i>
                                    <input type="password" name="current_password" id="curPw" class="form-control" autocomplete="current-password" required>
                                    <button type="button" class="reveal-btn" data-reveal="#curPw"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New password *</label>
                                <div class="input-icon">
                                    <i class="bi bi-lock"></i>
                                    <input type="password" name="password" id="newPw" class="form-control" autocomplete="new-password" required data-strength>
                                    <button type="button" class="reveal-btn" data-reveal="#newPw"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm new password *</label>
                                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                            </div>
                            <div class="col-12"><div class="form-text">{{ \App\Support\PasswordPolicy::MESSAGE }} Changing it signs you out on other devices.</div></div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-p" data-busy-label="Updating…"><i class="bi bi-key"></i> Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Security</div>
            <div class="card-body">
                <div class="kv-grid one-col">
                    <div><span>Last sign-in</span><strong>{{ optional($user->last_login_at)->format('d M Y, H:i') ?? '-' }}</strong></div>
                    <div><span>From</span><strong>{{ $user->last_login_ip ?: '-' }}</strong></div>
                    <div><span>Password changed</span><strong>{{ optional($user->password_changed_at)->diffForHumans() ?? 'Not recorded' }}</strong></div>
                </div>

                {{-- Each person turns the emailed code on for themselves --}}
                <div class="access-row">
                    <div>
                        <div class="fw-semibold">Email code at sign-in</div>
                        <div class="text-muted" style="font-size:12.5px;">
                            {{ $user->two_factor_enabled
                                ? 'On. After your password we email you a six digit code.'
                                : 'Off. Your password alone signs you in.' }}
                            @unless($user->email) Add an email address above first. @endunless
                        </div>
                    </div>
                    <form method="POST" action="{{ route('profile.two-factor') }}" data-self-service>@csrf
                        <button class="btn btn-sm {{ $user->two_factor_enabled ? 'btn-outline-secondary' : 'btn-outline-p' }}" @disabled(! $user->email)>
                            {{ $user->two_factor_enabled ? 'Turn off' : 'Turn on' }}
                        </button>
                    </form>
                </div>

                <div class="access-row">
                    <div>
                        <div class="fw-semibold">This device</div>
                        <div class="text-muted" style="font-size:12.5px;">
                            One device at a time. Signed in
                            {{ optional($user->session_started_at)->diffForHumans() ?? 'recently' }};
                            signing in elsewhere ends this session.
                        </div>
                    </div>
                    <span class="pill pill-live"><span class="dot"></span> Active</span>
                </div>
                <div class="fw-semibold mt-3 mb-2" style="font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em;">Recent sessions</div>
                @forelse($recentLogins as $log)
                    <div class="d-flex justify-content-between py-1" style="font-size:12.5px; border-bottom:1px solid var(--line);">
                        <span>{{ optional($log->login_date)->format('d M') }} &middot; {{ substr((string) $log->login_time, 0, 5) }}</span>
                        <span class="text-muted">{{ $log->minutes_logged_in ? $log->minutes_logged_in.' h' : 'active' }}</span>
                    </div>
                @empty
                    <div class="text-muted" style="font-size:12.5px;">No sessions yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
