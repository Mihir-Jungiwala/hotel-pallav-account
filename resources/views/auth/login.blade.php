@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh; margin:-24px;">
    <div class="card p-4" style="max-width:400px; width:100%;">
        <div class="text-center mb-3">
            <div class="brand-font" style="font-size:24px; font-weight:700; color:var(--p800);">🏨 Hotel Pallav</div>
            <div class="text-muted" style="font-size:13.5px;">Management Suite Login</div>
        </div>
        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-p w-100 py-2">Sign In</button>
        </form>
        <div class="text-center mt-3">
            <a href="{{ route('password.request') }}" style="color:var(--p700); font-size:13.5px; font-weight:600;">Forgot password?</a>
        </div>
    </div>
</div>
@endsection
