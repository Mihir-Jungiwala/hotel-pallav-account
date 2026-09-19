@php
    $open = $separations->whereNull('rejoined_at');
    $relieved = $open->where('status', 'Relieved')->count();
    $pending = $open->where('status', '!=', 'Relieved')->count();
    $rejoined = $separations->whereNotNull('rejoined_at')->count();
@endphp

@extends('payroll.layout', [
    'title' => 'Resignation',
    'subtitle' => 'Exits from '.$company->name.', newest first. Record the notice period and reason here, attach the signed acceptance, and issue the experience letter once the person is relieved.',
])

@section('page-actions')
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addSeparation" data-write-only>
        <i class="bi bi-box-arrow-right"></i> Record Exit
    </button>
@endsection

@section('toolbar')
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search name, reason or type&hellip;"
               data-filter-target="#separationTable" data-filter-count="#sepCount" aria-label="Search exits">
    </div>
    <div class="toolbar-end">
        <span class="pill pill-unlocked">{{ $pending }} in notice</span>
        <span class="pill pill-locked">{{ $relieved }} relieved</span>
        @if($rejoined)<span class="pill pill-live"><span class="dot"></span> {{ $rejoined }} rejoined</span>@endif
        <span class="text-muted" style="font-size:12.5px;"><span id="sepCount">{{ $separations->count() }}</span> shown</span>
    </div>
@endsection

@section('page')
@include('payroll.partials._phone-data')

@unless($hasExperienceTemplate)
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-info-circle mt-1"></i>
        <span>
            There is no experience letter template for {{ $company->name }} yet.
            <a href="{{ route('payroll.experience-letter.index') }}" class="fw-semibold">Set one up</a>
            to issue letters from this page.
        </span>
    </div>
@endunless

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="separationTable" data-paginate="10" data-pager="#separationPager" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th style="width:46px;"></th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="text">Type</th>
                    <th data-sort="date">Resigned</th>
                    <th data-sort="date">Last working day</th>
                    <th>Reason</th>
                    <th data-sort="text">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($separations as $row)
                @php $emp = $row->employee; @endphp
                <tr data-row="{{ optional($emp)->name }} {{ optional($emp)->employee_code }} {{ $row->separation_type }} {{ $row->reason }}">
                    <td class="sno"></td>
                    <td>
                        <div class="avatar" title="{{ optional($emp)->name }}">
                            {{ collect(explode(' ', optional($emp)->name ?? '?'))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
                        </div>
                    </td>
                    <td style="min-width:160px;">
                        <span class="cell-main">{{ optional($emp)->name }}</span>
                        <span class="cell-sub">{{ optional($emp)->employee_code }} &middot; {{ optional($emp)->designation }}</span>
                    </td>
                    <td class="text-nowrap">{{ $row->separation_type }}</td>
                    <td class="text-nowrap" data-sort-value="{{ $row->resignation_date->format('Ymd') }}">
                        {{ $row->resignation_date->format('d M Y') }}
                    </td>
                    <td class="text-nowrap" data-sort-value="{{ $row->last_working_date->format('Ymd') }}">
                        {{ $row->last_working_date->format('d M Y') }}
                        <span class="cell-sub">{{ $row->resignation_date->diffInDays($row->last_working_date) }} days notice</span>
                    </td>
                    <td style="max-width:220px;">
                        <span class="text-muted" style="font-size:12.5px;">{{ \Illuminate\Support\Str::limit($row->reason, 56) }}</span>
                        @if($row->document_path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($row->document_path) }}" target="_blank"
                               class="d-inline-flex align-items-center gap-1"
                               style="font-size:11.5px; color:var(--p700); font-weight:600;">
                                <i class="bi bi-paperclip"></i> Acceptance
                            </a>
                        @endif
                    </td>
                    <td data-sort-value="{{ $row->hasRejoined() ? 'Rejoined' : $row->status }}">
                        @if($row->hasRejoined())
                            <span class="pill pill-live"><span class="dot"></span> Rejoined {{ $row->rejoined_at->format('M Y') }}</span>
                        @elseif($row->status === 'Relieved')
                            <span class="pill pill-locked">Relieved</span>
                        @else
                            <span class="pill pill-unlocked">{{ $row->status }}</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($row->canIssueExperienceLetter() && $hasExperienceTemplate)
                            <a class="btn-icon" href="{{ route('payroll.separation.experience-letter', $row) }}" target="_blank"
                               title="Experience letter"><i class="bi bi-file-earmark-check"></i></a>
                        @endif
                        <a class="btn-icon" href="{{ route('payroll.separation.view', $row) }}" target="_blank"
                           title="Exit summary PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editSeparation{{ $row->id }}"
                                data-open-record title="Open record"><i class="bi bi-pencil-square"></i></button>
                        @if(! $row->hasRejoined())
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#rejoin{{ $row->id }}"
                                    title="Rejoin employee"><i class="bi bi-arrow-counterclockwise"></i></button>
                        @endif
                        <form method="POST" action="{{ route('payroll.separation.destroy', $row) }}" class="d-inline"
                              data-confirm="Remove the exit record for {{ optional($emp)->name }}? They become an active employee again.">
                            @csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-box-arrow-right"></i></div>
                            <div class="es-title">No exits recorded</div>
                            <div class="es-text">Record a resignation to capture the notice period and reason, attach the signed acceptance, and issue an experience letter once the person is relieved.</div>
                            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addSeparation" data-write-only>
                                <i class="bi bi-box-arrow-right"></i> Record the first exit
                            </button>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching records</div>
                        <div class="es-text">Try a different name, reason or exit type.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'separationPager'])
</div>

@foreach($separations as $row)
    @php $emp = $row->employee; @endphp
    <div class="modal fade pay-form-modal" id="editSeparation{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.separation.update', $row) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">{{ $row->separation_type }}</div>
                        <h5 class="modal-title">{{ optional($emp)->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('payroll.partials._separation-fields', ['target' => $row, 'activeEmployees' => $activeEmployees])
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-p me-auto" href="{{ route('payroll.separation.view', $row) }}" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Exit Summary
                    </a>
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>

    @if(! $row->hasRejoined())
    <div class="modal fade pay-form-modal" id="rejoin{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.separation.rejoin', $row) }}">@csrf
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">Rejoining</div>
                        <h5 class="modal-title">{{ optional($emp)->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._rejoin-fields', ['employee' => $emp])</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Rejoin Employee</button>
                </div>
            </form>
        </div></div>
    </div>
    @endif
@endforeach

<div class="modal fade pay-form-modal" id="addSeparation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.separation.store') }}" enctype="multipart/form-data">@csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New &middot; {{ $company->name }}</div>
                    <h5 class="modal-title">Record an Exit</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('payroll.partials._separation-fields', ['target' => null, 'activeEmployees' => $activeEmployees])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Save Exit</button>
            </div>
        </form>
    </div></div>
</div>

@endsection
