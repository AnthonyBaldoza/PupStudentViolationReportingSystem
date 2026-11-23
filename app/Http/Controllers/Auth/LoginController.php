<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        // Disabled accounts cannot sign in (is_active is part of the credentials query).
        $credentials = [
            'email' => strtolower($request->string('email')->toString()),
            'password' => $request->input('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey());
            $audit->log('auth.failed', null, null, ['email' => $credentials['email']]);

            // Same message for wrong password, unknown email and disabled account (no user enumeration).
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records, or the account is disabled.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate(); // prevents session fixation

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
