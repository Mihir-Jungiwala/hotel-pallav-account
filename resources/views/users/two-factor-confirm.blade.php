@extends('layouts.app')
@section('title', 'Switch on the sign-in code')

@section('content')

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">Security</div>
        <h2 class="pms-title">Confirm your email</h2>
        <p class="pms-sub">The sign-in code only switches on once you enter the code we just sent.</p>
    </div>
    <a class="btn btn-outline-p" href="{{ route('profile.show') }}"><i class="bi bi-x-lg"></i> Not now</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card reveal">
            <div class="card-header">Code from your email</div>
            <div class="card-body">
                @error('code')
                    <div class="auth-note err mb-3"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                @enderror

                <form method="POST" action="{{ route('profile.two-factor.check') }}" data-self-service novalidate>
                    @csrf
                    <label class="form-label" for="code">Enter the six digits sent to {{ $user->email }}</label>
                    <input id="code" name="code" class="form-control otp-input" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus data-otp>

                    <button type="submit" class="btn btn-p w-100 py-2 mt-3" data-busy-label="Checking...">Switch it on</button>
                </form>

                <form method="POST" action="{{ route('profile.two-factor') }}" class="mt-2" data-self-service>
                    @csrf
                    <button class="btn btn-outline-p w-100" @disabled($secondsUntilResend > 0) data-resend-at="{{ $secondsUntilResend }}">
                        <i class="bi bi-arrow-repeat"></i>
                        <span data-resend-label>{{ $secondsUntilResend > 0 ? 'Send another code in '.$secondsUntilResend.'s' : 'Send another code' }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card reveal h-100">
            <div class="card-header">What changes</div>
            <div class="card-body">
                <div class="mini-row">
                    <span class="mini-icon"><i class="bi bi-1-circle"></i></span>
                    <span class="mini-body">You sign in with your username and password as usual.</span>
                </div>
                <div class="mini-row">
                    <span class="mini-icon"><i class="bi bi-2-circle"></i></span>
                    <span class="mini-body">We email a six digit code, good for {{ \App\Services\Auth\OneTimeCode::VALID_MINUTES }} minutes.</span>
                </div>
                <div class="mini-row">
                    <span class="mini-icon"><i class="bi bi-3-circle"></i></span>
                    <span class="mini-body">Enter it and you are in. Five wrong codes block the account.</span>
                </div>

                <div class="master-note mt-3">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        If you later lose access to {{ $user->email }}, an administrator can switch the code off for you.
                        You can also turn it off yourself at any time from My Profile.
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
