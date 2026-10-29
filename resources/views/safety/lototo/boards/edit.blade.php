<x-app-layout>
    <x-slot name="header">
        <div class="flex w-full items-center gap-4">
            @include('safety.lototo._back-button', ['href' => route('safety.lototo.boards.show', $board), 'label' => 'Kembali ke '.$board->division_name, 'unsaved' => true])
            <span>Edit Board · {{ $board->division_name }}</span>
        </div>
    </x-slot>

    <div class="lototo-boards-page mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('safety.lototo.boards.index', ['board' => $board->id]) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold" data-unsaved-back>Manage Boards</a>
        </div>
        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">Identitas Board</h2>
            <form method="POST" action="{{ route('safety.lototo.boards.update', $board) }}" class="mt-4 grid gap-4 sm:grid-cols-2" data-unsaved-watch>
                @csrf @method('PUT')
                <input type="hidden" name="return_board" value="{{ $board->id }}">
                <label class="text-sm font-semibold text-neutral-700">Nama Divisi
                    <input name="division_name" value="{{ old('division_name', $board->division_name) }}" required maxlength="100" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                </label>
                <label class="text-sm font-semibold text-neutral-700">Business Unit
                    <select name="business_unit_id" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                        @foreach ($businessUnits as $businessUnit)<option value="{{ $businessUnit->id }}" @selected(old('business_unit_id', $board->business_unit_id) == $businessUnit->id)>{{ $businessUnit->name }}{{ $businessUnit->code ? ' · '.$businessUnit->code : '' }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-neutral-700">Jumlah gembok Spare yang disediakan
                    <input name="spare_lock_count" type="number" min="0" max="200" step="1" value="{{ old('spare_lock_count', $spareSlotCount) }}" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">
                    <span class="mt-1 block text-xs font-normal text-neutral-500">Menambah jumlah membuat posisi dan gembok fisik baru. Mengurangi jumlah ikut menghapus gembok di posisi terakhir hanya jika gembok itu belum pernah digunakan; gembok yang sedang digunakan atau memiliki riwayat ditolak.</span>
                </label>
                <label class="text-sm font-semibold text-neutral-700">Prefix Kode Spare untuk gembok baru
                    <input name="spare_code_prefix" value="{{ old('spare_code_prefix', $sparePrefix) }}" maxlength="20" pattern="[A-Za-z0-9]+([_\-][A-Za-z0-9]+)*" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300 uppercase">
                    <span class="mt-1 block text-xs font-normal text-neutral-500">Hanya dipakai untuk gembok yang baru dibuat. Kode gembok lama tidak diubah; ubah kode satu per satu di Settings → Spare.</span>
                </label>
                <div class="text-sm font-semibold text-neutral-700">Status
                    <p class="mt-1 flex min-h-10 items-center"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $board->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-100 text-neutral-700' }}">{{ $board->is_active ? 'Active' : 'Inactive' }}</span></p>
                </div>
                <input type="hidden" name="is_active" value="{{ $board->is_active ? '1' : '0' }}">
                <div class="flex items-end gap-2"><button class="min-h-10 rounded-md bg-blue-700 px-4 text-sm font-bold text-white">Save Board</button><a href="{{ route('safety.lototo.boards.index', ['board' => $board->id]) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold" data-unsaved-back>Cancel</a></div>
            </form>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">Tampilan dan Komponen</h2>
            <p class="mt-1 text-sm text-neutral-600">Pengaturan ini hanya menyimpan teks, urutan section, dan visibilitas komponen. Data operasional serta assignment lock tidak berubah.</p>
            @php($componentSettings = $board->component_settings ?? [])
            @php($sectionOrder = array_values(array_filter($board->layout['section_order'] ?? ['spare', 'other'], fn ($section) => in_array($section, ['other', 'spare'], true))))
            <form method="POST" action="{{ route('safety.lototo.boards.presentation.update', $board) }}" class="mt-4 space-y-5" data-unsaved-watch>
                @csrf @method('PUT')
                <label class="block text-sm font-semibold text-neutral-700">Judul Board<input name="title" value="{{ old('title', $board->title) }}" required maxlength="160" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300"></label>
                <label class="block text-sm font-semibold text-neutral-700">Subtitle<input name="subtitle" value="{{ old('subtitle', $board->subtitle) }}" required maxlength="255" class="mt-1 block min-h-10 w-full rounded-md border-neutral-300"></label>
                <fieldset><legend class="text-sm font-bold text-neutral-800">Kolom per baris — Seluruh Board</legend><div class="mt-2"><label class="text-xs font-semibold text-neutral-600">Jumlah kolom<select name="board_columns" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">@foreach ([10, 15, 20] as $columnCount)<option value="{{ $columnCount }}" @selected((int) old('board_columns', $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20) === $columnCount)>{{ $columnCount }} kolom</option>@endforeach</select></label></div></fieldset>
                <fieldset><legend class="text-sm font-bold text-neutral-800">Urutan section di bawah Technician / Supervisor</legend><div class="mt-2 grid gap-3 sm:grid-cols-2">@for ($position = 0; $position < 2; $position++)<label class="text-xs font-semibold text-neutral-600">Posisi {{ $position + 1 }}<select name="section_order[]" required class="mt-1 block min-h-10 w-full rounded-md border-neutral-300">@foreach (['other' => 'Other / Tools', 'spare' => 'Spare'] as $optionKey => $optionLabel)<option value="{{ $optionKey }}" @selected(($sectionOrder[$position] ?? null) === $optionKey)>{{ $optionLabel }}</option>@endforeach</select></label>@endfor</div></fieldset>
                <fieldset><legend class="text-sm font-bold text-neutral-800">Komponen board</legend><div class="mt-2 flex flex-wrap gap-4">@foreach (['technician' => 'Technician', 'supervisor' => 'Supervisor', 'other' => 'Other / Tools', 'spare' => 'Spare'] as $key => $label)<label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="components[{{ $key }}]" value="0"><input type="checkbox" name="components[{{ $key }}]" value="1" @checked(old('components.'.$key, $componentSettings[$key] ?? true))> {{ $label }}</label>@endforeach</div></fieldset>
                <div class="flex justify-end gap-2"><a href="{{ route('safety.lototo.boards.show', $board) }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold" data-unsaved-back>Cancel</a><button class="min-h-10 rounded-md bg-blue-700 px-4 text-sm font-bold text-white">Save Changes</button></div>
            </form>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">Konfigurasi Board</h2>
            <p class="mt-1 text-sm text-neutral-600">Pilih area untuk mengatur posisi slot dan assignment.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach (['technician' => 'Technician', 'supervisor' => 'Supervisor', 'spare' => 'Spare', 'tool' => 'Other / Tools'] as $section => $label)
                    <a href="{{ route('safety.lototo.settings', ['board' => $board->id, 'section' => $section]) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-semibold hover:bg-neutral-50">{{ $label }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
