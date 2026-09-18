@if($deductionOptions->isEmpty())
    <div class="alert alert-warning">No deductions are configured yet. Add them in <strong>Deduction Master</strong> first if you want to assign deductions to employees.</div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-field" style="max-width:320px;flex:1 1 240px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employees&hellip;"
               data-filter-target="#staffTable" data-filter-count="#staffCount" aria-label="Search employees">
    </div>
    <div class="d-flex gap-2">
        @if($rejoinable->isNotEmpty())
            <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#rejoinEmployee">
                <i class="bi bi-arrow-counterclockwise"></i> Rejoin Employee
            </button>
        @endif
        <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addEmployee"><i class="bi bi-person-plus"></i> Add Employee</button>
    </div>
</div>

@if($rejoinable->isNotEmpty())
<div class="modal fade" id="rejoinEmployee" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Rejoin a Former Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label">Former employee *</label>
        <select class="form-select mb-3" id="rejoinPicker">
            <option value="">Choose who is rejoining&hellip;</option>
            @foreach($rejoinable as $sep)
                <option value="{{ $sep->id }}">
                    {{ optional($sep->employee)->name }} ({{ optional($sep->employee)->employee_code }}) - left {{ $sep->last_working_date->format('M Y') }}
                </option>
            @endforeach
        </select>

        @foreach($rejoinable as $sep)
            @continue(! $sep->employee)
            <form method="POST" action="{{ route('payroll.separation.rejoin', $sep) }}" class="rejoin-form" data-sep="{{ $sep->id }}" data-bank-scope hidden>
                @csrf
                @include('payroll.partials._rejoin-fields', ['employee' => $sep->employee])
                <div class="d-flex justify-content-end mt-3 pt-3 border-top">
                    <button class="btn btn-p"><i class="bi bi-check-lg"></i> Rejoin {{ $sep->employee->name }}</button>
                </div>
            </form>
        @endforeach
    </div>
</div></div></div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Staff Management - {{ $company->name }}</span>
        <span class="text-muted fw-normal" style="font-size:12.5px;"><span id="staffCount">{{ $employees->count() }}</span> shown</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="staffTable" data-paginate="10" data-pager="#staffPager">
            <thead><tr><th></th><th>Employee ID</th><th>Name</th><th>Designation</th><th class="money">Salary</th><th>Mode</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($employees as $row)
                <tr class="reveal" data-row="{{ $row->name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->department }}">
                    <td>
                        <div class="avatar" title="{{ $row->name }}">
                            @if($row->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($row->photo_path) }}" alt="{{ $row->name }}">
                            @else
                                {{ collect(explode(' ', $row->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                            @endif
                        </div>
                    </td>
                    <td>{{ $row->employee_code }}</td>
                    <td class="fw-semibold">{{ $row->name }}</td>
                    <td>{{ $row->designation }}</td>
                    <td class="money">₹{{ number_format($row->salary, 2) }}</td>
                    <td>{{ $row->payment_mode }}</td>
                    <td>
                        <form method="POST" action="{{ route('payroll.employee.toggle-active', $row) }}" data-status-toggle>@csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.joining-letter.generate', $row) }}" target="_blank" title="Joining letter"><i class="bi bi-file-earmark-text"></i></a>
                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editEmployee{{ $row->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.employee.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this employee?')">@csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade employee-modal" id="editEmployee{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.employee.update', $row) }}" enctype="multipart/form-data">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">{{ $row->name }} <span class="text-muted fw-normal fs-6">&middot; {{ $row->employee_code }}</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('payroll.partials._employee-fields', ['target' => $row])</div>
                        <div class="modal-footer">
                            <a class="btn btn-outline-p me-auto" href="{{ route('payroll.employee.view', $row) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Employee PDF</a>
                            <button class="btn btn-p">Save Changes</button>
                        </div>
                    </form>
                </div></div></div>
            @empty
                <tr data-empty>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-people"></i></div>
                            <div class="es-title">No employees yet</div>
                            <div class="es-text">Add your first employee to start recording attendance, advances and salary for {{ $company->name }}.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching employees</div>
                        <div class="es-text">Try a different name, employee ID, designation or department.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'staffPager'])
</div>

<div class="modal fade employee-modal" id="addEmployee" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.employee.store') }}" enctype="multipart/form-data">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('payroll.partials._employee-fields', ['target' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Add Employee</button></div>
    </form>
</div></div></div>

@push('scripts')
<script>
(function(){
    const picker = document.getElementById('rejoinPicker');
    if (!picker) return;
    picker.addEventListener('change', function(){
        document.querySelectorAll('.rejoin-form').forEach(function(form){
            form.hidden = form.dataset.sep !== picker.value;
        });
    });
})();

document.querySelectorAll('.employee-modal').forEach(function(modal){
    const rows = modal.querySelector('.deduction-rows');
    const template = modal.querySelector('.deduction-template');
    const addButton = modal.querySelector('.add-deduction-row');
    if (!rows || !template || !addButton) return;

    let index = rows.querySelectorAll('.deduction-row').length;

    addButton.addEventListener('click', function(){
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', 'new' + index));
        index++;
    });

    rows.addEventListener('click', function(event){
        const remove = event.target.closest('.remove-deduction-row');
        if (remove) remove.closest('.deduction-row').remove();
    });

    rows.addEventListener('change', function(event){
        const select = event.target.closest('select[name*="[deduction_id]"]');
        if (!select) return;
        const amount = select.selectedOptions[0]?.dataset.amount;
        const amountInput = select.closest('.deduction-row').querySelector('input[name*="[amount]"]');
        if (amount && amountInput && !amountInput.value) amountInput.value = amount;
    });
});
</script>
@endpush
