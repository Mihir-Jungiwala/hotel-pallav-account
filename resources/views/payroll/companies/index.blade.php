{{-- Company Listing - the payroll landing page. Its only question is "which
     company are you managing payroll for?", so the company names and the way
     into them carry the page, and setup lives behind secondary actions. --}}
@extends('payroll.layout', [
    'title' => 'Company Listing',
    'subtitle' => 'Choose the company you want to manage payroll for. Everything after this - staff, attendance, salary and documents - stays inside the company you open.',
])

@section('page-actions')
    @if($activeCompanyCount < $maxCompanies)
        <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addCompanyModal" data-write-only>
            <i class="bi bi-plus-circle"></i> New Company
        </button>
    @endif
@endsection

@section('page')

@php
    $initials = fn ($name) => collect(explode(' ', trim((string) $name)))
        ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

{{-- A short read on the whole payroll estate, before picking one company --}}
<div class="pay-stats mb-3">
    <div class="pay-stat">
        <div class="ps-label">Companies</div>
        <div class="ps-value">{{ $companies->count() }}</div>
        <div class="ps-sub">{{ $activeCompanyCount }} active of {{ $maxCompanies }} allowed</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Staff on payroll</div>
        <div class="ps-value">{{ number_format($totalStaff) }}</div>
        <div class="ps-sub">Active employees across all companies</div>
    </div>
    <div class="pay-stat {{ $unpaidCount > 0 ? 'warn' : 'good' }}">
        <div class="ps-label">Salaries outstanding</div>
        <div class="ps-value">{{ number_format($unpaidCount) }}</div>
        <div class="ps-sub">{{ $unpaidCount > 0 ? 'Not yet fully paid' : 'Everything is settled' }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Capacity left</div>
        <div class="ps-value">{{ max(0, $maxCompanies - $activeCompanyCount) }}</div>
        <div class="ps-sub">More companies you can activate</div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Companies</span>
        <form method="GET" action="{{ route('payroll.index') }}" class="search-field mb-0" data-no-busy="true">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control" value="{{ $search }}"
                   placeholder="Search name, code or owner&hellip;" data-filter-target="#companyTable" aria-label="Search companies">
        </form>
    </div>

    @if($companies->isEmpty())
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-buildings"></i></div>
            <div class="es-title">{{ $search !== '' ? 'No company matches that search' : 'No companies yet' }}</div>
            <div class="es-text">
                {{ $search !== ''
                    ? 'Try a different name, code or owner.'
                    : 'Add the first company to start recording its staff, attendance and salary.' }}
            </div>
            @if($search !== '')
                <a class="btn btn-outline-p mt-3" href="{{ route('payroll.index') }}">Clear search</a>
            @else
                <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addCompanyModal" data-write-only>
                    <i class="bi bi-plus-circle"></i> Add the first company
                </button>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="companyTable"
                   data-paginate="10" data-pager="#companyPager" data-sortable>
                <thead>
                    <tr>
                        <th class="col-sno">S.No.</th>
                        <th data-sort="text">Company</th>
                        <th data-sort="text">Code</th>
                        <th data-sort="text">Owner</th>
                        <th data-sort="num" class="text-center">Staff</th>
                        <th data-sort="text">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($companies as $row)
                    <tr class="company-row" data-row="{{ $row->name }} {{ $row->code }} {{ $row->owner_name }}"
                        data-open-company="{{ route('payroll.index', ['current_company' => $row->id]) }}"
                        @if(! $row->is_active) title="Activate this company before you can open it" @endif>
                        <td class="sno text-muted"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar" style="width:34px;height:34px;font-size:12px;">
                                    @if($row->logo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($row->logo_path) }}" alt="">
                                    @else
                                        {{ $initials($row->name) }}
                                    @endif
                                </span>
                                <span>
                                    <span class="fw-semibold d-block">{{ $row->name }}</span>
                                    @if($row->city)
                                        <span class="text-muted" style="font-size:11.5px;">{{ $row->city }}{{ $row->state ? ', '.$row->state : '' }}</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td><code class="master-value">{{ $row->code }}</code></td>
                        <td class="text-muted">{{ $row->owner_name ?: '-' }}</td>
                        <td class="text-center" data-sort-value="{{ $row->active_employees_count }}">
                            <span class="fw-semibold">{{ $row->active_employees_count }}</span>
                            @if($row->employees_count > $row->active_employees_count)
                                <span class="text-muted" style="font-size:11.5px;"> / {{ $row->employees_count }}</span>
                            @endif
                        </td>
                        <td onclick="event.stopPropagation()">
                            <form method="POST" action="{{ route('payroll.company.toggle-active', $row) }}" data-status-toggle>
                                @csrf
                                <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                    {{ $row->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-end text-nowrap" onclick="event.stopPropagation()">
                            @if($row->is_active)
                                <a class="btn btn-sm btn-p" href="{{ route('payroll.index', ['current_company' => $row->id]) }}">
                                    Open <i class="bi bi-arrow-right-short"></i>
                                </a>
                            @else
                                <span class="text-muted me-2" style="font-size:12px;">Inactive</span>
                            @endif
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editCompany{{ $row->id }}"
                                    data-open-record title="Edit company"><i class="bi bi-pencil-square"></i></button>
                            <a class="btn-icon" href="{{ route('payroll.company.view', $row) }}" target="_blank"
                               title="Company PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            <form method="POST" action="{{ route('payroll.company.destroy', $row) }}" class="d-inline"
                                  data-confirm="Delete {{ $row->name }}? This cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="btn-icon danger" title="Delete company"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                <tr data-no-match hidden>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-search"></i></div>
                            <div class="es-title">No matching companies</div>
                            <div class="es-text">Try a different name, code or owner.</div>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        @include('payroll.partials._pager', ['id' => 'companyPager'])
    @endif
</div>

{{-- Modals live outside the table: a <form> is not valid content inside a
     <tbody>, and browsers repair that by moving it out of the DOM tree the
     JS expects, which left every edit form blank. --}}
@foreach($companies as $row)
    <div class="modal fade pay-form-modal" id="editCompany{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.company.update', $row) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">Company</div>
                        <h5 class="modal-title">{{ $row->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._company-fields', ['target' => $row])</div>
                <div class="modal-footer">
                    <a class="btn btn-outline-p me-auto" href="{{ route('payroll.company.view', $row) }}" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Company PDF
                    </a>
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal" id="addCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.company.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New</div>
                    <h5 class="modal-title">Add a Company</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">@include('payroll.partials._company-fields', ['target' => null])</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Create Company</button>
            </div>
        </form>
    </div></div>
</div>

@push('scripts')
<script>
(function () {
    // The whole row opens that company. Buttons and forms inside it stop the
    // click from bubbling, so Edit, Delete and the status toggle still work.
    document.querySelectorAll('[data-open-company]').forEach(function (row) {
        row.addEventListener('click', function () { window.location.href = row.dataset.openCompany; });
    });
})();
</script>
@endpush

@endsection
