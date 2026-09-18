@php
    $brandName = $processing->company_name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state, $company->pincode])->filter()->implode(', '))
        . ($company->mobile_number ? '  ·  '.$company->mobile_number : '');
    $docType = 'Salary Slip';
    $docSub = $processing->periodLabel();

    $deductionLines = $processing->lines->where('category', 'Deduction');
    $advanceLines   = $processing->lines->where('category', 'Advance');
    $earningLines   = $processing->lines->whereIn('category', ['Bonus', 'Incentive']);

    // Only show components that actually carry a value - no empty filler rows
    $earnings = collect([
        ['Attendance Salary', number_format($processing->total_payable_days, 2).' payable days × ₹'.number_format($processing->daily_salary, 2), $processing->attendance_salary, true],
        ['Overtime', number_format($processing->overtime_hours, 2).' hrs × ₹'.number_format($processing->hourly_rate, 2).'/hr', $processing->overtime_amount, $processing->overtime_amount > 0],
        ['Bonus', $earningLines->where('category', 'Bonus')->pluck('label')->implode(', '), $processing->bonus_amount, $processing->bonus_amount > 0],
        ['Incentive', $earningLines->where('category', 'Incentive')->pluck('label')->implode(', '), $processing->incentive_amount, $processing->incentive_amount > 0],
    ])->filter(fn ($row) => $row[3]);

    $employee = $processing->employee;
    $showBank = $processing->payment_mode === 'Bank' && $employee && $employee->account_number;

    $grossEarnings = $processing->attendance_salary + $processing->overtime_amount + $processing->bonus_amount + $processing->incentive_amount;
    $totalDeductions = $processing->deduction_amount + $processing->advance_deduction;
@endphp

@extends('payroll.pdf._base')

@section('content')

{{-- Employee identity + period, in one compact band --}}
<table class="fields avoid-break">
    <tr>
        <td class="k">Employee</td>
        <td class="v">{{ $processing->employee_name }}</td>
        <td class="k">Employee ID</td>
        <td class="v">{{ $processing->employee_code }}</td>
    </tr>
    <tr>
        <td class="k">Designation</td>
        <td class="v">{{ $processing->designation ?: '-' }}</td>
        <td class="k">Department</td>
        <td class="v">{{ $processing->department ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Pay Period</td>
        <td class="v">{{ $processing->periodLabel() }}</td>
        <td class="k">Payment Mode</td>
        <td class="v">
            {{ $processing->payment_mode ?: '-' }}
        </td>
    </tr>
</table>

{{-- Attendance breakdown --}}
<h2 class="section">Attendance Summary</h2>
<table class="grid avoid-break att-summary">
    <thead>
        <tr>
            <th class="center">Days</th>
            <th class="center">100%</th>
            <th class="center">75%</th>
            <th class="center">50%</th>
            <th class="center">25%</th>
            <th class="center">0%</th>
            <th class="center">Payable</th>
            <th class="center">Overtime</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="center">{{ $processing->total_days_in_month }}</td>
            <td class="center">{{ (int) $processing->days_100 }}</td>
            <td class="center">{{ (int) $processing->days_75 }}</td>
            <td class="center">{{ (int) $processing->days_50 }}</td>
            <td class="center">{{ (int) $processing->days_25 }}</td>
            <td class="center">{{ (int) $processing->days_0 }}</td>
            <td class="center"><strong>{{ number_format($processing->total_payable_days, 2) }}</strong></td>
            <td class="center">{{ number_format($processing->overtime_hours, 2) }} hrs</td>
        </tr>
    </tbody>
</table>

{{-- Earnings and deductions side by side so the page fills evenly --}}
<table class="avoid-break" style="margin-top:14px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:9px;">
            <h2 class="section" style="margin-top:0;">Earnings</h2>
            <table class="grid">
                <thead><tr><th>Component</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach($earnings as [$label, $detail, $amount, $show])
                    <tr>
                        <td>
                            {{ $label }}
                            @if($detail)<div class="muted" style="font-size:7.5pt;">{{ $detail }}</div>@endif
                        </td>
                        <td class="num">{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach
                    <tr class="total">
                        <td>Gross Earnings</td>
                        <td class="num">{{ number_format($grossEarnings, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>

        <td style="width:50%; vertical-align:top; padding-left:9px;">
            <h2 class="section" style="margin-top:0;">Deductions</h2>
            <table class="grid">
                <thead><tr><th>Component</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse($deductionLines as $line)
                    <tr>
                        <td>
                            {{ $line->label }}
                            <div class="muted" style="font-size:7.5pt;">{{ $line->deduction_type }}</div>
                        </td>
                        <td class="num">{{ number_format($line->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="muted">No standard deductions</td><td class="num">0.00</td></tr>
                @endforelse

                @foreach($advanceLines as $line)
                    <tr>
                        <td>
                            Advance Recovery
                            <div class="muted" style="font-size:7.5pt;">{{ $line->deduction_type }}{{ $line->pending_amount > 0 ? ' · ₹'.number_format($line->pending_amount, 2).' pending' : ' · cleared' }}</div>
                        </td>
                        <td class="num">{{ number_format($line->amount, 2) }}</td>
                    </tr>
                @endforeach

                    <tr class="total">
                        <td>Total Deductions</td>
                        <td class="num">{{ number_format($totalDeductions, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

{{-- Net pay --}}
<div class="panel avoid-break">
    <table>
        <tr>
            <td style="width:58%;">
                <div class="label">Net Payable Salary</div>
                <div class="value">₹{{ number_format($processing->net_salary, 2) }}</div>
                <div class="words">{{ \App\Support\NumberToWords::convert($processing->net_salary) }}</div>
            </td>
            <td style="width:42%; vertical-align:top;">
                <table>
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Gross Earnings</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;">₹{{ number_format($grossEarnings, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Total Deductions</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;">− ₹{{ number_format($totalDeductions, 2) }}</td>
                    </tr>
                    @if($processing->pending_advance_amount > 0)
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Advance carried forward</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;">₹{{ number_format($processing->pending_advance_amount, 2) }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</div>

@if($processing->pending_advance_amount > 0)
    <div class="muted" style="font-size:7.5pt; margin-top:6px;">
        An advance balance of ₹{{ number_format($processing->pending_advance_amount, 2) }} remains and has been carried forward to the next payroll month.
    </div>
@endif

{{-- Payment routing - the detail an employee actually needs to reconcile --}}
<h2 class="section">Payment Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Method</td>
        <td class="v">{{ $processing->payment_mode ?: '-' }}</td>
        <td class="k">Status</td>
        <td class="v">{{ $processing->payment_status }}</td>
    </tr>
    @if($showBank)
    <tr>
        <td class="k">Bank</td>
        <td class="v">{{ $employee->bank_name ?: '-' }}</td>
        <td class="k">Branch</td>
        <td class="v">{{ $employee->branch_name ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Account</td>
        <td class="v">
            @php $acc = (string) $employee->account_number; @endphp
            {{ strlen($acc) > 4 ? str_repeat('•', max(0, strlen($acc) - 4)).substr($acc, -4) : $acc }}
        </td>
        <td class="k">IFSC</td>
        <td class="v">{{ $employee->ifsc_code ?: '-' }}</td>
    </tr>
    @endif
    <tr>
        <td class="k">Monthly CTC</td>
        <td class="v">₹{{ number_format($processing->monthly_salary, 2) }}</td>
        <td class="k">Working Hours</td>
        <td class="v">{{ rtrim(rtrim(number_format($processing->daily_working_hours, 2), '0'), '.') }} hrs / day</td>
    </tr>
</table>

{{-- Signature --}}
<div class="sign-area">
    <table>
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="muted" style="font-size:7.5pt;">
                    Processed by {{ optional($processing->processor)->name ?? 'System' }}
                    on {{ $processing->processed_at->format('d M Y, H:i') }}
                </div>
                <div class="muted" style="font-size:7pt; margin-top:2px;">
                    This is a computer-generated salary slip and does not require a physical signature.
                </div>
            </td>
            <td style="width:50%; text-align:right; vertical-align:bottom;">
                @if($company->signature_image_path && file_exists(public_path('storage/'.$company->signature_image_path)))
                    <img src="{{ public_path('storage/'.$company->signature_image_path) }}" style="max-height:38px;">
                @endif
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>{{ $company->authorized_person_name ?: 'Authorised Signatory' }}</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ $company->authorized_designation }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
