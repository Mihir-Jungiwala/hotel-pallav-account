@php
    $open = $separations->whereNull('rejoined_at');
    $relieved = $open->where('status', 'Relieved')->count();
    $pending = $open->where('status', '!=', 'Relieved')->count();
@endphp

@unless($hasExperienceTemplate)
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-info-circle"></i>
        <span>No experience letter template yet &mdash; set one up in <strong>Experience Letter</strong> to issue letters from here.</span>
    </div>
@endunless

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-field" style="max-width:320px;flex:1 1 240px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search name, reason or type&hellip;"
               data-filter-target="#separationTable" aria-label="Search separations">
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="pill pill-locked">{{ $relieved }} relieved</span>
        @if($pending)<span class="pill pill-unlocked">{{ $pending }} in notice</span>@endif
        <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addSeparation">
            <i class="bi bi-box-arrow-right"></i> Record Exit
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">Resignations &amp; Exits</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="separationTable" data-paginate="10" data-pager="#separationPager">
            <thead><tr>
                <th></th><th>Employee</th><th>Type</th><th>Resigned</th><th>Last Working Day</th>
                <th>Reason</th><th>Status</th><th class="text-end">Action</th>
            </tr></thead>
            <tbody>
            @forelse($separations as $row)
                @php $emp = $row->employee; @endphp
                <tr data-row="{{ optional($emp)->name }} {{ optional($emp)->employee_code }} {{ $row->separation_type }} {{ $row->reason }}">
                    <td>
                        <div class="avatar" title="{{ optional($emp)->name }}">
                            {{ collect(explode(' ', optional($emp)->name ?? '?'))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                        </div>
                    </td>
                    <td style="min-width:170px;">
                        <div class="fw-semibold text-nowrap">{{ optional($emp)->name }}</div>
                        <div class="text-muted" style="font-size:11px;">{{ optional($emp)->employee_code }} &middot; {{ optional($emp)->designation }}</div>
                    </td>
                    <td class="text-nowrap">{{ $row->separation_type }}</td>
                    <td class="text-nowrap">{{ $row->resignation_date->format('d M Y') }}</td>
                    <td class="text-nowrap">{{ $row->last_working_date->format('d M Y') }}</td>
                    <td style="max-width:220px;">
                        <span class="text-muted" style="font-size:12.5px;">{{ \Illuminate\Support\Str::limit($row->reason, 60) }}</span>
                        @if($row->document_path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($row->document_path) }}" target="_blank"
                               class="d-inline-flex align-items-center gap-1" style="font-size:11.5px; color:var(--p700); font-weight:600;">
                                <i class="bi bi-paperclip"></i> Acceptance
                            </a>
                        @endif
                    </td>
                    <td>
                        @if($row->hasRejoined())
                            <span class="pill pill-live"><span class="dot"></span> Rejoined {{ $row->rejoined_at->format('M Y') }}</span>
                        @elseif($row->status === 'Relieved')
                            <span class="pill pill-locked">Relieved</span>
                        @else
                            <span class="pill pill-unlocked">{{ $row->status }}</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.separation.view', $row) }}" target="_blank" title="Exit summary"><i class="bi bi-eye"></i></a>

                        @if($row->canIssueExperienceLetter() && $hasExperienceTemplate)
                            <a class="btn-icon" href="{{ route('payroll.separation.experience-letter', $row) }}" target="_blank" title="Experience letter">
                                <i class="bi bi-file-earmark-check"></i>
                            </a>
                        @endif

                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editSeparation{{ $row->id }}" title="Edit"><i class="bi bi-pencil"></i></button>

                        @if(! $row->hasRejoined())
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#rejoin{{ $row->id }}" title="Rejoin employee"><i class="bi bi-arrow-counterclockwise"></i></button>
                        @endif

                        <form method="POST" action="{{ route('payroll.separation.destroy', $row) }}" class="d-inline"
                              onsubmit="return confirm('Remove this exit record? The employee becomes active again.')">
                            @csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editSeparation{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.separation.update', $row) }}" enctype="multipart/form-data">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit Exit &mdash; {{ optional($emp)->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            @include('payroll.partials._separation-fields', ['target' => $row, 'activeEmployees' => $activeEmployees])
                        </div>
                        <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                    </form>
                </div></div></div>

                @if(! $row->hasRejoined())
                <div class="modal fade" id="rejoin{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.separation.rejoin', $row) }}" data-bank-scope>@csrf
                        <div class="modal-header"><h5 class="modal-title">Rejoin &mdash; {{ optional($emp)->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            @include('payroll.partials._rejoin-fields', ['employee' => $emp])
                        </div>
                        <div class="modal-footer"><button class="btn btn-p">Rejoin Employee</button></div>
                    </form>
                </div></div></div>
                @endif
            @empty
                <tr data-empty>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-box-arrow-right"></i></div>
                            <div class="es-title">No exits recorded</div>
                            <div class="es-text">Record a resignation to capture the reason, attach the signed acceptance, and issue an experience letter once the person is relieved.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="8"><div class="empty-state"><div class="es-title">No matching records</div></div></td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'separationPager'])
</div>

<div class="modal fade" id="addSeparation" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.separation.store') }}" enctype="multipart/form-data">@csrf
        <div class="modal-header"><h5 class="modal-title">Record Employee Exit</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @include('payroll.partials._separation-fields', ['target' => null, 'activeEmployees' => $activeEmployees])
        </div>
        <div class="modal-footer"><button class="btn btn-p">Save Exit</button></div>
    </form>
</div></div></div>
