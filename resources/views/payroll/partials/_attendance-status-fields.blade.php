@php
    $s = $target ?? null;
    $takenKeys = ($statuses ?? collect())->pluck('shortcut_key')->values();
    $uid = $s->id ?? 'new';
@endphp

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-tag"></i> The mark</div>
        <div class="fs-hint">The shortcut key is what gets typed into the attendance sheet, so keep it to a single memorable letter where you can. Key and colour must both be unique within this company.</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="as_name_{{ $uid }}">Status name<span class="req">*</span></label>
            <input name="name" id="as_name_{{ $uid }}" class="form-control"
                   value="{{ old('name', $s->name ?? '') }}" placeholder="Present, Absent, Half Day&hellip;" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="as_key_{{ $uid }}">Shortcut key<span class="req">*</span></label>
            <input name="shortcut_key" id="as_key_{{ $uid }}" class="form-control text-uppercase" maxlength="10"
                   value="{{ old('shortcut_key', $s->shortcut_key ?? '') }}" required
                   data-unique-among='@json($takenKeys)'
                   data-unique-self="{{ $s->shortcut_key ?? '' }}"
                   data-unique-message="This shortcut key is already taken">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="as_color_{{ $uid }}">Colour<span class="req">*</span></label>
            <input type="color" name="color" id="as_color_{{ $uid }}" class="form-control form-control-color w-100"
                   value="{{ old('color', $s->color ?? '#8B5CF6') }}" required>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-calculator"></i> What it pays</div>
        <div class="fs-hint">Attendance percentage is what salary is calculated from: a day marked at 50% pays half a day.</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="as_pct_{{ $uid }}">Counts as<span class="req">*</span></label>
            <select name="attendance_percentage" id="as_pct_{{ $uid }}" class="form-select" required>
                @foreach(\App\Models\AttendanceStatus::ALLOWED_PERCENTAGES as $percentage)
                    <option value="{{ $percentage }}" @selected((int) old('attendance_percentage', $s->attendance_percentage ?? 100) === $percentage)>
                        {{ $percentage }}% of a working day
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="as_type_{{ $uid }}">Status type<span class="req">*</span></label>
            <select name="status_type" id="as_type_{{ $uid }}" class="form-select" required>
                @foreach(['Paid', 'Unpaid'] as $type)
                    <option value="{{ $type }}" @selected(old('status_type', $s->status_type ?? 'Paid') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
