{{-- Pay to Pallav Food: what Hotel Pallav owes for its staff's meals this month.
     The staff do not pay - the owner does - so this never touches salary. --}}
<div class="card mt-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-cup-hot me-1"></i> Pay to {{ $food['payee'] }}</span>
        <a class="btn btn-sm btn-outline-p" target="_blank"
           href="{{ route('payroll.report.food-charges', ['year' => $food['month']->year, 'month' => $food['month']->month]) }}">
            <i class="bi bi-file-earmark-pdf"></i> Statement PDF
        </a>
    </div>

    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>
                Paid by Hotel Pallav, not by the staff, so it is not taken from any salary.
                {{ $food['payee'] }} charges &#8377;{{ number_format($food['rate'], 2) }} per employee for the month, counted by calendar days
                ({{ $food['daysInMonth'] }} in {{ $food['month']->format('F') }}) from the joining date.
            </span>
        </div>
    </div>

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
                    <td><span class="cell-main">{{ $row['name'] }}</span><span class="cell-sub">{{ $row['code'] }}</span></td>
                    <td>{{ $row['designation'] }}</td>
                    <td class="text-center">{{ $row['days'] }} / {{ $food['daysInMonth'] }}</td>
                    <td class="money">&#8377;{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @endforeach
                <tr class="fw-bold">
                    <td colspan="4">Total payable to {{ $food['payee'] }} &middot; {{ \App\Support\NumberToWords::convert($food['total']) }}</td>
                    <td class="money">&#8377;{{ number_format($food['total'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
