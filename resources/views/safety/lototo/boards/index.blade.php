<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center gap-4">
            @include('safety.lototo._back-button', ['href' => $returnBoard ? route('safety.lototo.boards.show', $returnBoard) : route('safety.lototo.index'), 'label' => 'Kembali ke LOTOTO Board'])
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
            <p class="mt-1 text-sm text-neutral-600">Board baru memakai layout standar LOTOTO. Technician dan Supervisor diambil dari Business Unit terpilih; gembok Spare dibuat otomatis dengan kode SP-{inisial Business Unit}-{nomor urut} (contoh: SP-HP-01, SP-HP-02).</p>
            <form method="POST" action="{{ route('safety.lototo.boards.store') }}" class="mt-4 grid gap-4 sm:grid-cols-4" data-board-create-form>
                @csrf
                <label class="text-sm font-semibold text-neutral-700">Division Name
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
                <label class="text-sm font-semibold text-neutral-700">Number of Spare Locks
                    <input name="spare_lock_count" type="number" min="0" max="200" step="1" value="{{ old('spare_lock_count', 10) }}" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                </label>
                <div class="flex items-end">
                    <button type="submit" class="min-h-10 rounded-md bg-blue-700 px-4 text-sm font-bold text-white hover:bg-blue-600">Save</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-200 px-5 py-4"><h2 class="text-lg font-bold text-neutral-900">Boards</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-600"><tr><th class="px-4 py-3">Divisi</th><th class="px-4 py-3">Business Unit</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($boards as $board)
                            @php($openActivities = (int) ($activeActivities[$board->code] ?? 0))
                            <tr>
                                <td class="px-4 py-3 font-semibold text-neutral-900">{{ $board->division_name }}</td>
                                <td class="px-4 py-3">{{ $board->businessUnit?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-bold {{ $board->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-200 text-neutral-700' }}" data-board-status>{{ $board->is_active ? 'Active' : 'Inactive' }}</span>
                                    @if ($openActivities > 0)
                                        <span class="ml-1 text-xs text-amber-800">{{ $openActivities }} aktivitas IN</span>
                                    @endif
                                </td>
                                <td class="flex flex-wrap gap-2 px-4 py-3">
                                    <a class="rounded-md border border-neutral-300 px-3 py-2 font-semibold hover:bg-neutral-50" href="{{ route('safety.lototo.boards.show', $board) }}">Open</a>
                                    <form method="POST" action="{{ route('safety.lototo.boards.status', $board) }}" data-confirm-board-status data-board-name="{{ $board->division_name }}" data-next-status="{{ $board->is_active ? 'inactive' : 'active' }}" data-open-activities="{{ $openActivities }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $board->is_active ? '0' : '1' }}">
                                        <input type="hidden" name="return_board" value="{{ $returnBoard?->id }}">
                                        <button type="submit" class="rounded-md border px-3 py-2 font-semibold {{ $board->is_active ? 'border-amber-300 text-amber-900 hover:bg-amber-50' : 'border-emerald-300 text-emerald-800 hover:bg-emerald-50' }}">{{ $board->is_active ? 'Set Inactive' : 'Set Active' }}</button>
                                    </form>
                                    @if ($board->code !== 'mtn_tr')
                                        <form method="POST" action="{{ route('safety.lototo.boards.destroy', $board) }}" data-confirm-board-delete data-board-name="{{ $board->division_name }} · {{ $board->businessUnit?->name ?? 'tanpa Business Unit' }}">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="return_board" value="{{ $returnBoard?->id }}">
                                            <button
                                                type="submit"
                                                @disabled($board->is_active)
                                                title="{{ $board->is_active ? 'Nonaktifkan board terlebih dahulu sebelum menghapus.' : 'Hapus board yang belum pernah digunakan' }}"
                                                class="inline-flex items-center gap-1 rounded-md border border-red-300 px-3 py-2 font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:text-neutral-400 disabled:hover:bg-transparent"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-neutral-500">Belum ada board LOTOTO.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="border-t border-neutral-200 px-5 py-3 text-xs text-neutral-600">Board Inactive tidak menerima aktivitas LOTOTO baru; aktivitas yang masih IN tetap dapat diselesaikan (OUT / Handover). Delete hanya untuk board Inactive yang belum pernah digunakan: board dengan histori aktivitas, atau gembok Spare / Tools yang sedang IN atau punya riwayat, ditolak dan tetap tersimpan.</p>
        </section>
    </div>
</x-app-layout>
