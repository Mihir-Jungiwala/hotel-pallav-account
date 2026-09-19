@php
    $initials = fn ($name) => collect(explode(' ', trim($name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $maxCompanies = config('payroll.max_companies');
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Company Setup</span>
        <span class="small fw-normal text-muted">{{ $activeCompanyCount }} of {{ $maxCompanies }} active companies used</span>
    </div>

    @if($companies->isEmpty())
        <div class="empty-state py-5">
            <div class="es-icon"><i class="bi bi-building"></i></div>
            <div class="es-title">No companies yet</div>
            <div class="es-text">Add the first company to start recording its staff, attendance and salary.</div>
            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                <i class="bi bi-plus-circle"></i> Add New Company
            </button>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:48px;">Sr.</th>
                        <th>Company</th>
                        <th>Code</th>
                        <th>Owner</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($companies as $i => $row)
                    <tr class="company-row" data-open-company="{{ route('payroll.index', ['current_company' => $row->id]) }}">
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar" style="width:34px;height:34px;font-size:12px;">
                                    @if($row->logo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($row->logo_path) }}" alt="{{ $row->name }}">
                                    @else
                                        {{ $initials($row->name) }}
                                    @endif
                                </span>
                                <span class="fw-semibold">{{ $row->name }}</span>
                            </div>
                        </td>
                        <td><code class="master-value">{{ $row->code }}</code></td>
                        <td class="text-muted">{{ $row->owner_name ?: '-' }}</td>
                        <td class="text-muted">{{ $row->employees_count }} {{ Str::plural('employee', $row->employees_count) }}</td>
                        <td>
                            <form method="POST" action="{{ route('payroll.company.toggle-active', $row) }}" data-status-toggle onclick="event.stopPropagation()">
                                @csrf
                                <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                    {{ $row->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-end text-nowrap" onclick="event.stopPropagation()">
                            <a class="btn btn-sm btn-p" href="{{ route('payroll.index', ['current_company' => $row->id]) }}">
                                Open <i class="bi bi-arrow-right-short"></i>
                            </a>
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editCompany{{ $row->id }}" data-open-record title="Edit"><i class="bi bi-pencil-square"></i></button>
                            <form method="POST" action="{{ route('payroll.company.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this company?')">
                                @csrf @method('DELETE')
                                <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Modals live outside the table - a <form> is not valid content inside a
     <tbody>, and the browser corrects that in ways that break its own JS. --}}
@foreach($companies as $row)
    <div class="modal fade" id="editCompany{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.company.update', $row) }}" enctype="multipart/form-data">@csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">{{ $row->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">@include('payroll.partials._company-fields', ['target' => $row])</div>
            <div class="modal-footer">
                <a class="btn btn-outline-p me-auto" href="{{ route('payroll.company.view', $row) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Company PDF</a>
                <button class="btn btn-p">Save Changes</button>
            </div>
        </form>
    </div></div></div>
@endforeach

<div class="modal fade" id="addCompanyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.company.store') }}" enctype="multipart/form-data">@csrf
        <div class="modal-header"><h5 class="modal-title">Add New Company</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('payroll.partials._company-fields', ['target' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Create Company</button></div>
    </form>
</div></div></div>

@push('scripts')
<script>
(function () {
    // The row itself opens the company; buttons and forms inside it stop the
    // click from bubbling (see onclick="event.stopPropagation()" above) so
    // Edit, Delete and the status toggle keep working as their own actions.
    document.querySelectorAll('[data-open-company]').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location.href = row.dataset.openCompany;
        });
    });
})();
</script>
@endpush
