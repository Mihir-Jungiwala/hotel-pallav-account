<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addDeduction"><i class="bi bi-plus-lg"></i> Add Deduction</button>
</div>

<div class="card">
    <div class="card-header">Deduction Master</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Deduction Name</th><th>Amount</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($deductions as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->name }}</td>
                    <td>₹{{ number_format($row->amount, 2) }}</td>
                    <td>
                        <form method="POST" action="{{ route('payroll.deduction.toggle-active', $row) }}">@csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</button>
                        </form>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editDeduction{{ $row->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.deduction.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this deduction?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No deductions configured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modals live outside the table - a <form> is not valid content inside a
     <tbody>, and the browser corrects that in ways that break its own JS. --}}
@foreach($deductions as $row)
    <div class="modal fade" id="editDeduction{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.deduction.update', $row) }}">@csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit {{ $row->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Deduction Name *</label><input name="name" class="form-control" value="{{ $row->name }}" required></div>
                <div class="mb-3"><label class="form-label">Deduction Amount *</label><input type="number" step="0.01" name="amount" class="form-control" value="{{ $row->amount }}" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
        </form>
    </div></div></div>
@endforeach

<div class="modal fade" id="addDeduction" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.deduction.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Deduction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Deduction Name *</label><input name="name" class="form-control" placeholder="Food, Uniform, Accommodation&hellip;" required></div>
            <div class="mb-3"><label class="form-label">Deduction Amount *</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-p">Add Deduction</button></div>
    </form>
</div></div></div>
