<?php

use App\Enums\RoleName;
use App\Http\Middleware\EnsureMenuEditPermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'menu.edit' => EnsureMenuEditPermission::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePasswordIsChanged::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureMenuVisible::class);

        // Anyone hitting a protected page without a session lands as the
        // read-only Guest account instead of a login wall - see
        // GuestSessionController and the "Login" button in the topbar.
        $middleware->redirectGuestsTo(fn () => route('guest-login'));

        // face_trusted_device is a plain random token the server hashes and
        // compares itself (see FaceLoginController) - it's already
        // unguessable, and framework cookie-encryption would need decrypting
        // before that lookup could ever match, for no added protection.
        $middleware->encryptCookies(except: ['face_trusted_device']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A link from an email (task, certificate, ...) opened without a
        // session lands on the shared Guest account (redirectGuestsTo above),
        // which usually may not see that record. Instead of a 403, drop the
        // Guest session and send the visitor to log in; LoginRequest's
        // redirect()->intended() then brings them back to the link.
        // Real accounts that lack access still get the normal 403.
        $exceptions->render(function (Throwable $e, Request $request) {
            $forbidden = $e instanceof AuthorizationException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403);

            if (! $forbidden || ! $request->isMethod('GET') || $request->expectsJson() || ! $request->hasSession()) {
                return null;
            }

            // Only pages that require an account. Public signed links (LOTOTO
            // follow-up/feedback, verification pages, QR form) keep their own
            // 403 explaining why the link does not open.
            $middleware = $request->route()?->gatherMiddleware() ?? [];
            $requiresLogin = collect($middleware)->contains(
                fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')),
            );

            if (! $requiresLogin) {
                return null;
            }

            $user = $request->user();

            if ($user && ! $user->hasRole(RoleName::Guest->value)) {
                return null;
            }

            if ($user) {
                Auth::guard('web')->logout();
                $request->session()->regenerate(true);
                $request->session()->regenerateToken();
            }

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login')
                ->with('status', 'Please log in with your own account to open that page.');
        });
    })->create();
