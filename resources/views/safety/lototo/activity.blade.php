<x-app-layout>
    <x-slot name="header">LOTOTO Activity</x-slot>

    @php
        $isSupervisor = preg_match('/^\d{3}$/', trim((string) $employee->lototo_number)) === 1;
        $initials = \Illuminate\Support\Str::of($employee->preferred_name ?: $employee->full_name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->map(fn ($name) => substr($name, 0, 1))
            ->take(2)
            ->implode('');
        $latestActivity = $activities->getCollection()->first();
    @endphp

    <section class="lototo-activity-page min-h-[312px] overflow-hidden border border-neutral-300 bg-white shadow-sm">
        <header class="flex min-h-20 items-center gap-3 border-b border-neutral-300 bg-neutral-100 px-3 py-2 sm:px-5">
            <a
                href="{{ isset($board) ? route('safety.lototo.boards.show', $board) : route('safety.lototo.index') }}"
                aria-label="Kembali ke papan LOTOTO"
                class="order-3 ml-auto flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-yellow-400 text-neutral-900 transition hover:bg-yellow-500 sm:order-last"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                </svg>
            </a>
            @if ($employee->photo_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}"
                    alt=""
                    class="h-16 w-16 shrink-0 border border-neutral-400 object-cover"
                >
            @else
                <div class="flex h-16 w-16 shrink-0 items-center justify-center border border-neutral-400 bg-neutral-200 text-lg font-bold text-neutral-700">
                    {{ $initials }}
                </div>
            @endif

            <div class="grid min-w-0 grid-cols-2 overflow-hidden border border-neutral-400 bg-white text-center sm:min-w-[270px]">
                <div class="border-r border-neutral-400 px-3 py-1">
                    <p class="text-xl font-black leading-tight">{{ $employee->lototo_number ?: '-' }}</p>
                    <p class="truncate text-sm font-bold uppercase">{{ $employee->preferred_name ?: $employee->full_name }}</p>
                </div>
                <div class="flex flex-col justify-center px-3 py-1">
                    <p class="truncate text-xs font-semibold text-neutral-600">{{ $employee->employee_number }}</p>
                    <p class="text-xs font-medium">{{ $isSupervisor ? 'SUPERVISOR' : 'TEKNISI' }}</p>
                </div>
            </div>

            <div class="hidden min-w-0 flex-1 text-center lg:block">
                <p class="text-2xl font-black uppercase tracking-wide text-neutral-500">Lockout Station</p>
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-neutral-400">LOTOTO Activity</p>
            </div>

            <div class="hidden shrink-0 border border-neutral-400 bg-white px-3 py-2 text-center sm:block">
                <p class="text-lg font-black">{{ $activities->total() }}</p>
                <p class="text-[10px] font-semibold uppercase text-neutral-600">Aktivitas</p>
            </div>
        </header>

        @include('safety.lototo._activity-table', [
            'activities' => $activities,
            'emptyMessage' => 'Belum ada aktivitas LOTOTO untuk karyawan ini.',
        ])

        @if ($activities->hasPages())
            <div class="border-t border-neutral-200 px-4 py-3">{{ $activities->links() }}</div>
        @endif
    </section>
</x-app-layout>
