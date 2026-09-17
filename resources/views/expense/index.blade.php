@extends('layouts.app')
@section('title', 'Expenses')
@section('content')

<div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addHotelWithdraw"><i class="bi bi-plus-lg"></i> Hotel Withdrawal</button>
    <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#addFoodWithdraw"><i class="bi bi-plus-lg"></i> Food Withdrawal</button>
    <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#addHotelMisc"><i class="bi bi-plus-lg"></i> Hotel Misc. Expense</button>
    <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#addFoodMisc"><i class="bi bi-plus-lg"></i> Food Misc. Expense</button>
    <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#addStaffAdvance"><i class="bi bi-plus-lg"></i> Staff Advance</button>
</div>

<div class="card mb-4">
    <div class="card-header">Hotel Cash Withdrawals</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Withdrawer</th><th>Amount</th><th>By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($hotelWithdrawals as $w)
            <tr><td>{{ optional($w->date)->format('d-m-Y') }} {{ $w->time }}</td><td>{{ $w->withdrawer }}</td><td>₹{{ number_format($w->amount,2) }}</td><td>{{ $w->full_name }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-p" href="{{ route('expense.hotel-withdrawal.view',$w) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                    <form method="POST" action="{{ route('expense.hotel-withdrawal.destroy',$w) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No records.</td></tr>
        @endforelse
        </tbody></table></div>
</div>

<div class="card mb-4">
    <div class="card-header">Food Cash Withdrawals</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Withdrawer</th><th>Amount</th><th>By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($foodWithdrawals as $w)
            <tr><td>{{ optional($w->date)->format('d-m-Y') }} {{ $w->time }}</td><td>{{ $w->withdrawer }}</td><td>₹{{ number_format($w->amount,2) }}</td><td>{{ $w->full_name }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-p" href="{{ route('expense.food-withdrawal.view',$w) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                    <form method="POST" action="{{ route('expense.food-withdrawal.destroy',$w) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No records.</td></tr>
        @endforelse
        </tbody></table></div>
</div>

<div class="card mb-4">
    <div class="card-header">Hotel Miscellaneous Expenses</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Expense</th><th>Amount</th><th>By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($hotelMisc as $e)
            <tr><td>{{ optional($e->date)->format('d-m-Y') }} {{ $e->time }}</td><td>{{ $e->expense_name }}</td><td>₹{{ number_format($e->amount,2) }}</td><td>{{ $e->full_name }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-p" href="{{ route('expense.hotel-misc.view',$e) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                    <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editHotelMisc{{ $e->id }}"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('expense.hotel-misc.destroy',$e) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td></tr>
            <div class="modal fade" id="editHotelMisc{{ $e->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('expense.hotel-misc.update',$e) }}">@csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">Edit Hotel Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ optional($e->date)->format('Y-m-d') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ $e->time }}" required></div>
                        <div class="col-12"><label class="form-label">Expense Name</label><input name="expense_name" class="form-control" value="{{ $e->expense_name }}" required></div>
                        <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="{{ $e->amount }}" required></div>
                        <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control">{{ $e->instruction }}</textarea></div>
                    </div></div>
                    <div class="modal-footer"><button class="btn btn-p">Save</button></div>
                </form>
            </div></div></div>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No records.</td></tr>
        @endforelse
        </tbody></table></div>
</div>

<div class="card mb-4">
    <div class="card-header">Food Miscellaneous Expenses</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Expense</th><th>Amount</th><th>By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($foodMisc as $e)
            <tr><td>{{ optional($e->date)->format('d-m-Y') }} {{ $e->time }}</td><td>{{ $e->expense_name }}</td><td>₹{{ number_format($e->amount,2) }}</td><td>{{ $e->full_name }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-p" href="{{ route('expense.food-misc.view',$e) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                    <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editFoodMisc{{ $e->id }}"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('expense.food-misc.destroy',$e) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td></tr>
            <div class="modal fade" id="editFoodMisc{{ $e->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('expense.food-misc.update',$e) }}">@csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">Edit Food Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ optional($e->date)->format('Y-m-d') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ $e->time }}" required></div>
                        <div class="col-12"><label class="form-label">Expense Name</label><input name="expense_name" class="form-control" value="{{ $e->expense_name }}" required></div>
                        <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="{{ $e->amount }}" required></div>
                        <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control">{{ $e->instruction }}</textarea></div>
                    </div></div>
                    <div class="modal-footer"><button class="btn btn-p">Save</button></div>
                </form>
            </div></div></div>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No records.</td></tr>
        @endforelse
        </tbody></table></div>
</div>

<div class="card">
    <div class="card-header">Staff Advance Salaries</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Staff</th><th>Month</th><th>Amount</th><th>By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($staffAdvances as $a)
            <tr><td>{{ optional($a->date)->format('d-m-Y') }} {{ $a->time }}</td><td>{{ optional($a->staff)->name }}</td><td>{{ $a->year_month }}</td><td>₹{{ number_format($a->amount,2) }}</td><td>{{ $a->full_name }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-p" href="{{ route('expense.staff-advance.view',$a) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                    <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editAdvance{{ $a->id }}"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('expense.staff-advance.destroy',$a) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td></tr>
            <div class="modal fade" id="editAdvance{{ $a->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('expense.staff-advance.update',$a) }}">@csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">Edit Staff Advance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ optional($a->date)->format('Y-m-d') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ $a->time }}" required></div>
                        <div class="col-md-6"><label class="form-label">Staff Member</label>
                            <select name="employee_id" class="form-select" required>
                                @foreach($activeStaff as $sp)
                                    <option value="{{ $sp->id }}" @selected($a->employee_id === $sp->id)>{{ $sp->name }} ({{ $sp->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Month (YYYY-MM)</label><input name="year_month" class="form-control" value="{{ $a->year_month }}" required></div>
                        <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="{{ $a->amount }}" required></div>
                        <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control">{{ $a->instruction }}</textarea></div>
                    </div></div>
                    <div class="modal-footer"><button class="btn btn-p">Save</button></div>
                </form>
            </div></div></div>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No records.</td></tr>
        @endforelse
        </tbody></table></div>
</div>

{{-- Add modals --}}
@foreach(['hotel' => ['addHotelWithdraw','expense.hotel-withdrawal.store','Hotel Cash Withdrawal'], 'food' => ['addFoodWithdraw','expense.food-withdrawal.store','Food Cash Withdrawal']] as $w)
<div class="modal fade" id="{{ $w[0] }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route($w[1]) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">{{ $w[2] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
            <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ date('H:i') }}" required></div>
            <div class="col-12"><label class="form-label">Withdrawer</label><input name="withdrawer" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-p">Save</button></div>
    </form>
</div></div></div>
@endforeach

@foreach(['hotel' => ['addHotelMisc','expense.hotel-misc.store','Hotel Miscellaneous Expense'], 'food' => ['addFoodMisc','expense.food-misc.store','Food Miscellaneous Expense']] as $m)
<div class="modal fade" id="{{ $m[0] }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route($m[1]) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">{{ $m[2] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
            <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ date('H:i') }}" required></div>
            <div class="col-12"><label class="form-label">Expense Name</label><input name="expense_name" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control"></textarea></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-p">Save</button></div>
    </form>
</div></div></div>
@endforeach

<div class="modal fade" id="addStaffAdvance" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('expense.staff-advance.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Staff Advance Salary</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
            <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ date('H:i') }}" required></div>
            <div class="col-md-6"><label class="form-label">Staff Member</label>
                <select name="employee_id" class="form-select" required>
                    <option value="">Select&hellip;</option>
                    @foreach($activeStaff as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->name }} ({{ $sp->employee_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Month (YYYY-MM)</label><input name="year_month" class="form-control" value="{{ date('Y-m') }}" required></div>
            <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control"></textarea></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-p">Save</button></div>
    </form>
</div></div></div>
@endsection
