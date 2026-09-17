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

        <h1 class="auth-title">Choose a new password</h1>
        <p class="auth-lead">{{ \App\Support\PasswordPolicy::MESSAGE }}</p>

        @if($errors->any())
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ old('email', $email) }}">

            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required data-strength>
                    <button type="button" class="reveal-btn" data-reveal="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>
            <button class="btn btn-p w-100 py-2" data-busy-label="Saving…">Update password</button>
        </form>
    </div>
</div>
@endsection
