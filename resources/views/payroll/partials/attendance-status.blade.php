<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addStatus"><i class="bi bi-plus-lg"></i> Add Status</button>
</div>

<div class="card">
    <div class="card-header">Attendance Status Master</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Status Name</th><th>Shortcut</th><th>Colour</th><th>Attendance %</th><th>Type</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($statuses as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->name }}</td>
                    <td><span class="badge-p px-2 py-1 rounded">{{ $row->shortcut_key }}</span></td>
                    <td><span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:{{ $row->color }};border:1px solid var(--line2);"></span> <span class="small text-muted">{{ $row->color }}</span></td>
                    <td>{{ $row->attendance_percentage }}%</td>
                    <td>{{ $row->status_type }}</td>
                    <td>
                        <form method="POST" action="{{ route('payroll.attendance-status.toggle-active', $row) }}">@csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</button>
                        </form>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editStatus{{ $row->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.attendance-status.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this status?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editStatus{{ $row->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.attendance-status.update', $row) }}">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit {{ $row->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('payroll.partials._attendance-status-fields', ['target' => $row])</div>
                        <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                    </form>
                </div></div></div>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No attendance statuses configured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addStatus" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.attendance-status.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Attendance Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('payroll.partials._attendance-status-fields', ['target' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Add Status</button></div>
    </form>
</div></div></div>
