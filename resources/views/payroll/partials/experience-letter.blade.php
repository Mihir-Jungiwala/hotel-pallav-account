@php $l = $letter; @endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Experience Letter Template &mdash; {{ $company->name }}</span>
        <span class="text-muted fw-normal" style="font-size:12.5px;">One template per company</span>
    </div>
    <div class="card-body">
        <div class="alert alert-light border" style="background:var(--p50);">
            <div class="fw-semibold mb-2" style="color:var(--p800); font-size:13px;">Placeholders</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Models\ExperienceLetter::PLACEHOLDERS as $placeholder)
                    <button type="button" class="btn btn-sm btn-outline-p placeholder-chip" data-placeholder="{{ $placeholder }}">{{ $placeholder }}</button>
                @endforeach
            </div>
            <div class="form-text mt-2">Click to insert at the cursor. Replaced with the employee's details when the letter is generated.</div>
        </div>

        <form method="POST" action="{{ route('payroll.experience-letter.save') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Subject</label>
                    <input name="subject" class="form-control rte" value="{{ old('subject', $l->subject ?? 'Experience Certificate') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Body</label>
                    <textarea name="body_content" class="form-control rte" rows="5">{{ old('body_content', $l->body_content ?? 'This is to certify that {EMPLOYEE_NAME} was employed with us as {DESIGNATION} in the {DEPARTMENT} department from {JOINING_DATE} to {LAST_WORKING_DATE}, a period of {DURATION}.') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Conduct &amp; Performance</label>
                    <textarea name="conduct_remarks" class="form-control rte" rows="3">{{ old('conduct_remarks', $l->conduct_remarks ?? 'During the tenure, we found {EMPLOYEE_NAME} to be sincere, hardworking and professional in conduct.') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Closing Message</label>
                    <textarea name="closing_message" class="form-control rte" rows="2">{{ old('closing_message', $l->closing_message ?? 'We wish {EMPLOYEE_NAME} every success in future endeavours.') }}</textarea>
                </div>

                <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Authorised Signatory</div></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="use_company_signatory" value="1" id="expUseCompanySignatory"
                               @checked(old('use_company_signatory', $l->use_company_signatory ?? true))>
                        <label class="form-check-label" for="expUseCompanySignatory">Use Company Authorised Signatory</label>
                    </div>
                </div>
                <div id="expCustomSignatory" class="row g-3">
                    <div class="col-md-6"><label class="form-label">Authorised Name</label><input name="authorized_name" class="form-control" value="{{ old('authorized_name', $l->authorized_name ?? '') }}"></div>
                    <div class="col-md-6"><label class="form-label">Designation</label><input name="authorized_designation" class="form-control" value="{{ old('authorized_designation', $l->authorized_designation ?? '') }}"></div>
                    <div class="col-md-6"><label class="form-label">Signature Image</label><input type="file" name="signature_image" class="form-control" accept="image/*"></div>
                </div>
                <div class="col-12">
                    <label class="form-label">Closing Text</label>
                    <textarea name="authorized_closing_text" class="form-control rte" rows="2">{{ old('authorized_closing_text', $l->authorized_closing_text ?? 'For '.$company->name) }}</textarea>
                </div>

                <div class="col-12">
                    <button class="btn btn-p">{{ $l ? 'Update Template' : 'Create Template' }}</button>
                </div>
            </div>
        </form>

        @if($l)
            <div class="d-flex gap-2 mt-3 pt-3 border-top">
                <form method="POST" action="{{ route('payroll.experience-letter.toggle-active', $l) }}">@csrf
                    <button class="btn btn-sm {{ $l->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">{{ $l->is_active ? 'Active' : 'Inactive' }}</button>
                </form>
                <form method="POST" action="{{ route('payroll.experience-letter.destroy', $l) }}" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')
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

    const toggle = document.getElementById('expUseCompanySignatory');
    const custom = document.getElementById('expCustomSignatory');
    function sync(){ custom.style.display = toggle.checked ? 'none' : ''; }
    toggle.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
