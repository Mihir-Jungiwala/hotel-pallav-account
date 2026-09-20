{{-- Shared shell for every individual payroll page.
     It supplies the two things that are the same everywhere - which company
     is open, and the menu of pages - so each feature view only has to
     describe itself. The company sits at the head of the payroll menu rather
     than in the page header: it belongs with the navigation it scopes, and it
     leaves the top right of every page for that page's own actions. --}}
@extends('layouts.app')

@php
    $company = \App\Support\PayrollContext::current();
    $companyOptions = \App\Support\PayrollContext::selectable();
    $sections = \App\Support\PayrollNav::sections($company);
    $counts = \App\Support\PayrollNav::counts($company);
    $initials = fn ($name) => collect(explode(' ', trim((string) $name)))
        ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

@section('title', $title ?? 'Payroll')

@section('content')

{{-- Page header: where you are, and what you can do here --}}
<div class="pay-head reveal">
    <div class="ph-text">
        <nav class="ph-crumbs" aria-label="Breadcrumb">
            <a href="{{ route('payroll.index') }}">Payroll</a>
            @if($company)
                <i class="bi bi-chevron-right"></i>
                <a href="{{ route('payroll.dashboard.index') }}">{{ $company->name }}</a>
            @endif
        </nav>
        <h2 class="pms-title">{{ $title ?? 'Payroll' }}</h2>
        @isset($subtitle)
            <p class="pms-subtitle">{{ $subtitle }}</p>
        @endisset
    </div>

    <div class="ph-actions">
        @yield('page-actions')
    </div>
</div>

@hasSection('toolbar')
    <div class="pay-toolbar reveal">@yield('toolbar')</div>
@endif

<div class="reveal" style="min-width:0;">
    @yield('page')
</div>

@endsection

@section('subnav')
    <div class="subnav-head">
        {{-- The panel's own title and close control get a slim row of their
             own, so the company card below can use the full width instead of
             sharing it with a button. --}}
        <div class="subnav-top">
            <span class="subnav-title"><i class="bi bi-people-fill"></i> Payroll</span>
            <button type="button" class="panel-toggle panel-close" id="subnavToggle"
                    title="Hide this panel" aria-expanded="true">
                <i class="bi bi-chevron-double-left"></i>
            </button>
        </div>

        @include('payroll.partials._company-switch', [
            'company' => $company,
            'companyOptions' => $companyOptions,
            'initials' => $initials,
        ])
    </div>

    <nav class="subnav-body" aria-label="Payroll pages">
        {{-- The Company Listing is reached through the switcher above - it is
             the same question ("which company?") so it lives in one place.
             The feature pages below only exist once a company is open. --}}
        @if($company)
            @foreach($sections as $sectionLabel => $items)
                <div class="rail-group">
                    {{-- Dashboard stands alone at the top; a heading over one
                         entry would only be noise --}}
                    @if($sectionLabel !== 'Company')
                        <div class="rail-label">{{ $sectionLabel }}</div>
                    @endif
                    @foreach($items as $slug => [$route, $label, $icon])
                        @php $isCurrent = request()->routeIs($route); @endphp
                        <a class="rail-item {{ $isCurrent ? 'active' : '' }}"
                           href="{{ route($route) }}"
                           @if($isCurrent) aria-current="page" @endif>
                            <i class="bi {{ $icon }}"></i>
                            <span>{{ $label }}</span>
                            @if(($counts[$slug] ?? 0) > 0)
                                <span class="rail-count">{{ $counts[$slug] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        @else
            <p class="rail-note">
                <i class="bi bi-info-circle"></i>
                Open a company to reach Staff, Attendance, Salary and the rest.
            </p>
        @endif

        {{-- Only the SuperAdmin sees this. It works with or without a company
             open: without one it reaches every company, with one only that one. --}}
        @if(auth()->user()->isSuperAdmin())
            <div class="rail-group">
                <div class="rail-label">Administration</div>
                <a class="rail-item {{ request()->routeIs('payroll.master.*') ? 'active' : '' }}"
                   href="{{ route('payroll.master.index') }}"
                   @if(request()->routeIs('payroll.master.*')) aria-current="page" @endif>
                    <i class="bi bi-sliders"></i>
                    <span>Payroll Master</span>
                </a>
                <a class="rail-item {{ request()->routeIs('payroll.log.*') ? 'active' : '' }}"
                   href="{{ route('payroll.log.index') }}"
                   @if(request()->routeIs('payroll.log.*')) aria-current="page" @endif>
                    <i class="bi bi-journal-text"></i>
                    <span>Payroll Log</span>
                </a>
            </div>
        @endif
    </nav>
@endsection
