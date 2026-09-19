<div class="card">
    <div class="card-header">Salary Update Management</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Employee ID</th><th>Name</th><th>Designation</th><th>Department</th><th>Current Salary</th><th>Last Updated</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($employees as $row)
                <tr>
                    <td>{{ $row->employee_code }}</td>
                    <td class="fw-semibold">{{ $row->name }}</td>
                    <td>{{ $row->designation }}</td>
                    <td>{{ $row->department }}</td>
                    <td>₹{{ number_format($row->salary, 2) }}</td>
                    <td>
                        {{ $row->updated_at?->format('d-m-Y H:i') }}
                        @if($row->update_histories_count)
                            <span class="badge-p px-2 py-1 rounded ms-1">{{ $row->update_histories_count }} change(s)</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#updateSalary{{ $row->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No employees yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modals live outside the table - a <form> is not valid content inside a
     <tbody>, and the browser corrects that in ways that break its own JS. --}}
@foreach($employees as $row)
    <div class="modal fade" id="updateSalary{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.salary-update.update', $row) }}" class="salary-update-form" data-bank-scope>@csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Update {{ $row->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-light border" style="background:var(--p50);">
                    <div class="row small">
                        <div class="col-6"><span class="text-muted">Current User:</span> <strong>{{ auth()->user()->name }}</strong></div>
                        <div class="col-6"><span class="text-muted">Current Date &amp; Time:</span> <strong>{{ now()->format('d-m-Y H:i') }}</strong></div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Effective Date *</label><input type="date" name="effective_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                    <div class="col-md-6"><label class="form-label">Salary *</label><input type="number" step="0.01" name="salary" class="form-control" value="{{ $row->salary }}" required></div>
                    <div class="col-md-6"><label class="form-label">Designation</label><input name="designation" class="form-control" value="{{ $row->designation }}"></div>
                    <div class="col-md-6"><label class="form-label">Department</label><input name="department" class="form-control" value="{{ $row->department }}"></div>
                    <div class="col-md-6"><label class="form-label">Working Hours *</label><input type="number" step="0.5" name="daily_working_hours" class="form-control" value="{{ $row->daily_working_hours }}" required></div>
                    <div class="col-md-6"><label class="form-label">Salary Payment Type *</label>
                        <select name="payment_mode" class="form-select payment-mode" required>
                            @foreach(\App\Support\Masters::valuesOr('salary_payment_mode', ['Cash', 'Bank']) as $mode)
                                <option value="{{ $mode }}" @selected($row->payment_mode === $mode)>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 bank-details"><hr><div class="fw-bold" style="color:var(--p700);">Bank Details</div></div>
                    <div class="col-md-6 bank-details"><label class="form-label">Bank Name</label><input name="bank_name" class="form-control" value="{{ $row->bank_name }}"></div>
                    <div class="col-md-6 bank-details"><label class="form-label">Account Holder Name</label><input name="account_holder_name" class="form-control" value="{{ $row->account_holder_name }}"></div>
                    <div class="col-md-4 bank-details"><label class="form-label">Account Number</label><input name="account_number" class="form-control" value="{{ $row->account_number }}"></div>
                    <div class="col-md-4 bank-details"><label class="form-label">IFSC Code</label><input name="ifsc_code" class="form-control" value="{{ $row->ifsc_code }}"></div>
                    <div class="col-md-4 bank-details"><label class="form-label">Branch Name</label><input name="branch_name" class="form-control" value="{{ $row->branch_name }}"></div>
                </div>
                <div class="form-text mt-2">Only fields you actually change are recorded in the update history. Previous records are never overwritten.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-p me-auto view-history" data-url="{{ route('payroll.salary-update.history', $row) }}" data-name="{{ $row->name }}"><i class="bi bi-clock-history"></i> Update History</button>
                <button class="btn btn-p">Save Update</button>
            </div>
        </form>
    </div></div></div>
@endforeach

<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="historyTitle">Update History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="historyBody">Loading&hellip;</div>
</div></div></div>

@push('scripts')
<script>
const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
document.querySelectorAll('.view-history').forEach(function(button){
    button.addEventListener('click', async function(){
        document.getElementById('historyTitle').textContent = 'Update History - ' + button.dataset.name;
        document.getElementById('historyBody').innerHTML = 'Loading…';
        const owner = button.closest('.modal');
        if (owner) {
            owner.addEventListener('hidden.bs.modal', () => historyModal.show(), { once: true });
            bootstrap.Modal.getInstance(owner).hide();
        } else {
            historyModal.show();
        }
        const response = await fetch(button.dataset.url);
        document.getElementById('historyBody').innerHTML = await response.text();
    });
});
</script>
@endpush
