<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\SidebarMenu;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administrator-only screen (PermissionName::ManageUsers) to show or hide
 * sidebar menus for individual users or many at once, so menus still being
 * built or refined can be kept away from employees until they're ready.
 */
class MenuVisibilityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'role']);

        $users = User::query()
            ->with(['roles', 'hiddenMenus', 'employee.position'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->orderBy('name')
            ->paginate(200)
            ->appends($filters);

        return view('admin.menu-visibility.index', [
            'users' => $users,
            'roles' => RoleName::cases(),
            'groups' => SidebarMenu::grouped(),
            'filters' => $filters,
        ]);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['hide', 'show'])],
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'exists:users,id'],
            'menus' => ['required', 'array', 'min:1'],
            'menus.*' => [Rule::enum(SidebarMenu::class)],
        ], [
            'users.required' => 'Pilih minimal satu user.',
            'menus.required' => 'Pilih minimal satu menu.',
        ]);

        $users = User::whereIn('id', $data['users'])->get();

        foreach ($users as $user) {
            foreach ($data['menus'] as $key) {
                if ($data['action'] === 'hide') {
                    $user->hiddenMenus()->firstOrCreate(['menu_key' => $key]);
                } else {
                    $user->hiddenMenus()->where('menu_key', $key)->delete();
                }
            }
        }

        $verb = $data['action'] === 'hide' ? 'disembunyikan dari' : 'ditampilkan untuk';

        return back()->with('status', count($data['menus']).' menu '.$verb.' '.$users->count().' user.');
    }

    public function edit(User $user): View
    {
        $user->load(['roles', 'hiddenMenus', 'employee.position']);

        return view('admin.menu-visibility.edit', [
            'user' => $user,
            'groups' => SidebarMenu::grouped(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'visible' => ['array'],
            'visible.*' => [Rule::enum(SidebarMenu::class)],
        ]);

        $visible = $data['visible'] ?? [];

        foreach (SidebarMenu::cases() as $menu) {
            if (in_array($menu->value, $visible, true)) {
                $user->hiddenMenus()->where('menu_key', $menu->value)->delete();
            } else {
                $user->hiddenMenus()->firstOrCreate(['menu_key' => $menu->value]);
            }
        }

        return redirect()->route('admin.menu-visibility.edit', $user)->with('status', 'Menu visibility updated.');
    }
}
