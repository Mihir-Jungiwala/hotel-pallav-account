{{-- The strip at the top of Revenue and Expense: All / Hotel / Food, and for
     Expense the kind of entry too. Plain links, so the page (and its pager)
     always agree with what is picked. Pass $route, $filter (all|hotel|food),
     and optionally $kinds (value => label) with the current $kind. --}}
@php
    $link = fn (array $q) => route($route, array_filter($q + ['per' => request('per'), 'q' => request('q')], fn ($v) => $v !== null && $v !== 'all' && $v !== ''));
    $books = ['all' => ['All', 'bi-grid'], 'hotel' => ['Hotel Pallav', 'bi-building'], 'food' => ['Pallav Food', 'bi-cup-hot']];
@endphp

<div class="cb-filters reveal">
    <div class="cb-filter-group">
        <span class="cb-filter-label">Book</span>
        <div class="cb-seg" role="group" aria-label="Show which book">
            @foreach($books as $value => [$label, $icon])
                <a href="{{ $link(['book' => $value, 'kind' => $kind ?? null]) }}" class="{{ $filter === $value ? 'active' : '' }}" @if($filter === $value) aria-current="true" @endif>
                    <i class="bi {{ $icon }}"></i> {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @isset($kinds)
        <div class="cb-filter-group">
            <span class="cb-filter-label">Kind</span>
            <div class="cb-seg" role="group" aria-label="Show which kind of entry">
                @foreach(['all' => 'Everything'] + $kinds as $value => $label)
                    {{-- Staff advances belong to the whole business, so choosing them clears the book --}}
                    <a href="{{ $link(['book' => $value === 'advance' ? null : $filter, 'kind' => $value]) }}" class="{{ $kind === $value ? 'active' : '' }}" @if($kind === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    @endisset

    <form method="GET" action="{{ route($route) }}" class="cb-search" role="search" data-cb-search>
        @foreach(['per', 'kind'] as $keep)
            @if(request($keep) && request($keep) !== 'all')<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
        @endforeach
        @if(($filter ?? 'all') !== 'all')<input type="hidden" name="book" value="{{ $filter }}">@endif
        <div class="search-field smart">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $q ?? '' }}" class="form-control" autocomplete="off"
                   placeholder="{{ $searchHint ?? 'Search name, source, amount or date' }}&hellip;" aria-label="Search the list" title='Tips: several words must all match, "exact phrase", -leave out, &gt;5000, &lt;500, 500-2000'>
            @if(($q ?? '') !== '')
                <a class="search-clear" href="{{ $link(['book' => $filter ?? null, 'kind' => $kind ?? null, 'q' => null]) }}" title="Clear search" aria-label="Clear search"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>
