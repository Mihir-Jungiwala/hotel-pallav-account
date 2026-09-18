@php
    $s = $target ?? null;
    $takenKeys = ($statuses ?? collect())->pluck('shortcut_key')->values();
@endphp
<div class="row g-3">
    <div class="col-md-8"><label class="form-label">Status Name *</label><input name="name" class="form-control" value="{{ old('name', $s->name ?? '') }}" required></div>
    <div class="col-md-4"><label class="form-label">Shortcut Key *</label>
        <input name="shortcut_key" class="form-control text-uppercase" maxlength="10"
               value="{{ old('shortcut_key', $s->shortcut_key ?? '') }}" required
               data-unique-among='@json($takenKeys)'
               data-unique-self="{{ $s->shortcut_key ?? '' }}"
               data-unique-message="This shortcut key is already taken">
    </div>
    <div class="col-md-4"><label class="form-label">Colour *</label><input type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', $s->color ?? '#8B5CF6') }}" required></div>
    <div class="col-md-4"><label class="form-label">Attendance % *</label>
        <select name="attendance_percentage" class="form-select" required>
            @foreach(\App\Models\AttendanceStatus::ALLOWED_PERCENTAGES as $percentage)
                <option value="{{ $percentage }}" @selected((int) old('attendance_percentage', $s->attendance_percentage ?? 100) === $percentage)>{{ $percentage }}%</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Status Type *</label>
        <select name="status_type" class="form-select" required>
            @foreach(\App\Support\Masters::valuesOr('attendance_status_type', ['Paid', 'Unpaid']) as $type)
                <option value="{{ $type }}" @selected(old('status_type', $s->status_type ?? 'Paid') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12"><div class="form-text">Shortcut key and colour must both be unique within this company. Attendance percentage can only be 0, 25, 50, 75 or 100.</div></div>
</div>
