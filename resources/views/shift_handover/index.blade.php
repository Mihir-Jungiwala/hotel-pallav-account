@extends('layouts.app')
@section('title', 'Handover')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/shift-handover.css') }}?v=19">
@endpush

@section('content')
@php
    use App\Models\ShiftHandover;
    use App\Support\RichText;

    $money = fn ($n) => '₹'.number_format((float) $n, 2);
    $dayLabel = function ($date) {
        if (! $date) return 'No date';
        if ($date->isToday()) return 'Today';
        if ($date->isYesterday()) return 'Yesterday';
        return $date->format('l, d M Y');
    };
    $latest = $stats['latest'];
@endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">{{ now()->format('l, d F Y') }}</div>
        <h2 class="pms-title">Handovers</h2>
        <p class="pms-sub">Cash counted and points left for the next shift.</p>
    </div>
    <div class="sh-head-actions">
        {{-- Same search box as Payroll; the server searches every Handover, not just this page --}}
        <form method="GET" action="{{ route('shift-handover.index') }}" class="pay-toolbar" data-no-busy="true" data-self-service>
            @if(request('per'))<input type="hidden" name="per" value="{{ (int) request('per') }}">@endif
            <div class="search-field">
                <i class="bi bi-search"></i>
                <input type="search" name="q" class="form-control" value="{{ $search }}" autocomplete="off" data-handover-search
                       placeholder="Search shift, name or note&hellip;" aria-label="Search Handovers">
                @if($search !== '')
                    <a class="search-clear" href="{{ route('shift-handover.index', array_filter(['per' => request('per')])) }}" title="Clear search" aria-label="Clear search"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>
        <button type="button" class="btn btn-p sh-new" data-bs-toggle="modal" data-bs-target="#handoverModal" data-write-only>
            <i class="bi bi-plus-lg"></i> New Handover
        </button>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon month"><i class="bi bi-arrow-left-right"></i></span>
            <span class="kpi-label">Handovers today</span>
        </div>
        <div class="kpi-value">{{ $stats['today'] }}</div>
        <div class="kpi-foot">
            <span class="kpi-chip">{{ $stats['today'] ? 'Recorded' : 'None yet today' }}</span>
        </div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon in"><i class="bi bi-cash-stack"></i></span>
            <span class="kpi-label">Cash counted today</span>
        </div>
        <div class="kpi-value">{{ $money($stats['todayCash']) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">Across today's Handovers</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon due"><i class="bi bi-calendar3"></i></span>
            <span class="kpi-label">This month</span>
        </div>
        <div class="kpi-value">{{ $stats['month'] }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ now()->format('F Y') }}</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon out"><i class="bi bi-clock-history"></i></span>
            <span class="kpi-label">Last Handover</span>
        </div>
        @if($latest)
            <div class="kpi-value sh-kpi-small">{{ $latest->shift }} &middot; {{ substr((string) $latest->time, 0, 5) }}</div>
            <div class="kpi-foot"><span class="kpi-chip">{{ $dayLabel($latest->date) }} by {{ $latest->user?->displayName() ?? $latest->full_name }}</span></div>
        @else
            <div class="kpi-value sh-kpi-small">None yet</div>
            <div class="kpi-foot"><span class="kpi-chip">Record the first one</span></div>
        @endif
    </div>
</div>

@if($records->isEmpty())
    <div class="card reveal">
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-arrow-left-right"></i></div>
            <div class="es-title">{{ $search !== '' ? 'No Handover matches that search' : 'No Handovers yet' }}</div>
            <div class="es-text">{{ $search !== '' ? 'Try a different word, a date like 19-09-2026, an entry number like #5, or an amount.' : 'When a shift ends, count the cash and leave points for whoever takes over. They will all be listed here.' }}</div>
            @if($search !== '')
                <a class="btn btn-outline-p mt-3" href="{{ route('shift-handover.index') }}">Clear search</a>
            @else
                <button type="button" class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#handoverModal" data-write-only><i class="bi bi-plus-lg"></i> New Handover</button>
            @endif
        </div>
    </div>
@else
    @foreach($records->groupBy(fn ($r) => optional($r->date)->toDateString()) as $day => $group)
        <div class="sh-day reveal">
            <div class="sh-day-head">
                <span>{{ $dayLabel($group->first()->date) }}</span>
                <span class="sh-day-meta">{{ $group->count() }} {{ Str::plural('Handover', $group->count()) }}</span>
            </div>

            @foreach($group as $r)
                @php
                    $notes = $r->noteList();
                    $instructions = $r->instructionList();
                    $who = $r->user?->displayName() ?? $r->full_name;
                @endphp
                <details class="sh-card">
                    <summary>
                        <div class="sh-when">
                            <span class="sh-time">{{ substr((string) $r->time, 0, 5) }}</span>
                            <span class="sh-shift">{{ $r->shift }}</span>
                        </div>

                        <div class="sh-cols">
                            <div class="sh-col">
                                <span class="sh-lab">Entry</span>
                                <span class="sh-val">#{{ $r->entryNumber() }}</span>
                            </div>
                            <div class="sh-col">
                                <span class="sh-lab">Handed over by</span>
                                <span class="sh-val" title="{{ $who }}">{{ $who }}</span>
                            </div>
                            <div class="sh-col notes">
                                <span class="sh-lab">Notes <b>{{ count($notes) }}</b></span>
                                @if($notes)
                                    <span class="sh-val text">{{ Str::limit(RichText::plain($notes[0]), 70) }}</span>
                                @else
                                    <span class="sh-val none">None</span>
                                @endif
                            </div>
                            <div class="sh-col instructions {{ $instructions ? 'warn' : '' }}">
                                <span class="sh-lab">@if($instructions)<i class="bi bi-exclamation-triangle-fill"></i> @endif Instructions <b>{{ count($instructions) }}</b></span>
                                @if($instructions)
                                    <span class="sh-val">{{ Str::limit($instructions[0], 70) }}</span>
                                @else
                                    <span class="sh-val none">None</span>
                                @endif
                            </div>
                        </div>

                        <div class="sh-cash">
                            <small>Total cash</small>
                            <strong>{{ $money($r->total) }}</strong>
                        </div>

                        <i class="bi bi-chevron-down sh-caret" aria-hidden="true"></i>
                    </summary>

                    <div class="sh-body">
                        <div class="sh-body-grid">
                            <div>
                                <h6 class="sh-h"><i class="bi bi-cash-coin"></i> Cash count</h6>
                                <table class="sh-count-table">
                                    <tbody>
                                    @foreach(ShiftHandover::DENOMINATIONS as $denom => $value)
                                        @continue(! (int) $r->{"{$denom}_count"})
                                        <tr>
                                            <td>{{ ShiftHandover::denominationLabel($denom) }}</td>
                                            <td class="text-muted">{{ $denom === 'coins' ? '' : '× '.(int) $r->{"{$denom}_count"} }}</td>
                                            <td class="text-end">{{ $money($r->{"{$denom}_total"}) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr><td colspan="2">Total</td><td class="text-end">{{ $money($r->total) }}</td></tr>
                                    </tfoot>
                                </table>
                                <div class="sh-words">{{ $r->total_in_words }}</div>
                            </div>

                            <div>
                                <h6 class="sh-h"><i class="bi bi-sticky"></i> Notes for the next shift <span class="sh-count">{{ count($notes) }}</span></h6>
                                @if($notes)
                                    <ul class="sh-points">
                                        @foreach($notes as $note)<li>{!! $note !!}</li>@endforeach
                                    </ul>
                                @else
                                    <p class="text-muted small mb-0">No notes.</p>
                                @endif

                                @if($instructions)
                                    <h6 class="sh-h mt-3"><i class="bi bi-exclamation-circle"></i> Special instructions <span class="sh-count warn">{{ count($instructions) }}</span></h6>
                                    <ul class="sh-points warn">
                                        @foreach($instructions as $point)<li>{{ $point }}</li>@endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        <div class="sh-actions">
                            <span class="text-muted small me-auto">Recorded {{ $r->created_at?->format('d M Y, H:i') }}</span>
                            <a class="btn btn-sm btn-outline-p" href="{{ route('shift-handover.view', $r) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                            <button type="button" class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#handoverModal"
                                    data-handover-id="{{ $r->id }}" data-open-record title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>
                            <form method="POST" action="{{ route('shift-handover.destroy', $r) }}" class="d-inline" data-confirm-title="Delete Handover #{{ $r->entryNumber() }}?" data-confirm="This cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
    @endforeach

    <div class="card sh-pager-card">@include('partials._server-pager', ['paginator' => $records])</div>
@endif

@include('shift_handover._modal')

<script type="application/json" id="handoverData">{!! json_encode(['blank' => $blank, 'records' => $forms, 'reopen' => $reopen, 'shiftClock' => $shiftClock], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

@push('scripts')
<script src="{{ asset('assets/shift-handover.js') }}?v=6"></script>
@endpush
@endsection
