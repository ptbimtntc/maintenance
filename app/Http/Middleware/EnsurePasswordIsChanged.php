<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts provisioned with a starting password (see the
 * app:provision-employee-users command) can only reach the change-password page,
 * where they set their own password, until they've done so.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change', 'password.update', 'logout', 'face-login.enroll', 'face-login.disable')) {
            return redirect()->route('password.change')
                ->with('status', 'password-must-change');
        }

        return $next($request);
    }
}
