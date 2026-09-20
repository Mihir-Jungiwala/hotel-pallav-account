{{-- Payroll Log: everything done in payroll, as a diary. One line per event,
     grouped by day; open a line for everything that was recorded. --}}
@extends('payroll.layout', [
    'title' => 'Payroll Log',
    'subtitle' => $company
        ? 'Everything done in '.$company->name.', newest first.'
        : 'Everything done across both companies, newest first. Choose a company to see only its own.',
])

@php
    $scopeName = $company?->name ?? 'All companies';

    // How each kind of event reads at a glance: its icon, and the colour of its rail
    $look = [
        'created' => ['bi-plus-lg', 'created'],
        'updated' => ['bi-pencil', 'updated'],
        'deleted' => ['bi-trash', 'deleted'],
        'emailed' => ['bi-envelope', 'emailed'],
        'error' => ['bi-exclamation-triangle', 'error'],
    ];
@endphp

@section('page-actions')
    @if($logs->total() > 0)
        <a class="btn btn-outline-p" href="{{ route('payroll.log.download', request()->query()) }}">
            <i class="bi bi-download"></i> Export CSV
        </a>
    @endif
@endsection

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
    <div class="pay-stat {{ $stats['failed'] ? 'warn' : 'good' }}">
        <div class="ps-label">Problems</div>
        <div class="ps-value">{{ number_format($stats['failed']) }}</div>
        <div class="ps-sub">{{ $stats['failed'] ? 'Errors and emails that did not go' : 'Nothing has failed' }}</div>
    </div>
</div>

{{-- The questions people actually ask, one click each --}}
<div class="log-views">
    <a class="log-chip {{ ! ($filters['view'] ?? null) ? 'active' : '' }}"
       href="{{ route('payroll.log.index', collect(request()->query())->except('view', 'page')->all()) }}">
        <i class="bi bi-list-ul"></i> Everything
    </a>
    @foreach($quickViews as $key => [$label, $icon])
        <a class="log-chip {{ ($filters['view'] ?? null) === $key ? 'active' : '' }} {{ $key === 'problems' ? 'danger' : '' }}"
           href="{{ route('payroll.log.index', collect(request()->query())->except('page')->put('view', $key)->all()) }}">
            <i class="bi {{ $icon }}"></i> {{ $label }}
        </a>
    @endforeach
</div>

{{-- Everything else, out of the way until it is wanted --}}
<details class="log-advanced" {{ ($filters['q'] ?? $filters['entity'] ?? $filters['user'] ?? $filters['from'] ?? null) ? 'open' : '' }}>
    <summary><i class="bi bi-funnel"></i> Search and filter</summary>

    <form method="GET" action="{{ route('payroll.log.index') }}" class="log-filters">
        @if($filters['view'] ?? null)<input type="hidden" name="view" value="{{ $filters['view'] }}">@endif

        <div class="lf-cell lf-wide">
            <label class="form-label" for="lf_q">Search</label>
            <div class="search-field smart mb-0">
                <i class="bi bi-search"></i>
                <input type="search" name="q" id="lf_q" class="form-control" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Name, record, amount, anything&hellip;" aria-label="Search the log">
            </div>
        </div>
        <div class="lf-cell">
            <label class="form-label" for="lf_action">Action</label>
            <select name="action" id="lf_action" class="form-select">
                <option value="">Any action</option>
                @foreach(\App\Models\PayrollLog::ACTIONS as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="lf-cell">
            <label class="form-label" for="lf_entity">What</label>
            <select name="entity" id="lf_entity" class="form-select">
                <option value="">Anything</option>
                @foreach($entities as $entity)
                    <option value="{{ $entity }}" @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>
                @endforeach
            </select>
        </div>
        <div class="lf-cell">
            <label class="form-label" for="lf_user">Who</label>
            <select name="user" id="lf_user" class="form-select">
                <option value="">Anyone</option>
                @foreach($people as $person)
                    <option value="{{ $person }}" @selected(($filters['user'] ?? '') === $person)>{{ $person }}</option>
                @endforeach
            </select>
        </div>
        <div class="lf-cell">
            <label class="form-label" for="lf_from">From</label>
            <input type="date" name="from" id="lf_from" class="form-control" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="lf-cell">
            <label class="form-label" for="lf_to">To</label>
            <input type="date" name="to" id="lf_to" class="form-control" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="lf-cell lf-actions">
            <button class="btn btn-p w-100"><i class="bi bi-search"></i> Apply</button>
            @if($hasFilters)
                <a class="btn btn-ghost w-100 mt-2" href="{{ route('payroll.log.index') }}">Clear all</a>
            @endif
        </div>
    </form>
</details>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            {{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}
            @if($hasFilters)<span class="text-muted fw-normal" style="font-size:12.5px;">matching your filters</span>@endif
        </span>
        @if($logs->total() > 0)
            <span class="text-muted" style="font-size:12.5px;">Showing {{ $logs->firstItem() }}-{{ $logs->lastItem() }}</span>
        @endif
    </div>

    @forelse($days as $date => $entries)
        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
        <div class="log-day">
            <span class="ld-date">{{ $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l, j F Y')) }}</span>
            <span class="ld-count">{{ $entries->count() }}</span>
        </div>

        @foreach($entries as $log)
            @php [$icon, $tone] = $look[$log->action] ?? ['bi-dot', 'updated']; @endphp
            <div class="log-entry tone-{{ $log->status === 'failed' ? 'error' : $tone }}">
                <button type="button" class="le-head" data-log-toggle="log{{ $log->id }}" aria-expanded="false">
                    <span class="le-icon"><i class="bi {{ $icon }}"></i></span>

                    <span class="le-body">
                        <span class="le-summary">{{ $log->summary }}</span>
                        <span class="le-meta">
                            <span><i class="bi bi-person"></i> {{ $log->user_name ?: 'System' }}</span>
                            <span><i class="bi bi-clock"></i> {{ $log->created_at->format('h:i A') }}</span>
                            <span class="le-tag">{{ $log->entity }}</span>
                            @unless($company)
                                @if($log->company_name)<span class="le-tag">{{ $log->company_name }}</span>@endif
                            @endunless
                            @if($log->status === 'failed')<span class="le-tag bad">Did not work</span>@endif
                        </span>
                    </span>

                    <i class="bi bi-chevron-down le-caret" aria-hidden="true"></i>
                </button>

                <div class="le-detail" id="log{{ $log->id }}" hidden>
                    <div class="le-meta-full">
                        <span><b>Entry</b> #{{ $log->id }}</span>
                        <span><b>Exact time</b> {{ $log->created_at->format('d M Y, h:i:s A') }}</span>
                        <span><b>By</b> {{ $log->user_name ?: 'System' }}</span>
                        @if($log->subject_label)<span><b>Record</b> {{ $log->subject_label }}</span>@endif
                        @if($log->ip_address)<span><b>From</b> {{ $log->ip_address }}</span>@endif
                    </div>

                    @if($log->details)
                        @if($log->action === 'updated')
                            <table class="table table-sm mb-0 le-table">
                                <thead><tr><th>Field</th><th>Was</th><th>Became</th></tr></thead>
                                <tbody>
                                @foreach($log->details as $field => $change)
                                    <tr>
                                        <td class="fw-semibold">{{ $field }}</td>
                                        {{-- A blank is "empty", but a value of 0 is a value --}}
                                        <td><span class="diff-old">{{ is_array($change) ? (blank($change['was'] ?? null) ? 'empty' : $change['was']) : '-' }}</span></td>
                                        <td><span class="diff-new">{{ is_array($change) ? (blank($change['became'] ?? null) ? 'empty' : $change['became']) : $change }}</span></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <table class="table table-sm mb-0 le-table">
                                <tbody>
                                @foreach($log->details as $field => $value)
                                    <tr>
                                        <td class="fw-semibold" style="width:190px;">{{ $field }}</td>
                                        <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    @else
                        <div class="text-muted" style="font-size:12.5px;">No further detail was recorded for this entry.</div>
                    @endif
                </div>
            </div>
        @endforeach
    @empty
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-journal-text"></i></div>
            <div class="es-title">{{ $hasFilters ? 'Nothing matches' : 'Nothing logged yet' }}</div>
            <div class="es-text">
                {{ $hasFilters
                    ? 'Try a wider date range, or clear the filters.'
                    : 'Payroll changes and emails appear here as they happen.' }}
            </div>
            @if($hasFilters)
                <a class="btn btn-outline-p mt-3" href="{{ route('payroll.log.index') }}">Clear filters</a>
            @endif
        </div>
    @endforelse

    @if($logs->hasPages())
        <div class="log-pager">{{ $logs->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    document.addEventListener('click', function (e) {
        var head = e.target.closest('[data-log-toggle]');
        if (!head) return;
        var detail = document.getElementById(head.dataset.logToggle);
        if (!detail) return;
        var open = detail.hidden;
        detail.hidden = !open;
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
        head.closest('.log-entry').classList.toggle('open', open);
    });
})();
</script>
@endpush
@endsection
