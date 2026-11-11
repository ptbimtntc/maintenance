// Run with: node --test tests/js/lototo-board-draft.test.js
import assert from 'node:assert/strict';
import test from 'node:test';
import {
    DraftError, addSlot, bandsOffBoardColumns, createDraft, describeChanges, moveSection, placeSection, reflowSection, removeNewSlot, removeSlot, savedSlots, setColumns,
    toPayload, updateSlot,
} from '../../resources/js/lototo-board-draft.js';

const slot = (id, position, extra = {}) => ({
    id, position, employee_id: null, spare_lock_id: null, tool_id: null, tool_label: null, tool_column_span: 1, is_enabled: true, locked: false, ...extra,
});

function layout() {
    const source = {
        version: 'v1', title: 'BOARD', subtitle: 'Sub',
        board_columns: 20,
        columns: { technician: 20, supervisor: 20, spare: 20, tool: 20 },
        section_order: ['spare', 'supervisor', 'other'],
        components: { technician: true, supervisor: true, other: true, spare: true },
        slots: {
            technician: [slot(1, 1, { employee_id: 11 }), slot(2, 2)],
            supervisor: [slot(3, 1, { employee_id: 21, locked: true }), slot(4, 2)],
            spare: [slot(5, 1, { spare_lock_id: 31 }), slot(6, 2, { spare_lock_id: 32 })],
            tool: [slot(7, 1, { tool_id: 41, tool_label: 'Kunci' })],
        },
        // The board's tools (records made with "+ Tools"); a Tools slot holds one of them.
        options: { tool: [{ id: 41, code: 'TL-01', name: 'Kunci' }, { id: 42, code: 'TL-02', name: 'Gerinda' }, { id: 43, code: 'TL-03', name: 'X' }] },
    };
    // The layout endpoint gives every slot its band; here each sits in its own category's band.
    Object.entries(source.slots).forEach(([category, slots]) => slots.forEach((s) => { s.section = category; }));

    return source;
}

const label = (category, s) => String(s.employee_id ?? s.spare_lock_id ?? s.tool_id ?? 'kosong');

test('an untouched draft sends back the layout unchanged and lists no changes', () => {
    const source = layout();
    const draft = createDraft(source);

    assert.deepEqual(describeChanges(source, draft, label), []);
    const payload = toPayload(draft);
    assert.equal(payload.version, 'v1');
    assert.deepEqual(payload.slots.technician, source.slots.technician);
    assert.equal('key' in payload.slots.spare[0], false);
});

test('editing the draft never touches the loaded layout', () => {
    const source = layout();
    const draft = createDraft(source);
    updateSlot(draft, savedSlots(source), '2', { employee_id: 12 });

    assert.equal(source.slots.technician[1].employee_id, null);
    assert.equal(draft.slots.technician.find((s) => s.key === '2').employee_id, 12);
    assert.deepEqual(describeChanges(source, draft, label), ['Technician 02 → Technician 02: 12']);
});

test('technician positions are locked, other positions can move', () => {
    const source = layout();
    const draft = createDraft(source);
    const saved = savedSlots(source);

    assert.throws(() => updateSlot(draft, saved, '1', { position: 5, employee_id: 11 }), DraftError);
    updateSlot(draft, saved, '6', { position: 4, spare_lock_id: 32 });
    assert.equal(toPayload(draft).slots.spare.find((s) => s.id === 6).position, 4);
});

test('a slot with an active lock cannot be changed', () => {
    const source = layout();
    const draft = createDraft(source);

    assert.throws(() => updateSlot(draft, savedSlots(source), '3', { employee_id: null }), /aktivitas LOTOTO aktif/);
});

test('one employee or spare lock goes to one position only, and positions stay unique', () => {
    const source = layout();
    const draft = createDraft(source);
    const saved = savedSlots(source);

    assert.throws(() => updateSlot(draft, saved, '2', { employee_id: 11 }), /posisi 1/);
    assert.throws(() => updateSlot(draft, saved, '6', { spare_lock_id: 31 }), DraftError);
    assert.throws(() => updateSlot(draft, saved, '6', { position: 1, spare_lock_id: 32 }), /sudah dipakai/);
    // Clearing first, then assigning, moves an employee.
    updateSlot(draft, saved, '1', { employee_id: null });
    updateSlot(draft, saved, '2', { employee_id: 11 });
    assert.deepEqual(toPayload(draft).slots.technician.map((s) => s.employee_id), [null, 11]);
});

test('any saved slot changes category, Technician and filled ones included, unless something on it is IN', () => {
    const source = layout();
    const draft = createDraft(source);
    const saved = savedSlots(source);

    // An empty Technician slot becomes a tool slot, in its own band.
    updateSlot(draft, saved, '2', { category: 'tool', position: 2, tool_id: 42 });
    // A spare slot holding lock 31 becomes a Technician slot: the lock is released from the slot (it stays a record).
    updateSlot(draft, saved, '6', { category: 'technician', position: 2, employee_id: null });
    // Supervisor 1 has LOTOTO IN: refused.
    assert.throws(() => updateSlot(draft, saved, '3', { category: 'tool', position: 3 }), /aktivitas LOTOTO aktif/);
    const payload = toPayload(draft);
    assert.deepEqual(payload.slots.tool.find((s) => s.id === 2), slot(2, 2, { tool_id: 42, tool_label: 'Gerinda', section: 'technician' }));
    assert.deepEqual(payload.slots.technician.find((s) => s.id === 6), slot(6, 2, { section: 'spare' }));
    assert.deepEqual(payload.slots.spare.map((s) => s.id), [5]);
});

test('a category change keeps the slot position number and its enabled state', () => {
    const source = layout();
    source.slots.supervisor[1].is_enabled = false;
    const draft = createDraft(source);

    updateSlot(draft, savedSlots(source), '4', { category: 'tool', tool_id: 42 });
    const moved = toPayload(draft).slots.tool.find((s) => s.id === 4);
    assert.equal(moved.position, 2);
    assert.equal(moved.is_enabled, false);
    // Positions of every other slot are untouched.
    assert.deepEqual(toPayload(draft).slots.supervisor.map((s) => [s.id, s.position]), [[3, 1]]);
    assert.deepEqual(toPayload(draft).slots.tool.map((s) => [s.id, s.position]), [[7, 1], [4, 2]]);
});

test('a category change to a taken position is refused and changes nothing', () => {
    const source = layout();
    const draft = createDraft(source);
    const before = JSON.stringify(toPayload(draft));

    // Spare position 2 is held by slot 6: no move to another free index, no swap.
    assert.throws(() => updateSlot(draft, savedSlots(source), '4', { category: 'spare', spare_lock_id: null }), /sudah terisi slot lain.*Slot tidak dipindahkan/);
    assert.equal(JSON.stringify(toPayload(draft)), before);
});

test('changing only the assignment keeps the enabled state', () => {
    const source = layout();
    source.slots.technician[1].is_enabled = false;
    const draft = createDraft(source);

    updateSlot(draft, savedSlots(source), '2', { employee_id: 12 });
    assert.equal(toPayload(draft).slots.technician.find((s) => s.id === 2).is_enabled, false);
});

test('new positions are added without an id and can be removed again; saved ones cannot', () => {
    const source = layout();
    const draft = createDraft(source);
    const added = addSlot(draft, 'technician');

    assert.equal(added.position, 3);
    assert.throws(() => addSlot(draft, 'technician', 3), DraftError);
    assert.equal(toPayload(draft).slots.technician.at(-1).id, null);
    assert.deepEqual(describeChanges(source, draft, label), ['Posisi baru · Technician 03: kosong']);
    assert.throws(() => removeNewSlot(draft, '1'), DraftError);
    removeNewSlot(draft, added.key);
    assert.deepEqual(describeChanges(source, draft, label), []);
});

// The same board with stored cells. Supervisor band (5 columns): 3 at 1:1, 4 at 1:2, 9 at 1:3, 10 at 1:5.
// Tools band (6 columns): 7 (span 2) at 1:1, 8 numbered 7 at 1:3. Spare band (8 columns): 5 at 1:1, 6 at 1:2.
function cellLayout() {
    const source = layout();
    source.coordinates = true;
    source.column_limits = { min: 5, max: 30 };
    source.columns = { technician: 20, supervisor: 5, spare: 8, tool: 6 };
    const cells = { technician: [[1, 1], [1, 2]], supervisor: [[1, 1], [1, 2]], spare: [[1, 1], [1, 2]] };
    Object.entries(cells).forEach(([category, list]) => list.forEach(([row, column], index) => Object.assign(source.slots[category][index], { grid_row: row, grid_column: column })));
    source.slots.supervisor.push(slot(9, 9, { section: 'supervisor', grid_row: 1, grid_column: 3 }), slot(10, 10, { section: 'supervisor', grid_row: 1, grid_column: 5 }));
    source.slots.tool = [slot(7, 1, { section: 'tool', tool_id: 41, tool_label: 'Kunci', tool_column_span: 2, grid_row: 1, grid_column: 1 }), slot(8, 7, { section: 'tool', grid_row: 1, grid_column: 3 })];

    return source;
}

const cellsOf = (draft) => Object.fromEntries(Object.values(toPayload(draft).slots).flat().map((s) => [s.id, `${s.grid_row}:${s.grid_column}`]));

test('with cells, a category change keeps the slot in its band, order and number; the band closes its gaps', () => {
    const source = cellLayout();
    const draft = createDraft(source);

    // Supervisor 4 (1:2) becomes a tool; it stays at 1:2 of the Supervisor band, not in the Tools band.
    updateSlot(draft, savedSlots(source), '4', { category: 'tool', tool_id: 42 });
    const changed = toPayload(draft).slots.tool.find((s) => s.id === 4);
    assert.deepEqual([changed.section, changed.grid_row, changed.grid_column, changed.position], ['supervisor', 1, 2, 2]);
    // Automatic layout: Supervisor 10 closes the gap at 1:4; every other slot keeps its cell and content.
    assert.deepEqual(toPayload(draft).slots.tool.filter((s) => s.id !== 4), source.slots.tool);
    assert.deepEqual(toPayload(draft).slots.supervisor, source.slots.supervisor.filter((s) => s.id !== 4).map((s) => (s.id === 10 ? { ...s, grid_column: 4 } : s)));
});

test('with cells, a wider tool pushes the slots after it along and a narrower one lets them close up', () => {
    const source = cellLayout();
    const draft = createDraft(source);
    const saved = savedSlots(source);

    // Supervisor 4 (1:2) as a two-column tool takes 1:2-1:3: Supervisor 9 and 10 move along, nothing is refused.
    updateSlot(draft, saved, '4', { category: 'tool', tool_id: 43, tool_column_span: 2 });
    assert.deepEqual([cellsOf(draft)[4], cellsOf(draft)[9], cellsOf(draft)[10]], ['1:2', '1:4', '1:5']);
    // Tools 1 (slot 7, two columns at 1:1) widened to three: Tools 7 (slot 8) moves from 1:3 to 1:4; back to one: 1:2.
    updateSlot(draft, saved, '7', { tool_id: 41, tool_column_span: 3 });
    assert.deepEqual([cellsOf(draft)[7], cellsOf(draft)[8]], ['1:1', '1:4']);
    updateSlot(draft, saved, '7', { tool_id: 41, tool_column_span: 1 });
    assert.deepEqual([cellsOf(draft)[7], cellsOf(draft)[8]], ['1:1', '1:2']);
    // Supervisor 10 (now 1:5, the band's last column) as a two-column tool does not fit there: it starts row 2.
    updateSlot(draft, saved, '10', { category: 'tool', tool_id: 42, tool_column_span: 2 });
    assert.equal(cellsOf(draft)[10], '2:1');
    // Numbers, order and ids never change; the Supervisor band never overlaps.
    assert.deepEqual(Object.values(toPayload(draft).slots).flat().filter((s) => s.section === 'supervisor').map((s) => [s.id, s.position]).sort((a, b) => a[0] - b[0]), [[3, 1], [4, 2], [9, 9], [10, 10]]);
    // One tool record in one slot only.
    assert.throws(() => updateSlot(draft, saved, '8', { tool_id: 41 }), /sudah ditempatkan pada posisi 1/);
    // A span wider than the whole band is refused.
    draft.column_limits = { min: 1, max: 30 };
    setColumns(draft, 2);
    const before = JSON.stringify(toPayload(draft));
    assert.throws(() => updateSlot(draft, saved, '7', { tool_id: 41, tool_column_span: 3 }), /tidak muat pada pita Other \/ Tools \(2 kolom\)/);
    assert.equal(JSON.stringify(toPayload(draft)), before);
});

test('with cells, a slot in any band may become a Technician slot', () => {
    const source = cellLayout();
    const draft = createDraft(source);

    updateSlot(draft, savedSlots(source), '4', { category: 'technician', employee_id: null });
    const changed = toPayload(draft).slots.technician.find((s) => s.id === 4);
    assert.deepEqual([changed.section, changed.grid_row, changed.grid_column, changed.position], ['supervisor', 1, 2, 2]);
});

test('with cells, new slots get a number of their own and join the end of their packed band', () => {
    const draft = createDraft(cellLayout());

    const first = addSlot(draft, 'tool');
    assert.deepEqual([first.position, first.section, first.grid_row, first.grid_column], [8, 'tool', 1, 4]);
    // A given cell only says where it goes in the reading order: the band is packed, Supervisor 10 closes the gap
    // at 1:4 and Supervisor 11 follows it.
    const given = addSlot(draft, 'supervisor', 11, { row: 3, column: 2 });
    assert.deepEqual([given.grid_row, given.grid_column], [1, 5]);
    assert.equal(cellsOf(draft)[10], '1:4');
    assert.throws(() => addSlot(draft, 'supervisor', 11), /sudah ada/);
    // The section "+": an empty slot after the last one.
    const spare = addSlot(draft, 'spare', undefined, null, 'spare');
    assert.deepEqual([spare.position, spare.grid_row, spare.grid_column, spare.spare_lock_id], [3, 1, 3, null]);
    const supervisor = addSlot(draft, 'supervisor', undefined, null, 'supervisor');
    assert.deepEqual([supervisor.position, supervisor.grid_row, supervisor.grid_column], [12, 2, 1]);
});

test('a new column count packs every band in reading order, keeping ids, numbers, content and order', () => {
    const source = cellLayout();
    const draft = createDraft(source);
    const content = (payload) => Object.fromEntries(Object.values(payload.slots).flat().map((s) => [s.id, [s.position, s.employee_id, s.spare_lock_id, s.tool_id, s.section]]));
    const cells = () => Object.fromEntries(Object.values(toPayload(draft).slots).flat().map((s) => [s.id, `${s.grid_row}:${s.grid_column}`]));

    // The cell layout still has per-band widths from before; they stay until the count is changed.
    assert.deepEqual(bandsOffBoardColumns(draft), ['supervisor', 'spare', 'tool']);
    assert.deepEqual([toPayload(draft).board_columns, toPayload(draft).apply_columns], [20, false]);
    setColumns(draft, 5);
    assert.deepEqual(draft.columns, { technician: 5, supervisor: 5, spare: 5, tool: 5 });
    assert.deepEqual(bandsOffBoardColumns(draft), []);
    // Supervisor 10 closes the gap at 1:4; the rest is already packed.
    assert.deepEqual(cells(), { 1: '1:1', 2: '1:2', 3: '1:1', 4: '1:2', 9: '1:3', 10: '1:4', 5: '1:1', 6: '1:2', 7: '1:1', 8: '1:3' });
    // Narrower than a row: slots wrap in order; a two-column tool that does not fit starts the next row.
    draft.column_limits = { min: 1, max: 30 };
    setColumns(draft, 2);
    assert.deepEqual(cells(), { 1: '1:1', 2: '1:2', 3: '1:1', 4: '1:2', 9: '2:1', 10: '2:2', 5: '1:1', 6: '1:2', 7: '1:1', 8: '2:1' });
    assert.throws(() => setColumns(draft, 0), /Jumlah kolom per baris harus 1 sampai 30/);
    assert.deepEqual(content(toPayload(draft)), content(source));
    assert.deepEqual([toPayload(draft).board_columns, toPayload(draft).apply_columns, 'columns' in toPayload(draft)], [2, true, false]);
});

test('the reading-order packing (same rule as LototoSlotGrid::reflow)', () => {
    const cells = reflowSection([
        { key: 'c', row: 2, column: 1, span: 1 }, { key: 'a', row: 1, column: 3, span: 1 }, { key: 'b', row: 1, column: 9, span: 3 }, { key: 'new', row: null, column: null, span: 1 },
    ], 4);
    assert.deepEqual(Object.fromEntries([...cells].map(([key, cell]) => [key, `${cell.row}:${cell.column}`])), { a: '1:1', b: '1:2', c: '2:1', new: '2:2' });
});

test('a slot of any category can be removed and the slots after it close up; IN slots stay', () => {
    const source = cellLayout();
    const draft = createDraft(source);

    removeSlot(draft, '9');
    const payload = toPayload(draft);
    assert.deepEqual(payload.removed, [9]);
    assert.equal(payload.slots.supervisor.some((s) => s.id === 9), false);
    // Supervisor 10 closes up from 1:5 to 1:3; numbers stay.
    assert.deepEqual(payload.slots.supervisor.map((s) => [s.id, s.position, `${s.grid_row}:${s.grid_column}`]), [[3, 1, '1:1'], [4, 2, '1:2'], [10, 10, '1:3']]);
    assert.deepEqual(describeChanges(source, draft, label), ['Dihapus · Supervisor 09: kosong']);
    // A Technician slot holding an employee can be removed (the editor asks first); Technician 02 moves to 1:1.
    removeSlot(draft, '1');
    assert.deepEqual(toPayload(draft).slots.technician.map((s) => [s.id, `${s.grid_row}:${s.grid_column}`]), [[2, '1:1']]);
    assert.throws(() => removeSlot(draft, '3'), /aktivitas LOTOTO aktif/);
    // A slot that holds a tool can go too; the tool record stays.
    removeSlot(draft, '7');
    assert.equal(cellsOf(draft)[8], '1:1');
    const added = addSlot(draft, 'technician');
    removeSlot(draft, added.key);
    assert.deepEqual(toPayload(draft).removed, [9, 1, 7]);
});

test('bands below Technician can be ordered freely', () => {
    const draft = createDraft(cellLayout());

    assert.equal(moveSection(draft, 'other', -1), true);
    assert.deepEqual(draft.section_order, ['spare', 'other', 'supervisor']);
    assert.equal(moveSection(draft, 'spare', -1), false);
    placeSection(draft, 'supervisor', 'spare');
    assert.deepEqual(draft.section_order, ['supervisor', 'spare', 'other']);
    placeSection(draft, 'supervisor', null);
    assert.deepEqual(draft.section_order, ['spare', 'other', 'supervisor']);
});

test('board settings changes are listed', () => {
    const source = layout();
    const draft = createDraft(source);
    draft.title = 'BARU';
    setColumns(draft, 8);
    draft.section_order = ['other', 'spare', 'supervisor'];
    draft.components.other = false;

    assert.deepEqual(describeChanges(source, draft, label), [
        'Judul: "BARU"', 'Kolom per baris (seluruh board): 8, slot disusun ulang', 'Urutan section: Other > Spare > Supervisor', 'Other / Tools: disembunyikan',
    ]);
});
