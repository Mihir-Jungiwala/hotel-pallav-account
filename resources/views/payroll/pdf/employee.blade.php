@php
    $company = $employee->company;
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state, $company->pincode])->filter()->implode(', '));
    $docType = 'Employee Record';
    $docSub = $employee->employee_code;
    $photo = $employee->photo_path && file_exists(public_path('storage/'.$employee->photo_path))
        ? public_path('storage/'.$employee->photo_path) : null;
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="avoid-break" style="margin-bottom:4px;">
    <tr>
        @if($photo)
        <td style="width:72px; vertical-align:top;">
            <img src="{{ $photo }}" style="width:62px; height:62px; border-radius:6px;">
        </td>
        @endif
        <td style="vertical-align:top;">
            <div style="font-size:14pt; font-weight:bold; letter-spacing:-0.3pt;">{{ $employee->name }}</div>
            <div class="muted" style="font-size:9pt;">
                {{ $employee->designation ?: 'No designation' }}@if($employee->department) &middot; {{ $employee->department }}@endif
            </div>
            <div style="margin-top:4px;">
                <span class="chip {{ $employee->is_active ? 'ok' : 'warn' }}">{{ $employee->is_active ? 'Active' : 'Inactive' }}</span>
                <span class="chip">{{ $employee->payment_mode }}</span>
            </div>
        </td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Employment</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Employee ID</td><td class="v">{{ $employee->employee_code }}</td>
        <td class="k">Joining Date</td><td class="v">{{ optional($employee->joining_date)->format('d M Y') ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Monthly Salary</td><td class="v">₹{{ number_format($employee->salary, 2) }}</td>
        <td class="k">Working Hours</td><td class="v">{{ rtrim(rtrim(number_format($employee->daily_working_hours, 2), '0'), '.') }} hrs / day</td>
    </tr>
    <tr>
        <td class="k">Contact</td><td class="v">{{ $employee->contactDisplay() ?: '-' }}</td>
        <td class="k">Payment Mode</td><td class="v">{{ $employee->payment_mode }}</td>
    </tr>
    <tr>
        <td class="k">Address</td><td class="v" colspan="3">{{ $employee->address ?: '-' }}</td>
    </tr>
</table>

@if($employee->responsibilities)
<h2 class="section"><span class="dot"></span>Responsibilities</h2>
<div style="font-size:9pt; line-height:1.55;">{{ $employee->responsibilities }}</div>
@endif

@if($employee->payment_mode === 'Bank')
<h2 class="section"><span class="dot"></span>Bank Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Bank</td><td class="v">{{ $employee->bank_name ?: '-' }}</td>
        <td class="k">Account Holder</td><td class="v">{{ $employee->account_holder_name ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Account No.</td><td class="v">{{ $employee->account_number ?: '-' }}</td>
        <td class="k">IFSC</td><td class="v">{{ $employee->ifsc_code ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Branch</td><td class="v" colspan="3">{{ $employee->branch_name ?: '-' }}</td>
    </tr>
</table>
@endif

<h2 class="section"><span class="dot"></span>Identification</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">ID Proof Type</td><td class="v">{{ $employee->id_proof_type ?: (optional($employee->idProofType)->name ?: '-') }}</td>
        <td class="k">ID Proof No.</td><td class="v">{{ $employee->id_proof_number ?: '-' }}</td>
    </tr>
</table>

@if($employee->deductions->isNotEmpty())
<h2 class="section"><span class="dot"></span>Assigned Deductions</h2>
<table class="grid avoid-break">
    <thead><tr><th>Deduction</th><th>Type</th><th class="num">Amount</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($employee->deductions as $assignment)
        <tr>
            <td>{{ optional($assignment->deduction)->name ?: '-' }}</td>
            <td>{{ $assignment->deduction_type }}</td>
            <td class="num">{{ number_format($assignment->amount, 2) }}</td>
            <td>{{ $assignment->is_settled ? 'Settled' : 'Recurring' }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="2">Total per cycle</td>
            <td class="num">{{ number_format($employee->deductions->where('is_settled', false)->sum('amount'), 2) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
@endif

<div class="sign-area">
    <table>
        <tr>
            <td style="width:55%; vertical-align:bottom;" class="muted">
                <div style="font-size:7.5pt;">Record maintained by {{ $company->name }}. Figures reflect the employee master at the time of printing.</div>
            </td>
            <td style="width:45%; text-align:right; vertical-align:bottom;">
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>{{ $company->authorized_person_name ?: 'Authorised Signatory' }}</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ $company->authorized_designation }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
