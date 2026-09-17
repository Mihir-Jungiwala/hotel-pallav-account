@extends('layouts.app')
@section('title', 'Reset Password')
@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; margin:-24px;">
    <div class="card p-4" style="max-width:420px; width:100%;">
        <div class="text-center mb-3">
            <div class="brand-font" style="font-size:22px; font-weight:700; color:var(--p800);">Reset your password</div>
            <div class="text-muted" style="font-size:13.5px;">We'll email you a reset link, valid for 10 minutes.</div>
        </div>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <button class="btn btn-p w-100 py-2">Send Reset Link</button>
        </form>
        <div class="text-center mt-3">
            <a href="{{ route('login') }}" style="color:var(--p700); font-size:13.5px; font-weight:600;">Back to login</a>
        </div>
    </div>
</div>
@endsection
