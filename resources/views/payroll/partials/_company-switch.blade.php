{{-- Which company payroll is working inside, and the way to every other one.

     This used to be two things stacked on top of each other - a switcher, and
     a separate "Company Listing" menu entry beneath it - that both asked the
     same question. It is one control now: the card shows where you are, and
     its menu lists every company plus the full listing.

     Switching reloads through the listing route, so the new company's data is
     fetched fresh rather than the previous company's lingering on screen. --}}
@php
    $onListing = request()->routeIs('payroll.index') || request()->routeIs('payroll.company.index');
@endphp

<div class="company-switch {{ $company ? '' : 'is-empty' }} {{ $onListing ? 'on-listing' : '' }}" id="companySwitch">
    <button type="button" class="company-switch-btn" aria-haspopup="listbox" aria-expanded="false"
            title="{{ $company?->name ?? 'Choose a company' }}">
        <span class="cs-mark">
            @if($company)
                {{ $initials($company->name) }}
            @else
                <i class="bi bi-buildings"></i>
            @endif
        </span>
        <span class="cs-text">
            <span class="cs-hint">{{ $company ? 'Current company' : 'Payroll' }}</span>
            <span class="cs-name">{{ $company?->name ?? 'Choose a company' }}</span>
        </span>
        <i class="bi bi-chevron-expand cs-caret" aria-hidden="true"></i>
    </button>

    <div class="company-menu" role="listbox">
        {{-- The full listing leads the menu: it is where a company is added,
             edited or deactivated, not just picked --}}
        <a class="cm-listing {{ $onListing ? 'selected' : '' }}" href="{{ route('payroll.index') }}">
            <span class="cm-listing-icon"><i class="bi bi-grid-3x3-gap"></i></span>
            <span class="cm-listing-text">
                <span class="cm-listing-title">Company Listing</span>
                <span class="cm-listing-sub">Add, edit or open any company</span>
            </span>
        </a>

        @if($companyOptions->isNotEmpty())
            <div class="cm-divider"><span>Switch to</span></div>

            @if($companyOptions->count() > 6)
                <div class="cm-search">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Find a company&hellip;" aria-label="Find a company">
                </div>
            @endif

            <div class="cm-list">
                @foreach($companyOptions as $option)
                    @php $isCurrent = $company && $company->id === $option->id; @endphp
                    <a class="cm-item {{ $isCurrent ? 'selected' : '' }}"
                       href="{{ route('payroll.index', ['current_company' => $option->id]) }}"
                       data-name="{{ $option->name }}"
                       role="option" aria-selected="{{ $isCurrent ? 'true' : 'false' }}">
                        <span class="cm-mark">{{ $initials($option->name) }}</span>
                        <span>{{ $option->name }}</span>
                        @if($isCurrent)<i class="bi bi-check-lg cm-check"></i>@endif
                    </a>
                @endforeach

                <div class="cm-empty" data-no-match hidden>No company matches that name.</div>
            </div>
        @endif
    </div>
</div>
