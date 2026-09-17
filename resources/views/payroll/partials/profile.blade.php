<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Company Setup</span>
        <span class="small fw-normal text-muted">{{ $activeCompanyCount }} of {{ config('payroll.max_companies') }} active companies used</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Sr.</th><th>Company Name</th><th>Code</th><th>Owner</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($companies as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="fw-semibold">{{ $row->name }}</td>
                    <td>{{ $row->code }}</td>
                    <td>{{ $row->owner_name }}</td>
                    <td>
                        <form method="POST" action="{{ route('payroll.company.toggle-active', $row) }}">
                            @csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $row->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('payroll.company.view', $row) }}" target="_blank"><i class="bi bi-eye"></i></a>
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editCompany{{ $row->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('payroll.company.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this company?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editCompany{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.company.update', $row) }}" enctype="multipart/form-data">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit {{ $row->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('payroll.partials._company-fields', ['target' => $row])</div>
                        <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                    </form>
                </div></div></div>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No companies yet. Use <strong>Add New Company</strong> to create the first one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addCompanyModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.company.store') }}" enctype="multipart/form-data">@csrf
        <div class="modal-header"><h5 class="modal-title">Add New Company</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('payroll.partials._company-fields', ['target' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Create Company</button></div>
    </form>
</div></div></div>
