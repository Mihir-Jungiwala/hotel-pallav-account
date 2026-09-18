@php $s = $target ?? null; @endphp

<div class="form-wizard" data-wizard>

    <div class="form-step" data-step="Employee">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Employee *</label>
                @if($s)
                    <input class="form-control" value="{{ optional($s->employee)->name }} ({{ optional($s->employee)->employee_code }})" disabled>
                    <input type="hidden" name="employee_id" value="{{ $s->employee_id }}">
                @else
                    <select name="employee_id" class="form-select" required>
                        <option value="">Select an employee&hellip;</option>
                        @foreach($activeEmployees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_code }})</option>
                        @endforeach
                    </select>
                    <div class="form-text">Only active employees can be exited.</div>
                @endif
            </div>
            <div class="col-md-5">
                <label class="form-label">Exit Type *</label>
                <select name="separation_type" class="form-select" required>
                    @foreach(\App\Models\EmployeeSeparation::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('separation_type', $s->separation_type ?? 'Resignation') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Resignation / Notice Date *</label>
                <input type="date" name="resignation_date" class="form-control"
                       value="{{ old('resignation_date', optional($s->resignation_date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Working Day *</label>
                <input type="date" name="last_working_date" class="form-control"
                       value="{{ old('last_working_date', optional($s->last_working_date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required>
            </div>
        </div>
    </div>

    <div class="form-step" data-step="Reason">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Reason *</label>
                <textarea name="reason" class="form-control" rows="4" required
                          placeholder="Why is the employee leaving?">{{ old('reason', $s->reason ?? '') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label">HR Remarks <span class="wz-optional">optional</span></label>
                <textarea name="remarks" class="form-control" rows="3"
                          placeholder="Handover notes, dues cleared, rehire eligibility…">{{ old('remarks', $s->remarks ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="form-step" data-step="Documents">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Status *</label>
                <select name="status" class="form-select" required>
                    @foreach(\App\Models\EmployeeSeparation::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', $s->status ?? 'Pending') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <div class="form-text">
                    Marking as <strong>Relieved</strong> deactivates the employee and unlocks the experience letter.
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Acceptance Document <span class="wz-optional">optional</span></label>
                <input type="file" name="document" class="form-control" accept="application/pdf,image/*">
                <div class="form-text">Signed resignation acceptance or relieving letter - PDF or image.</div>

                @if($s && $s->document_path)
                    <a class="d-inline-flex align-items-center gap-1 mt-2" style="font-size:12.5px; color:var(--p700); font-weight:600;"
                       href="{{ \Illuminate\Support\Facades\Storage::url($s->document_path) }}" target="_blank">
                        <i class="bi bi-paperclip"></i> View current attachment
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
