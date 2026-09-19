@extends('payroll.layout', [
    'title' => 'Deduction Management',
    'subtitle' => 'The deductions this company can apply - food, uniform, accommodation and the like. Each one is defined once here, then assigned to individual employees on their staff record.',
])

@section('page-actions')
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addDeduction" data-write-only>
        <i class="bi bi-plus-lg"></i> Add Deduction
    </button>
@endsection

@section('page')

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="deductionTable" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th data-sort="text">Deduction</th>
                    <th data-sort="num" class="money">Standard amount</th>
                    <th data-sort="num" class="text-center">Assigned to</th>
                    <th data-sort="text">Active</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($deductions as $row)
                <tr data-row="{{ $row->name }}">
                    <td class="sno"></td>
                    <td class="cell-main">{{ $row->name }}</td>
                    <td class="money" data-sort-value="{{ $row->amount }}">₹{{ number_format($row->amount, 2) }}</td>
                    <td class="text-center" data-sort-value="{{ $row->assignments_count }}">
                        @if($row->assignments_count)
                            <span class="badge-p px-2 py-1 rounded">{{ $row->assignments_count }} {{ Str::plural('employee', $row->assignments_count) }}</span>
                        @else
                            <span class="text-muted">Nobody yet</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('payroll.deduction.toggle-active', $row) }}" data-status-toggle>@csrf
                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $row->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editDeduction{{ $row->id }}"
                                data-open-record title="Open record"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.deduction.destroy', $row) }}" class="d-inline"
                              data-confirm="Delete the deduction &quot;{{ $row->name }}&quot;?">
                            @csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-dash-circle"></i></div>
                            <div class="es-title">No deductions configured</div>
                            <div class="es-text">Define a deduction here first; you can then assign it, with its own amount, to any employee on their staff record.</div>
                            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addDeduction" data-write-only>
                                <i class="bi bi-plus-lg"></i> Add the first deduction
                            </button>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($deductions as $row)
    <div class="modal fade pay-form-modal" id="editDeduction{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.deduction.update', $row) }}">@csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">Deduction</div>
                        <h5 class="modal-title">{{ $row->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._deduction-fields', ['target' => $row])</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal" id="addDeduction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.deduction.store') }}">@csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New &middot; {{ $company->name }}</div>
                    <h5 class="modal-title">Add Deduction</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">@include('payroll.partials._deduction-fields', ['target' => null])</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Add Deduction</button>
            </div>
        </form>
    </div></div>
</div>

@endsection
