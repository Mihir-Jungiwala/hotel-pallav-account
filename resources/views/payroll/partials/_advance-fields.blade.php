@php $a = $target ?? null; @endphp

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-person"></i> Who and when</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="adv_emp_{{ $a->id ?? 'new' }}">Employee<span class="req">*</span></label>
            <select name="employee_id" id="adv_emp_{{ $a->id ?? 'new' }}" class="form-select" required>
                <option value="">Choose an employee&hellip;</option>
                @foreach($activeEmployees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) old('employee_id', $a->employee_id ?? 0) === $employee->id)>
                        {{ $employee->name }} ({{ $employee->employee_code }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="adv_date_{{ $a->id ?? 'new' }}">Advance date<span class="req">*</span></label>
            <input type="datetime-local" name="advance_date" id="adv_date_{{ $a->id ?? 'new' }}" class="form-control"
                   max="{{ now()->format('Y-m-d\TH:i') }}"
                   value="{{ old('advance_date', optional($a->advance_date ?? null)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}" required>
            <div class="form-text">Cannot be in the future.</div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-cash-stack"></i> Amount and recovery</div>
        <div class="fs-hint">Salary processing recovers this automatically. A monthly advance is recovered in instalments until it clears; anything left carries into the next month.</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="adv_amt_{{ $a->id ?? 'new' }}">Advance amount<span class="req">*</span></label>
            <input type="number" step="0.01" min="0.01" name="amount" id="adv_amt_{{ $a->id ?? 'new' }}"
                   class="form-control advance-amount" value="{{ old('amount', $a->amount ?? '') }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="adv_type_{{ $a->id ?? 'new' }}">Recovery<span class="req">*</span></label>
            <select name="deduction_type" id="adv_type_{{ $a->id ?? 'new' }}" class="form-select advance-type" required>
                @foreach(['One Time', 'Monthly'] as $type)
                    <option value="{{ $type }}" @selected(old('deduction_type', $a->deduction_type ?? 'One Time') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <div class="form-text" data-show-when="deduction_type=One Time">Taken in full from the next salary.</div>
        </div>

        {{-- Only a Monthly recovery has an instalment, so only then is it asked
             for - and then it is required. --}}
        <div class="col-md-6" data-show-when="deduction_type=Monthly">
            <label class="form-label" for="adv_inst_{{ $a->id ?? 'new' }}">
                Instalment<span class="req">*</span><span class="opt">per month</span>
            </label>
            <input type="number" step="0.01" min="0.01" name="deduction_amount" id="adv_inst_{{ $a->id ?? 'new' }}"
                   class="form-control advance-instalment" value="{{ old('deduction_amount', $a->deduction_amount ?? '') }}" required>
        </div>
        <div class="col-md-6" data-show-when="deduction_type=Monthly">
            <div class="instalment-hint" data-instalment-hint>
                <i class="bi bi-calendar2-check"></i>
                <span>Enter the amount and instalment to see how long this takes to clear.</span>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="adv_rem_{{ $a->id ?? 'new' }}">Remarks<span class="opt">optional</span></label>
            <textarea name="remarks" id="adv_rem_{{ $a->id ?? 'new' }}" class="form-control" rows="2"
                      placeholder="What this advance is for">{{ old('remarks', $a->remarks ?? '') }}</textarea>
        </div>
    </div>
</div>
