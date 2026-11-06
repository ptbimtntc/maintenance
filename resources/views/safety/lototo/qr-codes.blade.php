<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center gap-4">
            @include('safety.lototo._back-button', ['href' => route('safety.lototo.boards.show', $board), 'label' => 'Kembali ke LOTOTO Board'])
            <span>QR Code · {{ $board->division_name }}</span>
        </div>
    </x-slot>

    {{-- QR codes of the board (technician, supervisor, spare lock, tools) and the print designs popup. --}}
    <div class="lototo-settings-page space-y-6">
        <section class="space-y-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-neutral-900">QR Gembok LOTOTO</h2>
                    <p class="text-sm text-neutral-600">QR membuka form berdasarkan identitas yang tersimpan. Status dan aktivitas selalu dibaca dari database.</p>
                </div>
                @can('safety.manage')
                    <a href="{{ route('safety.lototo.boards.index', ['board' => $board->id]) }}" class="inline-flex min-h-10 items-center rounded-md bg-neutral-800 px-4 text-sm font-semibold text-white hover:bg-neutral-700">
                        Manage Board
                    </a>
                @endcan
            </header>

            @foreach ($qrGroups as $qrGroup)
                <div>
                    <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-neutral-700">{{ $qrGroup['label'] }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($qrGroup['employees'] as $employee)
                            <div class="rounded-lg border border-neutral-200 p-3 text-center">
                                <p class="text-sm font-bold text-neutral-900">{{ $employee->preferred_name ?: $employee->full_name }}</p>
                                <p class="text-xs text-neutral-500">LOTOTO {{ $employee->lototo_number }}</p>
                                <canvas class="mx-auto mt-2 h-auto max-w-full" data-qrcode="{{ \App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', ['lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board' => $board->id]) }}" aria-label="QR {{ $qrGroup['label'] }} {{ $employee->lototo_number }}"></canvas>
                                <button type="button" data-print-open="{{ $qrGroup['kind'] }}-{{ $employee->id }}" class="mt-2 min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                    Download QR
                                </button>
                            </div>
                        @endforeach
                    </div>
                    @if ($qrGroup['employees']->isEmpty())
                        <p class="rounded-md bg-neutral-50 px-3 py-2 text-sm text-neutral-500">Belum ada identitas {{ strtolower($qrGroup['label']) }} pada board.</p>
                    @endif
                </div>
            @endforeach

            <div>
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-neutral-700">Spare Locks</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($qrSpareLocks as $spareLock)
                        <div class="rounded-lg border border-neutral-200 p-3 text-center">
                            <p class="text-sm font-bold text-neutral-900">{{ $spareLock->code }}</p>
                            <p class="text-xs {{ $spareLock->is_active ? 'text-emerald-700' : 'text-neutral-500' }}">{{ $spareLock->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                            <canvas class="mx-auto mt-2 h-auto max-w-full" data-qrcode="{{ \App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board->id]) }}" aria-label="QR Spare Lock {{ $spareLock->code }}"></canvas>
                            <button type="button" data-print-open="spare-{{ $spareLock->id }}" class="mt-2 min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                Download QR
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-neutral-700">Tools</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($tools as $tool)
                        <div class="rounded-lg border border-neutral-200 p-3 text-center">
                            <p class="text-sm font-bold text-neutral-900">{{ $tool->code }}</p>
                            <p class="text-xs text-neutral-500">{{ $tool->name }}{{ $tool->is_active ? '' : ' · Nonaktif' }}</p>
                            <canvas class="mx-auto mt-2 h-auto max-w-full" data-qrcode="{{ route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $board->id]) }}" aria-label="QR Tools {{ $tool->code }}"></canvas>
                            <button type="button" data-print-open="tool-{{ $tool->id }}" class="mt-2 min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                Download QR
                            </button>
                        </div>
                    @endforeach
                </div>
                @if ($tools->isEmpty())
                    <p class="rounded-md bg-neutral-50 px-3 py-2 text-sm text-neutral-500">Belum ada Tools pada board ini.</p>
                @endif
            </div>

            @include('safety.lototo._print-designs')
        </section>
    </div>
</x-app-layout>
