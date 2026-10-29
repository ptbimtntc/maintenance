<x-app-layout>
    <x-slot name="header">Data Gembok Spare</x-slot>

    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-neutral-900">Master Gembok Spare</h1>
                <p class="mt-1 text-sm text-neutral-600">Saat handover Outstanding, aktivitas IN gembok spare dimulai otomatis dengan data yang sama. Scan QR gembok spare untuk mencatat OUT/Finish.</p>
            </div>
            <a href="{{ route('safety.lototo.boards.show', $board) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                Kembali ke papan LOTOTO
            </a>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
            <h2 class="font-semibold text-neutral-900">Tambah gembok spare</h2>
            <form method="POST" action="{{ route('safety.lototo.spare-locks.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <input type="hidden" name="board_id" value="{{ $board->id }}">
                <div class="flex-1">
                    <label for="code" class="text-sm font-medium text-neutral-700">Kode gembok <span class="text-red-600">*</span></label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        value="{{ old('code') }}"
                        maxlength="50"
                        pattern="[A-Za-z0-9_-]+"
                        placeholder="Contoh: SP-01"
                        required
                        class="mt-1 block min-h-11 w-full rounded-lg border-neutral-300 text-base shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                    >
                    <p class="mt-1 text-xs text-neutral-500">Huruf dan angka; tanda hubung atau garis bawah diperbolehkan. Kode disimpan dalam huruf kapital.</p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-neutral-900 px-5 text-sm font-bold text-white transition hover:bg-neutral-700">
                    Tambah gembok
                </button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 px-4 py-4 sm:px-6">
                <h2 class="font-semibold text-neutral-900">Daftar kode</h2>
                <p class="mt-1 text-xs text-neutral-500">Gembok nonaktif tetap tersimpan dan tidak akan ditawarkan sebagai pilihan.</p>
                <p class="mt-1 text-xs text-neutral-500">QR membuka alur OUT untuk gembok tersebut. Gembok yang sedang dipakai tidak tersedia untuk handover baru sampai di-Finish.</p>
            </div>

            @if ($spareLocks->isEmpty())
                <p class="px-4 py-10 text-center text-sm text-neutral-500">Belum ada kode gembok spare. Tambahkan kode sesuai nomor fisik pada gembok.</p>
            @else
                <ul class="divide-y divide-neutral-200">
                    @foreach ($spareLocks as $spareLock)
                        <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $spareLock->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-100 text-neutral-500' }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="4" y="10" width="16" height="11" rx="2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 1 1 8 0v3" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-neutral-900">{{ $spareLock->code }}</p>
                                    <p class="text-xs {{ $spareLock->is_active ? 'text-emerald-700' : 'text-neutral-500' }}">
                                        @if (! $spareLock->is_active)
                                            Nonaktif
                                        @elseif ($spareLock->current_activity_id)
                                            Sedang digunakan
                                        @else
                                            Tersedia
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-2">
                                <div class="text-center">
                                    <p class="mb-1 text-[10px] font-bold uppercase text-neutral-600">QR {{ $spareLock->code }}</p>
                                    <canvas
                                        data-qrcode="{{ route('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board->id]) }}"
                                        class="h-24 w-24 rounded border border-neutral-200 bg-white"
                                        aria-label="QR untuk gembok spare {{ $spareLock->code }}"
                                    ></canvas>
                                    <button
                                        type="button"
                                        data-download-qr
                                        data-filename="{{ $spareLock->code }}"
                                        data-name="GEMBOK SPARE {{ $spareLock->code }}"
                                        data-nik="SCAN UNTUK OUT / FINISH"
                                        class="mt-1 w-full rounded border border-neutral-300 bg-white px-2 py-1 text-[10px] font-semibold text-neutral-700 hover:bg-neutral-100"
                                    >
                                        Download QR
                                    </button>
                                </div>
                                <a
                                    href="{{ route('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board->id]) }}"
                                    class="text-xs font-semibold text-blue-700 underline"
                                >
                                    Buka form OUT
                                </a>
                            </div>

                            <form method="POST" action="{{ route('safety.lototo.spare-locks.update', $spareLock) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="board_id" value="{{ $board->id }}">
                                <label class="sr-only" for="code-{{ $spareLock->id }}">Kode gembok {{ $spareLock->code }}</label>
                                <input
                                    id="code-{{ $spareLock->id }}"
                                    name="code"
                                    type="text"
                                    value="{{ $spareLock->code }}"
                                    maxlength="50"
                                    pattern="[A-Za-z0-9_-]+"
                                    required
                                    class="min-h-10 rounded-lg border-neutral-300 text-sm shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                                >
                                <input type="hidden" name="is_active" value="{{ $spareLock->is_active ? '0' : '1' }}">
                                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-neutral-300 px-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                                    {{ $spareLock->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-neutral-200 px-4 py-3 sm:px-6">
                    {{ $spareLocks->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
