<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center gap-4">
            @include('safety.lototo._back-button', ['href' => $selectedSection ? route('safety.lototo.boards.edit', $board) : route('safety.lototo.boards.show', $board), 'label' => $selectedSection ? 'Kembali ke Edit Board' : 'Kembali ke LOTOTO Board', 'unsaved' => (bool) $selectedSection])
            <span>LOTOTO Board Settings{{ $selectedSection ? ' · '.$groups[$selectedSection]['label'] : '' }}</span>
            @if (! $selectedSection)<a class="ml-auto text-sm font-semibold text-blue-700 hover:text-blue-900" href="{{ route('safety.lototo.boards.index', ['board' => $board->id]) }}">Manage Boards</a>@endif
        </div>
    </x-slot>

    <div class="lototo-settings-page space-y-6">
        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <p class="font-bold">Pengaturan belum dapat disimpan.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($selectedSection)
        @if (! $board->is_active)
        <div role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            Board berstatus Inactive. Konfigurasi tersimpan tetap dapat ditinjau dan dilepas, tetapi assignment Technician, Supervisor, atau Spare Lock baru ditolak sampai board diaktifkan kembali. Aktivitas yang sudah berjalan hanya dapat diselesaikan dengan OUT Finish.
        </div>
        @endif
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
            Nomor posisi adalah slot tetap pada board. Kosongkan pilihan untuk melepas penempatan tanpa menghapus data employee,
            gembok, atau riwayat aktivitas. Untuk memindahkan atau menukar penempatan, ubah pilihan pada semua slot terkait lalu simpan
            dalam satu kali aksi. Posisi dengan aktivitas aktif tidak dapat dipindahkan atau dinonaktifkan.
        </div>

        @else
        <section class="space-y-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-neutral-900">QR Gembok LOTOTO</h2>
                    <p class="text-sm text-neutral-600">QR membuka form berdasarkan identitas yang tersimpan. Status dan aktivitas selalu dibaca dari database.</p>
                </div>
                <a href="{{ route('safety.lototo.boards.index', ['board' => $board->id]) }}" class="inline-flex min-h-10 items-center rounded-md bg-neutral-800 px-4 text-sm font-semibold text-white hover:bg-neutral-700">
                    Manage Boards
                </a>
            </header>

            @foreach ($qrGroups as $qrGroup)
                <div>
                    <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-neutral-700">{{ $qrGroup['label'] }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($qrGroup['employees'] as $employee)
                            <div class="rounded-lg border border-neutral-200 p-3 text-center">
                                <p class="text-sm font-bold text-neutral-900">{{ $employee->preferred_name ?: $employee->full_name }}</p>
                                <p class="text-xs text-neutral-500">LOTOTO {{ $employee->lototo_number }}</p>
                                <canvas class="mx-auto mt-2 h-auto max-w-full" data-qrcode="{{ route('safety.lototo.form', ['lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board' => $board->id]) }}" aria-label="QR {{ $qrGroup['label'] }} {{ $employee->lototo_number }}"></canvas>
                                <button type="button" data-download-qr data-name="{{ $qrGroup['label'] }}" data-nik="{{ $employee->lototo_number }}" data-identifier-label="LOTOTO" data-filename="lototo-{{ strtolower(str_replace(' ', '-', $qrGroup['label'])) }}-{{ $employee->lototo_number }}" class="mt-2 min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
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
                            <canvas class="mx-auto mt-2 h-auto max-w-full" data-qrcode="{{ route('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board->id]) }}" aria-label="QR Spare Lock {{ $spareLock->code }}"></canvas>
                            <button type="button" data-download-qr data-name="Spare Lock" data-nik="{{ $spareLock->code }}" data-identifier-label="KODE" data-filename="lototo-spare-{{ strtolower($spareLock->code) }}" class="mt-2 min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
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
                            <button type="button" data-download-qr data-name="{{ $tool->name }}" data-nik="{{ $tool->code }}" data-identifier-label="TOOLS" data-filename="lototo-tools-{{ strtolower($tool->code) }}" class="mt-2 min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                Download QR
                            </button>
                        </div>
                    @endforeach
                </div>
                @if ($tools->isEmpty())
                    <p class="rounded-md bg-neutral-50 px-3 py-2 text-sm text-neutral-500">Belum ada Tools pada board ini.</p>
                @endif
            </div>
        </section>
        @endif

        @foreach ($groups as $category => $group)
            @if ($selectedSection === $category)
            {{-- relative: the sr-only labels in the table are absolutely positioned; without a positioned ancestor they
                 escape the <main> scroll container and stretch the document, which showed a second page scrollbar. --}}
            <section class="relative overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3">
                    <div>
                        <h2 class="font-bold text-neutral-900">{{ $group['label'] }}</h2>
                        <p class="text-xs text-neutral-500">{{ $group['slots']->count() }} posisi tersimpan</p>
                    </div>
                    <form method="POST" action="{{ route('safety.lototo.settings.positions.store', $category) }}">
                        @csrf
                        <input type="hidden" name="board_id" value="{{ $board->id }}">
                        <input type="number" min="1" max="99999" name="position" placeholder="Berikutnya" class="min-h-9 w-28 rounded-md border-neutral-300 text-sm" aria-label="Nomor posisi baru untuk {{ $group['label'] }}">
                        <button type="submit" class="min-h-9 rounded-md bg-neutral-800 px-3 text-sm font-semibold text-white hover:bg-neutral-700">
                            Add Position
                        </button>
                    </form>
                </header>

                @if ($category === 'technician')
                    <p class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                        Posisi Technician masih mengikuti LOTOTO number pada data legacy. Pengubahan urutan ditahan sampai mapping slot lama dapat diverifikasi; tambah posisi dan pengelolaan slot lain tetap tersedia.
                    </p>
                @endif

                <form method="POST" action="{{ route('safety.lototo.settings.update', $category) }}" data-unsaved-watch>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="board_id" value="{{ $board->id }}">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-600">
                                <tr>
                                    <th scope="col" class="w-12 px-3 py-2">Urut</th>
                                    <th scope="col" class="px-4 py-2">Posisi tetap</th>
                                    <th scope="col" class="px-4 py-2">{{ $group['assignment_label'] }}</th>
                                    <th scope="col" class="px-4 py-2">Status posisi</th>
                                    <th scope="col" class="px-3 py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100" data-slot-list>
                                @foreach ($group['slots'] as $slot)
                                    @php
                                        $assignmentValue = old("slots.{$slot->id}.{$group['assignment_field']}", $slot->getAttribute($group['assignment_field']));
                                        $enabledValue = old("slots.{$slot->id}.is_enabled", $slot->is_enabled ? '1' : '0');
                                    @endphp
                                    <tr data-slot-row data-slot-id="{{ $slot->id }}">
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" draggable="{{ $category !== 'technician' ? 'true' : 'false' }}" data-drag-handle aria-label="Geser posisi {{ $slot->position }}" title="{{ $category === 'technician' ? 'Posisi dikunci oleh LOTOTO number' : 'Geser untuk mengurutkan' }}" class="cursor-grab rounded px-2 py-1 text-xs font-bold text-neutral-500 hover:bg-neutral-100 active:cursor-grabbing disabled:cursor-not-allowed" @disabled($category === 'technician')>MOVE</button>
                                        </td>
                                        <th scope="row" class="whitespace-nowrap px-4 py-2 font-semibold text-neutral-800">
                                            <label class="sr-only" for="slot-position-{{ $slot->id }}">Nomor posisi</label>
                                            <input id="slot-position-{{ $slot->id }}" type="number" min="1" max="99999" name="slots[{{ $slot->id }}][position]" value="{{ old("slots.{$slot->id}.position", $slot->position) }}" data-slot-position class="w-24 rounded border-neutral-300 text-sm font-semibold text-neutral-800" @readonly($category === 'technician')>
                                        </th>
                                        <td class="min-w-[18rem] px-4 py-2">
                                            @if ($category === 'tool')
                                                <input
                                                    name="slots[{{ $slot->id }}][{{ $group['assignment_field'] }}]"
                                                    value="{{ $assignmentValue }}"
                                                    placeholder="Set as Empty"
                                                    maxlength="100"
                                                    class="block min-h-9 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                >
                                                <label class="mt-2 block text-xs font-semibold text-neutral-600">Ukuran kolom
                                                    <select name="slots[{{ $slot->id }}][tool_column_span]" class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm">
                                                        <option value="1" @selected((int) old("slots.{$slot->id}.tool_column_span", $slot->tool_column_span ?? 1) === 1)>Tipe 1 · satu kolom</option>
                                                        <option value="2" @selected((int) old("slots.{$slot->id}.tool_column_span", $slot->tool_column_span ?? 1) === 2)>Tipe 2 · dua kolom</option>
                                                        <option value="3" @selected((int) old("slots.{$slot->id}.tool_column_span", $slot->tool_column_span ?? 1) === 3)>Tipe 3 · tiga kolom</option>
                                                    </select>
                                                </label>
                                            @else
                                                <select
                                                    name="slots[{{ $slot->id }}][{{ $group['assignment_field'] }}]"
                                                    class="block min-h-9 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                >
                                                    <option value="">Set as Empty</option>
                                                    @foreach ($group['options'] as $option)
                                                        <option value="{{ $option->id }}" @selected((string) $assignmentValue === (string) $option->id)>
                                                            {{ $group['option_label']($option) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                        <td class="w-48 px-4 py-2">
                                            <select
                                                name="slots[{{ $slot->id }}][is_enabled]"
                                                class="block min-h-9 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            >
                                                <option value="1" @selected((string) $enabledValue === '1')>Enabled</option>
                                                <option value="0" @selected((string) $enabledValue === '0')>Disabled</option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($category === 'tool' && $slot->position <= 15)
                                                <button type="button" disabled title="Fallback legacy masih dipertahankan sampai konfigurasi lama diverifikasi" class="min-h-9 rounded border border-neutral-200 px-2 text-xs font-semibold text-neutral-400">Legacy</button>
                                            @else
                                                <button type="submit" form="delete-slot-{{ $slot->id }}" class="min-h-9 rounded border border-red-200 px-2 text-xs font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <footer class="flex justify-end border-t border-neutral-200 px-4 py-3">
                        <button type="submit" class="min-h-10 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-600">
                            Save Changes
                        </button>
                    </footer>
                </form>
                @foreach ($group['slots'] as $slot)
                    <form id="delete-slot-{{ $slot->id }}" method="POST" action="{{ route('safety.lototo.settings.positions.destroy', [$category, $slot]) }}" class="hidden" data-confirm-delete-slot>
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="board_id" value="{{ $board->id }}">
                    </form>
                @endforeach
            </section>
            @if ($category === 'spare')
                <section id="spare-locks" class="relative mt-5 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <header class="border-b border-neutral-200 px-4 py-3">
                        <h3 class="font-bold text-neutral-900">Gembok Spare</h3>
                        <p class="text-xs text-neutral-500">Kelola kode dan status di board ini. Gembok yang sedang digunakan tidak dapat diubah, dinonaktifkan, atau dihapus; gembok yang memiliki riwayat hanya dapat dinonaktifkan.</p>
                        <p class="mt-1 text-xs font-semibold text-amber-800">QR memuat kode gembok dan board ini. Setelah mengganti kode, cetak ulang QR dari halaman Settings; QR lama tidak berlaku lagi.</p>
                    </header>
                    @php
                        $sparePrefix = $board->defaultSparePrefix();
                    @endphp
                    <form method="POST" action="{{ route('safety.lototo.spare-locks.store-batch') }}" class="flex flex-wrap items-end gap-3 border-b border-neutral-200 p-4" data-unsaved-watch>
                        @csrf
                        <input type="hidden" name="board_id" value="{{ $board->id }}">
                        <label class="w-32 text-sm font-semibold text-neutral-700">Jumlah gembok
                            <input name="count" type="number" min="1" max="50" step="1" value="{{ old('count', 1) }}" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 text-sm">
                        </label>
                        <label class="w-40 text-sm font-semibold text-neutral-700">Prefix kode
                            <input name="spare_code_prefix" value="{{ old('spare_code_prefix', $sparePrefix) }}" maxlength="20" pattern="[A-Za-z0-9]+([_\-][A-Za-z0-9]+)*" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 text-sm uppercase">
                        </label>
                        <button type="submit" class="min-h-10 rounded-md bg-neutral-800 px-4 text-sm font-semibold text-white hover:bg-neutral-700">Tambah gembok Spare</button>
                        <p class="basis-full text-xs text-neutral-500">Kode dibuat otomatis melanjutkan nomor bebas berikutnya ({{ $sparePrefix }}-01, {{ $sparePrefix }}-02, …) dan setiap gembok langsung mendapat posisi baru di akhir bagian Spare. Gembok yang sudah ada tidak diubah.</p>
                    </form>
                    <details class="border-b border-neutral-200 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-neutral-700">Tambah satu gembok dengan kode manual</summary>
                        <form method="POST" action="{{ route('safety.lototo.spare-locks.store') }}" class="mt-3 flex flex-wrap items-end gap-3" data-unsaved-watch>
                            @csrf
                            <input type="hidden" name="board_id" value="{{ $board->id }}">
                            <label class="min-w-56 flex-1 text-sm font-semibold text-neutral-700">Kode gembok
                                <input name="code" required maxlength="50" pattern="[A-Za-z0-9_-]+" placeholder="Contoh: SP-01" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 text-sm">
                            </label>
                            <button type="submit" class="min-h-10 rounded-md border border-neutral-300 px-4 text-sm font-semibold hover:bg-neutral-50">Tambah gembok</button>
                            <p class="basis-full text-xs text-neutral-500">Untuk gembok pengganti dengan kode tertentu. Gembok ini belum dipasang di posisi; pasang melalui pengaturan posisi Spare di atas.</p>
                        </form>
                    </details>
                    @if ($qrSpareLocks->isEmpty())
                        <p class="p-4 text-sm text-neutral-500">Belum ada gembok spare pada board ini.</p>
                    @else
                        <ul class="divide-y divide-neutral-100">
                            @foreach ($qrSpareLocks as $spareLock)
                                @php
                                    $spareInUse = (bool) $spareLock->current_activity_id;
                                    $spareHasHistory = $spareLock->activities_count > 0;
                                    $sparePosition = $sparePositions->get($spareLock->id);
                                @endphp
                                <li class="flex flex-wrap items-center gap-3 p-3">
                                    <div class="min-w-28">
                                        <span class="block font-bold text-neutral-900">{{ $spareLock->code }}</span>
                                        <span class="block text-xs text-neutral-500">{{ $sparePosition ? 'Posisi '.sprintf('%02d', $sparePosition) : 'Belum dipasang di posisi' }}</span>
                                    </div>
                                    <span class="text-xs {{ $spareLock->is_active ? 'text-emerald-700' : 'text-neutral-500' }}">{{ $spareInUse ? 'Sedang digunakan' : ($spareLock->is_active ? 'Aktif' : 'Nonaktif') }}</span>
                                    <div class="ml-auto flex flex-wrap items-center gap-2">
                                        <form method="POST" action="{{ route('safety.lototo.spare-locks.update', $spareLock) }}" class="flex flex-wrap items-center gap-2" data-unsaved-watch data-spare-code-form data-current-code="{{ $spareLock->code }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="board_id" value="{{ $board->id }}">
                                            <input type="hidden" name="is_active" value="{{ $spareLock->is_active ? '1' : '0' }}">
                                            <label class="sr-only" for="spare-code-{{ $spareLock->id }}">Kode gembok {{ $spareLock->code }}</label>
                                            <input id="spare-code-{{ $spareLock->id }}" name="code" value="{{ $spareLock->code }}" required maxlength="50" pattern="[A-Za-z0-9_-]+" class="min-h-9 w-32 rounded-md border-neutral-300 text-sm" @readonly($spareInUse)>
                                            <button type="submit" class="min-h-9 rounded-md bg-neutral-800 px-3 text-xs font-semibold text-white hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-50" @disabled($spareInUse)>Simpan kode</button>
                                        </form>
                                        <form method="POST" action="{{ route('safety.lototo.spare-locks.update', $spareLock) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="board_id" value="{{ $board->id }}">
                                            <input type="hidden" name="code" value="{{ $spareLock->code }}">
                                            <input type="hidden" name="is_active" value="{{ $spareLock->is_active ? '0' : '1' }}">
                                            <button type="submit" class="min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50" @disabled($spareInUse && $spareLock->is_active)>{{ $spareLock->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                        @if (! $spareInUse && ! $spareHasHistory)
                                            <form method="POST" action="{{ route('safety.lototo.spare-locks.destroy', $spareLock) }}" data-confirm-spare-delete data-spare-code="{{ $spareLock->code }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="board_id" value="{{ $board->id }}">
                                                <button type="submit" class="min-h-9 rounded-md border border-red-300 px-3 text-xs font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-neutral-500" title="Gembok yang sedang digunakan atau memiliki riwayat tidak dapat dihapus.">{{ $spareInUse ? 'Tidak dapat dihapus' : 'Memiliki riwayat' }}</span>
                                        @endif
                                        <a href="{{ route('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board->id]) }}" class="text-xs font-semibold text-blue-700 hover:underline">Form</a>
                                        <a href="{{ route('safety.lototo.spare-locks.show', $spareLock) }}" class="text-xs font-semibold text-blue-700 hover:underline">Riwayat</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
            @if ($category === 'tool')
                <section id="tools" class="relative mt-5 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <header class="border-b border-neutral-200 px-4 py-3">
                        <h3 class="font-bold text-neutral-900">Daftar Tools</h3>
                        <p class="text-xs text-neutral-500">Tools milik board ini. QR Tools memuat ID Tools dan board ini, sehingga tetap berlaku walaupun kode atau nama diubah. Tools yang sedang IN tidak dapat dinonaktifkan atau dihapus; Tools yang memiliki riwayat hanya dapat dinonaktifkan.</p>
                    </header>
                    <form method="POST" action="{{ route('safety.lototo.tools.store') }}" enctype="multipart/form-data" class="grid gap-3 border-b border-neutral-200 p-4 sm:grid-cols-3" data-unsaved-watch>
                        @csrf
                        <input type="hidden" name="board_id" value="{{ $board->id }}">
                        <label class="text-sm font-semibold text-neutral-700">Kode
                            <input name="code" value="{{ old('code') }}" required maxlength="50" pattern="[A-Za-z0-9_-]+" placeholder="Contoh: TL-16" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 text-sm uppercase">
                        </label>
                        <label class="text-sm font-semibold text-neutral-700">Nama
                            <input name="name" value="{{ old('name') }}" required maxlength="100" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 text-sm">
                        </label>
                        <label class="text-sm font-semibold text-neutral-700">Gambar
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-xs">
                            <span class="mt-1 block text-xs font-normal text-neutral-500">JPG, PNG, atau WEBP, maksimal 2 MB.</span>
                        </label>
                        <div class="flex flex-wrap items-center gap-3 sm:col-span-3">
                            <button type="submit" class="min-h-10 rounded-md bg-neutral-800 px-4 text-sm font-semibold text-white hover:bg-neutral-700">Tambah Tools</button>
                            <span class="text-xs text-neutral-500">Tools langsung dipasang di posisi Tools kosong pertama board ini (atau posisi baru di akhir). Urutan dan ukuran kolom diatur pada tabel posisi Tools di atas.</span>
                        </div>
                    </form>
                    @if ($tools->isEmpty())
                        <p class="p-4 text-sm text-neutral-500">Belum ada Tools pada board ini.</p>
                    @else
                        <ul class="divide-y divide-neutral-100">
                            @foreach ($tools as $tool)
                                @php
                                    $toolInUse = $tool->openActivity !== null;
                                    $toolPosition = $toolPositions->get($tool->id);
                                @endphp
                                <li class="flex flex-wrap items-start gap-3 p-3">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50">
                                        @if ($tool->image_path)
                                            <img src="{{ route('safety.lototo.tools.image', $tool) }}?v={{ $tool->updated_at?->timestamp }}" alt="Gambar {{ $tool->name }}" class="h-full w-full object-contain" loading="lazy">
                                        @else
                                            <span class="text-[10px] text-neutral-400">Tanpa gambar</span>
                                        @endif
                                    </div>
                                    <div class="min-w-36">
                                        <span class="block font-bold text-neutral-900">{{ $tool->code }} · {{ $tool->name }}</span>
                                        <span class="block text-xs text-neutral-500">{{ $toolPosition ? 'Posisi '.sprintf('%02d', $toolPosition) : 'Belum dipasang di posisi' }}{{ $tool->location ? ' · '.$tool->location : '' }}</span>
                                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-bold {{ $toolInUse ? 'bg-amber-100 text-amber-900' : ($tool->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-100 text-neutral-600') }}">
                                            {{ $toolInUse ? 'IN · '.$tool->openActivity->location : ($tool->is_active ? 'OUT · di board' : 'Nonaktif') }}
                                        </span>
                                    </div>
                                    <div class="ml-auto flex flex-wrap items-end gap-2">
                                        <form method="POST" action="{{ route('safety.lototo.tools.update', $tool) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2" data-unsaved-watch>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="board_id" value="{{ $board->id }}">
                                            <input type="hidden" name="is_active" value="{{ $tool->is_active ? '1' : '0' }}">
                                            <label class="text-xs font-semibold text-neutral-600">Kode<input name="code" value="{{ $tool->code }}" required maxlength="50" pattern="[A-Za-z0-9_-]+" class="mt-1 block min-h-9 w-24 rounded-md border-neutral-300 text-sm uppercase"></label>
                                            <label class="text-xs font-semibold text-neutral-600">Nama<input name="name" value="{{ $tool->name }}" required maxlength="100" class="mt-1 block min-h-9 w-36 rounded-md border-neutral-300 text-sm"></label>
                                            <label class="text-xs font-semibold text-neutral-600">Lokasi<input name="location" value="{{ $tool->location }}" maxlength="255" class="mt-1 block min-h-9 w-36 rounded-md border-neutral-300 text-sm"></label>
                                            <label class="text-xs font-semibold text-neutral-600">Ganti gambar<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-44 text-xs"></label>
                                            @if ($tool->image_path)
                                                <label class="inline-flex min-h-9 items-center gap-1 text-xs text-neutral-600"><input type="checkbox" name="remove_image" value="1"> Hapus gambar</label>
                                            @endif
                                            <button type="submit" class="min-h-9 rounded-md bg-neutral-800 px-3 text-xs font-semibold text-white hover:bg-neutral-700">Simpan</button>
                                        </form>
                                        <form method="POST" action="{{ route('safety.lototo.tools.update', $tool) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="board_id" value="{{ $board->id }}">
                                            <input type="hidden" name="code" value="{{ $tool->code }}">
                                            <input type="hidden" name="name" value="{{ $tool->name }}">
                                            <input type="hidden" name="location" value="{{ $tool->location }}">
                                            <input type="hidden" name="is_active" value="{{ $tool->is_active ? '0' : '1' }}">
                                            <button type="submit" class="min-h-9 rounded-md border border-neutral-300 px-3 text-xs font-semibold hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50" @disabled($toolInUse && $tool->is_active)>{{ $tool->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                        @if (! $toolInUse && $tool->activities_count === 0)
                                            <form method="POST" action="{{ route('safety.lototo.tools.destroy', $tool) }}" data-confirm-tool-delete data-tool-code="{{ $tool->code }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="board_id" value="{{ $board->id }}">
                                                <button type="submit" class="min-h-9 rounded-md border border-red-300 px-3 text-xs font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                            </form>
                                        @else
                                            <span class="min-h-9 text-xs text-neutral-500" title="Tools yang sedang IN atau memiliki riwayat tidak dapat dihapus.">{{ $toolInUse ? 'Sedang IN' : 'Memiliki riwayat' }}</span>
                                        @endif
                                        <a href="{{ route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $board->id]) }}" class="text-xs font-semibold text-blue-700 hover:underline">Form &amp; riwayat</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
            @endif
        @endforeach
    </div>
</x-app-layout>
