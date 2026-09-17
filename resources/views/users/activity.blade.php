@extends('layouts.app')
@section('title', 'Activity Log')
@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <div class="pms-eyebrow">Access control</div>
        <h2 class="pms-title">Activity Log</h2>
        <p class="pms-subtitle">Every sign-in and every change to an account, newest first.</p>
    </div>
    <a class="btn btn-outline-p" href="{{ route('users.index') }}"><i class="bi bi-arrow-left"></i> User Accounts</a>
</div>

<div class="card mb-3">
    <div class="card-header">Account changes</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>When</th><th>By</th><th>Action</th><th>Account</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($audits as $a)
                <tr>
                    <td class="text-nowrap">
                        <div style="font-size:12.5px;">{{ $a->created_at->format('d M Y, H:i') }}</div>
                        <div class="text-muted" style="font-size:10.5px;">{{ $a->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="text-nowrap">{{ optional($a->actor)->name ?? 'System' }}</td>
                    <td class="text-nowrap"><span class="badge-p px-2 py-1 rounded">{{ $a->label() }}</span></td>
                    <td class="text-nowrap">{{ $a->target_username ? '@'.$a->target_username : '—' }}</td>
                    <td style="font-size:12px; max-width:320px;">
                        @if($a->details)
                            @foreach($a->details as $key => $value)
                                <div>
                                    <span class="text-muted">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                    @if(is_array($value) && array_key_exists('from', $value))
                                        <span class="diff-old">{{ $value['from'] ?? '—' }}</span> &rarr; <span class="diff-new">{{ $value['to'] ?? '—' }}</span>
                                    @else
                                        {{ is_bool($value) ? ($value ? 'yes' : 'no') : (is_array($value) ? json_encode($value) : $value) }}
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-muted" style="font-size:12px;">{{ $a->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><div class="es-title">No account changes yet</div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($audits->hasPages())
        <div class="pms-pager">{{ $audits->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    @endif
</div>

<div class="card">
    <div class="card-header">Sign-in sessions</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>User</th><th>Signed in</th><th>Signed out</th><th>Duration</th></tr></thead>
            <tbody>
            @forelse($logins as $log)
                <tr>
                    <td class="text-nowrap">{{ optional($log->user)->name ?? 'Deleted user' }} <span class="text-muted" style="font-size:11.5px;">{{ $log->user ? '@'.$log->user->username : '' }}</span></td>
                    <td class="text-nowrap">{{ optional($log->login_date)->format('d M Y') }} {{ substr((string) $log->login_time, 0, 5) }}</td>
                    <td class="text-nowrap">{{ $log->logout_date ? $log->logout_date->format('d M Y').' '.substr((string) $log->logout_time, 0, 5) : '—' }}</td>
                    <td>{{ $log->minutes_logged_in ? $log->minutes_logged_in.' h' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state"><div class="es-title">No sign-ins yet</div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logins->hasPages())
        <div class="pms-pager">{{ $logins->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
