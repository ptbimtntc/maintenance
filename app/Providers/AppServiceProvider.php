<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dev port-forwarding proxies (e.g. GitHub Codespaces) rewrite the
        // Host header to the local upstream address before it reaches PHP's
        // built-in server, which would otherwise make Laravel generate
        // redirect/asset URLs pointing at "localhost" instead of the public
        // forwarded URL. Forcing the root URL from APP_URL keeps generated
        // URLs correct regardless of what Host header the proxy sends.
        if (config('app.env') === 'local' && config('app.url') !== 'http://localhost') {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme(parse_url(config('app.url'), PHP_URL_SCHEME));

            // Pagination builds its "current path" from the raw request URL
            // rather than the forced root URL above, so it needs its own fix
            // or "Next page" links break the same way for the same reason.
            Paginator::currentPathResolver(function () {
                return url()->to(request()->getPathInfo());
            });
        }
    }
}
