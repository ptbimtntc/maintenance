<x-app-layout>
    <x-slot name="header">Roles &amp; Permissions</x-slot>

    <div x-data="{ activeRole: '{{ old('_active_role', $roleNames[0]->value) }}', resetOpen: false }" class="max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
            <p class="max-w-2xl text-sm text-neutral-600">
                Edit which permissions each role grants, then save. If a role has drifted from the app's baseline, use
                <span class="font-medium text-neutral-800">Reset to default</span> to restore its original permission set.
            </p>
            <button type="button" @click="resetOpen = true"
                    class="shrink-0 rounded-md border border-brand-300 bg-brand-50 px-3.5 py-1.5 text-sm font-medium text-brand-700 shadow-sm transition hover:bg-brand-100">
                Reset roles to default…
            </button>
        </div>

        {{-- Bulk reset dialog: pick any subset of roles (or all) and reapply RoleName::defaultPermissions(). --}}
        <div x-show="resetOpen" x-cloak
             class="fixed inset-0 z-30 flex items-center justify-center bg-neutral-900/40 px-4"
             @keydown.escape.window="resetOpen = false">
            <div @click.outside="resetOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-sm font-semibold text-neutral-900">Reset roles to default permissions</h3>
                <p class="mt-1 text-sm text-neutral-500">
                    This overwrites the selected roles' permissions with the application's baseline set. Any custom changes you made to them will be lost.
                </p>

                <form method="POST" action="{{ route('admin.roles.reset-defaults') }}" class="mt-4 space-y-4">
                    @csrf

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 border-b border-neutral-100 pb-2">
                            <input type="checkbox"
                                   @change="$el.closest('form').querySelectorAll('input[name=&quot;roles[]&quot;]').forEach(cb => cb.checked = $el.checked)"
                                   class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-sm font-medium text-neutral-800">All roles</span>
                        </label>
                        @foreach ($roleNames as $roleName)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="roles[]" value="{{ $roleName->value }}"
                                       class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                                <span class="text-sm text-neutral-700">{{ $roleName->label() }}</span>
                            </label>
                        @endforeach
                        <p class="pt-1 text-xs text-neutral-400">Leave everything unchecked to reset every role at once.</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="resetOpen = false" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50">Cancel</button>
                        <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-brand-700"
                                onclick="return confirm('Reset the selected roles\' permissions to default? Custom changes will be lost.')">
                            Reset to default
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Role tabs: sticky so they (and the actions below them) stay visible while the permission grid scrolls. --}}
        <div class="sticky top-0 z-10 flex flex-wrap gap-1.5 border-b border-neutral-200 bg-neutral-50 pb-px pt-1">
            @foreach ($roleNames as $roleName)
                <button type="button" @click="activeRole = '{{ $roleName->value }}'"
                        :class="activeRole === '{{ $roleName->value }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-transparent text-neutral-500 hover:bg-neutral-50 hover:text-neutral-700'"
                        class="rounded-t-md border-b-2 px-3.5 py-2 text-sm font-medium transition">
                    {{ $roleName->label() }}
                    <span class="ml-1 text-xs text-neutral-400">({{ $roles[$roleName->value]->permissions->count() ?? 0 }})</span>
                </button>
            @endforeach
        </div>

        {{-- One panel + form per role; only the active one is shown, so switching tabs never loses unsaved checkbox state. --}}
        @foreach ($roleNames as $roleName)
            @php $role = $roles[$roleName->value] ?? null; @endphp
            <div x-show="activeRole === '{{ $roleName->value }}'" x-cloak>
                @if ($role)
                    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        @if ($roleName === \App\Enums\RoleName::Administrator)
                            <div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
                                Administrator is meant to keep every permission. Unchecking items here removes that access immediately for all administrators.
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($permissionGroups as $group => $permissions)
                                <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md">
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $group }}</h3>
                                    <div class="mt-3 space-y-2.5">
                                        @foreach ($permissions as $permission)
                                            <label class="flex items-start gap-2.5">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->value }}"
                                                       class="mt-0.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                                                       @checked($role->hasPermissionTo($permission->value))>
                                                <span class="text-sm text-neutral-700">{{ $permission->label() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" form="reset-role-{{ $role->id }}"
                                    class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 transition hover:border-accent-400 hover:bg-accent-50 hover:text-accent-700">
                                Reset this role to default
                            </button>
                            <button type="submit" class="rounded-md bg-brand-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                                Save {{ $roleName->label() }}
                            </button>
                        </div>
                    </form>

                    <form id="reset-role-{{ $role->id }}" method="POST" action="{{ route('admin.roles.reset-defaults') }}"
                          onsubmit="return confirm('Reset &quot;{{ $roleName->label() }}&quot; to its default permissions? Unsaved changes above will be discarded.')">
                        @csrf
                        <input type="hidden" name="roles[]" value="{{ $roleName->value }}">
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
