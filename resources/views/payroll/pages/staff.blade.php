@extends('payroll.layout', [
    'title' => 'Staff Management',
    'subtitle' => 'Everyone on '.$company->name."'s payroll. Open a row to see the full record; the table itself stays short on purpose.",
])

@section('page-actions')
    @if($rejoinable->isNotEmpty())
        <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#rejoinEmployee">
            <i class="bi bi-arrow-counterclockwise"></i> Rejoin
        </button>
    @endif
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addEmployee" data-write-only>
        <i class="bi bi-person-plus"></i> Add Employee
    </button>
@endsection

@section('toolbar')
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search name, ID, mobile, email&hellip;"
               data-filter-target="#staffTable" data-filter-count="#staffCount" aria-label="Search employees">
    </div>
    <div class="toolbar-end">
        <span class="text-muted" style="font-size:12.5px;"><span id="staffCount">{{ $employees->count() }}</span> of {{ $employees->count() }} shown</span>
    </div>
@endsection

@section('page')
@include('payroll.partials._phone-data')

@if($deductionOptions->isEmpty())
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle mt-1"></i>
        <span>
            No deductions are configured for {{ $company->name }} yet.
            <a href="{{ route('payroll.deduction.index') }}" class="fw-semibold">Set them up first</a>
            if you want to assign deductions to an employee.
        </span>
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="staffTable" data-paginate="10" data-pager="#staffPager" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th style="width:46px;"></th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="text">Designation</th>
                    <th data-sort="date">Joined</th>
                    <th data-sort="num" class="money">Salary</th>
                    <th data-sort="text">Mode</th>
                    <th data-sort="text">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($employees as $row)
                <tr data-row="{{ $row->name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->department }} {{ $row->contact_number }} {{ $row->email }} {{ $row->payment_mode }} {{ $row->is_active ? 'active' : 'inactive' }}">
                    <td class="sno"></td>
                    <td>
                        <div class="avatar" title="{{ $row->name }}">
                            @if($row->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($row->photo_path) }}" alt="">
                            @else
                                {{ collect(explode(' ', $row->name))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <span class="cell-main">{{ $row->name }}</span>
                        <span class="cell-sub">{{ $row->employee_code }}</span>
                    </td>
                    <td>
                        {{ $row->designation }}
                        @if($row->department)<span class="cell-sub">{{ $row->department }}</span>@endif
                    </td>
                    <td data-sort-value="{{ $row->joining_date?->format('Ymd') }}">
                        {{ $row->joining_date?->format('d M Y') ?: '-' }}
                    </td>
                    <td class="money" data-sort-value="{{ $row->salary }}">₹{{ number_format($row->salary, 2) }}</td>
                    <td>{{ $row->payment_mode }}</td>
                    <td>
                        <form method="POST" action="{{ route('payroll.employee.toggle-active', $row) }}" data-status-toggle>@csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $row->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.joining-letter.generate', $row) }}" target="_blank"
                           title="Joining letter"><i class="bi bi-file-earmark-text"></i></a>
                        <form method="POST" action="{{ route('payroll.employee.offer-letter.send', $row) }}" class="d-inline"
                              data-confirm-title="Email the appointment letter?" data-confirm="It is sent to {{ $row->email ?: 'the email on record' }}."
                              data-confirm-label="Send letter" data-confirm-icon="bi-envelope-paper">
                            @csrf
                            <button class="btn-icon" title="Email appointment letter" @disabled(! $row->email)><i class="bi bi-envelope-paper"></i></button>
                        </form>
                        {{-- Share this person's details with an address from Payroll Master --}}
                        <div class="dropdown d-inline share-menu">
                            <button type="button" class="btn-icon" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'
                                    aria-expanded="false" title="Share details by email"><i class="bi bi-send"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <div class="sm-head">Email details to</div>
                                @forelse($shareRecipients as $recipient)
                                    <form method="POST" action="{{ route('payroll.employee.share', $row) }}"
                                          data-confirm-title="Share {{ $row->name }}'s details?"
                                          data-confirm="The full record is emailed to {{ $recipient->label }} ({{ $recipient->value }})."
                                          data-confirm-label="Send" data-confirm-icon="bi-send">
                                        @csrf
                                        <input type="hidden" name="recipient" value="{{ $recipient->id }}">
                                        <button class="sm-item">
                                            <span class="sm-name">{{ $recipient->label }}</span>
                                            <span class="sm-mail">{{ $recipient->value }}</span>
                                        </button>
                                    </form>
                                @empty
                                    <div class="sm-empty">No recipients yet.@if(auth()->user()->isSuperAdmin()) <a href="{{ route('payroll.master.index', ['list' => 'share_emails']) }}">Add some in Payroll Master</a>.@else Ask the SuperAdmin to add some in Payroll Master.@endif</div>
                                @endforelse
                            </div>
                        </div>
                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editEmployee{{ $row->id }}"
                                data-open-record title="Open record"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.employee.destroy', $row) }}" class="d-inline"
                              data-confirm="Delete {{ $row->name }} ({{ $row->employee_code }})? This cannot be undone.">
                            @csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-people"></i></div>
                            <div class="es-title">No staff yet</div>
                            <div class="es-text">Add the first employee to start recording attendance, advances and salary for {{ $company->name }}.</div>
                            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addEmployee" data-write-only>
                                <i class="bi bi-person-plus"></i> Add the first employee
                            </button>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching staff</div>
                        <div class="es-text">Try a different name, employee ID, designation or department.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'staffPager'])
</div>

@if($rejoinable->isNotEmpty())
<div class="modal fade pay-form-modal" id="rejoinEmployee" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <div>
                <div class="pms-eyebrow">Staff</div>
                <h5 class="modal-title">Rejoin a Former Employee</h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label" for="rejoinPicker">Former employee<span class="req">*</span></label>
                <select class="form-select" id="rejoinPicker">
                    <option value="">Choose who is rejoining&hellip;</option>
                    @foreach($rejoinable as $sep)
                        <option value="{{ $sep->id }}">
                            {{ optional($sep->employee)->name }} ({{ optional($sep->employee)->employee_code }})
                            - left {{ $sep->last_working_date->format('M Y') }}
                        </option>
                    @endforeach
                </select>
                <div class="fs-hint">Their previous record is reused, so history and documents stay attached.</div>
            </div>

            @foreach($rejoinable as $sep)
                @continue(! $sep->employee)
                <form method="POST" action="{{ route('payroll.separation.rejoin', $sep) }}"
                      class="rejoin-form" data-sep="{{ $sep->id }}" hidden>
                    @csrf
                    @include('payroll.partials._rejoin-fields', ['employee' => $sep->employee])
                    <div class="d-flex justify-content-end mt-3 pt-3 border-top">
                        <button class="btn btn-p"><i class="bi bi-check-lg"></i> Rejoin {{ $sep->employee->name }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div></div>
</div>
@endif

{{-- Modals live outside the table: a <form> is not valid content inside a
     <tbody>, and browsers repair that by moving it out of the DOM tree the
     JS expects, which left every edit form blank. --}}
@foreach($employees as $row)
    <div class="modal fade pay-form-modal employee-modal" id="editEmployee{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.employee.update', $row) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">{{ $row->employee_code }}</div>
                        <h5 class="modal-title">{{ $row->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._employee-fields', ['target' => $row])</div>
                <div class="modal-footer">
                    <a class="btn btn-outline-p me-auto" href="{{ route('payroll.employee.view', $row) }}" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Employee PDF
                    </a>
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal employee-modal" id="addEmployee" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.employee.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New &middot; {{ $company->name }}</div>
                    <h5 class="modal-title">Add Employee</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">@include('payroll.partials._employee-fields', ['target' => null])</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Add Employee</button>
            </div>
        </form>
    </div></div>
</div>

@push('scripts')
<script>
(function(){
    const picker = document.getElementById('rejoinPicker');
    if (picker) {
        picker.addEventListener('change', function(){
            document.querySelectorAll('.rejoin-form').forEach(function(form){
                form.hidden = form.dataset.sep !== picker.value;
            });
        });
    }

    document.querySelectorAll('.employee-modal').forEach(function(modal){
        const rows = modal.querySelector('.deduction-rows');
        const template = modal.querySelector('.deduction-template');
        const addButton = modal.querySelector('.add-deduction-row');
        if (!rows || !template || !addButton) return;

        let index = rows.querySelectorAll('.deduction-row').length;

        addButton.addEventListener('click', function(){
            rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', 'new' + index));
            index++;
            window.PMS?.enhance(rows);
        });

        rows.addEventListener('click', function(event){
            const remove = event.target.closest('.remove-deduction-row');
            if (remove) remove.closest('.deduction-row').remove();
        });

        // Picking a deduction fills in its standard amount, so the common case
        // is one click rather than looking the figure up
        rows.addEventListener('change', function(event){
            const select = event.target.closest('select[name*="[deduction_id]"]');
            if (!select) return;
            const amount = select.selectedOptions[0]?.dataset.amount;
            const amountInput = select.closest('.deduction-row').querySelector('input[name*="[amount]"]');
            if (amount && amountInput && !amountInput.value) amountInput.value = amount;
        });
    });
})();
</script>
@endpush

@endsection
