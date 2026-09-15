<?php

namespace App\Http\Controllers;

use App\Enums\MenuKey;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Administrator-only screen (PermissionName::ManageUsers) to manage which
 * users can access the app, what role/position they act as, and - on top
 * of that role - which menus they're individually allowed to edit rather
 * than just view. This is the "who can do what" control the brief asked
 * for, separate from the (also role-gated) master data screens.
 */
class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles', 'employee.position', 'employee.department'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => RoleName::cases(),
            'unlinkedEmployees' => Employee::query()
                ->whereNull('user_id')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number']),
        ]);
    }

    /**
     * Creates the login account, optionally linking it to an existing
     * employee record (and therefore its position) in the same step -
     * linking can also be done later from the Employee form's own "Linked
     * User" field, so this is a convenience, not the only way to do it.
     * Email is marked verified immediately: an Administrator creating the
     * account is already vouching for it, so there's no self-registration
     * flow (and no email deliverability) to gate access behind.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::in(array_map(fn (RoleName $r) => $r->value, RoleName::cases()))],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($data['role']);

        if (! empty($data['employee_id'])) {
            Employee::whereKey($data['employee_id'])->whereNull('user_id')->update(['user_id' => $user->id]);
        }

        return redirect()->route('admin.users.edit', $user)->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $user->load(['roles', 'menuPermissions', 'employee.position', 'employee.department']);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => RoleName::cases(),
            'menus' => MenuKey::all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (RoleName $r) => $r->value, RoleName::cases()))],
            'menus' => ['array'],
            'menus.*' => ['boolean'],
        ]);

        $user->syncRoles([$data['role']]);

        foreach (MenuKey::all() as $menu) {
            $user->menuPermissions()->updateOrCreate(
                ['menu_key' => $menu->value],
                ['can_edit' => $request->boolean("menus.{$menu->value}")]
            );
        }

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated.');
    }
}
