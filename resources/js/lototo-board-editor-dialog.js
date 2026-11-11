import { CATEGORIES, CATEGORY_LABELS, DraftError, assignmentField, pad } from './lototo-board-draft';

const MODAL = 'lototo-slot-editor';

/**
 * The Edit Slot popup (form[data-slot-dialog] inside <x-modal name="lototo-slot-editor">). It only edits the
 * draft: apply(changes) updates it or throws a DraftError, whose message stays in the popup.
 */
export function createSlotDialog(form, { getOptions, isBusy }) {
    const fields = {
        category: form.elements.category,
        employee: form.elements.employee_id,
        spare: form.elements.spare_lock_id,
        tool: form.elements.tool_id,
        span: form.elements.tool_column_span,
    };
    const error = form.querySelector('[data-slot-dialog-error]');
    const removeButton = form.querySelector('[data-slot-dialog-remove]');
    const moveNote = form.querySelector('[data-slot-dialog-move-note]');
    const toolCode = form.querySelector('[data-slot-tool-code]');
    let current = null;

    // Tools are records of the board: chosen here, created with "+ Tools" in the toolbar.
    const showToolCode = () => {
        toolCode.textContent = (getOptions().tool ?? []).length
            ? 'Pilih Tool dari data board. Tool baru dibuat lewat tombol + Tools di toolbar.'
            : 'Board ini belum memiliki data Tool. Buat lewat tombol + Tools di toolbar.';
    };

    // The category only says what a slot holds: the slot stays in its band and cell, keeping its number.
    const showMoveNote = () => {
        const target = fields.category.value;
        const { slot } = current;
        const band = CATEGORY_LABELS[slot.section ?? current.category];
        // Without stored cells (database not migrated yet) the board still draws a slot in its category's band.
        const text = slot.grid_row
            ? `Slot tetap di pita ${band} dan urutannya; isinya menjadi ${CATEGORY_LABELS[target]}. Nomor ${pad(slot.position)} tetap; letak slot sesudahnya disesuaikan otomatis.`
            : `Nomor ${pad(slot.position)} tetap; setelah disimpan slot tampil di pita ${CATEGORY_LABELS[target]} karena letak slot belum tersimpan di database.`;
        moveNote.textContent = target === current.category ? '' : text;
        moveNote.hidden = target === current.category;
    };

    const close = () => window.dispatchEvent(new CustomEvent('close-modal', { detail: MODAL }));

    const showError = (message) => {
        error.textContent = message;
        error.hidden = !message;
    };

    const fillAssignments = (category) => {
        const draftSlots = current.draft.slots[category] ?? [];
        const usedAt = (field, id) => draftSlots.find((slot) => slot.key !== current.key && slot[field] === id)?.position;
        const optionList = (select, items, field, text) => {
            select.replaceChildren(new Option('Set as Empty', ''));
            items.forEach((item) => {
                const position = usedAt(field, item.id);
                const option = new Option(text(item) + (position ? ` — dipakai di posisi ${pad(position)}` : ''), String(item.id));
                option.disabled = Boolean(position);
                select.add(option);
            });
            // A saved assignment that is no longer offered (inactive employee or lock) stays selectable, so opening
            // the popup never clears it by accident.
            const assigned = category === current.category ? current.slot[field] : null;
            if (assigned && !items.some((item) => item.id === assigned)) {
                select.add(new Option(`#${assigned} — penempatan saat ini, tidak ada di daftar aktif (tetap dipertahankan)`, String(assigned)));
            }
        };
        const options = getOptions();
        form.querySelector('[data-slot-employee-label]').textContent = category === 'supervisor' ? 'Supervisor' : 'Teknisi';
        if (category === 'technician' || category === 'supervisor') {
            optionList(fields.employee, options[category] ?? [], 'employee_id', (item) => `${item.lototo_number} — ${item.name}`);
        }
        if (category === 'spare') {
            optionList(fields.spare, options.spare ?? [], 'spare_lock_id', (item) => item.code);
        }
        if (category === 'tool') {
            optionList(fields.tool, options.tool ?? [], 'tool_id', (item) => `${item.code} — ${item.name}`);
        }
        form.querySelectorAll('[data-slot-field]').forEach((wrapper) => {
            const visible = wrapper.dataset.slotField === (category === 'technician' || category === 'supervisor' ? 'employee' : category);
            wrapper.hidden = !visible;
            wrapper.querySelectorAll('select, input').forEach((input) => { input.disabled = !visible || current.slot.locked; });
        });
    };

    fields.category.addEventListener('change', () => {
        if (isBusy()) return;
        fillAssignments(fields.category.value);
        const { slot } = current;
        fields.employee.value = '';
        fields.spare.value = '';
        fields.tool.value = '';
        if (fields.category.value === current.category) {
            fields.employee.value = slot.employee_id ?? '';
            fields.spare.value = slot.spare_lock_id ?? '';
            fields.tool.value = slot.tool_id ?? '';
        }
        showMoveNote();
        showToolCode();
        showError('');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        // While a save is running the draft is frozen, whatever triggers the submit.
        if (isBusy()) return;
        const category = fields.category.value;
        const field = assignmentField(category);
        const raw = form.elements[field].value;
        try {
            // No position and no enabled state: the slot keeps both, whatever the category.
            current.apply({
                category,
                [field]: raw === '' ? null : Number(raw),
                tool_column_span: Number(fields.span.value || 1),
            });
            close();
        } catch (exception) {
            if (!(exception instanceof DraftError)) throw exception;
            showError(exception.message);
        }
    });

    removeButton.addEventListener('click', () => {
        if (isBusy()) return;
        try {
            current.remove();
            close();
        } catch (exception) {
            if (!(exception instanceof DraftError)) throw exception;
            showError(exception.message);
        }
    });
    form.querySelector('[data-slot-dialog-close]').addEventListener('click', close);

    return {
        close,
        /** Disables every popup control while a save runs; open() sets the per-slot locks again for the next slot. */
        setDisabled(disabled) {
            [...form.elements].forEach((element) => { element.disabled = disabled; });
        },
        /**
         * Opens the popup for one draft slot; saved tells which rules apply (locked, Technician, empty). A pending
         * slot ({ category, slot }) is not in the draft yet: apply adds it, closing in any way leaves nothing behind.
         */
        open({ draft, saved, key, pending = null, apply, remove }) {
            if (isBusy()) return;
            const found = pending ?? CATEGORIES.map((category) => ({ category, slot: draft.slots[category].find((slot) => slot.key === key) }))
                .find((entry) => entry.slot);
            const original = saved.get(key);
            const isNew = !original;
            current = { draft, key, category: found.category, slot: found.slot, apply, remove, original, isNew };
            const { slot, category } = found;
            const locked = Boolean(slot.locked);
            // Every slot, Technician included, may change category in any band, unless something on it is IN.
            const canChangeCategory = !locked;

            form.querySelector('[data-slot-dialog-title]').textContent = `${isNew ? 'Tambah' : 'Edit'} slot ${CATEGORY_LABELS[category]} ${pad(slot.position)}`;
            form.querySelector('[data-slot-dialog-locked]').hidden = !locked;
            fields.category.value = category;
            fields.category.disabled = !canChangeCategory;
            fillAssignments(category);
            fields.employee.value = slot.employee_id ?? '';
            fields.spare.value = slot.spare_lock_id ?? '';
            fields.tool.value = slot.tool_id ?? '';
            fields.span.value = String(slot.tool_column_span ?? 1);
            showMoveNote();
            showToolCode();
            form.querySelector('[data-slot-dialog-apply]').disabled = locked;
            removeButton.hidden = !isNew || Boolean(pending);
            showError('');
            window.dispatchEvent(new CustomEvent('open-modal', { detail: MODAL }));
        },
    };
}
