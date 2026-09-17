<?php

namespace App\Http\Middleware;

use App\Enums\MenuKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every create/update/delete route for a given menu behind the
 * per-user edit permission (User::canEditMenu()), on top of whatever
 * role-based permission the route/controller already requires. Applied as
 * route middleware: ->middleware('menu.edit:'.MenuKey::Employees->value).
 */
class EnsureMenuEditPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        abort_unless($request->user()?->canEditMenu(MenuKey::from($menuKey)), 403);

        return $next($request);
    }
}
