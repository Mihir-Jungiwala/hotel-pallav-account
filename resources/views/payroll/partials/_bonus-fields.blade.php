@php $b = $target ?? null; @endphp

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-person"></i> Who and when</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="bon_emp_{{ $b->id ?? 'new' }}">Employee<span class="req">*</span></label>
            <select name="employee_id" id="bon_emp_{{ $b->id ?? 'new' }}" class="form-select" required>
                <option value="">Choose an employee&hellip;</option>
                @foreach($activeEmployees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) old('employee_id', $b->employee_id ?? 0) === $employee->id)>
                        {{ $employee->name }} ({{ $employee->employee_code }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="bon_date_{{ $b->id ?? 'new' }}">Date<span class="req">*</span></label>
            <input type="datetime-local" name="entry_date" id="bon_date_{{ $b->id ?? 'new' }}" class="form-control"
                   max="{{ now()->format('Y-m-d\TH:i') }}"
                   value="{{ old('entry_date', optional($b->entry_date ?? null)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}" required>
            <div class="form-text">The month this date falls in is the salary it is added to.</div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-gift"></i> What is being paid</div>
        <div class="fs-hint">This is paid on top of salary, listed as its own line on the salary slip rather than folded into basic pay.</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="bon_type_{{ $b->id ?? 'new' }}">Type<span class="req">*</span></label>
            <select name="type" id="bon_type_{{ $b->id ?? 'new' }}" class="form-select" required>
                @foreach(['Bonus', 'Incentive'] as $type)
                    <option value="{{ $type }}" @selected(old('type', $b->type ?? 'Bonus') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="bon_amt_{{ $b->id ?? 'new' }}">Amount<span class="req">*</span></label>
            <input type="number" step="0.01" min="0.01" name="amount" id="bon_amt_{{ $b->id ?? 'new' }}"
                   class="form-control" value="{{ old('amount', $b->amount ?? '') }}" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="bon_rem_{{ $b->id ?? 'new' }}">Reason<span class="opt">optional</span></label>
            <textarea name="remarks" id="bon_rem_{{ $b->id ?? 'new' }}" class="form-control" rows="2"
                      placeholder="Why this is being awarded">{{ old('remarks', $b->remarks ?? '') }}</textarea>
        </div>
    </div>
</div>
