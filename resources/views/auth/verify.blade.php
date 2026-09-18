@extends('layouts.app')
@section('title', 'Enter your code')
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

        <h1 class="auth-title">Check your email</h1>
        <p class="auth-lead">Enter the code we emailed you. It works for {{ \App\Services\Auth\OneTimeCode::VALID_MINUTES }} minutes and only once.</p>

        @if(session('success'))
            <div class="auth-note ok"><i class="bi bi-envelope-check"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @error('code')
            <div class="auth-note err"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('login.verify.check') }}" novalidate>
            @csrf
            <label class="form-label" for="code">Your code</label>
            <input id="code" name="code" class="form-control otp-input" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus data-otp>
            <div class="form-text">{{ $triesLeft }} {{ $triesLeft === 1 ? 'try' : 'tries' }} left before the account locks.</div>

            <button type="submit" class="btn btn-p w-100 py-2 mt-3" data-busy-label="Checking...">Sign in</button>
        </form>

        <form method="POST" action="{{ route('login.verify.resend') }}" class="mt-2">
            @csrf
            <button class="btn btn-outline-p w-100" @disabled($secondsUntilResend > 0)
                    data-resend-at="{{ $secondsUntilResend }}">
                <i class="bi bi-arrow-repeat"></i>
                <span data-resend-label>{{ $secondsUntilResend > 0 ? 'Send another code in '.$secondsUntilResend.'s' : 'Send another code' }}</span>
            </button>
        </form>

        <div class="auth-foot">
            <a class="auth-link" href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Use a different account</a>
        </div>
    </div>
</div>
@endsection
