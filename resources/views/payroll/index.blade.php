@extends('layouts.app')
@section('title', 'Payroll')
@section('content')

@php
    // Modules grouped by what they're for, following the mandatory sequence
    $groups = [
        'Setup' => [
            'attendance-status' => ['Attendance Status', 'bi-palette2'],
            'deduction' => ['Deductions', 'bi-dash-circle'],
            'joining-letter' => ['Joining Letter', 'bi-file-earmark-text'],
            'experience-letter' => ['Experience Letter', 'bi-file-earmark-check'],
        ],
        'People' => [
            'staff' => ['Staff', 'bi-people'],
            'separation' => ['Resignations', 'bi-box-arrow-right'],
        ],
        'Operations' => [
            'attendance' => ['Attendance', 'bi-calendar3'],
            'advance' => ['Advances', 'bi-wallet2'],
            'bonus-incentive' => ['Bonus & Incentive', 'bi-gift'],
        ],
        'Payroll' => [
            'salary-update' => ['Salary Updates', 'bi-clock-history'],
            'salary-payment' => ['Salary Payments', 'bi-credit-card-2-back'],
        ],
        'Reports' => [
            'salary-slip' => ['Salary Slips', 'bi-receipt'],
            'period-report' => ['Period Report', 'bi-calendar-range'],
            'monthly-report' => ['Monthly Report', 'bi-bar-chart'],
        ],
    ];

    $initials = fn ($name) => collect(explode(' ', trim($name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $activeCompanies = $companies->where('is_active', true);
@endphp

{{-- Workspace header --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 reveal">
    <div>
        <div class="pms-eyebrow">Payroll</div>
        <h2 class="pms-title">{{ $company?->name ?? 'Company Setup' }}</h2>
    </div>

    <div class="d-flex align-items-center gap-2">
        <div class="company-switch" id="companySwitch">
            <button type="button" class="company-switch-btn" aria-haspopup="listbox" aria-expanded="false">
                <span class="cs-mark">{{ $company ? $initials($company->name) : '★' }}</span>
                <span class="cs-text">
                    <span class="cs-name">{{ $company?->name ?? 'All Companies' }}</span>
                    <span class="cs-hint">{{ $company ? 'Current company' : 'Choose a company' }}</span>
                </span>
                <i class="bi bi-chevron-down cs-caret"></i>
            </button>

            <div class="company-menu" role="listbox">
                @if($activeCompanies->count() > 5)
                    <div class="cm-search">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Find a company…" aria-label="Find a company">
                    </div>
                @endif

                <div class="cm-list">
                    <a class="cm-item {{ $company === null ? 'selected' : '' }}"
                       href="{{ route('payroll.index', ['current_company' => \App\Support\PayrollContext::SENTINEL]) }}"
                       data-name="All Companies">
                        <span class="cm-mark">★</span>
                        <span>Company Setup</span>
                        @if($company === null)<i class="bi bi-check-lg cm-check"></i>@endif
                    </a>

                    @foreach($activeCompanies as $option)
                        <a class="cm-item {{ $company && $company->id === $option->id ? 'selected' : '' }}"
                           href="{{ route('payroll.index', ['current_company' => $option->id]) }}"
                           data-name="{{ $option->name }}">
                            <span class="cm-mark">{{ $initials($option->name) }}</span>
                            <span>{{ $option->name }}</span>
                            @if($company && $company->id === $option->id)<i class="bi bi-check-lg cm-check"></i>@endif
                        </a>
                    @endforeach

                    <div class="cm-empty" hidden>No company matches that name.</div>
                </div>

                <div class="cm-foot">
                    <a class="cm-item" href="{{ route('payroll.index', ['current_company' => \App\Support\PayrollContext::SENTINEL]) }}">
                        <span class="cm-mark"><i class="bi bi-plus-lg"></i></span>
                        <span>Add a company</span>
                    </a>
                </div>
            </div>
        </div>

        @if($company === null)
            <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                <i class="bi bi-plus-circle"></i> New Company
            </button>
        @endif
    </div>
</div>

<div class="reveal" style="min-width:0;">
    @includeIf('payroll.partials.'.($company === null ? 'profile' : $category))
</div>

@endsection

@if($company !== null)
@section('subnav')
    <div class="subnav-head">
        <div>
            <div class="pms-eyebrow">Payroll</div>
            <div style="font-weight:700; font-size:13.5px; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px;">
                {{ $company->name }}
            </div>
        </div>
        <a class="panel-toggle" href="{{ route('payroll.index', ['current_company' => \App\Support\PayrollContext::SENTINEL]) }}" title="Back to Company Setup">
            <i class="bi bi-arrow-left-short" style="font-size:18px;"></i>
        </a>
    </div>

    <nav class="subnav-body" aria-label="Payroll modules">
        @foreach($groups as $groupLabel => $items)
            <div class="rail-group">
                <div class="rail-label">{{ $groupLabel }}</div>
                @foreach($items as $slug => [$label, $icon])
                    <a class="rail-item {{ $category === $slug ? 'active' : '' }}"
                       href="{{ route('payroll.index', ['category' => $slug]) }}"
                       @if($category === $slug) aria-current="page" @endif>
                        <i class="bi {{ $icon }}"></i>
                        <span>{{ $label }}</span>
                        @if(($railCounts[$slug] ?? null))
                            <span class="rail-count">{{ $railCounts[$slug] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
@endsection
@endif
