@extends('layouts.app')
@section('title', 'Set New Password')
@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; margin:-24px;">
    <div class="card p-4" style="max-width:440px; width:100%;">
        <div class="text-center mb-3">
            <div class="brand-font" style="font-size:22px; font-weight:700; color:var(--p800);">Choose a new password</div>
        </div>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" required>
                <div class="form-text">Min 8 characters, with uppercase, lowercase, number and special character.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <button class="btn btn-p w-100 py-2">Update Password</button>
        </form>
    </div>
</div>
@endsection
