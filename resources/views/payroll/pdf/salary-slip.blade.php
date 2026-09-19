@php
    $brandName = $processing->company_name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state, $company->pincode])->filter()->implode(', '))
        . ($company->mobile_number ? '  ·  '.$company->mobile_number : '');
    $docType = 'Salary Slip';
    $docSub = $processing->periodLabel();

    $deductionLines = $processing->lines->where('category', 'Deduction');
    $advanceLines   = $processing->lines->where('category', 'Advance');
    $earningLines   = $processing->lines->whereIn('category', ['Bonus', 'Incentive']);

    // Only components that actually carry a value - no empty filler rows
    $earnings = collect([
        ['Attendance Salary', number_format($processing->total_payable_days, 2).' payable days × ₹'.number_format($processing->daily_salary, 2).' per day', $processing->attendance_salary, true],
        ['Overtime', number_format($processing->overtime_hours, 2).' hrs × ₹'.number_format($processing->hourly_rate, 2).' per hour', $processing->overtime_amount, $processing->overtime_amount > 0],
        ['Bonus', $earningLines->where('category', 'Bonus')->pluck('label')->implode(', '), $processing->bonus_amount, $processing->bonus_amount > 0],
        ['Incentive', $earningLines->where('category', 'Incentive')->pluck('label')->implode(', '), $processing->incentive_amount, $processing->incentive_amount > 0],
    ])->filter(fn ($row) => $row[3]);

    $employee = $processing->employee;
    $showBank = $processing->payment_mode === 'Bank' && $employee && $employee->account_number;

    $grossEarnings = $processing->attendance_salary + $processing->overtime_amount + $processing->bonus_amount + $processing->incentive_amount;
    $totalDeductions = $processing->deduction_amount + $processing->advance_deduction;

    $statusChip = match ($processing->payment_status) {
        'Paid' => 'ok',
        'Failed' => 'bad',
        'Pending', 'On Hold', 'Partially Paid', 'Processing' => 'warn',
        default => '',
    };
    $empInitials = collect(explode(' ', trim($processing->employee_name)))->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

@extends('payroll.pdf._base')

@section('content')

{{-- Who this slip belongs to, set large, with the month and whether it has
     been paid right beside - the three things a reader checks first --}}
<table class="avoid-break" style="margin-bottom:12px;">
    <tr>
        <td style="width:52px; vertical-align:middle;">
            {{-- Initials centred in a circle: a one-cell table, since a
                 line-height trick leaves the letters sitting low --}}
            <div style="width:42px; height:42px; border-radius:21px; background:#EFE9FE;">
                <table style="width:42px; height:42px;"><tr>
                    <td style="width:42px; height:42px; text-align:center; vertical-align:middle;
                               color:#5B21B6; font-weight:bold; font-size:12pt;">{{ $empInitials }}</td>
                </tr></table>
            </div>
        </td>
        <td style="vertical-align:middle; padding-left:6px;">
            <div style="font-size:14pt; font-weight:bold; color:#23193F; letter-spacing:-0.3pt; line-height:1.2;">{{ $processing->employee_name }}</div>
            <div class="muted" style="font-size:8.2pt;">
                {{ $processing->designation ?: 'No designation' }}{{ $processing->department ? ' · '.$processing->department : '' }}
                &middot; ID {{ $processing->employee_code }}
            </div>
        </td>
        <td style="width:34%; text-align:right; vertical-align:middle;">
            <div class="muted" style="font-size:6.8pt; text-transform:uppercase; letter-spacing:0.6pt; font-weight:bold;">Pay period</div>
            <div style="font-size:11pt; font-weight:bold; color:#5B21B6;">{{ $processing->periodLabel() }}</div>
            <div style="margin-top:3px;"><span class="chip {{ $statusChip }}">{{ $processing->payment_status }}</span></div>
        </td>
    </tr>
</table>

{{-- Attendance the pay was worked out from, as tiles --}}
<h2 class="section"><span class="dot"></span>Attendance</h2>
<table class="stats avoid-break">
    <tr>
        <td>
            <div class="s-label">Month</div>
            <div class="s-value">{{ $processing->total_days_in_month }}</div>
        </td>
        <td>
            <div class="s-label">Full</div>
            <div class="s-value">{{ (int) $processing->days_100 }}</div>
        </td>
        <td>
            <div class="s-label">Part</div>
            <div class="s-value">{{ (int) $processing->days_75 + (int) $processing->days_50 + (int) $processing->days_25 }}</div>
        </td>
        <td>
            <div class="s-label">Unpaid</div>
            <div class="s-value">{{ (int) $processing->days_0 }}</div>
        </td>
        <td>
            <div class="s-label">Payable</div>
            <div class="s-value accent">{{ number_format($processing->total_payable_days, 2) }}</div>
        </td>
        <td>
            <div class="s-label">Overtime</div>
            <div class="s-value">{{ number_format($processing->overtime_hours, 1) }}<span style="font-size:8pt;"> hrs</span></div>
        </td>
    </tr>
</table>
@if((int) $processing->days_75 + (int) $processing->days_50 + (int) $processing->days_25 > 0)
    <div class="muted" style="font-size:7pt; margin:-6px 0 4px 6px;">
        Part days: {{ (int) $processing->days_75 }} at 75%, {{ (int) $processing->days_50 }} at 50%, {{ (int) $processing->days_25 }} at 25%.
    </div>
@endif

{{-- Earnings and deductions each run the full width, one component per row,
     so the amount column stays in one place all the way down the page. --}}
<h2 class="section"><span class="dot"></span>Earnings</h2>
<table class="grid ledger avoid-break">
    <thead>
        <tr>
            <th style="width:36%;">Component</th>
            <th style="width:44%;">Basis</th>
            <th class="num" style="width:20%;">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
    @foreach($earnings as [$label, $detail, $amount, $show])
        <tr>
            <td class="lbl">{{ $label }}</td>
            <td class="muted">{{ $detail ?: '-' }}</td>
            <td class="num">{{ number_format($amount, 2) }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="2">Gross Earnings (A)</td>
            <td class="num">{{ number_format($grossEarnings, 2) }}</td>
        </tr>
    </tbody>
</table>

<h2 class="section"><span class="dot"></span>Deductions</h2>
<table class="grid ledger deduct avoid-break">
    <thead>
        <tr>
            <th style="width:36%;">Component</th>
            <th style="width:44%;">Basis</th>
            <th class="num" style="width:20%;">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
    @forelse($deductionLines as $line)
        <tr>
            <td class="lbl">{{ $line->label }}</td>
            <td class="muted">{{ $line->deduction_type ?: '-' }}</td>
            <td class="num">{{ number_format($line->amount, 2) }}</td>
        </tr>
    @empty
        @if($advanceLines->isEmpty())
            <tr>
                <td class="lbl muted">No deductions this month</td>
                <td class="muted">-</td>
                <td class="num">0.00</td>
            </tr>
        @endif
    @endforelse

    @foreach($advanceLines as $line)
        <tr>
            <td class="lbl">Advance Recovery</td>
            <td class="muted">
                {{ $line->deduction_type }}{{ $line->pending_amount > 0
                    ? ' · ₹'.number_format($line->pending_amount, 2).' still pending'
                    : ' · fully cleared' }}
            </td>
            <td class="num">{{ number_format($line->amount, 2) }}</td>
        </tr>
    @endforeach

        <tr class="total">
            <td colspan="2">Total Deductions (B)</td>
            <td class="num">{{ number_format($totalDeductions, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- Net pay: the figure the whole slip exists to state --}}
<div class="panel avoid-break">
    <table>
        <tr>
            <td style="width:55%; vertical-align:middle;">
                <div class="label">Net Payable Salary</div>
                <div class="value">₹{{ number_format($processing->net_salary, 2) }}</div>
                <div class="words">{{ \App\Support\NumberToWords::convert($processing->net_salary) }}</div>
            </td>
            <td style="width:45%; vertical-align:middle; padding-left:14px;">
                <div class="side">
                    <table class="net-lines">
                        <tr>
                            <td>Gross Earnings (A)</td>
                            <td class="num">₹{{ number_format($grossEarnings, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Total Deductions (B)</td>
                            <td class="num">− ₹{{ number_format($totalDeductions, 2) }}</td>
                        </tr>
                        <tr class="rule">
                            <td><strong>Net (A − B)</strong></td>
                            <td class="num"><strong>₹{{ number_format($processing->net_salary, 2) }}</strong></td>
                        </tr>
                        @if($processing->pending_advance_amount > 0)
                        <tr>
                            <td class="muted">Advance carried forward</td>
                            <td class="num muted">₹{{ number_format($processing->pending_advance_amount, 2) }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>
</div>

@if($processing->pending_advance_amount > 0)
    <div class="muted" style="font-size:7.3pt; margin-top:6px;">
        An advance balance of ₹{{ number_format($processing->pending_advance_amount, 2) }} remains
        and has been carried forward to the next payroll month.
    </div>
@endif

{{-- Payment routing - what the employee needs to match against their bank --}}
<h2 class="section"><span class="dot"></span>Payment Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Method</td>
        <td class="v">{{ $processing->payment_mode ?: '-' }}</td>
        <td class="k">Paid on</td>
        <td class="v">{{ $processing->paid_at?->format('d M Y') ?: 'Not yet paid' }}</td>
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
    @if($processing->payment_reference)
    <tr>
        <td class="k">Reference</td>
        <td class="v" colspan="3">{{ $processing->payment_reference }}</td>
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
            <td style="width:52%; vertical-align:bottom;">
                <div class="muted" style="font-size:7.3pt;">
                    Processed by {{ optional($processing->processor)->name ?? 'System' }}
                    on {{ $processing->processed_at->format('d M Y, H:i') }}
                </div>
                <div class="muted" style="font-size:6.9pt; margin-top:2px;">
                    This is a computer-generated salary slip and does not require a physical signature.
                </div>
            </td>
            <td style="width:48%; text-align:right; vertical-align:bottom;">
                @if($company->signature_image_path && file_exists(public_path('storage/'.$company->signature_image_path)))
                    <img src="{{ public_path('storage/'.$company->signature_image_path) }}" style="max-height:38px;"><br>
                @endif
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>{{ $company->authorized_person_name ?: 'Authorised Signatory' }}</strong><br>
                    <span class="muted" style="font-size:7.3pt;">{{ $company->authorized_designation }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
