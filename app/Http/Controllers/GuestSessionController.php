<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestSessionController extends Controller
{
    /**
     * Logs the visitor into the single shared, read-only Guest account
     * (seeded by DemoUsersSeeder) - no credentials required. The Guest role
     * holds only View* permissions, and User::canEditMenu() additionally
     * hard-blocks the role regardless of any menu override, so this can
     * never become a write session no matter what.
     *
     * This is now the default entry point for unauthenticated visitors
     * (see the "/" route and the auth middleware's guest redirect), not
     * just an opt-in button - anyone who hasn't logged in lands here.
     */
    public function start(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        $guest = User::whereHas('roles', fn ($q) => $q->where('name', RoleName::Guest->value))->first();

        abort_unless($guest, 404, 'No guest account has been configured.');

        Auth::login($guest);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
