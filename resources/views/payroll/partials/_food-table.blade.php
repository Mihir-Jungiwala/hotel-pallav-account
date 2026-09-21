{{-- The month's food bill: one line per person, with why a month is part of one.
     Used on the Staff Meals page and at the foot of the Monthly Report. --}}
{{-- This page works the bill out live; the report only carries it once salary is generated --}}
@if(isset($food['final']) && ! $food['final'])
    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-broadcast"></i>
            <span>
                <strong>Live.</strong>
                @if($food['asOf'])
                    Counted up to {{ $food['asOf']->format('j F') }}, the days that have happened so far; it grows each day.
                @else
                    Worked out for the whole month.
                @endif
                It becomes final, and joins the Monthly Report, once salary is generated for {{ $food['month']->format('F Y') }}.
            </span>
        </div>
    </div>
@endif

@if(count($food['prices']) > 1)
    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-arrow-repeat"></i>
            <span>
                The price changed during {{ $food['month']->format('F') }}:
                @foreach($food['prices'] as $price)
                    <strong>&#8377;{{ number_format($price['amount'], 2) }}</strong>{{ $loop->first ? ' from the 1st' : ' from '.$price['from']->format('j M') }}{{ $loop->last ? '.' : ',' }}
                @endforeach
                Each day is charged at the price in force that day.
            </span>
        </div>
    </div>
@endif

<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th class="col-sno">S.No.</th>
                <th>Employee</th>
                <th>Designation</th>
                <th class="text-center">Days counted</th>
                <th class="money">Amount</th>
            </tr>
        </thead>
        <tbody>
        @foreach($food['rows'] as $row)
            <tr>
                <td class="col-sno">{{ $loop->iteration }}</td>
                <td>
                    <span class="cell-main">{{ $row['name'] }}</span>
                    <span class="cell-sub">{{ $row['code'] }}</span>
                </td>
                <td>{{ $row['designation'] }}</td>
                <td class="text-center">
                    <span class="{{ $row['days'] < $food['daysInMonth'] ? 'fw-semibold' : '' }}">{{ $row['days'] }} / {{ $food['daysInMonth'] }}</span>
                    @if($row['note'])<span class="cell-sub">{{ $row['note'] }}</span>@endif
                </td>
                <td class="money">₹{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
            <tr class="fw-bold">
                <td colspan="4">
                    {{ $food['owed'] ? 'Total payable to '.$food['payee'] : 'Total cost of staff meals' }}
                    <span class="cell-sub fw-normal">{{ \App\Support\NumberToWords::convert($food['total']) }}</span>
                </td>
                <td class="money">₹{{ number_format($food['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
