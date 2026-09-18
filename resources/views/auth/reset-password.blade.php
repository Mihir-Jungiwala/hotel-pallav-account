@extends('layouts.app')
@section('title', 'Choose a new password')
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

        <h1 class="auth-title">New password</h1>
        <p class="auth-lead">Enter the code sent to {{ $maskedEmail }}, then choose a password.</p>

        @if(session('success'))
            <div class="auth-note ok"><i class="bi bi-envelope-check"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @error('code')
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label" for="code">Code from your email</label>
                <input id="code" name="code" class="form-control otp-input" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus data-otp>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required data-strength>
                    <button type="button" class="reveal-btn" data-reveal="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
                @error('password')<div class="text-danger" style="font-size:12.5px;">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Repeat password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-p w-100 py-2" data-busy-label="Saving...">Set password and sign out other devices</button>
        </form>

        <div class="auth-foot">
            <i class="bi bi-shield-lock"></i>
            {{ \App\Support\PasswordPolicy::MESSAGE }}
        </div>
    </div>
</div>
@endsection
