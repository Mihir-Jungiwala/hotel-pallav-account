@extends('layouts.app')
@section('title', 'User Accounts')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/access.css') }}?v=1">
@endpush
@section('content')

@php
    $roleIcons = ['SuperAdmin' => 'bi-shield-fill-check', 'Admin' => 'bi-person-gear', 'Editor' => 'bi-pencil-square', 'Viewer' => 'bi-eye'];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <div class="pms-eyebrow">Access control</div>
        <h2 class="pms-title">User Accounts</h2>
        <p class="pms-subtitle">{{ $stats['active'] }} active of {{ $stats['total'] }}@if($stats['locked']) &middot; {{ $stats['locked'] }} locked @endif</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-p" href="{{ route('users.activity') }}"><i class="bi bi-clock-history"></i> Activity Log</a>
        @if($assignableRoles)
            <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#createUser"><i class="bi bi-person-plus"></i> New User</button>
        @endif
    </div>
</div>

{{-- The hierarchy, visible at a glance --}}
<div class="role-ladder mb-3">
    @foreach(\App\Models\User::ROLE_DESCRIPTIONS as $role => $description)
        <button type="button" class="role-step role-{{ strtolower($role) }} role-filter" data-role="{{ $role }}">
            <span class="rs-top">
                <i class="bi {{ $roleIcons[$role] }}"></i>
                <span class="rs-name">{{ $role }}</span>
                <span class="rs-count">{{ $stats['byRole'][$role] }}</span>
            </span>
            <span class="rs-desc">{{ $description }}</span>
        </button>
    @endforeach
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-field" style="max-width:320px;flex:1 1 240px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" id="userSearch" placeholder="Search name, username or email&hellip;" aria-label="Search users">
    </div>
    <button type="button" class="btn btn-sm btn-outline-p" id="clearRoleFilter" hidden><i class="bi bi-x"></i> Show all roles</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="userTable" data-paginate="10" data-pager="#userPager">
            <thead>
                <tr><th>User</th><th>Role</th><th>Status</th><th>Last sign-in</th><th>Created by</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
            @foreach($users as $u)
                @php
                    $isSelf = $actor->is($u);
                    $canManage = $hierarchy->canManage($actor, $u);
                    $canTransfer = $hierarchy->canTransferSuperAdminTo($actor, $u);
                @endphp
                <tr data-row="{{ $u->name }} {{ $u->username }} {{ $u->email }}" data-role="{{ $u->role }}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar role-av-{{ strtolower($u->role) }}">{{ $u->initials() }}</div>
                            <div style="min-width:0;">
                                <div class="fw-semibold text-nowrap">
                                    {{ $u->name }}
                                    @if($isSelf)<span class="you-tag">You</span>@endif
                                </div>
                                <div class="text-muted text-nowrap" style="font-size:11.5px;">
                                    &#64;{{ $u->username }}@if($u->email) &middot; {{ $u->email }}@endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><span class="role-pill role-{{ strtolower($u->role) }}"><i class="bi {{ $roleIcons[$u->role] }}"></i>{{ $u->role }}</span></td>
                    <td>
                        @if($u->isBlocked())
                            <span class="pay-pill pay-failed"><i class="bi bi-slash-circle-fill"></i>Blocked</span>
                        @elseif($u->isLocked())
                            <span class="pay-pill pay-failed"><i class="bi bi-lock-fill"></i>Locked</span>
                        @elseif($u->is_active)
                            <span class="pay-pill pay-paid"><i class="bi bi-check-circle"></i>Active</span>
                        @else
                            <span class="pay-pill pay-hold"><i class="bi bi-slash-circle"></i>Inactive</span>
                        @endif
                        @if($u->must_change_password)
                            <div class="text-muted mt-1" style="font-size:10.5px;"><i class="bi bi-key"></i> Must change password</div>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if($u->last_login_at)
                            <div style="font-size:12.5px;">{{ $u->last_login_at->diffForHumans() }}</div>
                            <div class="text-muted" style="font-size:10.5px;">{{ $u->last_login_at->format('d M Y, H:i') }}</div>
                        @else
                            <span class="text-muted" style="font-size:12.5px;">Never</span>
                        @endif
                    </td>
                    <td class="text-muted text-nowrap" style="font-size:12.5px;">{{ optional($u->creator)->name ?? '-' }}</td>
                    <td class="text-end">
                        @if($isSelf)
                            <a class="btn btn-sm btn-outline-p" href="{{ route('profile.show') }}"><i class="bi bi-person-circle"></i> My Profile</a>
                        @else
                            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#user{{ $u->id }}">
                                <i class="bi {{ $canManage || $canTransfer ? 'bi-sliders' : 'bi-eye' }}"></i> {{ $canManage || $canTransfer ? 'Manage' : 'View' }}
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
                <tr data-no-match hidden>
                    <td colspan="6"><div class="empty-state"><div class="es-title">No matching accounts</div></div></td>
                </tr>
            </tbody>
        </table>
    </div>
    @include('payroll.partials._pager', ['id' => 'userPager'])
</div>

{{-- ============================ Manage / view modals ============================ --}}
@foreach($users as $u)
    @continue($actor->is($u))
    @php
        $canManage = $hierarchy->canManage($actor, $u);
        $canTransfer = $hierarchy->canTransferSuperAdminTo($actor, $u);
        $blocking = $canManage ? $hierarchy->blockingRecords($u) : [];
        $tabId = 'u'.$u->id;
    @endphp
    <div class="modal fade" id="user{{ $u->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar role-av-{{ strtolower($u->role) }}" style="width:44px;height:44px;font-size:14px;">{{ $u->initials() }}</div>
                <div>
                    <h5 class="modal-title mb-0">{{ $u->name }}</h5>
                    <div class="text-muted" style="font-size:12px;">&#64;{{ $u->username }} &middot; {{ $u->role }}</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            @if($canManage || $canTransfer)
                <ul class="nav seg-tabs mb-3" role="tablist">
                    @if($canManage)
                        <li><button class="active" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}-details" type="button"><i class="bi bi-person"></i> Details</button></li>
                        <li><button data-bs-toggle="tab" data-bs-target="#{{ $tabId }}-password" type="button"><i class="bi bi-key"></i> Password</button></li>
                    @endif
                    <li><button class="{{ $canManage ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}-access" type="button"><i class="bi bi-shield-lock"></i> Access</button></li>
                    <li><button data-bs-toggle="tab" data-bs-target="#{{ $tabId }}-permissions" type="button"><i class="bi bi-key"></i> Permissions</button></li>
                </ul>
            @endif

            <div class="tab-content">
                @if($canManage)
                {{-- Details --}}
                <div class="tab-pane fade show active" id="{{ $tabId }}-details">
                    <form method="POST" action="{{ route('users.update', $u) }}">
                        @csrf @method('PUT')
                        @include('users._fields', ['target' => $u, 'roles' => $assignableRoles])
                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-p" data-busy-label="Saving…"><i class="bi bi-check-lg"></i> Save Changes</button>
                        </div>
                    </form>
                </div>

                {{-- Password --}}
                <div class="tab-pane fade" id="{{ $tabId }}-password">
                    <p class="text-muted" style="font-size:13px;">Set a temporary password and share it with {{ $u->name }} privately. This also signs them out of other devices and clears any lock.</p>
                    <form method="POST" action="{{ route('users.reset-password', $u) }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="master-note">
                                    <i class="bi bi-envelope-check"></i>
                                    <span>
                                        A new password is generated and emailed to
                                        <strong>{{ $u->email ?: 'this person' }}</strong>. Nobody else ever sees it, and
                                        &#64;{{ $u->username }} has to replace it before they can reach anything.
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end align-items-center mt-3">
                            <button class="btn btn-p" data-busy-label="Sending…" @disabled(! $u->email)
                                    data-confirm-title="Email a new password?"
                                    data-confirm="&#64;{{ $u->username }} is signed out and must set a new password the next time they sign in."
                                    data-confirm-label="Send it">
                                <i class="bi bi-key"></i> Reset and email password
                            </button>
                        </div>
                        @unless($u->email)
                            <p class="text-muted mt-2" style="font-size:12.5px;">
                                <i class="bi bi-exclamation-circle"></i> Add an email address on the Account tab first - that is where the password goes.
                            </p>
                        @endunless
                    </form>
                </div>
                @endif

                {{-- What they may actually do, and where each answer comes from --}}
                <div class="tab-pane fade" id="{{ $tabId }}-permissions">
                    @include('users._permissions', ['u' => $u, 'canManage' => $canManage])
                </div>

                {{-- Access --}}
                <div class="tab-pane fade {{ $canManage ? '' : 'show active' }}" id="{{ $tabId }}-access">
                    <div class="kv-grid mb-3">
                        <div><span>Username</span><strong>&#64;{{ $u->username }}</strong></div>
                        <div><span>Email</span><strong>{{ $u->email ?: '-' }}</strong></div>
                        <div><span>Phone</span><strong>{{ $u->phone ?: '-' }}</strong></div>
                        <div><span>Role</span><strong>{{ $u->role }}</strong></div>
                        <div><span>Last sign-in</span><strong>{{ optional($u->last_login_at)->format('d M Y, H:i') ?? 'Never' }}</strong></div>
                        <div><span>From</span><strong>{{ $u->last_login_ip ?: '-' }}</strong></div>
                        <div><span>Password changed</span><strong>{{ optional($u->password_changed_at)->format('d M Y') ?? '-' }}</strong></div>
                        <div><span>Created</span><strong>{{ $u->created_at->format('d M Y') }}{{ $u->creator ? ' by '.$u->creator->name : '' }}</strong></div>
                    </div>

                    @if($canManage)
                        <div class="access-row">
                            <div>
                                <div class="fw-semibold">{{ $u->is_active ? 'Deactivate account' : 'Activate account' }}</div>
                                <div class="text-muted" style="font-size:12.5px;">{{ $u->is_active ? 'Blocks sign-in immediately and signs them out. Their records stay intact.' : 'Allows this person to sign in again.' }}</div>
                            </div>
                            <form method="POST" action="{{ route('users.toggle-active', $u) }}">@csrf
                                <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-secondary' : 'btn-outline-p' }}">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>

                        @if($u->isLocked() || $u->isWaitingForCode())
                        <div class="access-row">
                            <div>
                                <div class="fw-semibold">{{ $u->isBlocked() ? 'Unblock account' : 'Clear the wait' }}</div>
                                <div class="text-muted" style="font-size:12.5px;">
                                    @if($u->isBlocked())
                                        Blocked {{ $u->blocked_at->diffForHumans() }}: {{ $u->blocked_reason ?: 'too many failed attempts' }}.
                                    @elseif($u->isLocked())
                                        Locked until {{ $u->locked_until->format('H:i') }}.
                                    @else
                                        Asked for too many sign-in codes, so the next one waits until {{ $u->otp_cooldown_until->format('H:i') }}.
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('users.unlock', $u) }}">@csrf
                                <button class="btn btn-sm btn-outline-p">{{ $u->isBlocked() ? 'Unblock' : 'Clear' }}</button>
                            </form>
                        </div>
                        @endif

                        <div class="access-row">
                            <div>
                                <div class="fw-semibold">Email code at sign-in</div>
                                <div class="text-muted" style="font-size:12.5px;">
                                    {{ $u->two_factor_enabled
                                        ? 'On. A six digit code is emailed after the password.'
                                        : 'Off. Only @'.$u->username.' can switch it on, by entering a code we email them.' }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('users.two-factor', $u) }}">@csrf
                                <button class="btn btn-sm btn-outline-secondary" @disabled(! $u->two_factor_enabled)>Turn off</button>
                            </form>
                        </div>
                    @endif

                    @if($canTransfer)
                        <div class="access-row danger-zone">
                            <div class="w-100">
                                <div class="fw-semibold"><i class="bi bi-shield-fill-check"></i> Transfer SuperAdmin to {{ $u->name }}</div>
                                <div class="text-muted" style="font-size:12.5px;">There can only be one SuperAdmin. {{ $u->name }} becomes SuperAdmin and <strong>you become an Admin</strong>. This cannot be undone by you.</div>
                                <form method="POST" action="{{ route('users.transfer-superadmin', $u) }}" class="row g-2 mt-2"
                                      data-confirm-title="Transfer SuperAdmin to {{ $u->username }}?" data-confirm="You will become an Admin, and only they can hand it back." data-confirm-label="Transfer" data-confirm-tone="warning">
                                    @csrf
                                    <div class="col-md-5">
                                        <input name="confirm_username" class="form-control form-control-sm" placeholder="Type {{ $u->username }} to confirm" autocomplete="off" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Your password" autocomplete="current-password" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-sm btn-danger w-100">Transfer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if($canManage)
                        <div class="access-row danger-zone">
                            <div>
                                <div class="fw-semibold text-danger">Delete account</div>
                                <div class="text-muted" style="font-size:12.5px;">
                                    @if($blocking)
                                        Has {{ collect($blocking)->map(fn ($n, $l) => "$n $l")->implode(', ') }} - deactivate instead so that history is kept.
                                    @else
                                        Permanently removes the account. It owns no records that would be lost.
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('users.destroy', $u) }}" data-confirm-title="Delete {{ $u->username }}?" data-confirm="Their account is closed; entries they recorded are kept.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" @disabled($blocking)>Delete</button>
                            </form>
                        </div>
                    @endif

                    @if(! $canManage && ! $canTransfer)
                        <div class="text-muted" style="font-size:12.5px;">
                            <i class="bi bi-info-circle"></i>
                            @if($u->isSuperAdmin())
                                The SuperAdmin account can only change hands when the SuperAdmin transfers the role.
                            @else
                                Only a higher role can manage this account.
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div></div></div>
@endforeach

{{-- ============================ Create ============================ --}}
@if($assignableRoles)
<div class="modal fade" id="createUser" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="modal-header"><h5 class="modal-title">New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="form-wizard" data-wizard>
                <div class="form-step" data-step="Account">
                    @include('users._fields', ['target' => null, 'roles' => $assignableRoles])
                </div>
                <div class="form-step" data-step="Sign-in">
                    <div class="signin-steps">
                        <div class="ss-item">
                            <span class="ss-num">1</span>
                            <div>
                                <div class="ss-title">We email them a password</div>
                                <div class="ss-text">Generated here, sent to the email address on the Account step. You never see it and neither does anyone else.</div>
                            </div>
                        </div>
                        <div class="ss-item">
                            <span class="ss-num">2</span>
                            <div>
                                <div class="ss-title">They sign in with it once</div>
                                <div class="ss-text">It gets them no further than the next step.</div>
                            </div>
                        </div>
                        <div class="ss-item">
                            <span class="ss-num">3</span>
                            <div>
                                <div class="ss-title">They choose their own password</div>
                                <div class="ss-text">Then they sign in again with it. From that point only they know it.</div>
                            </div>
                        </div>
                    </div>
                    <div class="master-note mt-3">
                        <i class="bi bi-info-circle"></i>
                        <span>If the email does not arrive, use <strong>Reset and email password</strong> on their record to send it again.</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-p" data-busy-label="Creating…"><i class="bi bi-person-plus"></i> Create Account</button></div>
    </form>
</div></div></div>
@endif

@push('scripts')
<script>
(function(){
    const table = document.getElementById('userTable');
    const search = document.getElementById('userSearch');
    const clear = document.getElementById('clearRoleFilter');
    let role = '';

    function apply(){
        const term = search.value.trim().toLowerCase();
        let shown = 0;
        table.querySelectorAll('tbody tr[data-row]').forEach(function(row){
            const ok = (!role || row.dataset.role === role) && (!term || row.dataset.row.toLowerCase().includes(term));
            if (ok) { delete row.dataset.filteredOut; shown++; } else { row.dataset.filteredOut = '1'; }
        });
        table.querySelector('tr[data-no-match]').hidden = shown !== 0;
        document.querySelectorAll('.role-filter').forEach(b => b.classList.toggle('active', b.dataset.role === role));
        clear.hidden = !role;
        table.dispatchEvent(new CustomEvent('pms:filtered'));
    }

    search.addEventListener('input', apply);
    document.querySelectorAll('.role-filter').forEach(function(btn){
        btn.addEventListener('click', function(){ role = role === btn.dataset.role ? '' : btn.dataset.role; apply(); });
    });
    clear.addEventListener('click', function(){ role = ''; apply(); });
})();
</script>
@endpush
@push('scripts')
<script src="{{ asset('assets/access.js') }}?v=1"></script>
@endpush
@endsection
