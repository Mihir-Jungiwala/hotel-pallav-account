@php use App\Models\OptionSet; $s = $set ?? null; @endphp

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">List name *</label>
        <input name="name" class="form-control" value="{{ old('name', $s->name ?? '') }}" maxlength="60" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Icon <span class="wz-optional">optional</span></label>
        <input name="icon" class="form-control" value="{{ old('icon', $s->icon ?? '') }}" maxlength="40" placeholder="bi-list-ul">
        <div class="form-text">Any Bootstrap icon name.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Description <span class="wz-optional">optional</span></label>
        <input name="description" class="form-control" value="{{ old('description', $s->description ?? '') }}" maxlength="160">
    </div>

    <div class="col-12">
        <label class="form-label">How forms show it *</label>
        <div class="role-options">
            @foreach(OptionSet::INPUTS as $value => $label)
                <label class="role-option role-admin">
                    <input type="radio" name="input" value="{{ $value }}" @checked(old('input', $s->input ?? 'select') === $value) required>
                    <span class="ro-body">
                        <span class="ro-head">
                            <i class="bi {{ ['select' => 'bi-caret-down-square', 'radio' => 'bi-ui-radios', 'checkbox' => 'bi-ui-checks'][$value] }}"></i>
                            {{ $label }}
                        </span>
                        <span class="ro-desc">
                            {{ ['select' => 'Best for longer lists.', 'radio' => 'Best for two or three choices.', 'checkbox' => 'Lets more than one be picked.'][$value] }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
</div>
