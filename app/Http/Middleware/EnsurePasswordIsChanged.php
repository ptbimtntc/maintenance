<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts provisioned with a starting password (see the
 * app:provision-employee-users command) can only reach the profile page,
 * where they set their own password, until they've done so.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('profile.edit', 'password.update', 'logout')) {
            return redirect()->route('profile.edit')
                ->with('status', 'password-must-change');
        }

        return $next($request);
    }
}
