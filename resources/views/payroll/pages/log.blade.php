{{-- Payroll Log: a full record of what was done in payroll. One line per event;
     open a line for everything it holds. Same scope as Payroll Master. --}}
@extends('payroll.layout', [
    'title' => 'Payroll Log',
    'subtitle' => $company
        ? 'Everything done in '.$company->name.', newest first.'
        : 'Everything done across every company, newest first. Choose a company to see only its own.',
])

@php
    $scopeName = $company?->name ?? 'All companies';
    $tone = ['created' => 'pill-live', 'updated' => 'pill-info', 'deleted' => 'pill-danger', 'emailed' => 'pill-live', 'error' => 'pill-danger'];
    $first = $logs->firstItem() ?? 0;
@endphp

@section('page')

<div class="master-scope {{ $company ? 'is-company' : 'is-all' }}">
    <span class="ms-icon"><i class="bi {{ $company ? 'bi-building' : 'bi-globe2' }}"></i></span>
    <span class="ms-text">
        <span class="ms-label">Showing</span>
        <span class="ms-name">{{ $scopeName }}</span>
    </span>
    <form method="GET" action="{{ route('payroll.log.index') }}" class="ms-form">
        <select name="scope" class="form-select" aria-label="Change scope" onchange="this.form.submit()">
            <option value="all" @selected(! $company)>All companies</option>
            @foreach($companies as $option)
                <option value="{{ $option->id }}" @selected($company && $company->id === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="pay-stats mb-3">
    <div class="pay-stat"><div class="ps-label">Entries</div><div class="ps-value">{{ number_format($stats['total']) }}</div><div class="ps-sub">On record</div></div>
    <div class="pay-stat"><div class="ps-label">Today</div><div class="ps-value">{{ number_format($stats['today']) }}</div><div class="ps-sub">Since midnight</div></div>
    <div class="pay-stat"><div class="ps-label">Emails sent</div><div class="ps-value">{{ number_format($stats['emails']) }}</div><div class="ps-sub">Letters and shared details</div></div>
    <div class="pay-stat {{ $stats['failed'] ? 'warn' : 'good' }}"><div class="ps-label">Failures</div><div class="ps-value">{{ number_format($stats['failed']) }}</div><div class="ps-sub">{{ $stats['failed'] ? 'Errors and emails that did not go' : 'Nothing has failed' }}</div></div>
</div>

<form method="GET" action="{{ route('payroll.log.index') }}" class="log-filters">
    <div class="search-field smart">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Search person, record, detail&hellip;" aria-label="Search the log">
    </div>
    <div class="lf-cell">
        <select name="action" class="form-select" aria-label="Action">
        <option value="">Any action</option>
        @foreach(\App\Models\PayrollLog::ACTIONS as $key => $label)
            <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    </div>
    <div class="lf-cell">
        <select name="entity" class="form-select" aria-label="What">
        <option value="">Anything</option>
        @foreach($entities as $entity)
            <option value="{{ $entity }}" @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>
        @endforeach
    </select>
    </div>
    <div class="lf-cell">
        <select name="status" class="form-select" aria-label="Outcome">
        <option value="">Any outcome</option>
        <option value="success" @selected(($filters['status'] ?? '') === 'success')>Succeeded</option>
        <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
    </select>
    </div>
    <div class="lf-cell">
        <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}" aria-label="From date" title="From">
    </div>
    <div class="lf-cell">
        <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}" aria-label="To date" title="To">
    </div>
    <button class="btn btn-p" data-no-busy><i class="bi bi-funnel"></i> Apply</button>
    @if(array_filter($filters))
        <a class="btn btn-ghost" href="{{ route('payroll.log.index') }}">Clear</a>
    @endif
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0 log-table">
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th>When</th>
                    @unless($company)<th>Company</th>@endunless
                    <th>Who</th>
                    <th>Action</th>
                    <th>What happened</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr class="log-row" data-log-toggle="log{{ $log->id }}">
                    <td class="col-sno">{{ $first + $loop->index }}</td>
                    <td class="text-nowrap">
                        <span class="cell-main">{{ $log->created_at->format('d M Y') }}</span>
                        <span class="cell-sub">{{ $log->created_at->format('h:i:s A') }}</span>
                    </td>
                    @unless($company)<td class="text-nowrap">{{ $log->company_name ?: '-' }}</td>@endunless
                    <td class="text-nowrap">{{ $log->user_name ?: 'System' }}</td>
                    <td>
                        <span class="pill {{ $log->status === 'failed' ? 'pill-danger' : ($tone[$log->action] ?? 'pill-locked') }}">
                            {{ $log->action === 'error' ? 'Error' : ($log->status === 'failed' ? 'Failed' : (\App\Models\PayrollLog::ACTIONS[$log->action] ?? ucfirst($log->action))) }}
                        </span>
                    </td>
                    <td>
                        <span class="cell-main">{{ $log->summary }}</span>
                        <span class="cell-sub">{{ $log->entity }}@if($log->subject_label) &middot; {{ $log->subject_label }}@endif</span>
                    </td>
                    <td class="text-end"><i class="bi bi-chevron-down log-caret" aria-hidden="true"></i></td>
                </tr>
                <tr class="log-detail" id="log{{ $log->id }}" hidden>
                    <td colspan="{{ $company ? 6 : 7 }}">
                        <div class="log-detail-body">
                            <div class="log-meta">
                                <span><b>Entry</b> #{{ $log->id }}</span>
                                <span><b>Recorded</b> {{ $log->created_at->format('d M Y, h:i:s A') }}</span>
                                <span><b>By</b> {{ $log->user_name ?: 'System' }}</span>
                                @if($log->ip_address)<span><b>From</b> {{ $log->ip_address }}</span>@endif
                            </div>

                            @if($log->details)
                                <table class="table table-sm mb-0">
                                    @if($log->action === 'updated')
                                        <thead><tr><th>Field</th><th>Was</th><th>Became</th></tr></thead>
                                        <tbody>
                                        @foreach($log->details as $field => $change)
                                            <tr>
                                                <td class="fw-semibold">{{ $field }}</td>
                                                <td><span class="diff-old">{{ is_array($change) ? ($change['was'] ?? '-') ?: '-' : '-' }}</span></td>
                                                <td><span class="diff-new">{{ is_array($change) ? ($change['became'] ?? '-') ?: '-' : $change }}</span></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    @else
                                        <thead><tr><th>Field</th><th>Value</th></tr></thead>
                                        <tbody>
                                        @foreach($log->details as $field => $value)
                                            <tr><td class="fw-semibold" style="width:220px;">{{ $field }}</td><td>{{ is_array($value) ? json_encode($value) : $value }}</td></tr>
                                        @endforeach
                                        </tbody>
                                    @endif
                                </table>
                            @else
                                <div class="text-muted" style="font-size:12.5px;">No further detail was recorded for this entry.</div>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $company ? 6 : 7 }}">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-journal-text"></i></div>
                            <div class="es-title">{{ array_filter($filters) ? 'Nothing matches' : 'Nothing logged yet' }}</div>
                            <div class="es-text">{{ array_filter($filters) ? 'Try a wider date range or fewer filters.' : 'Payroll changes and emails appear here as they happen.' }}</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="log-pager">{{ $logs->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var row = e.target.closest('[data-log-toggle]');
    if (!row) return;
    var detail = document.getElementById(row.dataset.logToggle);
    if (!detail) return;
    detail.hidden = !detail.hidden;
    row.classList.toggle('open', !detail.hidden);
});
</script>
@endpush
@endsection
