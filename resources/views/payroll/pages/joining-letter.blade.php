@php $l = $letter; @endphp

@extends('payroll.layout', [
    'title' => 'Joining Letter',
    'subtitle' => 'One template for '.$company->name.'. Write it once with placeholders, then issue it for any employee - their details and the company details are filled in automatically.',
])

@section('page')
<div class="doc-page">

{{-- What actually happens, so the two halves of this page read as one task --}}
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
            <span class="ds-title">Choose an employee</span>
            <span class="ds-hint">Their name, role, salary and joining date replace the placeholders.</span>
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

{{-- Issue a letter. Placed above the template, because on most visits that is
     the job; editing the template is the occasional one. --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Issue a Joining Letter</span>
        @if($l && ! $l->is_active)
            <span class="pill pill-unlocked">Template inactive</span>
        @endif
    </div>
    <div class="card-body">
        @if(! $l)
            <div class="empty-state py-4">
                <div class="es-icon"><i class="bi bi-file-earmark-text"></i></div>
                <div class="es-title">No template yet</div>
                <div class="es-text">Set the template up below and you will be able to issue a letter for anyone on the payroll in two clicks.</div>
            </div>
        @elseif($employees->isEmpty())
            <div class="empty-state py-4">
                <div class="es-icon"><i class="bi bi-people"></i></div>
                <div class="es-title">No active staff</div>
                <div class="es-text">There is nobody to issue a letter to yet.</div>
                <a class="btn btn-outline-p mt-3" href="{{ route('payroll.staff.index') }}">Go to Staff Management</a>
            </div>
        @else
            <div class="row g-3 align-items-end" id="issueJoining">
                <div class="col-md-7">
                    <label class="form-label" for="joiningEmployee">Employee<span class="req">*</span></label>
                    <select class="form-select" id="joiningEmployee"
                            data-route-template="{{ route('payroll.joining-letter.generate', ['employee' => '__ID__']) }}">
                        <option value="">Choose an employee&hellip;</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}"
                                    data-designation="{{ $employee->designation }}"
                                    data-joined="{{ $employee->joining_date?->format('d M Y') }}"
                                    data-salary="₹{{ number_format($employee->salary, 2) }}">
                                {{ $employee->name }} ({{ $employee->employee_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <a class="btn btn-p w-100 disabled" id="joiningGenerate" href="#" target="_blank" aria-disabled="true">
                        <i class="bi bi-file-earmark-pdf"></i> Generate Letter
                    </a>
                </div>
                <div class="col-12">
                    <div class="field-readonly" id="joiningPreview" hidden>
                        <i class="bi bi-eye"></i>
                        <span id="joiningPreviewText"></span>
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
                @foreach(\App\Models\JoiningLetter::PLACEHOLDERS as $placeholder)
                    <button type="button" class="btn btn-sm btn-outline-p placeholder-chip" data-placeholder="{{ $placeholder }}">{{ $placeholder }}</button>
                @endforeach
            </div>
            <div class="form-text mt-2">Click one to drop it in at the cursor. Each is replaced with the real value when a letter is generated.</div>
        </div>

        <form method="POST" action="{{ route('payroll.joining-letter.save') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-section">
                <div class="fs-head">
                    <div class="fs-title"><i class="bi bi-building"></i> Company details</div>
                    <div class="fs-hint">Taken from Company Setup and printed on every letter - nothing to re-enter here.</div>
                </div>
                <div class="field-readonly">
                    <i class="bi bi-lock"></i>
                    <span>{{ $company->name }} &middot; {{ $company->address }} &middot; {{ $company->mobile_number }}{{ $company->email ? ' · '.$company->email : '' }}</span>
                </div>
            </div>

            <div class="form-section">
                <div class="fs-head"><div class="fs-title"><i class="bi bi-card-text"></i> Letter content</div></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="jl_intro">Introduction / appointment</label>
                        <textarea name="introduction_content" id="jl_intro" class="form-control rte" rows="4">{{ old('introduction_content', $l->introduction_content ?? '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="jl_roles">Roles &amp; responsibilities</label>
                        <textarea name="roles_responsibilities" id="jl_roles" class="form-control rte" rows="3">{{ old('roles_responsibilities', $l->roles_responsibilities ?? '{RESPONSIBILITIES}') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="jl_terms">Terms &amp; conditions</label>
                        <textarea name="terms_conditions" id="jl_terms" class="form-control rte" rows="4">{{ old('terms_conditions', $l->terms_conditions ?? '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="jl_closing">Closing / welcome message</label>
                        <textarea name="closing_message" id="jl_closing" class="form-control rte" rows="3">{{ old('closing_message', $l->closing_message ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="fs-head"><div class="fs-title"><i class="bi bi-pen"></i> Authorised signatory</div></div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="use_company_signatory" value="1" id="useCompanySignatory"
                           @checked(old('use_company_signatory', $l->use_company_signatory ?? true))>
                    <label class="form-check-label" for="useCompanySignatory">Use the company's authorised signatory</label>
                    <div class="form-text">When on, the name, designation and signature image come from Company Setup. The closing text stays editable either way.</div>
                </div>
                <div class="row g-3" data-show-when="!use_company_signatory">
                    <div class="col-md-6">
                        <label class="form-label" for="jl_auth_name">Authorised name</label>
                        <input name="authorized_name" id="jl_auth_name" class="form-control" value="{{ old('authorized_name', $l->authorized_name ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="jl_auth_desig">Designation</label>
                        <input name="authorized_designation" id="jl_auth_desig" class="form-control" value="{{ old('authorized_designation', $l->authorized_designation ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="jl_auth_sig">Signature image</label>
                        <input type="file" name="signature_image" id="jl_auth_sig" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label" for="jl_auth_close">Closing text</label>
                    <textarea name="authorized_closing_text" id="jl_auth_close" class="form-control rte" rows="2">{{ old('authorized_closing_text', $l->authorized_closing_text ?? '') }}</textarea>
                </div>
            </div>

            <div class="form-section">
                <div class="fs-head">
                    <div class="fs-title"><i class="bi bi-check2-square"></i> Employee acceptance</div>
                    <div class="fs-hint">The section the employee signs and returns.</div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="jl_acc_head">Heading</label>
                        <input name="acceptance_heading" id="jl_acc_head" class="form-control" value="{{ old('acceptance_heading', $l->acceptance_heading ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="jl_acc_body">Content</label>
                        <textarea name="acceptance_content" id="jl_acc_body" class="form-control rte" rows="3">{{ old('acceptance_content', $l->acceptance_content ?? '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="jl_acc_close">Closing text</label>
                        <textarea name="acceptance_closing_text" id="jl_acc_close" class="form-control rte" rows="2">{{ old('acceptance_closing_text', $l->acceptance_closing_text ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-4 pt-3 border-top">
                <button class="btn btn-p">{{ $l ? 'Save Template' : 'Create Template' }}</button>
                @if($l)
                    <span class="ms-auto d-flex gap-2">
                        <span class="d-inline-block">
                            <button form="jlToggle" class="btn btn-sm {{ $l->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $l->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </span>
                        <span class="d-inline-block">
                            <button form="jlDelete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Template</button>
                        </span>
                    </span>
                @endif
            </div>
        </form>

        @if($l)
            {{-- Kept outside the template form: a form cannot be nested in another --}}
            <form id="jlToggle" method="POST" action="{{ route('payroll.joining-letter.toggle-active', $l) }}" data-status-toggle class="d-none">@csrf</form>
            <form id="jlDelete" method="POST" action="{{ route('payroll.joining-letter.destroy', $l) }}" class="d-none"
                  data-confirm="Delete the joining letter template for {{ $company->name }}?">@csrf @method('DELETE')</form>
        @endif
    </div>
</div>

</div>

@push('scripts')
<script>
(function(){
    // Placeholder chips insert at the cursor of whichever field was last used
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

    // Choose an employee, see what will be on the letter, then generate
    const picker = document.getElementById('joiningEmployee');
    const button = document.getElementById('joiningGenerate');
    const preview = document.getElementById('joiningPreview');
    const previewText = document.getElementById('joiningPreviewText');

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
                + ' · joined ' + (option.dataset.joined || 'unknown')
                + ' · ' + (option.dataset.salary || '');
            preview.hidden = false;
        });
    }
})();
</script>
@endpush

@endsection
