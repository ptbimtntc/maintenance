<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Log in from the floating modal in the top bar, so the visitor stays on
     * the page they're viewing. Reachable by anonymous visitors and by the
     * shared Guest account (which the "guest" middleware on POST /login
     * would turn away); any real account is already logged in.
     */
    public function storeModal(LoginRequest $request): JsonResponse|RedirectResponse
    {
        abort_if(Auth::check() && ! Auth::user()->hasRole(RoleName::Guest->value), 403);

        // A failed attempt throws before touching the session, so the
        // visitor keeps their current Guest session on a wrong password.
        $request->authenticate();

        $request->session()->regenerate();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->back();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Not redirect('/') - since guests are now auto-logged into the
        // read-only Guest account (see bootstrap/app.php's
        // redirectGuestsTo), landing on "/" here would immediately log
        // the visitor straight back in instead of showing the login form.
        return redirect()->route('login');
    }
}
