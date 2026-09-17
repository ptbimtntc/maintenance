<x-app-layout>
    <x-slot name="header">Manage User — {{ $user->name }}</x-slot>

    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h3 class="text-sm font-semibold text-gray-500">{{ $user->name }}</h3>
            <p class="text-sm text-gray-600">{{ $user->email }}</p>
            @if ($user->employee)
                <p class="mt-1 text-sm text-gray-500">
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

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <x-input-label for="role" value="Role" />
                <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($user->hasRole($role->value))>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h3 class="text-sm font-semibold text-gray-900">Menu Edit Permissions</h3>

                @if ($user->hasRole(\App\Enums\RoleName::Administrator->value))
                    <p class="mt-2 text-sm text-gray-500">Administrators always have full edit access everywhere — these checkboxes have no effect for this user.</p>
                @elseif ($user->hasRole(\App\Enums\RoleName::Guest->value))
                    <p class="mt-2 text-sm text-gray-500">Guest is always read-only everywhere, regardless of these checkboxes.</p>
                @else
                    <p class="mt-1 text-sm text-gray-500">Every menu defaults to read-only except Job Descriptions. Check a box to let this user create/edit/delete in that menu.</p>
                @endif

                <div class="mt-4 space-y-3">
                    @foreach ($menus as $menu)
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="menus[{{ $menu->value }}]" value="1"
                                   class="rounded border-gray-300 text-slate-900 focus:ring-slate-900"
                                   @checked($user->canEditMenu($menu))>
                            <span class="text-sm text-gray-700">{{ $menu->label() }}</span>
                            @if ($menu->editableByDefault())
                                <span class="text-xs text-gray-400">(editable by default)</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
