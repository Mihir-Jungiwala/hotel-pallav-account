@extends('layouts.app')
@section('title', 'Reset password')
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

        <h1 class="auth-title">Forgot your password?</h1>
        <p class="auth-lead">Enter your username. If your account has an email address, we'll send a reset link valid for 10 minutes.</p>

        @if(session('success'))
            <div class="auth-note ok"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input id="username" name="username" class="form-control" autocomplete="username" autocapitalize="none" required autofocus>
                </div>
            </div>
            <button class="btn btn-p w-100 py-2" data-busy-label="Sending…">Send reset link</button>
        </form>

        <div class="auth-foot">
            <a href="{{ route('login') }}" class="auth-link"><i class="bi bi-arrow-left"></i> Back to sign in</a>
        </div>
    </div>
</div>
@endsection
