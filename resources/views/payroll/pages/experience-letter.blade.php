@php $l = $letter; @endphp

@extends('payroll.layout', [
    'title' => 'Experience Letter',
    'subtitle' => 'One template for '.$company->name.'. Once someone has been relieved, their letter is generated from this with their dates, role and tenure filled in.',
])

@section('page')
<div class="doc-page">

<div class="doc-steps">
    <div class="doc-step {{ $l ? 'done' : '' }}">
        <span class="ds-num">{{ $l ? '✓' : '1' }}</span>
        <span>
            <span class="ds-title">Write the template</span>
            <span class="ds-hint">{{ $l ? 'Template is set up and ready to use.' : 'Not set up yet - fill in the form below.' }}</span>
        </span>
    </div>
    <div class="doc-step">
        <span class="ds-num">2</span>
        <span>
            <span class="ds-title">Choose a former employee</span>
            <span class="ds-hint">Only people who have actually been relieved appear.</span>
        </span>
    </div>
    <div class="doc-step">
        <span class="ds-num">3</span>
        <span>
            <span class="ds-title">Generate the PDF</span>
            <span class="ds-hint">Opens in a new tab, ready to print or send.</span>
        </span>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Issue an Experience Letter</span>
        @if($l && ! $l->is_active)
            <span class="pill pill-unlocked">Template inactive</span>
        @endif
    </div>
    <div class="card-body">
        @if(! $l)
            <div class="empty-state py-4">
                <div class="es-icon"><i class="bi bi-file-earmark-check"></i></div>
                <div class="es-title">No template yet</div>
                <div class="es-text">Set the template up below to issue letters from here.</div>
            </div>
        @elseif($separations->isEmpty())
            <div class="empty-state py-4">
                <div class="es-icon"><i class="bi bi-person-check"></i></div>
                <div class="es-title">Nobody has been relieved yet</div>
                <div class="es-text">An experience letter can only be issued once someone's exit is recorded and their status is Relieved.</div>
                <a class="btn btn-outline-p mt-3" href="{{ route('payroll.separation.index') }}">Go to Resignation</a>
            </div>
        @else
            <div class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label" for="expEmployee">Former employee<span class="req">*</span></label>
                    <select class="form-select" id="expEmployee"
                            data-route-template="{{ route('payroll.separation.experience-letter', ['separation' => '__ID__']) }}">
                        <option value="">Choose a former employee&hellip;</option>
                        @foreach($separations as $sep)
                            @continue(! $sep->employee)
                            <option value="{{ $sep->id }}"
                                    data-designation="{{ $sep->employee->designation }}"
                                    data-joined="{{ $sep->employee->joining_date?->format('d M Y') }}"
                                    data-left="{{ $sep->last_working_date->format('d M Y') }}">
                                {{ $sep->employee->name }} ({{ $sep->employee->employee_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <a class="btn btn-p w-100 disabled" id="expGenerate" href="#" target="_blank" aria-disabled="true">
                        <i class="bi bi-file-earmark-pdf"></i> Generate Letter
                    </a>
                </div>
                <div class="col-12">
                    <div class="field-readonly" id="expPreview" hidden>
                        <i class="bi bi-eye"></i>
                        <span id="expPreviewText"></span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Template</span>
        <span class="text-muted fw-normal" style="font-size:12.5px;">One template per company</span>
    </div>
    <div class="card-body">
        <div class="alert alert-light border">
            <div class="fw-semibold mb-2" style="color:var(--p700); font-size:13px;">Placeholders</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Models\ExperienceLetter::PLACEHOLDERS as $placeholder)
                    <button type="button" class="btn btn-sm btn-outline-p placeholder-chip" data-placeholder="{{ $placeholder }}">{{ $placeholder }}</button>
                @endforeach
            </div>
            <div class="form-text mt-2">Click one to drop it in at the cursor. Each is replaced with the employee's real details when the letter is generated.</div>
        </div>

        <form method="POST" action="{{ route('payroll.experience-letter.save') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-section">
                <div class="fs-head"><div class="fs-title"><i class="bi bi-card-text"></i> Letter content</div></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="el_subject">Subject</label>
                        <input name="subject" id="el_subject" class="form-control rte"
                               value="{{ old('subject', $l->subject ?? 'Experience Certificate') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="el_body">Body</label>
                        <textarea name="body_content" id="el_body" class="form-control rte" rows="5">{{ old('body_content', $l->body_content ?? 'This is to certify that {EMPLOYEE_NAME} was employed with us as {DESIGNATION} in the {DEPARTMENT} department from {JOINING_DATE} to {LAST_WORKING_DATE}, a period of {DURATION}.') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="el_conduct">Conduct &amp; performance</label>
                        <textarea name="conduct_remarks" id="el_conduct" class="form-control rte" rows="3">{{ old('conduct_remarks', $l->conduct_remarks ?? 'During the tenure, we found {EMPLOYEE_NAME} to be sincere, hardworking and professional in conduct.') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="el_closing">Closing message</label>
                        <textarea name="closing_message" id="el_closing" class="form-control rte" rows="2">{{ old('closing_message', $l->closing_message ?? 'We wish {EMPLOYEE_NAME} every success in future endeavours.') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="fs-head"><div class="fs-title"><i class="bi bi-pen"></i> Authorised signatory</div></div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="use_company_signatory" value="1" id="expUseCompanySignatory"
                           @checked(old('use_company_signatory', $l->use_company_signatory ?? true))>
                    <label class="form-check-label" for="expUseCompanySignatory">Use the company's authorised signatory</label>
                    <div class="form-text">When on, the name, designation and signature image come from Company Setup.</div>
                </div>
                <div class="row g-3" data-show-when="!use_company_signatory">
                    <div class="col-md-6">
                        <label class="form-label" for="el_auth_name">Authorised name</label>
                        <input name="authorized_name" id="el_auth_name" class="form-control" value="{{ old('authorized_name', $l->authorized_name ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="el_auth_desig">Designation</label>
                        <input name="authorized_designation" id="el_auth_desig" class="form-control" value="{{ old('authorized_designation', $l->authorized_designation ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="el_auth_sig">Signature image</label>
                        <input type="file" name="signature_image" id="el_auth_sig" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label" for="el_auth_close">Closing text</label>
                    <textarea name="authorized_closing_text" id="el_auth_close" class="form-control rte" rows="2">{{ old('authorized_closing_text', $l->authorized_closing_text ?? 'For '.$company->name) }}</textarea>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-4 pt-3 border-top">
                <button class="btn btn-p">{{ $l ? 'Save Template' : 'Create Template' }}</button>
                @if($l)
                    <span class="ms-auto d-flex gap-2">
                        <span class="d-inline-block">
                            <button form="elToggle" class="btn btn-sm {{ $l->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $l->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </span>
                        <span class="d-inline-block">
                            <button form="elDelete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Template</button>
                        </span>
                    </span>
                @endif
            </div>
        </form>

        @if($l)
            <form id="elToggle" method="POST" action="{{ route('payroll.experience-letter.toggle-active', $l) }}" data-status-toggle class="d-none">@csrf</form>
            <form id="elDelete" method="POST" action="{{ route('payroll.experience-letter.destroy', $l) }}" class="d-none"
                  data-confirm="Delete the experience letter template for {{ $company->name }}?">@csrf @method('DELETE')</form>
        @endif
    </div>
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
            if (!lastField) { window.PMSToast?.push('info', 'Click into a field first, then pick a placeholder.'); return; }
            const token = chip.dataset.placeholder;
            const start = lastField.selectionStart ?? lastField.value.length;
            const end = lastField.selectionEnd ?? lastField.value.length;
            lastField.value = lastField.value.slice(0, start) + token + lastField.value.slice(end);
            lastField.focus();
            lastField.selectionStart = lastField.selectionEnd = start + token.length;
        });
    });

    const picker = document.getElementById('expEmployee');
    const button = document.getElementById('expGenerate');
    const preview = document.getElementById('expPreview');
    const previewText = document.getElementById('expPreviewText');

    if (picker && button) {
        picker.addEventListener('change', function(){
            const option = picker.selectedOptions[0];
            const id = picker.value;

            if (!id) {
                button.classList.add('disabled');
                button.setAttribute('aria-disabled', 'true');
                button.removeAttribute('href');
                preview.hidden = true;
                return;
            }

            button.href = picker.dataset.routeTemplate.replace('__ID__', id);
            button.classList.remove('disabled');
            button.setAttribute('aria-disabled', 'false');

            previewText.textContent = option.textContent.trim()
                + ' · ' + (option.dataset.designation || 'No designation')
                + ' · ' + (option.dataset.joined || '?') + ' to ' + (option.dataset.left || '?');
            preview.hidden = false;
        });
    }
})();
</script>
@endpush

@endsection
