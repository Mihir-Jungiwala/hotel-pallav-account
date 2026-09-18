<div class="alert alert-light border" style="background:var(--p50);">
    <div class="d-flex align-items-center gap-2" style="font-size:13px;">
        <i class="bi bi-info-circle" style="color:var(--p600);"></i>
        <span>
            Details below are carried over from <strong>{{ $employee->name }}</strong>'s previous employment.
            Adjust anything that has changed, then save. Their Employee ID and past payroll records are kept.
        </span>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Employee ID</label>
        <input class="form-control" value="{{ $employee->employee_code }}" disabled>
        <div class="form-text">Unchanged, so history stays linked.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">New Joining Date *</label>
        <input type="date" name="joining_date" class="form-control" value="{{ date('Y-m-d') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Designation *</label>
        <input name="designation" class="form-control" value="{{ $employee->designation }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Department <span class="wz-optional">optional</span></label>
        <input name="department" class="form-control" value="{{ $employee->department }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Monthly Salary *</label>
        <input type="number" step="0.01" min="0" name="salary" class="form-control" value="{{ $employee->salary }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Daily Working Hours *</label>
        <input type="number" step="0.5" min="0.5" max="24" name="daily_working_hours" class="form-control" value="{{ $employee->daily_working_hours }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Contact Number <span class="wz-optional">optional</span></label>
        <input name="contact_number" class="form-control" value="{{ $employee->contact_number }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Address <span class="wz-optional">optional</span></label>
        <input name="address" class="form-control" value="{{ $employee->address }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">Payment Mode *</label>
        <select name="payment_mode" class="form-select payment-mode" required>
            @foreach(\App\Support\Masters::valuesOr('salary_payment_mode', ['Cash', 'Bank']) as $mode)
                <option value="{{ $mode }}" @selected($employee->payment_mode === $mode)>{{ $mode }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 bank-details"><hr class="mt-2"><div class="fw-bold" style="color:var(--p700);">Bank Details</div></div>
    <div class="col-md-6 bank-details">
        <label class="form-label">Bank Name *</label>
        <input name="bank_name" class="form-control" value="{{ $employee->bank_name }}" data-require-when-bank>
    </div>
    <div class="col-md-6 bank-details">
        <label class="form-label">Account Holder Name *</label>
        <input name="account_holder_name" class="form-control" value="{{ $employee->account_holder_name }}" data-require-when-bank>
    </div>
    <div class="col-md-4 bank-details">
        <label class="form-label">Account Number *</label>
        <input name="account_number" class="form-control" value="{{ $employee->account_number }}" data-require-when-bank>
    </div>
    <div class="col-md-4 bank-details">
        <label class="form-label">IFSC Code *</label>
        <input name="ifsc_code" class="form-control text-uppercase" value="{{ $employee->ifsc_code }}" data-require-when-bank>
    </div>
    <div class="col-md-4 bank-details">
        <label class="form-label">Branch <span class="wz-optional">optional</span></label>
        <input name="branch_name" class="form-control" value="{{ $employee->branch_name }}">
    </div>
</div>
