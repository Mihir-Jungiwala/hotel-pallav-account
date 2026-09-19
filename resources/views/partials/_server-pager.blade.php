{{-- The Payroll pager, for a list the server pages. Same look and controls
     (what is showing, rows per page, numbered pages); each button asks the
     server for its page. Pass $paginator (LengthAwarePaginator). --}}
@php
    $p = $paginator;
    $last = $p->lastPage();
    $current = $p->currentPage();
    $window = [];
    for ($n = 1; $n <= $last; $n++) {
        if ($n === 1 || $n === $last || abs($n - $current) <= 1) {
            $window[] = $n;
        } elseif (end($window) !== '…') {
            $window[] = '…';
        }
    }
@endphp

<form method="GET" class="pms-pager" data-no-busy="true" data-self-service>
    @foreach(request()->except(['page', 'per']) as $key => $value)
        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <div class="pg-left">
        <span class="pg-info">
            {{ $p->total() ? 'Showing '.$p->firstItem().'-'.$p->lastItem().' of '.$p->total() : 'No records' }}
        </span>
        <span class="pg-size">
            <span class="pg-size-label">Rows</span>
            <select name="per" aria-label="Rows per page" data-native data-pms-select onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                @foreach([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($p->perPage() === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </span>
    </div>
    <div class="pg-controls">
        <button type="submit" name="page" value="{{ $current - 1 }}" aria-label="Previous page" @disabled($current <= 1)><i class="bi bi-chevron-left"></i></button>
        @foreach($window as $n)
            @if($n === '…')
                <button type="button" disabled>…</button>
            @else
                <button type="submit" name="page" value="{{ $n }}" class="{{ $n === $current ? 'current' : '' }}" @if($n === $current) aria-current="page" @endif>{{ $n }}</button>
            @endif
        @endforeach
        <button type="submit" name="page" value="{{ $current + 1 }}" aria-label="Next page" @disabled($current >= $last)><i class="bi bi-chevron-right"></i></button>
    </div>
</form>
