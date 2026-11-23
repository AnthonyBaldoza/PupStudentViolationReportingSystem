<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created or reset by an admin carry a temporary password and must set their own
 * before using anything else. The password/logout routes sit outside this middleware group.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.edit')
                ->with('warning', 'For your security, please set a new password before continuing.');
        }

        return $next($request);
    }
}
