@php
    use App\Models\ShiftHandover;

    $brandName = config('app.name', 'Hotel Pallav');
    $brandMeta = 'Hotel Management Suite';
    $docType = 'Handover';
    $docSub = 'Entry #'.$record->entryNumber();
    $notes = $record->noteList();
    $instructions = $record->instructionList();
    $money = fn ($n) => 'Rs '.number_format((float) $n, 2);
    $handedBy = $record->user?->displayName() ?? $record->full_name;
    $counted = collect(ShiftHandover::DENOMINATIONS)->filter(fn ($v, $denom) => (int) $record->{"{$denom}_count"} > 0);
@endphp

@extends('payroll.pdf._base')

@section('content')
<style>
    ol.points { margin: 0; padding-left: 18px; }
    ol.points li { padding: 4px 0; border-bottom: 0.5pt solid #ECE6FB; }
    ol.points li:last-child { border-bottom: none; }
    ol.points a { color: #5B21B6; }

    /* Special instructions stand out: amber, bordered, rounded */
    .instructions { margin-top: 14px; border: 1.2pt solid #F59E0B; border-left: 4pt solid #D97706; border-radius: 9px; background: #FFF7E6; padding: 9px 14px 6px; }
    .instructions .i-title { font-size: 8pt; font-weight: bold; color: #92400E; text-transform: uppercase; letter-spacing: 0.8pt; margin-bottom: 3px; }
    .instructions ol { margin: 0; padding-left: 18px; }
    .instructions li { padding: 4px 0; border-bottom: 0.5pt solid #FBE3B5; font-weight: bold; color: #78350F; }
    .instructions li:last-child { border-bottom: none; }
</style>

<h2 class="section" style="margin-top:0;"><span class="dot"></span>Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Shift</td><td class="v">{{ $record->shift }}</td>
        <td class="k">Date</td><td class="v">{{ optional($record->date)->format('d-m-Y') }} {{ substr((string) $record->time, 0, 5) }}</td>
    </tr>
    <tr>
        <td class="k">Handed Over By</td><td class="v">{{ $handedBy }}</td>
        <td class="k">Entry No.</td><td class="v">#{{ $record->entryNumber() }}</td>
    </tr>
</table>

@if($notes)
    <h2 class="section"><span class="dot"></span>Notes for the Next Shift</h2>
    <ol class="points">
        @foreach($notes as $note)<li>{!! $note !!}</li>@endforeach
    </ol>
@endif

@if($instructions)
    <div class="instructions avoid-break">
        <div class="i-title">! Special Instructions</div>
        <ol>
            @foreach($instructions as $point)<li>{{ $point }}</li>@endforeach
        </ol>
    </div>
@endif

<h2 class="section"><span class="dot"></span>Cash Count</h2>
<table class="grid avoid-break">
    <thead><tr><th>Denomination</th><th class="num">Count</th><th class="num">Amount</th></tr></thead>
    <tbody>
    @forelse($counted as $denom => $value)
        <tr>
            <td>{{ $denom === 'coins' ? 'Coins' : 'Rs '.$value.' notes' }}</td>
            <td class="num">{{ $denom === 'coins' ? '-' : (int) $record->{"{$denom}_count"} }}</td>
            <td class="num">{{ $money($record->{"{$denom}_total"}) }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="muted">No cash counted.</td></tr>
    @endforelse
        <tr class="total"><td colspan="2">Total</td><td class="num">{{ $money($record->total) }}</td></tr>
    </tbody>
</table>

<div class="panel avoid-break">
    <div class="label">Total Cash Handed Over</div>
    <div class="value">{{ $money($record->total) }}</div>
    <div class="words">{{ $record->total_in_words }}</div>
</div>

<div class="sign-area" style="margin-top:58px;">
    <table>
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="sign-line" style="min-width:180px;">
                    <strong>Handed over by</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ $handedBy }}</span>
                </div>
            </td>
            <td style="width:50%; text-align:right; vertical-align:bottom;">
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>Received by</strong><br>
                    <span class="muted" style="font-size:7.5pt;">Name and signature</span>
                </div>
            </td>
        </tr>
    </table>
</div>
@endsection
