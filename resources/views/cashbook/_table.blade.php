{{-- The list: one table, newest first, with a slim heading row for each day
     that carries the day's count and total. Pass $entries, $records (the
     paginator), $direction ("in" or "out"), $noun/$nouns and $withKind. --}}
@php
    $money = fn ($n) => '₹'.number_format((float) $n, 2);
    $dayLabel = function ($date) {
        if (! $date) return 'No date';
        if ($date->isToday()) return 'Today';
        if ($date->isYesterday()) return 'Yesterday';
        return $date->format('l, d M Y');
    };
    $cols = $withKind ? 9 : 8;
@endphp

<div class="card cb-table-card reveal">
    <div class="table-responsive cb-table-wrap">
        <table class="table cb-table mb-0">
            <thead>
                <tr>
                    <th class="cb-c-no">No.</th>
                    <th>Time</th>
                    <th>Book</th>
                    @if($withKind)<th>Kind</th>@endif
                    <th>{{ $direction === 'in' ? 'Depositor' : 'Name' }}</th>
                    <th>Details</th>
                    <th class="text-end">Amount</th>
                    <th>By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach(collect($entries)->groupBy(fn ($e) => optional($e['record']->date)->toDateString()) as $day => $group)
                <tr class="cb-day-row">
                    <td colspan="{{ $cols }}">
                        <span class="cb-day-name">{{ $dayLabel($group->first()['record']->date) }}</span>
                        <span class="cb-day-meta">{{ $group->count() }} {{ $group->count() === 1 ? $noun : $nouns }} &middot; {{ $money($group->sum(fn ($e) => (float) $e['record']->amount)) }}</span>
                    </td>
                </tr>
                @foreach($group as $e)
                    @include('cashbook._entry', ['e' => $e, 'direction' => $direction, 'withKind' => $withKind])
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="cb-pager">@include('partials._server-pager', ['paginator' => $records])</div>
</div>
