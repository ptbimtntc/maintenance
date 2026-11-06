{{-- QR print designs (Print Out.docx, Desain 1-5) for the QR records of this board (resources/js/lototo-print.js).
     "Download QR" on a record opens this popup: choose the record, tick designs, preview them filled with that record,
     download one PDF whose pages have each design's printed size. QR URLs are the ones the QR cards above use. --}}
@php
    $printRecords = collect();
    foreach ($qrGroups as $qrGroup) {
        foreach ($qrGroup['employees'] as $employee) {
            $printRecords->push([
                'key' => "{$qrGroup['kind']}-{$employee->id}",
                'kind' => $qrGroup['kind'],
                'group' => $qrGroup['label'],
                // Full name; the designs show only its first word (two when the first is an initial), see shortName().
                'name' => \Illuminate\Support\Str::upper($employee->full_name ?: $employee->preferred_name),
                'number' => (string) $employee->lototo_number,
                // ?v= changes with the employee record, so a replaced photo is fetched again instead of read from the browser cache.
                'photo' => $employee->photo_path ? route('safety.lototo.print.photo', $employee, absolute: false).'?v='.$employee->updated_at?->timestamp : null,
                'qr' => \App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', ['lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board' => $board->id]),
                // Desain 4: the employee's LOTOTO Activity page on this board (login and visibility rules of that page apply).
                'activity' => url(route('safety.lototo.show', ['employee' => $employee->id, 'board' => $board->id], absolute: false)),
            ]);
        }
    }
    foreach ($qrSpareLocks as $spareLock) {
        $printRecords->push([
            'key' => "spare-{$spareLock->id}",
            'kind' => 'spare',
            'group' => 'Spare Locks',
            'name' => 'SPARE',
            'number' => (string) $spareLock->code,
            'photo' => null,
            'qr' => \App\Http\Middleware\AllowLototoQrForm::url('safety.lototo.form', ['spare_lock' => $spareLock->code, 'board' => $board->id]),
        ]);
    }
    foreach ($tools as $tool) {
        $printRecords->push([
            'key' => "tool-{$tool->id}",
            'kind' => 'tool',
            'group' => 'Tools',
            'name' => \Illuminate\Support\Str::upper($tool->name),
            'number' => (string) $tool->code,
            'photo' => $tool->image_path ? route('safety.lototo.tools.image', $tool, absolute: false) : null,
            'qr' => route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $board->id]),
        ]);
    }
@endphp
<script type="application/json" data-print-records>@json($printRecords->values())</script>

<dialog class="lototo-print-dialog" data-print-designs aria-labelledby="lototo-print-title">
    <div class="lototo-print-dialog__panel">
        <header class="lototo-print-dialog__header">
            <div>
                <h2 id="lototo-print-title" class="text-base font-bold text-neutral-900">Download QR — desain cetak</h2>
                <p class="text-xs text-neutral-600">Pilih identitas dan desain. Preview memakai data record terpilih; file PDF memakai ukuran cetak asli setiap desain (cetak dengan skala 100% / Actual size).</p>
            </div>
            <label class="lototo-print-dialog__identity">
                <span>Identitas QR</span>
                <select data-print-identity aria-label="Identitas QR yang dicetak">
                    @foreach ($printRecords->groupBy('group') as $group => $records)
                        <optgroup label="{{ $group }}">
                            @foreach ($records as $record)
                                <option value="{{ $record['key'] }}">{{ $record['number'] }} — {{ $record['name'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
        </header>

        <div class="lototo-print-dialog__tools">
            <button type="button" class="lototo-editor-button" data-print-select-all>Pilih Semua</button>
            <button type="button" class="lototo-editor-button" data-print-select-none>Batalkan Pilihan</button>
            <p class="text-xs text-neutral-600" data-print-note role="status"></p>
        </div>

        <div class="lototo-print-dialog__designs" data-print-cards></div>

        <p class="lototo-editor-message" role="alert" data-print-message hidden></p>

        <footer class="lototo-print-dialog__footer">
            <button type="button" class="lototo-editor-button" data-print-close>Tutup</button>
            <button type="button" class="lototo-editor-button lototo-editor-button--primary" data-print-download disabled>Download</button>
        </footer>
    </div>
</dialog>
