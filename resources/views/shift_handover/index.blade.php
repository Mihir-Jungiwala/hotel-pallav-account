@extends('layouts.app')
@section('title', 'Shift Handover')
@section('content')
@php use App\Support\UnitContext; @endphp

<div class="d-flex align-items-center gap-2 mb-3">
    <span class="unit-tag me-auto"><i class="bi bi-eye"></i> {{ UnitContext::label() }}</span>
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addShift"><i class="bi bi-plus-lg"></i> New Handover</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th>@if(UnitContext::isBoth())<th>Business</th>@endif<th>Shift</th><th>Handed By</th><th>Total</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($records as $r)
                <tr>
                    <td>{{ optional($r->date)->format('d-m-Y') }} {{ substr((string) $r->time, 0, 5) }}</td>
                    @if(UnitContext::isBoth())<td><span class="unit-tag {{ optional($r->businessUnit)->slug === 'food' ? 'food' : '' }}"><i class="bi {{ optional($r->businessUnit)->icon ?? 'bi-building' }}"></i> {{ optional($r->businessUnit)->name ?? '—' }}</span></td>@endif
                    <td>{{ $r->shift }}</td>
                    <td>{{ $r->full_name }}</td>
                    <td>₹{{ number_format($r->total, 2) }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('shift-handover.view', $r) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editShift{{ $r->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('shift-handover.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Delete this record?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editShift{{ $r->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('shift-handover.update', $r) }}" class="shift-form">
                                @csrf @method('PUT')
                                <div class="modal-header"><h5 class="modal-title">Edit Shift Handover</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">@include('shift_handover._fields', ['record' => $r])</div>
                                <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No shift handovers recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addShift" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('shift-handover.store') }}" class="shift-form">
                @csrf
                <div class="modal-header"><h5 class="modal-title">New Shift Handover</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">@include('shift_handover._fields', ['record' => null])</div>
                <div class="modal-footer"><button class="btn btn-p">Save</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.shift-form').forEach(function(form){
    const inputs = form.querySelectorAll('.denom-count');
    const totalEl = form.closest('.modal-content').querySelector('#grandTotal');
    function recalc(){
        let total = 0;
        inputs.forEach(function(input){
            total += (parseInt(input.value) || 0) * parseFloat(input.dataset.value);
        });
        totalEl.textContent = total.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    inputs.forEach(function(input){ input.addEventListener('input', recalc); });
});
</script>
@endpush
@endsection
