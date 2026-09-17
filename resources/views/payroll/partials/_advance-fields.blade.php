@php $a = $target ?? null; @endphp
<div class="alert alert-light border" style="background:var(--p50);">
    <div class="row small">
        <div class="col-6"><span class="text-muted">Current User:</span> <strong>{{ auth()->user()->name }}</strong></div>
        <div class="col-6"><span class="text-muted">Current Date &amp; Time:</span> <strong>{{ now()->format('d-m-Y H:i') }}</strong></div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Employee *</label>
        <select name="employee_id" class="form-select" required>
            <option value="">Select&hellip;</option>
            @foreach($activeEmployees as $employee)
                <option value="{{ $employee->id }}" @selected(($a->employee_id ?? null) === $employee->id)>{{ $employee->name }} ({{ $employee->employee_code }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Advance Date *</label>
        <input type="datetime-local" name="advance_date" class="form-control"
               max="{{ now()->format('Y-m-d\TH:i') }}"
               value="{{ old('advance_date', optional($a->advance_date ?? null)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}" required>
    </div>
    <div class="col-md-4"><label class="form-label">Advance Amount *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control advance-amount" value="{{ old('amount', $a->amount ?? '') }}" required></div>
    <div class="col-md-4"><label class="form-label">Deduction Type *</label>
        <select name="deduction_type" class="form-select advance-type" required>
            @foreach(['One Time', 'Monthly'] as $type)
                <option value="{{ $type }}" @selected(old('deduction_type', $a->deduction_type ?? 'One Time') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Deduction Amount</label><input type="number" step="0.01" name="deduction_amount" class="form-control advance-instalment" value="{{ old('deduction_amount', $a->deduction_amount ?? '') }}">
        <div class="form-text">Per salary cycle. Ignored for One Time.</div>
    </div>
    <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $a->remarks ?? '') }}</textarea></div>
</div>
