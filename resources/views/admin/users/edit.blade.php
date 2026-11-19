<x-app-layout>
    <x-slot name="header">Manage User — {{ $user->name }}</x-slot>

    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            <h3 class="text-sm font-semibold text-neutral-500">{{ $user->name }}</h3>
            <p class="text-sm text-neutral-600">{{ $user->email }}</p>
            @if ($user->employee)
                <p class="mt-1 text-sm text-neutral-500">
                    {{ $user->employee->position?->title ?? 'No position' }}
                    @if ($user->employee->department)
                        &middot; {{ $user->employee->department->name }}
                    @endif
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                <x-input-label for="role" value="Role" />
                <select id="role" name="role" class="mt-1 block w-full rounded-md border-neutral-300 text-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($user->hasRole($role->value))>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                <h3 class="text-sm font-semibold text-neutral-900">Menu Edit Permissions</h3>

                @if ($user->hasRole(\App\Enums\RoleName::Administrator->value))
                    <p class="mt-2 text-sm text-neutral-500">Administrators always have full edit access everywhere — these checkboxes have no effect for this user.</p>
                @elseif ($user->hasRole(\App\Enums\RoleName::Guest->value))
                    <p class="mt-2 text-sm text-neutral-500">Guest is always read-only everywhere, regardless of these checkboxes.</p>
                @else
                    <p class="mt-1 text-sm text-neutral-500">Every menu defaults to read-only except Job Descriptions. Check a box to let this user create/edit/delete in that menu.</p>
                @endif

                <div class="mt-4 space-y-3">
                    @foreach ($menus as $menu)
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="menus[{{ $menu->value }}]" value="1"
                                   class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                                   @checked($user->canEditMenu($menu))>
                            <span class="text-sm text-neutral-700">{{ $menu->label() }}</span>
                            @if ($menu->editableByDefault())
                                <span class="text-xs text-neutral-400">(editable by default)</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <x-button variant="secondary" href="{{ route('admin.users.index') }}">Cancel</x-button>
                <x-button type="submit">Save</x-button>
            </div>
        </form>

        @role(\App\Enums\RoleName::Administrator->value)
            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                <h3 class="text-sm font-semibold text-neutral-900">Reset Password</h3>
                <p class="mt-1 text-sm text-neutral-500">Set a new password for this user. They will need to use it on their next login.</p>

                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label for="password" value="New Password" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="Confirm New Password" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <x-button type="submit">Reset Password</x-button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-danger-200 bg-danger-50 p-6 shadow-md">
                <h3 class="text-sm font-semibold text-danger-800">Delete User</h3>
                <p class="mt-1 text-sm text-danger-700">Permanently removes this account. This cannot be undone.</p>

                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4 flex justify-end"
                      onsubmit="return confirm('Delete {{ $user->name }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" :disabled="$user->is(auth()->user())">Delete User</x-button>
                </form>
            </div>
        @endrole
    </div>
</x-app-layout>
