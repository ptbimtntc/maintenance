<?php

namespace App\Providers;

use App\Enums\MenuKey;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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

        // Retrofits every existing role-permission check for a "Manage*"
        // permission (route `can:` middleware, $this->authorize(...), and
        // Blade @can(...)) with the per-user menu-edit override, in one
        // place, rather than editing each of those call sites individually.
        // Only affects abilities mapped in MenuKey::forManagePermission() -
        // every other ability (including model policies like
        // EmployeePolicy::view/update) passes through untouched (null).
        //
        // - A role-based grant (Manager has ManageEmployees, say) can be
        //   narrowed to a denial if the admin has switched that user's menu
        //   override off.
        // - A role-based denial can be turned into a grant only for a menu
        //   that defaults to editable (currently just Job Descriptions),
        //   so staff without ManageJobDescriptions can still edit their own
        //   job description unless an admin explicitly revokes it. This
        //   never escalates any other menu, since their defaults are false.
        Gate::after(function (User $user, string $ability, ?bool $result) {
            $menu = MenuKey::forManagePermission($ability);

            if (! $menu) {
                return null;
            }

            if ($result) {
                return $user->canEditMenu($menu);
            }

            return $user->canEditMenu($menu) ? true : null;
        });
    }
}
