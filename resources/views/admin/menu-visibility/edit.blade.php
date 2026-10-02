<x-app-layout>
    <x-slot name="header">Menu Visibility — {{ $user->name }}</x-slot>

    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-success-50 px-4 py-3 text-sm text-success-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
            <h3 class="text-sm font-semibold text-neutral-900">{{ $user->name }}</h3>
            <p class="text-sm text-neutral-600">{{ $user->email }}</p>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $user->roles->pluck('name')->join(', ') }}
                @if ($user->employee?->position)
                    &middot; {{ $user->employee->position->title }}
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('admin.menu-visibility.update', $user) }}" class="space-y-6"
              x-data="{ setAll(v) { this.$el.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = v) } }">
            @csrf
            @method('PUT')

            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-md">
                @if ($user->hasRole(\App\Enums\RoleName::Administrator->value))
                    <p class="mb-4 text-sm text-neutral-500">Administrators always see every menu — these checkboxes have no effect for this user.</p>
                @else
                    <p class="mb-4 text-sm text-neutral-500">Centang menu yang <strong>ditampilkan</strong> untuk user ini. Menu yang tidak dicentang disembunyikan dari sidebar dan tidak bisa dibuka lewat URL. Menu tetap dibatasi juga oleh role/permission user.</p>
                @endif

                <div class="mb-4 flex gap-3 text-sm">
                    <button type="button" @click="setAll(true)" class="text-brand-700 hover:underline">Tampilkan semua</button>
                    <button type="button" @click="setAll(false)" class="text-brand-700 hover:underline">Sembunyikan semua</button>
                </div>

                <div class="space-y-5">
                    @foreach ($groups as $group => $menus)
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $group }}</h4>
                            <div class="mt-2 space-y-2">
                                @foreach ($menus as $menu)
                                    <label class="flex items-center gap-3">
                                        <input type="checkbox" name="visible[]" value="{{ $menu->value }}"
                                               class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                                               @checked(! in_array($menu->value, $user->hiddenMenuKeys(), true))>
                                        <span class="text-sm text-neutral-700">{{ $menu->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Save</button>
                <a href="{{ route('admin.menu-visibility.index') }}" class="text-sm text-neutral-600 hover:underline">Back</a>
            </div>
        </form>
    </div>
</x-app-layout>
