{{-- The month's bill: one line per person, with why a part month is part. --}}
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
                    Total payable to {{ $food['payee'] }}
                    <span class="cell-sub fw-normal">{{ \App\Support\NumberToWords::convert($food['total']) }}</span>
                </td>
                <td class="money">₹{{ number_format($food['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
