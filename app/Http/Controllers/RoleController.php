<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Administrator-only screen (PermissionName::ManageUsers) to edit which
 * permissions each role grants, and to bulk-reset roles back to the
 * application's baseline permission set (RoleName::defaultPermissions()) -
 * previously only doable by re-running RolesAndPermissionsSeeder from the
 * CLI.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get()
            ->keyBy('name');

        return view('admin.roles.index', [
            'roleNames' => RoleName::cases(),
            'roles' => $roles,
            'permissionGroups' => PermissionName::grouped(),
        ]);
    }

    /**
     * Save a single role's custom permission set.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionName::all())],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', "\"{$role->name}\" permissions updated.");
    }

    /**
     * Bulk-reset the selected roles (or every role, if none selected) back
     * to their baseline permission set. This is the in-app equivalent of
     * re-running RolesAndPermissionsSeeder, without touching the CLI.
     */
    public function resetDefaults(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(array_map(fn (RoleName $r) => $r->value, RoleName::cases()))],
        ]);

        $targetRoleNames = ! empty($data['roles'])
            ? array_filter(RoleName::cases(), fn (RoleName $r) => in_array($r->value, $data['roles'], true))
            : RoleName::cases();

        foreach ($targetRoleNames as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions($roleName->defaultPermissions());
        }

        $count = count($targetRoleNames);
        $label = $count === 1 ? reset($targetRoleNames)->label() : "{$count} roles";

        return redirect()->route('admin.roles.index')->with('status', "Reset {$label} to default permissions.");
    }
}
