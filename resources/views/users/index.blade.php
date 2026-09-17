@extends('layouts.app')
@section('title', 'User Accounts')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus"></i> Add User</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td><span class="badge-p px-3 py-2 rounded-pill">{{ $u->role }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('users.toggle-active', $u) }}">
                            @csrf
                            <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $u->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#roleModal{{ $u->id }}"><i class="bi bi-pencil"></i></button>
                        @if($u->name !== 'SuperAdmin')
                        <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Delete this user?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>

                <div class="modal fade" id="roleModal{{ $u->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('users.update-role', $u) }}">
                                @csrf @method('PATCH')
                                <div class="modal-header"><h5 class="modal-title">Update Role &mdash; {{ $u->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <select name="role" class="form-select">
                                        @foreach(['Admin','Editor','Viewer'] as $role)
                                            <option value="{{ $role }}" @selected($u->role === $role)>{{ $role }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-p">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No users yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.register') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">New User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Full Name</label><input name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="Viewer">Viewer</option>
                            <option value="Editor">Editor</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
                    <div class="form-text">Min 8 characters, with uppercase, lowercase, number and special character.</div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-p">Create Account</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
