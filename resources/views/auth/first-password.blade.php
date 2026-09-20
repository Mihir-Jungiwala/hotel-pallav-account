@extends('layouts.app')
@section('title', 'Choose your password')
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

        <h1 class="auth-title">Choose your password</h1>
        <p class="auth-lead">
            Welcome, {{ $user->name }}. The password we emailed you is temporary.
            Replace it with one only you know, then sign in with it.
        </p>

        @if(session('success'))
            <div class="auth-note ok"><i class="bi bi-envelope-check"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @error('current_password')
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('password.first.update') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label" for="current_password">Temporary password from your email</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input id="current_password" type="password" name="current_password" class="form-control"
                           autocomplete="current-password" required autofocus>
                    <button type="button" class="reveal-btn" data-reveal="#current_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
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
                <label class="form-label" for="password_confirmation">Repeat new password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-p w-100 py-2" data-busy-label="Saving...">Save password and sign in</button>
        </form>

        <div class="auth-foot">
            <i class="bi bi-shield-lock"></i>
            {{ \App\Support\PasswordPolicy::MESSAGE }}
        </div>
    </div>
</div>
@endsection
