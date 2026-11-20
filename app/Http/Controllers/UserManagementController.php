<?php

namespace App\Http\Controllers;

use App\Enums\MenuKey;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
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
    use \App\Concerns\FiltersByMultipleIds;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $roles = $this->valueList($request->input('role'));
        $departmentIds = $this->idList($request->input('department_id'));
        $positionIds = $this->idList($request->input('position_id'));

        $filters = [
            'search' => $search,
            'role' => $roles,
            'department_id' => $departmentIds,
            'position_id' => $positionIds,
        ];

        $users = User::query()
            ->with(['roles', 'employee.position', 'employee.department'])
            ->when($search !== '', fn ($q) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($roles !== [], fn ($q) => $q->role($roles))
            ->when($departmentIds !== [], fn ($q) => $q->whereHas('employee', fn ($q) => $q->whereIn('department_id', $departmentIds)))
            ->when($positionIds !== [], fn ($q) => $q->whereHas('employee', fn ($q) => $q->whereIn('position_id', $positionIds)))
            ->orderBy('name')
            ->paginate(20)
            ->appends($filters);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => RoleName::cases(),
            'departments' => Department::orderBy('name')->get(),
            'positions' => Position::orderBy('title')->get(),
            'filters' => $filters,
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

    /**
     * Administrator-only ("superadmin"): resets any user's password without
     * needing to know their current one. Gated by the `role:Administrator`
     * route middleware, stricter than the ManageUsers permission the rest of
     * this controller uses, since a non-admin ManageUsers holder shouldn't
     * be able to take over another account.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return redirect()->route('admin.users.edit', $user)->with('status', "Password for {$user->name} has been reset.");
    }

    /**
     * Administrator-only ("superadmin"): deletes a user account outright.
     * Blocked against self-deletion so an admin can't lock themselves out.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()->route('admin.users.index')->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}
