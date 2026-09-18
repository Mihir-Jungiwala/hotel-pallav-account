@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="auth-mark">HP</span>
            <div>
                <div class="auth-name">Hotel Pallav</div>
                <div class="auth-sub">Management Suite</div>
            </div>
        </div>

        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-lead">Sign in with your username.</p>

        @if(session('success'))
            <div class="auth-note ok"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @error('username')
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input id="username" name="username" class="form-control @error('username') is-invalid @enderror"
                           value="{{ old('username') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-baseline">
                    <label class="form-label" for="password">Password</label>
                    <a href="{{ route('password.request') }}" class="auth-link">Forgot password?</a>
                </div>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required>
                    <button type="button" class="reveal-btn" data-reveal="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn-p w-100 py-2" data-busy-label="Checking...">Continue</button>
        </form>

        <div class="auth-foot">
            <i class="bi bi-shield-lock"></i>
            One device at a time. {{ \App\Support\PasswordPolicy::MAX_ATTEMPTS }} wrong tries block the account until an administrator unblocks it.
        </div>
    </div>
</div>
@endsection
