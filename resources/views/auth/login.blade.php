@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<div class="login-wrap">
    <div class="login-card">
        <div class="login-side">
            <div class="mb-3"><i class="bi bi-shield-check" style="font-size: 2.5rem;"></i></div>
            <h1>Student Violation Monitoring &amp; Management System</h1>
            <p class="mb-1">{{ $appSettings['school_name'] ?? '' }}</p>
            <p class="mb-0">{{ $appSettings['council_name'] ?? '' }}</p>
            <hr class="border-light opacity-25 my-4">
            <p class="small mb-0"><i class="bi bi-lock me-1"></i>
                This system holds personal and disciplinary information about students. Access is limited to authorized
                personnel and every action is recorded. Handling of this data is governed by the Data Privacy Act of 2012 (RA 10173).
            </p>
        </div>

        <div class="login-form">
            <h2 class="h4 mb-1" style="color: var(--svms-navy);">Sign in</h2>
            <p class="text-muted mb-4">Use the account issued by the Student Council administrator.</p>

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf
                <x-field name="email" label="Email address" type="email" :required="true" autocomplete="username" autofocus />
                <x-field name="password" label="Password" type="password" :required="true" autocomplete="current-password" />

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label" for="remember">Keep me signed in on this device</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
            </form>

            <p class="small text-muted mt-4 mb-0">
                Forgot your password? Ask the administrator to issue a temporary one.
                @if(!empty($appSettings['dpo_contact']))
                    <br>Data Protection Officer: {{ $appSettings['dpo_contact'] }}
                @endif
            </p>
        </div>
    </div>
</div>
@endsection
