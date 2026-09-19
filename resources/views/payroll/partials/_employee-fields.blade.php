@php
    $e = $target ?? null;
    $takenCodes = ($employees ?? collect())->pluck('employee_code')->values();
    $takenEmails = ($employees ?? collect())->pluck('email')->filter()->map(fn ($v) => strtolower($v))->values();
    $uid = $e->id ?? 'new';

    $photoUrl = ! empty($e?->photo_path) ? \Illuminate\Support\Facades\Storage::url($e->photo_path) : null;
    $photoInitials = $e
        ? collect(explode(' ', trim($e->name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
        : '';
    $photoId = 'photo_'.$uid;
@endphp

{{-- Four steps, grouped the way a person thinks about a new joiner: who they
     are, what they will do, how they are paid, and their papers. Only short,
     related fields share a row (mobile beside email, ID beside designation);
     each field is as wide as what it holds. --}}
<datalist id="dl_desig_{{ $uid }}">@foreach(\App\Support\PayrollMasters::labels('designations', \App\Support\PayrollContext::current()) as $o)<option value="{{ $o }}">@endforeach</datalist>
<datalist id="dl_dept_{{ $uid }}">@foreach(\App\Support\PayrollMasters::labels('departments', \App\Support\PayrollContext::current()) as $o)<option value="{{ $o }}">@endforeach</datalist>
<div class="form-wizard" data-wizard>

    {{-- ============================== 1. Personal ============================== --}}
    <div class="form-step" data-step="Personal">

        <div class="photo-field" data-photo-field>
            <label class="photo-drop {{ $photoUrl ? 'has-photo' : '' }}" for="{{ $photoId }}"
                   title="{{ $photoUrl ? 'Change photo' : 'Add a photo' }}">
                <img class="photo-preview" src="{{ $photoUrl ?? '' }}" alt="" @unless($photoUrl) hidden @endunless>
                <span class="photo-placeholder" @if($photoUrl) hidden @endif>
                    @if($photoInitials)
                        <span class="photo-initials">{{ $photoInitials }}</span>
                    @else
                        <i class="bi bi-person"></i>
                    @endif
                </span>
                <span class="photo-badge" aria-hidden="true"><i class="bi bi-camera-fill"></i></span>
            </label>

            <div class="photo-text">
                <div class="photo-title">Profile photo <span class="opt">optional</span></div>
                <div class="photo-hint">Pick a picture, then crop it to a square. It shows on the staff list and the employee record.</div>
                <div class="photo-actions">
                    <label class="btn btn-sm btn-outline-p mb-0" for="{{ $photoId }}">
                        <i class="bi bi-upload"></i> <span data-photo-label>{{ $photoUrl ? 'Change photo' : 'Upload photo' }}</span>
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-p" data-photo-adjust hidden>
                        <i class="bi bi-crop"></i> Adjust crop
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" data-photo-clear hidden>
                        <i class="bi bi-arrow-counterclockwise"></i> Undo
                    </button>
                </div>
                <div class="photo-file" data-photo-name hidden></div>
            </div>

            <input type="file" name="photo" id="{{ $photoId }}" class="photo-input"
                   accept="image/jpeg,image/png,image/webp" data-photo-input>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="emp_name_{{ $uid }}">Full name<span class="req">*</span></label>
                <input name="name" id="emp_name_{{ $uid }}" class="form-control" autocomplete="off"
                       value="{{ old('name', $e->name ?? '') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="emp_dob_{{ $uid }}">Date of birth<span class="opt">optional</span></label>
                <input type="date" name="date_of_birth" id="emp_dob_{{ $uid }}" class="form-control"
                       max="{{ now()->subYears(14)->format('Y-m-d') }}"
                       value="{{ old('date_of_birth', optional($e->date_of_birth ?? null)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="emp_gender_{{ $uid }}">Gender<span class="opt">optional</span></label>
                <select name="gender" id="emp_gender_{{ $uid }}" class="form-select">
                    <option value="">Select&hellip;</option>
                    @foreach(\App\Support\PayrollMasters::choices('gender') as $option)
                        <option value="{{ $option }}" @selected(old('gender', $e->gender ?? '') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            {{-- The two ways to reach a person, side by side and both required:
                 the letter goes to one, and the other is who you ring. --}}
            <div class="col-md-6">
                <label class="form-label" for="emp_mobile_{{ $uid }}">Mobile number<span class="req">*</span></label>
                @include('payroll.partials._phone-field', [
                    'name' => 'contact_number', 'countryName' => 'contact_country',
                    'id' => 'emp_mobile_'.$uid,
                    'value' => $e->contact_number ?? '', 'country' => $e->contact_country ?? null,
                    'required' => true,
                ])
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_email_{{ $uid }}">Email address<span class="req">*</span></label>
                <input type="email" name="email" id="emp_email_{{ $uid }}" class="form-control"
                       autocomplete="off" placeholder="name@example.com"
                       data-unique-among='@json($takenEmails)'
                       data-unique-self="{{ strtolower($e->email ?? '') }}"
                       data-unique-message="Another employee in this company already uses this email"
                       value="{{ old('email', $e->email ?? '') }}" required>
            </div>

            <div class="col-12">
                <label class="form-label" for="emp_addr_{{ $uid }}">Address<span class="opt">optional</span></label>
                <textarea name="address" id="emp_addr_{{ $uid }}" class="form-control" rows="2"
                          placeholder="House, street, area, city">{{ old('address', $e->address ?? '') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_nat_{{ $uid }}">Nationality<span class="opt">optional</span></label>
                <input name="nationality" id="emp_nat_{{ $uid }}" class="form-control"
                       value="{{ old('nationality', $e->nationality ?? '') }}" placeholder="Indian">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_pin_{{ $uid }}">Country and pincode<span class="opt">optional</span></label>
                <input name="country_and_pincode" id="emp_pin_{{ $uid }}" class="form-control"
                       value="{{ old('country_and_pincode', $e->country_and_pincode ?? '') }}" placeholder="India, 360001">
            </div>
        </div>

        <div class="form-section">
            <div class="fs-head">
                <div class="fs-title"><i class="bi bi-telephone-plus"></i> Emergency contact <span class="opt">optional</span></div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="emp_ecn_{{ $uid }}">Name</label>
                    <input name="emergency_contact_name" id="emp_ecn_{{ $uid }}" class="form-control"
                           value="{{ old('emergency_contact_name', $e->emergency_contact_name ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="emp_ecr_{{ $uid }}">Relation</label>
                    <input name="emergency_contact_relation" id="emp_ecr_{{ $uid }}" class="form-control"
                           value="{{ old('emergency_contact_relation', $e->emergency_contact_relation ?? '') }}" placeholder="Spouse">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="emp_ecm_{{ $uid }}">Mobile</label>
                    @include('payroll.partials._phone-field', [
                        'name' => 'emergency_contact_number', 'countryName' => 'emergency_contact_country',
                        'id' => 'emp_ecm_'.$uid,
                        'value' => $e->emergency_contact_number ?? '', 'country' => $e->emergency_contact_country ?? null,
                        'required' => false,
                    ])
                </div>
            </div>
        </div>
    </div>

    {{-- ================================ 2. Job ================================= --}}
    <div class="form-step" data-step="Job">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="emp_code_{{ $uid }}">Employee ID<span class="req">*</span></label>
                <input name="employee_code" id="emp_code_{{ $uid }}" class="form-control" autocomplete="off"
                       value="{{ old('employee_code', $e->employee_code ?? '') }}" required
                       data-unique-among='@json($takenCodes)'
                       data-unique-self="{{ $e->employee_code ?? '' }}"
                       data-unique-message="This Employee ID is already used in this company">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="emp_desig_{{ $uid }}">Designation<span class="req">*</span></label>
                <input name="designation" id="emp_desig_{{ $uid }}" class="form-control" list="dl_desig_{{ $uid }}" autocomplete="off"
                       value="{{ old('designation', $e->designation ?? '') }}" placeholder="e.g. Front Office" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="emp_dept_{{ $uid }}">Department<span class="opt">optional</span></label>
                <input name="department" id="emp_dept_{{ $uid }}" class="form-control" list="dl_dept_{{ $uid }}" autocomplete="off"
                       value="{{ old('department', $e->department ?? '') }}">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="emp_join_{{ $uid }}">Joining date<span class="req">*</span></label>
                <input type="date" name="joining_date" id="emp_join_{{ $uid }}" class="form-control"
                       value="{{ old('joining_date', optional($e->joining_date ?? null)->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="emp_hrs_{{ $uid }}">Working hours<span class="req">*</span><span class="opt">per day</span></label>
                <input type="number" step="0.5" min="0.5" max="24" name="daily_working_hours" id="emp_hrs_{{ $uid }}"
                       class="form-control" value="{{ old('daily_working_hours', $e->daily_working_hours ?? 8) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="emp_qual_{{ $uid }}">Qualification<span class="opt">optional</span></label>
                <input name="qualification" id="emp_qual_{{ $uid }}" class="form-control"
                       value="{{ old('qualification', $e->qualification ?? '') }}">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="emp_inst_{{ $uid }}">Institution<span class="opt">optional</span></label>
                <input name="qualification_institution" id="emp_inst_{{ $uid }}" class="form-control"
                       value="{{ old('qualification_institution', $e->qualification_institution ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_skills_{{ $uid }}">Skills<span class="opt">optional</span></label>
                <input name="skills" id="emp_skills_{{ $uid }}" class="form-control"
                       value="{{ old('skills', $e->skills ?? '') }}" placeholder="Comma separated">
            </div>

            <div class="col-12">
                <label class="form-label" for="emp_resp_{{ $uid }}">Responsibilities<span class="opt">optional</span></label>
                <textarea name="responsibilities" id="emp_resp_{{ $uid }}" class="form-control" rows="3">{{ old('responsibilities', $e->responsibilities ?? '') }}</textarea>
                <div class="form-text">Fills the {RESPONSIBILITIES} placeholder in the appointment letter.</div>
            </div>
        </div>
    </div>

    {{-- ================================ 3. Pay ================================= --}}
    <div class="form-step" data-step="Pay">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="emp_sal_{{ $uid }}">Monthly salary<span class="req">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" step="0.01" min="0" name="salary" id="emp_sal_{{ $uid }}" class="form-control"
                           value="{{ old('salary', $e->salary ?? '') }}" required>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="emp_mode_{{ $uid }}">Payment mode<span class="req">*</span></label>
                <select name="payment_mode" id="emp_mode_{{ $uid }}" class="form-select payment-mode" required>
                    @foreach(\App\Support\PayrollMasters::choices('salary_payment_mode') as $mode)
                        <option value="{{ $mode }}" @selected(old('payment_mode', $e->payment_mode ?? 'Cash') === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Only asked for when salary goes to a bank --}}
        <div class="form-section" data-show-when="payment_mode=Bank">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-bank"></i> Bank account</div></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="emp_bank_{{ $uid }}">Bank name<span class="req">*</span></label>
                    <input name="bank_name" id="emp_bank_{{ $uid }}" class="form-control" value="{{ old('bank_name', $e->bank_name ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="emp_holder_{{ $uid }}">Account holder<span class="req">*</span></label>
                    <input name="account_holder_name" id="emp_holder_{{ $uid }}" class="form-control" value="{{ old('account_holder_name', $e->account_holder_name ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="emp_acc_{{ $uid }}">Account number<span class="req">*</span></label>
                    <input name="account_number" id="emp_acc_{{ $uid }}" class="form-control" inputmode="numeric" value="{{ old('account_number', $e->account_number ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="emp_ifsc_{{ $uid }}">IFSC code<span class="req">*</span></label>
                    <input name="ifsc_code" id="emp_ifsc_{{ $uid }}" class="form-control text-uppercase" value="{{ old('ifsc_code', $e->ifsc_code ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="emp_branch_{{ $uid }}">Branch<span class="opt">optional</span></label>
                    <input name="branch_name" id="emp_branch_{{ $uid }}" class="form-control" value="{{ old('branch_name', $e->branch_name ?? '') }}">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="fs-head d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-title"><i class="bi bi-dash-circle"></i> Deductions <span class="opt">optional</span></div>
                    <div class="fs-hint">Recovered automatically when salary is processed.</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-p add-deduction-row"><i class="bi bi-plus-lg"></i> Add</button>
            </div>

            <div class="deduction-rows">
                @foreach(($e->deductions ?? collect()) as $index => $assignment)
                    <div class="row g-2 mb-2 deduction-row">
                        <input type="hidden" name="deductions[{{ $index }}][id]" value="{{ $assignment->id }}">
                        <div class="col-md-5">
                            <select name="deductions[{{ $index }}][deduction_id]" class="form-select">
                                <option value="">Select deduction&hellip;</option>
                                @foreach($deductionOptions as $option)
                                    <option value="{{ $option->id }}" data-amount="{{ $option->amount }}" @selected($assignment->deduction_id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="deductions[{{ $index }}][deduction_type]" class="form-select">
                                @foreach(['One Time', 'Monthly'] as $type)
                                    <option value="{{ $type }}" @selected($assignment->deduction_type === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><input type="number" step="0.01" name="deductions[{{ $index }}][amount]" class="form-control" value="{{ $assignment->amount }}" placeholder="Amount"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-deduction-row" aria-label="Remove deduction"><i class="bi bi-x"></i></button></div>
                    </div>
                @endforeach
            </div>

            <template class="deduction-template">
                <div class="row g-2 mb-2 deduction-row">
                    <div class="col-md-5">
                        <select name="deductions[__INDEX__][deduction_id]" class="form-select">
                            <option value="">Select deduction&hellip;</option>
                            @foreach($deductionOptions as $option)
                                <option value="{{ $option->id }}" data-amount="{{ $option->amount }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="deductions[__INDEX__][deduction_type]" class="form-select">
                            <option value="One Time">One Time</option>
                            <option value="Monthly" selected>Monthly</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="number" step="0.01" name="deductions[__INDEX__][amount]" class="form-control" placeholder="Amount"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-deduction-row" aria-label="Remove deduction"><i class="bi bi-x"></i></button></div>
                </div>
            </template>
        </div>
    </div>

    {{-- ============================= 4. Documents ============================== --}}
    <div class="form-step" data-step="Documents">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="emp_idt_{{ $uid }}">ID proof<span class="opt">optional</span></label>
                <select name="id_proof_type" id="emp_idt_{{ $uid }}" class="form-select">
                    <option value="">Select a type&hellip;</option>
                    @foreach(\App\Support\PayrollMasters::choices('id_proofs') as $type)
                        <option value="{{ $type }}" @selected(old('id_proof_type', $e->id_proof_type ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_idn_{{ $uid }}">ID number<span class="opt">optional</span></label>
                <input name="id_proof_number" id="emp_idn_{{ $uid }}" class="form-control"
                       value="{{ old('id_proof_number', $e->id_proof_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_idf_{{ $uid }}">ID front<span class="opt">image</span></label>
                <input type="file" name="id_proof_image" id="emp_idf_{{ $uid }}" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $e->id_proof_image_path ?? null, 'label' => 'Current ID proof'])
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_idb_{{ $uid }}">ID back<span class="opt">image</span></label>
                <input type="file" name="id_proof_back_image" id="emp_idb_{{ $uid }}" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $e->id_proof_back_image_path ?? null, 'label' => 'Current back image'])
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emp_cv_{{ $uid }}">Resume<span class="opt">PDF or image</span></label>
                <input type="file" name="resume" id="emp_cv_{{ $uid }}" class="form-control" accept="application/pdf,image/*">
                @include('payroll.partials._current-file', ['path' => $e->resume_path ?? null, 'label' => 'Current resume'])
            </div>
        </div>

        {{-- Only when adding: saving sends the appointment letter to the email
             above. Editing someone never re-sends it; the staff list has a
             button for that. --}}
        @unless($e)
            <div class="offer-toggle {{ ($hasJoiningTemplate ?? true) ? '' : 'is-off' }}">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="send_offer_letter" value="1"
                           id="emp_offer_{{ $uid }}"
                           @checked(old('send_offer_letter', ($hasJoiningTemplate ?? true) ? '1' : null))
                           @disabled(! ($hasJoiningTemplate ?? true))>
                    <label class="form-check-label" for="emp_offer_{{ $uid }}">Email the appointment letter when I save</label>
                </div>
                <div class="offer-hint">
                    @if($hasJoiningTemplate ?? true)
                        <i class="bi bi-envelope-check"></i> The letter is generated from your joining letter template and sent to the email address on the Personal step.
                    @else
                        <i class="bi bi-info-circle"></i> There is no active joining letter template yet, so nothing can be sent.
                        <a href="{{ route('payroll.joining-letter.index') }}">Set one up</a>.
                    @endif
                </div>
            </div>
        @endunless
    </div>
</div>
