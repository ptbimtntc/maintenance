<x-app-layout>
    <x-slot name="header">LOTOTO Activity - Gembok Spare</x-slot>

    @php
        $currentEmployee = $spareLock->currentActivity?->employee;
        $employeeInitials = $currentEmployee
            ? \Illuminate\Support\Str::of($currentEmployee->preferred_name ?: $currentEmployee->full_name)
                ->trim()
                ->explode(' ')
                ->filter()
                ->map(fn ($name) => substr($name, 0, 1))
                ->take(2)
                ->implode('')
            : 'SP';
        $isAdministrator = auth()->user()->hasRole(\App\Enums\RoleName::Administrator->value);
        $isAssignedTechnician = $currentEmployee
            && auth()->user()->employee?->is($currentEmployee)
            && preg_match('/^\d{1,2}$/', trim((string) $currentEmployee->lototo_number)) === 1;
        $canCloseSpareLock = $isAdministrator || $isAssignedTechnician;
    @endphp

    <section class="lototo-activity-page min-h-[312px] overflow-hidden border border-neutral-300 bg-white shadow-sm">
        <header class="flex min-h-20 items-center gap-3 border-b border-neutral-300 bg-neutral-100 px-3 py-2 sm:px-5">
            <a
                href="{{ $board ? route('safety.lototo.boards.show', $board) : route('safety.lototo.index') }}"
                aria-label="Kembali ke papan LOTOTO"
                class="order-3 ml-auto flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-yellow-400 text-neutral-900 transition hover:bg-yellow-500 sm:order-last"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                </svg>
            </a>

            @if ($currentEmployee?->photo_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($currentEmployee->photo_path) }}"
                    alt=""
                    class="h-16 w-16 shrink-0 border border-neutral-400 object-cover"
                >
            @else
                <div class="flex h-16 w-16 shrink-0 items-center justify-center border border-neutral-400 bg-neutral-200 text-lg font-bold text-neutral-700">
                    {{ $employeeInitials }}
                </div>
            @endif

            <div class="grid min-w-0 grid-cols-2 overflow-hidden border border-neutral-400 bg-white text-center sm:min-w-[270px]">
                <div class="border-r border-neutral-400 px-3 py-1">
                    <p class="text-xl font-black leading-tight">{{ $currentEmployee?->lototo_number ?: $spareLock->code }}</p>
                    <p class="truncate text-sm font-bold uppercase">{{ $currentEmployee?->preferred_name ?: $currentEmployee?->full_name ?: 'GEMBOK SPARE' }}</p>
                </div>
                <div class="flex flex-col justify-center px-3 py-1">
                    <p class="truncate text-xs font-semibold text-neutral-600">{{ $spareLock->code }}</p>
                    <p class="text-xs font-medium uppercase">{{ $currentEmployee ? 'Pemakai saat ini' : 'Riwayat pemakaian' }}</p>
                </div>
            </div>

            <div class="hidden min-w-0 flex-1 text-center lg:block">
                <p class="text-2xl font-black uppercase tracking-wide text-neutral-500">Lockout Station</p>
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-neutral-400">LOTOTO Activity · Gembok Spare</p>
            </div>

            <div class="hidden shrink-0 border border-neutral-400 bg-white px-3 py-2 text-center sm:block">
                <p class="text-lg font-black">{{ $activities->total() }}</p>
                <p class="text-[10px] font-semibold uppercase text-neutral-600">Aktivitas</p>
            </div>
        </header>

        @if ($spareLock->currentActivity)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2">
                <p class="text-xs text-amber-900">
                    Gembok {{ $spareLock->code }} sedang IN oleh
                    <span class="font-bold">{{ $currentEmployee?->full_name }}</span>.
                </p>
                @if ($canCloseSpareLock)
                    <a
                        href="{{ route('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board?->id]) }}"
                        class="inline-flex min-h-8 items-center justify-center rounded bg-neutral-900 px-3 text-xs font-bold text-white hover:bg-neutral-700"
                    >
                        Form OUT / CLOSE
                    </a>
                @endif
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-200 bg-emerald-50 px-4 py-2">
                <p class="text-xs text-emerald-900">Gembok {{ $spareLock->code }} tersedia untuk dipakai.</p>
                @if ($isAdministrator)
                    <a
                        href="{{ route('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board?->id]) }}"
                        class="inline-flex min-h-8 items-center justify-center rounded bg-neutral-900 px-3 text-xs font-bold text-white hover:bg-neutral-700"
                    >
                        Form IN / Mulai
                    </a>
                @endif
            </div>
        @endif

        @include('safety.lototo._activity-table', [
            'activities' => $activities,
            'showTechnician' => true,
            'currentSpareActivityId' => $spareLock->current_activity_id,
            'emptyMessage' => 'Belum ada aktivitas yang tercatat untuk gembok spare '.$spareLock->code.'.',
        ])

        @if ($activities->hasPages())
            <div class="border-t border-neutral-200 px-4 py-3">{{ $activities->links() }}</div>
        @endif
    </section>
</x-app-layout>
