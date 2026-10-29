<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center gap-4">
            @include('safety.lototo._back-button', ['href' => $returnBoard ? route('safety.lototo.settings', ['board' => $returnBoard->id]) : route('safety.lototo.index'), 'label' => 'Kembali ke Settings'])
            <span>Manage LOTOTO Boards</span>
        </div>
    </x-slot>

    <div class="lototo-boards-page mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-neutral-900">Add Board</h2>
            <p class="mt-1 text-sm text-neutral-600">Board baru mendapat template standar dan konfigurasi tersendiri untuk Business Unit yang dipilih.</p>
            <form method="POST" action="{{ route('safety.lototo.boards.store') }}" class="mt-4 grid gap-4 sm:grid-cols-3" data-board-create-form>
                @csrf
                <label class="text-sm font-semibold text-neutral-700">Nama Divisi
                    <input name="division_name" value="{{ old('division_name') }}" required maxlength="100" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300" autocomplete="organization-title">
                </label>
                <label class="text-sm font-semibold text-neutral-700">Business Unit
                    <select name="business_unit_id" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                        <option value="">Pilih Business Unit</option>
                        @foreach ($businessUnits as $businessUnit)
                            <option value="{{ $businessUnit->id }}" @selected(old('business_unit_id') == $businessUnit->id)>{{ $businessUnit->name }}{{ $businessUnit->code ? ' · '.$businessUnit->code : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-neutral-700">Jumlah gembok Spare yang disediakan
                    <input name="spare_lock_count" type="number" min="0" max="200" step="1" value="{{ old('spare_lock_count', 20) }}" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                    <span class="mt-1 block text-xs font-normal text-neutral-500">Setiap gembok langsung terdaftar beserta posisinya dan QR-nya tersedia di Settings.</span>
                </label>
                <label class="text-sm font-semibold text-neutral-700">Prefix Kode Spare
                    <input name="spare_code_prefix" value="{{ old('spare_code_prefix', 'SP') }}" maxlength="20" pattern="[A-Za-z0-9]+([_\-][A-Za-z0-9]+)*" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 uppercase">
                    <span class="mt-1 block text-xs font-normal text-neutral-500">Contoh: SP atau SPH menghasilkan SPH-01, SPH-02, dan seterusnya.</span>
                </label>
                <label class="text-sm font-semibold text-neutral-700">Kolom per baris — Seluruh Board
                    <select name="board_columns" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                        @foreach ([10, 15, 20] as $columnCount)
                            <option value="{{ $columnCount }}" @selected((int) old('board_columns', 20) === $columnCount)>{{ $columnCount }} kolom</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="min-h-10 rounded-md bg-blue-700 px-4 text-sm font-bold text-white hover:bg-blue-600">Add Board</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 px-5 py-4"><h2 class="text-lg font-bold text-neutral-900">Boards</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-600"><tr><th class="px-4 py-3">Divisi</th><th class="px-4 py-3">Business Unit</th><th class="px-4 py-3">Kolom</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($boards as $board)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-neutral-900">{{ $board->division_name }}</td>
                                <td class="px-4 py-3">{{ $board->businessUnit?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20 }} kolom</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $board->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-100 text-neutral-600' }}">{{ $board->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="flex flex-wrap gap-2 px-4 py-3">
                                    <a class="rounded-md border border-neutral-300 px-3 py-2 font-semibold hover:bg-neutral-50" href="{{ route('safety.lototo.boards.show', $board) }}">Buka</a>
                                    <a class="rounded-md border border-neutral-300 px-3 py-2 font-semibold hover:bg-neutral-50" href="{{ route('safety.lototo.boards.edit', $board) }}">Edit Board</a>
                                    <form method="POST" action="{{ route('safety.lototo.boards.update', $board) }}" data-confirm-board-status data-board-name="{{ $board->division_name }}" data-next-status="{{ $board->is_active ? 'inactive' : 'active' }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="division_name" value="{{ $board->division_name }}">
                                        <input type="hidden" name="business_unit_id" value="{{ $board->business_unit_id }}">
                                        <input type="hidden" name="is_active" value="{{ $board->is_active ? '0' : '1' }}">
                                        <input type="hidden" name="return_board" value="{{ $returnBoard?->id }}">
                                        <button type="submit" class="rounded-md border px-3 py-2 font-semibold {{ $board->is_active ? 'border-amber-300 text-amber-900 hover:bg-amber-50' : 'border-emerald-300 text-emerald-800 hover:bg-emerald-50' }}">{{ $board->is_active ? 'Nonaktifkan' : 'Aktifkan Kembali' }}</button>
                                    </form>
                                    @if ($board->code !== 'mtn_tr')
                                        <form method="POST" action="{{ route('safety.lototo.boards.destroy', $board) }}" data-confirm-board-delete data-board-name="{{ $board->division_name }} · {{ $board->businessUnit?->name ?? 'tanpa Business Unit' }}">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="return_board" value="{{ $returnBoard?->id }}">
                                            <button
                                                type="submit"
                                                @disabled($board->is_active)
                                                title="{{ $board->is_active ? 'Nonaktifkan board terlebih dahulu sebelum menghapus.' : 'Hapus board beserta konfigurasi slotnya' }}"
                                                class="inline-flex items-center gap-1 rounded-md border border-red-300 px-3 py-2 font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:text-neutral-400 disabled:hover:bg-transparent"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-neutral-500">Belum ada board LOTOTO.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
