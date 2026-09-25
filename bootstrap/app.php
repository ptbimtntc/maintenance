<?php

use App\Http\Middleware\EnsureMenuEditPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'menu.edit' => EnsureMenuEditPermission::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePasswordIsChanged::class);

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
    })->create();
