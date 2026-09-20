@php
    $brandName = $company?->name ?? 'Hotel Pallav & Pallav Food';
    $brandMeta = $company
        ? trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '))
        : 'Payroll, both companies';
    $docType = 'Payroll Log';
    $docSub = $rows->isEmpty() ? '' : $rows->last()->created_at->format('d M Y').' to '.$rows->first()->created_at->format('d M Y');
@endphp

@extends('payroll.pdf._base')

@section('content')

<style>
    /* One entry = a heading line and its detail, kept together on a page */
    table.entry { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 7px; border: 0.8pt solid #E4DCF8; border-radius: 7px; page-break-inside: avoid; }
    table.entry td { padding: 0; }
    td.e-head { background: #F5F1FE; padding: 6px 10px !important; }
    td.e-tone { width: 4px; padding: 0 !important; }
    .e-summary { font-weight: bold; font-size: 8.6pt; color: #23193F; }
    .e-meta { font-size: 7pt; color: #6B6486; margin-top: 2px; }
    .e-pill { font-size: 6.6pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt; padding: 1px 6px; border-radius: 6px; }
    table.detail { width: 100%; border-collapse: collapse; }
    table.detail th { text-align: left; font-size: 6.6pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #6B6486; padding: 3px 10px; border-bottom: 0.5pt solid #ECE6FB; }
    table.detail td { font-size: 7.6pt; padding: 3px 10px; border-bottom: 0.5pt solid #F1ECFC; vertical-align: top; word-wrap: break-word; }
    table.detail tr:last-child td { border-bottom: none; }
    .was { color: #B91C1C; }
    .now { color: #15803D; font-weight: bold; }
    .day-head { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.7pt; color: #5B21B6; margin: 10px 0 5px; }
</style>

<table class="stats avoid-break" style="margin-bottom:6px;">
    <tr>
        <td><div class="s-label">Entries</div><div class="s-value">{{ number_format($rows->count()) }}</div></td>
        <td><div class="s-label">Emails</div><div class="s-value">{{ $rows->where('action', 'emailed')->count() }}</div></td>
        <td><div class="s-label">Deletions</div><div class="s-value">{{ $rows->where('action', 'deleted')->count() }}</div></td>
        <td><div class="s-label">Problems</div><div class="s-value {{ $rows->where('status', 'failed')->count() ? '' : 'accent' }}">{{ $rows->where('status', 'failed')->count() }}</div></td>
    </tr>
</table>

@if($filters)
    <p class="muted" style="font-size:7.5pt; margin: 2px 0 6px;">
        <strong>Filtered by:</strong> {{ implode('  ·  ', $filters) }}
    </p>
@endif

@if($truncated)
    <p class="muted" style="font-size:7.5pt; margin: 2px 0 6px;">
        Showing the newest {{ number_format($rows->count()) }} entries. Narrow the dates or filters to see the rest.
    </p>
@endif

@php
    $tones = ['created' => '#16A34A', 'updated' => '#6D28D9', 'deleted' => '#DC2626', 'emailed' => '#0EA5E9', 'error' => '#DC2626'];
    $names = \App\Models\PayrollLog::ACTIONS;
@endphp

@forelse($rows->groupBy(fn ($log) => $log->created_at->toDateString()) as $date => $entries)
    <div class="day-head">{{ \Illuminate\Support\Carbon::parse($date)->format('l, j F Y') }} &middot; {{ $entries->count() }}</div>

    @foreach($entries as $log)
        @php $tone = $log->status === 'failed' ? $tones['error'] : ($tones[$log->action] ?? '#6D28D9'); @endphp
        <table class="entry">
            <tr>
                <td class="e-tone" style="background: {{ $tone }};"></td>
                <td>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="e-head">
                                <div class="e-summary">{{ $log->summary }}</div>
                                <div class="e-meta">
                                    <span class="e-pill" style="background:#EFE9FE; color: {{ $tone }};">{{ $log->status === 'failed' ? 'Failed' : ($names[$log->action] ?? $log->action) }}</span>
                                    &nbsp;{{ $log->created_at->format('h:i:s A') }}
                                    &middot; {{ $log->user_name ?: 'System' }}
                                    &middot; {{ $log->entity }}
                                    @if(! $company && $log->company_name) &middot; {{ $log->company_name }} @endif
                                    @if($log->ip_address) &middot; from {{ $log->ip_address }} @endif
                                    &middot; entry #{{ $log->id }}
                                </div>
                            </td>
                        </tr>

                        @if($log->details)
                            <tr>
                                <td>
                                    @if($log->action === 'updated')
                                        <table class="detail">
                                            <thead><tr><th style="width:26%;">Field</th><th style="width:37%;">Was</th><th>Became</th></tr></thead>
                                            <tbody>
                                            @foreach($log->details as $field => $change)
                                                <tr>
                                                    <td><strong>{{ $field }}</strong></td>
                                                    <td class="was">{{ is_array($change) ? (blank($change['was'] ?? null) ? 'empty' : $change['was']) : '-' }}</td>
                                                    <td class="now">{{ is_array($change) ? (blank($change['became'] ?? null) ? 'empty' : $change['became']) : $change }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <table class="detail">
                                            <tbody>
                                            @foreach($log->details as $field => $value)
                                                <tr>
                                                    <td style="width:26%;"><strong>{{ $field }}</strong></td>
                                                    <td style="white-space: pre-wrap;">{{ is_array($value) ? json_encode($value) : $value }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    @endforeach
@empty
    <p class="muted">Nothing was logged for this selection.</p>
@endforelse

@endsection
