{{-- Edit Mode controls for Safety managers (resources/js/lototo-board-editor.js). Hidden until Edit Board is pressed.
     The board layout is saved only by "Simpan Perubahan"; the Kelola Tools / Kelola Spare Lock popups
     (resources/js/lototo-board-editor-manage.js) change tools and spare locks at once, without placing them on a slot:
     new ones are then offered in the Edit Slot popup. --}}
@php
    $editorCategories = ['technician' => 'Technician', 'supervisor' => 'Supervisor', 'spare' => 'Spare', 'tool' => 'Other / Tools'];
@endphp
<section class="lototo-board-editor rounded-lg border border-blue-300 bg-blue-50 p-3 text-sm text-neutral-800" data-editor-toolbar hidden aria-label="Edit Mode board">
    <div class="flex flex-wrap items-center gap-2">
        <p class="font-bold text-blue-900">Edit Mode</p>
        <p class="text-xs text-neutral-600">Klik slot untuk mengubahnya, + di kanan bawah section untuk menambah slot, ikon hapus pada slot untuk menghapusnya, atau seret pita lewat ikon panah empat arah. Perubahan layout hanya draft sampai disimpan.</p>
        <p class="text-xs font-semibold text-amber-800" data-editor-dirty hidden>Ada perubahan yang belum disimpan.</p>
        <div class="ml-auto flex flex-wrap gap-2">
            {{-- Board data (saved at once, never placed on a slot); Technician and Supervisor come from Employee. --}}
            <button type="button" class="lototo-editor-button" data-editor-manage="tools" title="Tambah, ubah, atau hapus Tools board ini">Kelola Tools</button>
            <button type="button" class="lototo-editor-button" data-editor-manage="spare" title="Tambah atau hapus gembok Spare board ini">Kelola Spare Lock</button>
            <button type="button" class="lototo-editor-button" data-editor-cancel>Batal</button>
            <button type="button" class="lototo-editor-button lototo-editor-button--primary" data-editor-save>Simpan Perubahan</button>
        </div>
    </div>

    <div class="lototo-editor-message mt-2" role="alert" data-editor-message hidden></div>

    <details class="mt-2">
        <summary class="cursor-pointer font-semibold text-blue-900">Pengaturan board</summary>
        <form class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-editor-settings novalidate>
            <label class="text-xs font-semibold text-neutral-700 sm:col-span-2">Judul Board
                <input name="title" maxlength="160" required class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm">
            </label>
            <label class="text-xs font-semibold text-neutral-700 sm:col-span-2">Subtitle
                <input name="subtitle" maxlength="255" required class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm">
            </label>
            <fieldset class="text-xs font-semibold text-neutral-700 sm:col-span-2">
                <legend>Komponen ditampilkan</legend>
                <div class="mt-2 flex flex-wrap gap-4 font-normal">
                    @foreach (['technician' => 'Technician', 'supervisor' => 'Supervisor', 'spare' => 'Spare', 'other' => 'Other / Tools'] as $component => $label)
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="component_{{ $component }}"> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <p class="text-xs text-neutral-600 sm:col-span-2 lg:col-span-4">Jumlah kolom per pita diatur di samping pilihan board; urutan pita diatur dengan menyeret ikon panah empat arah.</p>
        </form>
    </details>

    <div class="mt-2" data-editor-changes hidden>
        <p class="font-semibold text-blue-900">Perubahan draft</p>
        <ul class="mt-1 list-inside list-disc text-xs text-neutral-700"></ul>
    </div>
</section>

<x-modal name="lototo-slot-editor" maxWidth="md">
    <form class="space-y-3 p-5 text-sm" data-slot-dialog novalidate>
        <h2 class="text-base font-bold text-neutral-900" data-slot-dialog-title>Edit slot</h2>
        <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900" data-slot-dialog-locked hidden>
            Slot ini sedang memiliki aktivitas LOTOTO (IN) atau gembok yang sedang digunakan, sehingga tidak dapat diubah atau dipindahkan.
        </p>
        <label class="block text-xs font-semibold text-neutral-700">Kategori
            <select name="category" class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm">
                @foreach ($editorCategories as $category => $label)
                    <option value="{{ $category }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="mt-1 block font-normal text-neutral-500">Kategori hanya dapat diganti untuk slot kosong, bukan posisi Technician. Nomor posisi slot tidak berubah.</span>
            <span class="mt-1 block font-normal text-blue-800" data-slot-dialog-move-note hidden></span>
        </label>
        <label class="block text-xs font-semibold text-neutral-700" data-slot-field="employee"><span data-slot-employee-label>Teknisi</span>
            <select name="employee_id" class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm"></select>
        </label>
        <label class="block text-xs font-semibold text-neutral-700" data-slot-field="spare" hidden>Gembok spare
            <select name="spare_lock_id" class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm"></select>
        </label>
        <div class="grid gap-3 sm:grid-cols-2" data-slot-field="tool" hidden>
            <label class="block text-xs font-semibold text-neutral-700">Tool
                <select name="tool_id" class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm"></select>
            </label>
            <label class="block text-xs font-semibold text-neutral-700">Ukuran kolom
                <select name="tool_column_span" class="mt-1 block min-h-9 w-full rounded-md border-neutral-300 text-sm">
                    <option value="1">Tipe 1 · satu kolom</option>
                    <option value="2">Tipe 2 · dua kolom</option>
                    <option value="3">Tipe 3 · tiga kolom</option>
                </select>
            </label>
            <p class="text-xs text-neutral-600 sm:col-span-2" data-slot-tool-code></p>
        </div>
        <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-900" role="alert" data-slot-dialog-error hidden></p>
        <div class="flex flex-wrap justify-end gap-2 pt-2">
            <button type="button" class="lototo-editor-button mr-auto" data-slot-dialog-remove hidden>Hapus posisi baru</button>
            <button type="button" class="lototo-editor-button" data-slot-dialog-close>Tutup</button>
            <button type="submit" class="lototo-editor-button lototo-editor-button--primary" data-slot-dialog-apply>Terapkan ke draft</button>
        </div>
    </form>
</x-modal>

{{-- Kelola Tools / Kelola Spare Lock (resources/js/lototo-board-editor-manage.js). --}}
<dialog class="lototo-manage-dialog" data-manage-tools aria-labelledby="lototo-manage-tools-title">
    <div class="lototo-manage-head">
        <h2 id="lototo-manage-tools-title">Kelola Tools</h2>
        <button type="button" class="lototo-editor-button" data-manage-close aria-label="Tutup">Tutup</button>
    </div>
    <p class="lototo-manage-message" role="alert" data-manage-message hidden></p>
    <div data-tools-list-view>
        <p class="lototo-manage-note">Tools baru tidak otomatis ditempatkan; pilih lewat Edit Slot pada slot Other / Tools. Tools yang sedang IN, punya riwayat, atau masih terpasang di slot tidak dapat dihapus.</p>
        <ul class="lototo-manage-list" data-tools-list></ul>
        <p class="lototo-manage-empty" data-tools-empty hidden>Belum ada Tools di board ini.</p>
        <div class="lototo-manage-actions"><button type="button" class="lototo-editor-button lototo-editor-button--primary" data-tool-add>+ Tambah Tools</button></div>
    </div>
    <form data-tool-form hidden novalidate>
        <h3 class="lototo-manage-subtitle" data-tool-form-title>Tambah Tools</h3>
        <label class="lototo-manage-field">Nama Tools
            <input name="name" maxlength="100" required autocomplete="off">
        </label>
        <label class="lototo-manage-field">Gambar
            <input name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <span class="lototo-manage-hint" data-tool-image-hint></span>
        </label>
        <img class="lototo-manage-preview" alt="Pratinjau gambar Tools" data-tool-preview hidden>
        <div class="lototo-manage-actions">
            <button type="button" class="lototo-editor-button" data-tool-cancel>Batal</button>
            <button type="submit" class="lototo-editor-button lototo-editor-button--primary">Simpan</button>
        </div>
    </form>
</dialog>

<dialog class="lototo-manage-dialog" data-manage-spare aria-labelledby="lototo-manage-spare-title">
    <div class="lototo-manage-head">
        <h2 id="lototo-manage-spare-title">Kelola Spare Lock</h2>
        <button type="button" class="lototo-editor-button" data-manage-close aria-label="Tutup">Tutup</button>
    </div>
    <p class="lototo-manage-message" role="alert" data-manage-message hidden></p>
    <div data-spare-list-view>
        <p class="lototo-manage-note">Kode dibuat otomatis. Gembok baru tidak otomatis ditempatkan; pilih lewat Edit Slot pada slot Spare. Gembok yang sedang IN, punya riwayat, atau masih terpasang di slot tidak dapat dihapus.</p>
        <ul class="lototo-manage-list" data-spare-list></ul>
        <p class="lototo-manage-empty" data-spare-empty hidden>Belum ada gembok Spare di board ini.</p>
        <div class="lototo-manage-actions"><button type="button" class="lototo-editor-button lototo-editor-button--primary" data-spare-add>+ Tambah Spare Lock</button></div>
    </div>
    <div data-spare-confirm hidden>
        <p>Tambahkan satu gembok Spare dengan kode <strong data-spare-next></strong>? Gembok langsung tersimpan sebagai data board ini.</p>
        <div class="lototo-manage-actions">
            <button type="button" class="lototo-editor-button" data-spare-cancel>Batal</button>
            <button type="button" class="lototo-editor-button lototo-editor-button--primary" data-spare-save>Simpan</button>
        </div>
    </div>
</dialog>

{{-- Card templates for the live preview in Edit Mode: the board's own card partial with placeholder data
     (__NAMA__, __NO__, __KODE__, __TOOL__), so a slot whose category or content changes is drawn exactly like the
     board draws that kind of slot. The script fills in the slot's number, cell, band and content. --}}
@php
    $previewEmployee = (new \App\Models\Employee())->forceFill(['id' => 0, 'full_name' => '__NAMA__', 'preferred_name' => null, 'lototo_number' => '__NO__', 'photo_path' => null]);
    $previewSlot = function (string $category, array $relations = [], array $attributes = []) {
        $slot = (new \App\Models\LototoBoardSlot())->forceFill(['category' => $category, 'position' => 0, 'grid_row' => 0, 'grid_column' => 0, 'is_enabled' => true, 'tool_column_span' => 1, ...$attributes]);
        foreach (['employee' => null, 'spareLock' => null, 'tool' => null, ...$relations] as $relation => $value) {
            $slot->setRelation($relation, $value);
        }

        return $slot;
    };
    $previewLock = (new \App\Models\SpareLock())->forceFill(['id' => 0, 'code' => '__KODE__', 'is_active' => true])->setRelation('currentActivity', null);
    $previewCards = [
        'technician-empty' => $previewSlot('technician'),
        'technician-filled' => $previewSlot('technician', ['employee' => $previewEmployee]),
        'supervisor-empty' => $previewSlot('supervisor'),
        'supervisor-filled' => $previewSlot('supervisor', ['employee' => $previewEmployee]),
        'spare-empty' => $previewSlot('spare'),
        'spare-filled' => $previewSlot('spare', ['spareLock' => $previewLock]),
        'tool-empty' => $previewSlot('tool'),
        'tool-filled' => $previewSlot('tool', [], ['tool_label' => '__TOOL__']),
    ];
@endphp
<div hidden data-card-templates>
    @foreach ($previewCards as $name => $previewCard)
        <template data-card-template="{{ $name }}">@include('safety.lototo._slot-card', ['slot' => $previewCard, 'band' => 'preview', 'activeActivities' => collect(), 'pendingFollowUps' => collect()])</template>
    @endforeach
</div>
