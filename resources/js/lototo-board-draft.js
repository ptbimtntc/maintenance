/**
 * Draft of a LOTOTO board configuration for the inline Edit Mode. Pure data, no DOM: the board page edits a
 * copy of the layout endpoint's answer (LototoBoardSettingsController::layout) and sends it back unchanged in
 * shape to updateLayout. These checks only mirror the server rules for quick feedback; the server decides.
 */
export const CATEGORIES = ['technician', 'supervisor', 'spare', 'tool'];

export const CATEGORY_LABELS = { technician: 'Technician', supervisor: 'Supervisor', spare: 'Spare', tool: 'Other / Tools' };

const ASSIGNMENT_FIELDS = { technician: 'employee_id', supervisor: 'employee_id', spare: 'spare_lock_id', tool: 'tool_id' };

let newSlotCount = 0;

export class DraftError extends Error {}

export function assignmentField(category) {
    return ASSIGNMENT_FIELDS[category];
}

export function createDraft(layout) {
    const draft = JSON.parse(JSON.stringify(layout));
    CATEGORIES.forEach((category) => {
        // A slot sits in its band (section) whatever it holds; without the section column the band is the category.
        draft.slots[category] = (draft.slots[category] ?? []).map((slot) => ({ ...slot, section: slot.section ?? category, key: String(slot.id) }));
    });
    // Saved slots removed in this draft (ids); the save deletes them.
    draft.removed = [];

    return draft;
}

/** Saved slots by key ("id"), so every rule below can look at what the database holds now. */
export function savedSlots(layout) {
    const saved = new Map();
    CATEGORIES.forEach((category) => (layout.slots[category] ?? []).forEach((slot) => saved.set(String(slot.id), { ...slot, category })));

    return saved;
}

export function findSlot(draft, key) {
    for (const category of CATEGORIES) {
        const slot = draft.slots[category].find((candidate) => candidate.key === key);
        if (slot) return { category, slot };
    }

    return null;
}

export function slotAt(draft, category, position) {
    return draft.slots[category].find((slot) => slot.position === position) ?? null;
}

export function isEmpty(slot) {
    return !slot.employee_id && !slot.spare_lock_id && !slot.tool_id && !(slot.tool_label ?? '').trim();
}

export function nextPosition(draft, category) {
    return Math.max(0, ...draft.slots[category].map((slot) => slot.position)) + 1;
}

export function spanOf(slot, category) {
    return category === 'tool' ? Math.max(1, Math.min(3, Number(slot.tool_column_span ?? 1))) : 1;
}

/** Every slot drawn in a band, whatever it holds: [{ category, slot }]. */
export function slotsInSection(draft, section) {
    return CATEGORIES.flatMap((category) => draft.slots[category].filter((slot) => slot.section === section).map((slot) => ({ category, slot })));
}

/** The slot drawn in $section over any of the cells row/column..column+span-1, other than $exceptKey. */
export function occupantOf(draft, section, row, column, span, exceptKey = null) {
    const found = slotsInSection(draft, section).find(({ category, slot }) => slot.key !== exceptKey && slot.grid_row === row
        && slot.grid_column < column + span && column < slot.grid_column + spanOf(slot, category));

    return found ? found.slot : null;
}

/** First free cell of a band, reading row by row within its columns. */
export function firstFreeCell(draft, section, span = 1) {
    for (let row = 1; ; row++) {
        for (let column = 1; column + span - 1 <= draft.columns[section]; column++) {
            if (!occupantOf(draft, section, row, column, span)) return { row, column };
        }
    }
}

/**
 * Adds an empty slot of $category in $section (its own band unless said otherwise). It gets a number of its
 * own; with stored cells it also gets a cell: the one given (an empty position drawn on the board) or the first
 * free one (the first free cell reading row by row, so a gap left by a removed slot is filled first).
 */
export function addSlot(draft, category, position = nextPosition(draft, category), cell = null, section = category, extra = {}) {
    if (slotAt(draft, category, position)) {
        throw new DraftError(`Posisi ${position} sudah ada pada ${CATEGORY_LABELS[category]}.`);
    }
    const place = draft.coordinates ? (cell ?? firstFreeCell(draft, section)) : null;
    if (place && occupantOf(draft, section, place.row, place.column, 1)) {
        throw new DraftError(`Sel baris ${place.row} kolom ${place.column} pada pita ${CATEGORY_LABELS[section]} sudah ditempati.`);
    }
    const slot = {
        id: null, key: `new-${++newSlotCount}`, position, section, grid_row: place?.row ?? null, grid_column: place?.column ?? null,
        employee_id: null, spare_lock_id: null, tool_id: null, tool_label: null, tool_column_span: 1, is_enabled: true, locked: false,
        ...extra,
    };
    draft.slots[category].push(slot);
    packDraft(draft);

    return slot;
}

/**
 * Packs one band's slots into $columns in reading order (row, then column, as drawn now): from the top left, row by
 * row, without gaps; a tool wider than what is left of a row starts the next row. The server does the same
 * (LototoSlotGrid::reflow), so the preview is what the board shows after saving. items: [{ key, row, column, span }].
 */
export function reflowSection(items, columns) {
    const order = items.map((item, index) => ({ ...item, index })).sort((a, b) => (a.row ?? Infinity) - (b.row ?? Infinity)
        || (a.column ?? Infinity) - (b.column ?? Infinity) || a.index - b.index);
    const cells = new Map();
    let row = 1;
    let column = 1;
    order.forEach((item) => {
        const span = Math.max(1, Math.min(columns, item.span));
        if (column + span - 1 > columns) {
            row++;
            column = 1;
        }
        cells.set(item.key, { row, column });
        column += span;
    });

    return cells;
}

/**
 * Automatic layout of the whole draft: every band packed in its current reading order with reflowSection, so a
 * Tools slot made wider pushes the slots after it along, a narrower one or a removed slot lets them close up, and
 * no gap is left. Numbers and order never change. The save packs the same way (LototoSlotGrid::reflow), so the
 * preview is what the board shows after saving. Without stored cells the board is still placed by number.
 */
export function packDraft(draft) {
    if (!draft.coordinates) return;
    CATEGORIES.forEach((section) => {
        const entries = slotsInSection(draft, section);
        const cells = reflowSection(entries.map(({ category, slot }) => ({ key: slot.key, row: slot.grid_row, column: slot.grid_column, span: spanOf(slot, category) })), draft.columns[section]);
        entries.forEach(({ slot }) => {
            const cell = cells.get(slot.key);
            slot.grid_row = cell.row;
            slot.grid_column = cell.column;
        });
    });
}

/**
 * Sets the board's one column count ("Kolom per baris", board_columns, the value Settings shows): every band takes
 * it and its slots are packed into it in reading order. Slots keep their id, category, number and content.
 */
export function setColumns(draft, count) {
    const { min, max } = draft.column_limits ?? { min: 5, max: 30 };
    if (!Number.isInteger(count) || count < min || count > max) {
        throw new DraftError(`Jumlah kolom per baris harus ${min} sampai ${max}.`);
    }
    draft.board_columns = count;
    draft.columns_changed = true;
    CATEGORIES.forEach((category) => { draft.columns[category] = count; });
    packDraft(draft);
}

/** Bands still using their own width from before (they get the board count once it is changed). */
export function bandsOffBoardColumns(draft) {
    return draft.columns_changed ? [] : CATEGORIES.filter((category) => draft.columns[category] !== draft.board_columns);
}

/** Moves a band (not Technician) one place up (-1) or down (+1) among the bands below Technician. */
export function moveSection(draft, section, step) {
    const order = [...draft.section_order];
    const from = order.indexOf(section);
    const to = from + step;
    if (from < 0 || to < 0 || to >= order.length) return false;
    [order[from], order[to]] = [order[to], order[from]];
    draft.section_order = order;

    return true;
}

/** Puts $section right before $before (or last when $before is null). */
export function placeSection(draft, section, before) {
    const order = draft.section_order.filter((name) => name !== section);
    const index = before === null ? order.length : order.indexOf(before);
    order.splice(index < 0 ? order.length : index, 0, section);
    draft.section_order = order;
}
/** Removes a slot added in this draft. */
export function removeNewSlot(draft, key) {
    const found = findSlot(draft, key);
    if (!found || found.slot.id !== null) {
        throw new DraftError('Posisi ini sudah tersimpan; hapus lewat tombol hapus pada slot.');
    }
    draft.slots[found.category] = draft.slots[found.category].filter((slot) => slot.key !== key);
    packDraft(draft);
}

/**
 * Removes one slot of any category, Technician included (the save deletes a saved one); the slots after it close
 * up. A slot with something IN (employee activity, spare lock in use or tool IN) is refused. The employee, lock
 * or tool it held and every LOTOTO activity keep their own data.
 */
export function removeSlot(draft, key) {
    const found = findSlot(draft, key);
    if (!found) throw new DraftError('Posisi tidak ditemukan pada draft.');
    const { category, slot } = found;
    if (slot.locked) {
        throw new DraftError(`${CATEGORY_LABELS[category]} ${pad(slot.position)} memiliki aktivitas LOTOTO aktif, gembok yang sedang digunakan, atau Tools yang sedang IN, sehingga tidak dapat dihapus.`);
    }
    if (slot.id !== null) draft.removed.push(slot.id);
    draft.slots[category] = draft.slots[category].filter((candidate) => candidate.key !== key);
    packDraft(draft);

    return found;
}

/**
 * Applies the popup's values to one slot. $changes may hold category, position, the assignment field of the
 * (new) category, tool_column_span and is_enabled.
 */
export function updateSlot(draft, saved, key, changes) {
    const found = findSlot(draft, key);
    if (!found) throw new DraftError('Posisi tidak ditemukan pada draft.');
    const { slot } = found;
    const original = saved.get(key);
    const category = changes.category ?? found.category;
    const position = changes.position ?? slot.position;

    if (slot.locked) {
        throw new DraftError('Slot ini memiliki aktivitas LOTOTO aktif, gembok yang sedang digunakan, atau Tools yang sedang IN, sehingga tidak dapat diubah.');
    }
    // Any slot may change category; what it held leaves the board (the editor asks first when it is not empty).
    if (original?.category === 'technician' && category === 'technician' && position !== original.position) {
        // Only the number: a Technician slot's number follows the employee's LOTOTO number (server rule too).
        throw new DraftError('Nomor slot Technician mengikuti nomor LOTOTO karyawan dan tidak dapat diubah. Kategori dan penghapusan slot tetap dapat diatur.');
    }
    if (!Number.isInteger(position) || position < 1 || position > 99999) {
        throw new DraftError('Nomor posisi harus angka 1 sampai 99999.');
    }
    const span = category === 'tool' ? Math.max(1, Math.min(3, Number(changes.tool_column_span ?? slot.tool_column_span ?? 1))) : 1;
    if (draft.coordinates && slot.grid_row !== null && slot.grid_row !== undefined) {
        // The category only changes what the slot holds: it stays in its band, at its place in the reading order,
        // keeping its number. Its cell and those after it are computed again (packDraft), so a wider or narrower
        // span never collides with an old cell; only a span wider than the whole band is refused.
        const width = draft.columns[slot.section];
        if (span > width) {
            throw new DraftError(`Slot selebar ${span} kolom tidak muat pada pita ${CATEGORY_LABELS[slot.section]} (${width} kolom).`);
        }
        if (position !== slot.position && draft.slots[category].some((other) => other.key !== key && other.position === position)) {
            throw new DraftError(`Posisi ${pad(position)} sudah dipakai pada ${CATEGORY_LABELS[category]}.`);
        }
    } else {
        const occupant = slotAt(draft, category, position);
        if (occupant && occupant.key !== key) {
            // A slot is never moved to another free index behind the user's back.
            throw new DraftError(category === found.category
                ? `Posisi ${pad(position)} sudah dipakai pada ${CATEGORY_LABELS[category]}.`
                : `Posisi ${pad(position)} pada ${CATEGORY_LABELS[category]} sudah terisi slot lain, jadi kategori tidak dapat diganti tanpa mengubah posisi. Slot tidak dipindahkan.`);
        }
    }
    const field = assignmentField(category);
    const value = changes[field] ?? null;
    if (value !== null) {
        const other = draft.slots[category].find((candidate) => candidate.key !== key && candidate[field] === value);
        if (other) {
            throw new DraftError(`Pilihan ini sudah ditempatkan pada posisi ${other.position}. Kosongkan posisi itu terlebih dahulu.`);
        }
    }

    const tool = category === 'tool' && value !== null ? (draft.options?.tool ?? []).find((item) => item.id === value) : null;
    const updated = {
        ...slot,
        position,
        employee_id: null,
        spare_lock_id: null,
        tool_id: null,
        // The board shows the tool's name.
        tool_label: tool ? tool.name : null,
        tool_column_span: category === 'tool' ? span : slot.tool_column_span,
        is_enabled: changes.is_enabled ?? slot.is_enabled,
        [field]: value,
    };
    draft.slots[found.category] = draft.slots[found.category].filter((candidate) => candidate.key !== key);
    draft.slots[category].push(updated);
    packDraft(draft);

    return updated;
}

/** The draft in the shape PUT /safety/lototo/boards/{board}/layout expects. */
export function toPayload(draft) {
    return {
        version: draft.version,
        title: draft.title,
        subtitle: draft.subtitle,
        // One column count for the whole board; apply_columns says it was changed here, so every band takes it.
        board_columns: draft.board_columns,
        apply_columns: Boolean(draft.columns_changed),
        removed: [...(draft.removed ?? [])],
        section_order: [...draft.section_order],
        components: { ...draft.components },
        slots: Object.fromEntries(CATEGORIES.map((category) => [category, draft.slots[category]
            .slice()
            .sort((a, b) => a.position - b.position)
            .map(({ key, tool_code: toolCode, ...slot }) => slot)])),
    };
}

/** Human readable list of what the draft changes compared with the saved layout. */
export function describeChanges(layout, draft, label) {
    const saved = savedSlots(layout);
    const lines = [];
    ['title', 'subtitle'].forEach((field) => {
        if (draft[field] !== layout[field]) lines.push(`${field === 'title' ? 'Judul' : 'Subtitle'}: "${draft[field]}"`);
    });
    if (draft.columns_changed) lines.push(`Kolom per baris (seluruh board): ${draft.board_columns}, slot disusun ulang`);
    if (draft.section_order.join() !== layout.section_order.join()) {
        lines.push(`Urutan section: ${draft.section_order.map((section) => (section === 'other' ? 'Other' : CATEGORY_LABELS[section])).join(' > ')}`);
    }
    Object.keys(draft.components).forEach((component) => {
        if (draft.components[component] !== layout.components[component]) {
            lines.push(`${component === 'other' ? 'Other / Tools' : CATEGORY_LABELS[component]}: ${draft.components[component] ? 'ditampilkan' : 'disembunyikan'}`);
        }
    });
    (draft.removed ?? []).forEach((id) => {
        const original = saved.get(String(id));
        lines.push(`Dihapus · ${CATEGORY_LABELS[original.category]} ${pad(original.position)}: ${label(original.category, original)}`);
    });
    CATEGORIES.forEach((category) => draft.slots[category].forEach((slot) => {
        const original = saved.get(slot.key);
        const now = `${CATEGORY_LABELS[category]} ${pad(slot.position)}: ${label(category, slot)}${slot.is_enabled ? '' : ' (disabled)'}`;
        if (!original) {
            lines.push(`Posisi baru · ${now}`);
            return;
        }
        const field = assignmentField(category);
        const same = original.category === category && original.position === slot.position && original.is_enabled === slot.is_enabled
            && (original[field] ?? null) === (slot[field] ?? null)
            && (category !== 'tool' || original.tool_column_span === slot.tool_column_span);
        if (!same) {
            lines.push(`${CATEGORY_LABELS[original.category]} ${pad(original.position)} → ${now}`);
        }
    }));

    return lines;
}

export function pad(position) {
    return String(position).padStart(2, '0');
}
