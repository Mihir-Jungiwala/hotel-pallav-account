@php $b = $target ?? null; @endphp
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
                <option value="{{ $employee->id }}" @selected(($b->employee_id ?? null) === $employee->id)>{{ $employee->name }} ({{ $employee->employee_code }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Date *</label>
        <input type="datetime-local" name="entry_date" class="form-control"
               max="{{ now()->format('Y-m-d\TH:i') }}"
               value="{{ old('entry_date', optional($b->entry_date ?? null)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}" required>
    </div>
    <div class="col-md-6"><label class="form-label">Type *</label>
        <select name="type" class="form-select" required>
            @foreach(\App\Support\Masters::valuesOr('bonus_type', ['Bonus', 'Incentive']) as $type)
                <option value="{{ $type }}" @selected(old('type', $b->type ?? 'Bonus') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Amount *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $b->amount ?? '') }}" required></div>
    <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $b->remarks ?? '') }}</textarea></div>
</div>
