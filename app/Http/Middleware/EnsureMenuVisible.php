<?php

namespace App\Http\Middleware;

use App\Enums\SidebarMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks direct URL access to a menu an Administrator has hidden from the
 * signed-in user (see User::isMenuHidden()), so hiding is more than cosmetic.
 */
class EnsureMenuVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $menu = SidebarMenu::forRoute($request->route()?->getName());

        if ($menu && $request->user()?->isMenuHidden($menu)) {
            return redirect()->route('dashboard')->with('status', 'Menu "'.$menu->label().'" belum tersedia untuk Anda.');
        }

        return $next($request);
    }
}
