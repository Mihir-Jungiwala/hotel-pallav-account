@php
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Pay to '.$food['payee'];
    $docSub = $food['month']->format('F Y');
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="stats avoid-break" style="margin-bottom:6px;">
    <tr>
        <td><div class="s-label">Payable To</div><div class="s-value">{{ $food['payee'] }}</div></td>
        <td><div class="s-label">Employees</div><div class="s-value">{{ count($food['rows']) }}</div></td>
        <td><div class="s-label">Monthly Charge Each</div><div class="s-value">&#8377;{{ number_format($food['rate'], 2) }}</div></td>
        <td><div class="s-label">Total</div><div class="s-value accent">&#8377;{{ number_format($food['total'], 2) }}</div></td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Employees</h2>
<table class="grid">
    <thead>
        <tr>
            <th style="width:34px;">#</th>
            <th>Employee</th>
            <th>Designation</th>
            <th class="num">Days counted</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($food['rows'] as $row)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td><strong>{{ $row['name'] }}</strong> <span class="muted">{{ $row['code'] }}</span></td>
            <td>{{ $row['designation'] }}</td>
            <td class="num">{{ $row['days'] }} / {{ $food['daysInMonth'] }}</td>
            <td class="num">{{ number_format($row['amount'], 2) }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="4">Total payable to {{ $food['payee'] }} &middot; {{ \App\Support\NumberToWords::convert($food['total']) }}</td>
            <td class="num">{{ number_format($food['total'], 2) }}</td>
        </tr>
    </tbody>
</table>

<p class="muted" style="font-size:7.5pt; margin-top:8px;">
    Paid by {{ $company->name }}; it is not deducted from any employee's salary. The charge is counted by calendar
    days ({{ $food['daysInMonth'] }} in {{ $food['month']->format('F Y') }}), every day included, from each employee's joining date.
</p>

@include('payroll.pdf._signatures', [
    'top' => 18,
    'cells' => [
        ['caption' => 'For '.$company->name, 'name' => $company->authorized_person_name ?: 'Authorised Signatory', 'lines' => [$company->authorized_designation]],
        ['caption' => 'Received for '.$food['payee'], 'name' => null],
    ],
])

@endsection
