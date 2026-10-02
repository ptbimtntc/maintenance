<x-app-layout>
    <x-slot name="header">Menu Visibility</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-danger-50 px-4 py-3 text-sm text-danger-800">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-neutral-600">
            Atur menu yang tampil di sidebar per orang atau massal. Menu yang disembunyikan juga tidak bisa dibuka lewat URL. Administrator selalu melihat semua menu.
        </p>

        <form method="GET" action="{{ route('admin.menu-visibility.index') }}" class="grid grid-cols-1 gap-2.5 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm sm:grid-cols-4">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email..."
                   class="rounded-md border-neutral-300 py-1.5 text-sm sm:col-span-2" />
            <select name="role" class="rounded-md border-neutral-300 py-1.5 text-sm">
                <option value="">All Roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) == $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-brand-700">Filter</button>
                <a href="{{ route('admin.menu-visibility.index') }}" class="rounded-md border border-neutral-300 px-3.5 py-1.5 text-sm font-medium text-neutral-600 shadow-sm hover:bg-neutral-50">Reset</a>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.menu-visibility.bulk') }}" class="space-y-6"
              x-data="{ all: false, toggleAll() { this.$root.querySelectorAll('input[name=\'users[]\']').forEach(c => c.checked = this.all) } }">
            @csrf

            <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-md">
                <h3 class="text-sm font-semibold text-neutral-900">1. Pilih menu</h3>
                <div class="mt-3 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups as $group => $menus)
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $group }}</h4>
                            <div class="mt-1.5 space-y-1.5">
                                @foreach ($menus as $menu)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="menus[]" value="{{ $menu->value }}"
                                               class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                                        <span class="text-sm text-neutral-700">{{ $menu->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <h3 class="mt-5 text-sm font-semibold text-neutral-900">2. Pilih user di tabel di bawah, lalu:</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="submit" name="action" value="hide"
                            class="rounded-md bg-danger-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-danger-700">Sembunyikan menu terpilih</button>
                    <button type="submit" name="action" value="show"
                            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700">Tampilkan menu terpilih</button>
                </div>
            </div>

            <div class="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white shadow-md">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="border-b-2 border-brand-500 bg-brand-50">
                        <tr>
                            <th class="sticky top-0 z-10 w-10 bg-brand-50 px-3 py-2">
                                <input type="checkbox" x-model="all" @change="toggleAll()" class="rounded border-neutral-300 text-brand-600" title="Pilih semua">
                            </th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Name</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Role</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-brand-700">Menu disembunyikan</th>
                            <th class="sticky top-0 z-10 bg-brand-50 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($users as $user)
                            @php $hidden = $user->hiddenMenuKeys(); @endphp
                            <tr>
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="users[]" value="{{ $user->id }}" class="rounded border-neutral-300 text-brand-600">
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-neutral-900">{{ $user->name }}</div>
                                    <div class="text-xs text-neutral-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    @foreach ($user->roles as $role)
                                        <span class="inline-flex rounded-full bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td class="px-3 py-2 text-xs text-neutral-600">
                                    @if ($user->hasRole(\App\Enums\RoleName::Administrator->value))
                                        <span class="text-neutral-400">Selalu melihat semua</span>
                                    @elseif ($hidden === [])
                                        <span class="text-neutral-400">—</span>
                                    @else
                                        @foreach ($hidden as $key)
                                            <span class="mr-1 inline-flex rounded bg-danger-50 px-1.5 py-0.5 text-danger-700">{{ \App\Enums\SidebarMenu::tryFrom($key)?->label() ?? $key }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('admin.menu-visibility.edit', $user) }}" class="text-neutral-600 hover:underline">Atur</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        {{ $users->links() }}
    </div>
</x-app-layout>
