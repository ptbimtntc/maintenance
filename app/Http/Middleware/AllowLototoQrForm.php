<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The LOTOTO form opens from a lock's QR code without a login: a visitor without an account (or on the shared
 * read-only Guest account) passes when the URL carries a valid QR signature, and the form then acts for the
 * employee or spare lock the QR identifies (see LototoController::actingEmployee). Every other request goes
 * through the same auth, verified and can:ViewSafety checks as before.
 */
class AllowLototoQrForm
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::isQrVisitor($request)) {
            return $next($request);
        }

        return app(Authenticate::class)->handle($request, fn ($request) => app(EnsureEmailIsVerified::class)->handle(
            $request,
            fn ($request) => app(Authorize::class)->handle($request, $next, PermissionName::ViewSafety->value),
        ));
    }

    /** Absolute URL whose signature covers the path and query only, so the QR keeps working behind another host or scheme. */
    public static function url(string $routeName, array $parameters): string
    {
        return url(URL::signedRoute($routeName, $parameters, absolute: false));
    }

    /** No account, or the shared Guest account, on a URL signed by the QR code (path and query only, so the host may differ). */
    public static function isQrVisitor(Request $request): bool
    {
        $user = $request->user();

        return ($user === null || $user->hasRole(RoleName::Guest->value))
            && $request->hasValidSignature(false);
    }
}
