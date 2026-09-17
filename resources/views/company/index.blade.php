@extends('layouts.app')
@section('title', 'Company Profiles')
@section('content')

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addCompanyModal"><i class="bi bi-building-add"></i> Add Company</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>GST No.</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($companies as $company)
                <tr>
                    <td class="fw-semibold">{{ $company->name }}</td>
                    <td>{{ $company->email }}</td>
                    <td>{{ $company->mobile_number }}</td>
                    <td>{{ $company->gst_number }}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editCompany{{ $company->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('company.destroy', $company) }}" class="d-inline" onsubmit="return confirm('Delete this company profile?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editCompany{{ $company->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('company.update', $company) }}">
                                @csrf @method('PUT')
                                <div class="modal-header"><h5 class="modal-title">Edit {{ $company->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">@include('company._fields', ['company' => $company])</div>
                                <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No companies added yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('company.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Add Company Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">@include('company._fields', ['company' => null])</div>
                <div class="modal-footer"><button class="btn btn-p">Create Company</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
