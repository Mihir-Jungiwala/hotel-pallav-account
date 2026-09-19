@extends('payroll.layout', [
    'title' => 'Attendance Status',
    'subtitle' => 'The marks you can enter on the attendance sheet. Each one carries a shortcut key for fast entry and an attendance percentage, which is what salary is actually calculated from.',
])

@section('page-actions')
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addStatus" data-write-only>
        <i class="bi bi-plus-lg"></i> Add Status
    </button>
@endsection

@section('page')

@if($statuses->isNotEmpty())
    <div class="card mb-3">
        <div class="legend-row d-flex flex-wrap gap-2 align-items-center" style="border-bottom:none;border-radius:var(--r-lg);">
            <span class="pms-eyebrow me-1">On the sheet</span>
            @foreach($statuses->where('is_active', true) as $s)
                <span class="legend-chip" title="{{ $s->name }} - {{ $s->attendance_percentage }}% of a day, {{ $s->status_type }}">
                    <span class="lc-key" style="background:{{ $s->color }};">{{ strtoupper($s->shortcut_key) }}</span>
                    {{ $s->name }} &middot; {{ $s->attendance_percentage }}%
                </span>
            @endforeach
        </div>
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="statusTable" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th data-sort="text">Status</th>
                    <th data-sort="text">Shortcut</th>
                    <th>Colour</th>
                    <th data-sort="num">Counts as</th>
                    <th data-sort="text">Type</th>
                    <th data-sort="text">Active</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($statuses as $row)
                <tr data-row="{{ $row->name }} {{ $row->shortcut_key }} {{ $row->status_type }}">
                    <td class="sno"></td>
                    <td class="cell-main">{{ $row->name }}</td>
                    <td><kbd>{{ strtoupper($row->shortcut_key) }}</kbd></td>
                    <td>
                        <span class="d-inline-flex align-items-center gap-2">
                            <span style="display:inline-block;width:20px;height:20px;border-radius:6px;background:{{ $row->color }};border:1px solid var(--line2);"></span>
                            <span class="text-muted" style="font-size:11.5px;">{{ strtoupper($row->color) }}</span>
                        </span>
                    </td>
                    <td data-sort-value="{{ $row->attendance_percentage }}">
                        {{ $row->attendance_percentage }}%
                        <span class="cell-sub">of a working day</span>
                    </td>
                    <td>{{ $row->status_type }}</td>
                    <td>
                        <form method="POST" action="{{ route('payroll.attendance-status.toggle-active', $row) }}" data-status-toggle>@csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $row->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editStatus{{ $row->id }}"
                                data-open-record title="Open record"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.attendance-status.destroy', $row) }}" class="d-inline"
                              data-confirm="Delete the status &quot;{{ $row->name }}&quot;?">
                            @csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-palette2"></i></div>
                            <div class="es-title">No attendance statuses yet</div>
                            <div class="es-text">Attendance cannot be recorded until there is at least one status to mark a day with - Present, Absent, Half Day and so on.</div>
                            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addStatus" data-write-only>
                                <i class="bi bi-plus-lg"></i> Add the first status
                            </button>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($statuses as $row)
    <div class="modal fade pay-form-modal" id="editStatus{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.attendance-status.update', $row) }}">@csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">Attendance status</div>
                        <h5 class="modal-title">{{ $row->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('payroll.partials._attendance-status-fields', ['target' => $row, 'statuses' => $statuses])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal" id="addStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.attendance-status.store') }}">@csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New &middot; {{ $company->name }}</div>
                    <h5 class="modal-title">Add Attendance Status</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('payroll.partials._attendance-status-fields', ['target' => null, 'statuses' => $statuses])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Add Status</button>
            </div>
        </form>
    </div></div>
</div>

@endsection
