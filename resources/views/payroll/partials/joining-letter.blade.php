@php $l = $letter; @endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Joining Letter Template - {{ $company->name }}</span>
        <span class="small fw-normal text-muted">One template per company</span>
    </div>
    <div class="card-body">
        <div class="alert alert-light border" style="background:var(--p50);">
            <div class="fw-semibold mb-1" style="color:var(--p800);">Available Placeholders</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Models\JoiningLetter::PLACEHOLDERS as $placeholder)
                    <button type="button" class="btn btn-sm btn-outline-p placeholder-chip" data-placeholder="{{ $placeholder }}">{{ $placeholder }}</button>
                @endforeach
            </div>
            <div class="form-text mt-2">Click a placeholder to insert it at the cursor. Values are replaced automatically when the letter is generated for an employee.</div>
        </div>

        <form method="POST" action="{{ route('payroll.joining-letter.save') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-12"><div class="fw-bold" style="color:var(--p700);">Company Details (auto-fetched)</div>
                    <div class="small text-muted">{{ $company->name }} &middot; {{ $company->address }} &middot; {{ $company->mobile_number }} &middot; {{ $company->email }}</div>
                </div>

                <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Basic</div></div>
                <div class="col-12"><label class="form-label">Subject</label><input name="subject" class="form-control rte" value="{{ old('subject', $l->subject ?? '') }}"></div>
                <div class="col-12"><label class="form-label">Introduction / Appointment Content</label><textarea name="introduction_content" class="form-control rte" rows="4">{{ old('introduction_content', $l->introduction_content ?? '') }}</textarea></div>
                <div class="col-12"><label class="form-label">Roles &amp; Responsibilities</label><textarea name="roles_responsibilities" class="form-control rte" rows="3">{{ old('roles_responsibilities', $l->roles_responsibilities ?? '{RESPONSIBILITIES}') }}</textarea></div>
                <div class="col-12"><label class="form-label">Terms &amp; Conditions</label><textarea name="terms_conditions" class="form-control rte" rows="4">{{ old('terms_conditions', $l->terms_conditions ?? '') }}</textarea></div>
                <div class="col-12"><label class="form-label">Closing / Welcome Message</label><textarea name="closing_message" class="form-control rte" rows="3">{{ old('closing_message', $l->closing_message ?? '') }}</textarea></div>

                <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Authorized Signatory</div></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="use_company_signatory" value="1" id="useCompanySignatory" @checked(old('use_company_signatory', $l->use_company_signatory ?? true))>
                        <label class="form-check-label" for="useCompanySignatory">Use Company Authorized Signatory</label>
                    </div>
                    <div class="form-text">When on, the name, designation and signature image are taken from Company Setup. Closing text stays editable either way.</div>
                </div>
                <div id="customSignatory" class="row g-3">
                    <div class="col-md-6"><label class="form-label">Authorized Name</label><input name="authorized_name" class="form-control" value="{{ old('authorized_name', $l->authorized_name ?? '') }}"></div>
                    <div class="col-md-6"><label class="form-label">Designation</label><input name="authorized_designation" class="form-control" value="{{ old('authorized_designation', $l->authorized_designation ?? '') }}"></div>
                    <div class="col-md-6"><label class="form-label">Signature Image</label><input type="file" name="signature_image" class="form-control"></div>
                </div>
                <div class="col-12"><label class="form-label">Closing Text</label><textarea name="authorized_closing_text" class="form-control rte" rows="2">{{ old('authorized_closing_text', $l->authorized_closing_text ?? '') }}</textarea></div>

                <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Employee Acceptance</div></div>
                <div class="col-12"><label class="form-label">Acceptance Heading</label><input name="acceptance_heading" class="form-control" value="{{ old('acceptance_heading', $l->acceptance_heading ?? '') }}"></div>
                <div class="col-12"><label class="form-label">Acceptance Content</label><textarea name="acceptance_content" class="form-control rte" rows="3">{{ old('acceptance_content', $l->acceptance_content ?? '') }}</textarea></div>
                <div class="col-12"><label class="form-label">Closing Text</label><textarea name="acceptance_closing_text" class="form-control rte" rows="2">{{ old('acceptance_closing_text', $l->acceptance_closing_text ?? '') }}</textarea></div>

                <div class="col-12">
                    <button class="btn btn-p">{{ $l ? 'Update Template' : 'Add Template' }}</button>
                </div>
            </div>
        </form>

        @if($l)
            <div class="d-flex gap-2 mt-3 pt-3 border-top">
                <form method="POST" action="{{ route('payroll.joining-letter.toggle-active', $l) }}">@csrf
                    <button class="btn btn-sm {{ $l->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">{{ $l->is_active ? 'Active' : 'Inactive' }}</button>
                </form>
                <form method="POST" action="{{ route('payroll.joining-letter.destroy', $l) }}" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Template</button>
                </form>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function(){
    let lastField = null;
    document.querySelectorAll('.rte').forEach(function(el){
        el.addEventListener('focus', function(){ lastField = el; });
    });
    document.querySelectorAll('.placeholder-chip').forEach(function(chip){
        chip.addEventListener('click', function(){
            if (!lastField) return;
            const token = chip.dataset.placeholder;
            const start = lastField.selectionStart ?? lastField.value.length;
            const end = lastField.selectionEnd ?? lastField.value.length;
            lastField.value = lastField.value.slice(0, start) + token + lastField.value.slice(end);
            lastField.focus();
            lastField.selectionStart = lastField.selectionEnd = start + token.length;
        });
    });

    const toggle = document.getElementById('useCompanySignatory');
    const custom = document.getElementById('customSignatory');
    function sync(){ custom.style.display = toggle.checked ? 'none' : ''; }
    toggle.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
