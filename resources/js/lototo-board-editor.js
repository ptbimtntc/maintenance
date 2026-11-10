import {
    CATEGORY_LABELS, addSlot, assignmentField, bandsOffBoardColumns, createDraft, describeChanges, findSlot, firstFreeCell, isEmpty, moveSection, nextPosition, pad,
    placeSection, removeNewSlot, removeSlot, savedSlots, setColumns, toPayload, updateSlot, DraftError,
} from './lototo-board-draft';
import { createSlotDialog } from './lototo-board-editor-dialog';
import { createManagePopups } from './lototo-board-editor-manage';
import {
    addDragHandles, addSlotButtons, applyColumns, applyOrder, bandOf, buildCard, contentOf, gridOf, placeCards,
} from './lototo-board-editor-preview';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

const FIELD_LABELS = { title: 'Judul', subtitle: 'Subtitle', board_columns: 'Kolom per baris', section_order: 'Urutan section', components: 'Komponen', slots: 'Slot', removed: 'Hapus slot', version: 'Versi', board: 'Board' };

/**
 * Edit Mode on the board page (index.blade.php, partial _board-editor). Everything stays in a browser draft
 * until Save sends the whole configuration, with its version, to the layout endpoint; Cancel drops the draft
 * and puts the saved board back. The board itself previews the draft: changed slots are redrawn from the
 * board's own card templates, band widths and band order follow the draft. Board markup marks every slot with
 * data-slot-category / data-slot-position / data-grid-row / data-grid-column (and data-slot-id when saved,
 * data-slot-section when it sits in another category's band).
 */
function initBoardEditor(page) {
    const startButton = page.querySelector('[data-board-edit-start]');
    const toolbar = page.querySelector('[data-editor-toolbar]');
    const board = page.querySelector('.lototo-board');
    const message = toolbar.querySelector('[data-editor-message]');
    const changeList = toolbar.querySelector('[data-editor-changes]');
    const saveButton = toolbar.querySelector('[data-editor-save]');
    const cancelButton = toolbar.querySelector('[data-editor-cancel]');
    const settings = toolbar.querySelector('[data-editor-settings]');
    const columnsControl = page.querySelector('[data-editor-columns]');
    const columnCount = columnsControl.querySelector('[data-editor-column-count]');
    const columnsNote = columnsControl.querySelector('[data-editor-columns-note]');
    const templates = document.querySelector('[data-card-templates]');
    let layout = null;
    let saved = null;
    let draft = null;
    let saving = false;
    let snapshot = null;
    let draggedSection = null;
    // Tools / spare locks changed through the popups: Cancel then reloads the board instead of putting back the old one.
    let dataChanged = false;

    const dialog = createSlotDialog(document.querySelector('[data-slot-dialog]'), { getOptions: () => layout?.options ?? {}, isBusy: () => saving });
    const isEditing = () => draft !== null;
    const changes = () => (draft ? describeChanges(layout, draft, slotLabel) : []);

    function slotLabel(category, slot) {
        if (category === 'tool') {
            const tool = (layout.options.tool ?? []).find((item) => item.id === slot.tool_id);
            return tool ? `${tool.code} ${tool.name}` : (slot.tool_label || 'kosong');
        }
        const field = category === 'spare' ? 'spare_lock_id' : 'employee_id';
        if (!slot[field]) return 'kosong';
        const option = (layout.options[category] ?? []).find((item) => item.id === slot[field]);
        if (!option) return `#${slot[field]}`;

        return category === 'spare' ? option.code : `${option.lototo_number} ${option.name}`;
    }

    function showMessage(type, text, action = null) {
        message.hidden = !text;
        message.dataset.type = type;
        message.replaceChildren();
        if (!text) return;
        (Array.isArray(text) ? text : [text]).forEach((line) => {
            const item = document.createElement('p');
            item.textContent = line;
            message.append(item);
        });
        if (action) message.append(action);
    }

    /** Runs a draft change; a refused one (DraftError) leaves the draft as it was and says why. */
    function attempt(change) {
        try {
            change();
            showMessage('', '');

            return true;
        } catch (exception) {
            if (!(exception instanceof DraftError)) throw exception;
            showMessage('error', exception.message);

            return false;
        }
    }

    /**
     * While a save runs the draft is frozen: every control that changes it is disabled, and every handler also
     * checks `saving`, so keyboard or programmatic events cannot change what is being saved either.
     */
    function setBusy(busy) {
        saving = busy;
        if (busy) { dialog.close(); manage.close(); }
        dialog.setDisabled(busy);
        [saveButton, cancelButton, ...toolbar.querySelectorAll('[data-editor-manage]'), ...settings.elements,
            ...columnsControl.querySelectorAll('select, button'), ...board.querySelectorAll('.lototo-editor-add, .lototo-editor-drag, .lototo-editor-addslot, .lototo-editor-remove')]
            .forEach((control) => { control.disabled = busy; });
        page.toggleAttribute('data-saving', busy);
        toolbar.setAttribute('aria-busy', String(busy));
        saveButton.textContent = busy ? 'Menyimpan…' : 'Simpan Perubahan';
    }

    async function start() {
        startButton.disabled = true;
        try {
            const response = await fetch(page.dataset.layoutUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            layout = await response.json();
        } catch {
            startButton.disabled = false;
            window.alert('Konfigurasi board tidak dapat dimuat. Periksa koneksi atau izin Anda, lalu coba lagi.');
            return;
        }
        // Cancel puts back exactly this board.
        snapshot = { html: board.innerHTML, style: board.getAttribute('style'), wide: page.classList.contains('lototo-page--wide') };
        saved = savedSlots(layout);
        draft = createDraft(layout);
        page.dataset.editing = '';
        startButton.hidden = true;
        startButton.disabled = false;
        toolbar.hidden = false;
        columnsControl.hidden = false;
        page.querySelectorAll('[data-editor-reveal]').forEach((element) => { element.hidden = false; });
        page.querySelectorAll('[data-editor-hide]').forEach((element) => { element.hidden = true; });
        manage.setData(layout.manage);
        fillSettings();
        fillColumns();
        addDragHandles(page);
        addSlotButtons(page);
        board.querySelectorAll('[data-slot-category]').forEach(decorate);
        showMessage('', '');
        refresh();
    }

    function exit() {
        draft = null;
        layout = null;
        delete page.dataset.editing;
        toolbar.hidden = true;
        columnsControl.hidden = true;
        manage.close();
        startButton.hidden = false;
        if (snapshot) {
            board.innerHTML = snapshot.html;
            if (snapshot.style === null) board.removeAttribute('style'); else board.setAttribute('style', snapshot.style);
            page.classList.toggle('lototo-page--wide', snapshot.wide);
            snapshot = null;
        }
        page.querySelectorAll('[data-editor-reveal]').forEach((element) => { element.hidden = true; });
        page.querySelectorAll('[data-editor-hide]').forEach((element) => { element.hidden = false; });
        showMessage('', '');
    }

    function fillSettings() {
        settings.elements.title.value = draft.title;
        settings.elements.subtitle.value = draft.subtitle;
        Object.keys(draft.components).forEach((component) => { settings.elements[`component_${component}`].checked = draft.components[component]; });
    }

    function fillColumns() {
        columnCount.value = String(draft.board_columns);
        // A board saved with per-band widths keeps them until the count is changed here.
        const off = bandsOffBoardColumns(draft);
        columnsNote.hidden = off.length === 0;
        columnsNote.textContent = off.length
            ? `Lebar lama: ${off.map((category) => `${CATEGORY_LABELS[category]} ${draft.columns[category]}`).join(', ')} kolom. Mengubah jumlah kolom menyeragamkan semua pita.`
            : '';
    }

    /** Components shown or hidden on the board, as they will be after saving. */
    function applyComponents() {
        const bands = { technician: '.lototo-main-area:not(.lototo-supervisor-area)', supervisor: '.lototo-supervisor-area', spare: '.lototo-spare-group', other: '.lototo-other-group' };
        Object.entries(bands).forEach(([component, selector]) => {
            const band = board.querySelector(selector);
            if (band) band.hidden = !draft.components[component];
        });
        applyColumns(page, draft.columns, draft.components);
    }

    settings.addEventListener('input', () => {
        if (!isEditing()) return;
        if (saving) {
            // A value changed anyway (for example by script): put the form back to what is being saved.
            fillSettings();
            return;
        }
        draft.title = settings.elements.title.value;
        draft.subtitle = settings.elements.subtitle.value;
        Object.keys(draft.components).forEach((component) => { draft.components[component] = settings.elements[`component_${component}`].checked; });
        board.querySelector('.lototo-station-title').textContent = draft.title;
        board.querySelector('.lototo-station-subtitle').textContent = draft.subtitle;
        applyComponents();
        refresh();
    });
    settings.addEventListener('submit', (event) => event.preventDefault());

    // The board's one column count (the value Settings shows): every band takes it and its slots are packed into it
    // in reading order, on the board at once.
    function changeColumns(count) {
        if (!isEditing() || saving) return;
        if (attempt(() => setColumns(draft, count))) {
            applyColumns(page, draft.columns, draft.components);
            placeCards(board, draft, draftKeyFor);
            refresh();
        }
        fillColumns();
    }
    columnCount.addEventListener('change', () => changeColumns(Number(columnCount.value)));

    /** Marks a slot element as editable: a remove button (not on a saved Technician slot or one with IN) and, when empty, a +. */
    function decorate(element) {
        element.classList.add('lototo-editor-slot');
        element.querySelector(':scope > .lototo-editor-add')?.remove();
        element.querySelector(':scope > .lototo-editor-remove')?.remove();
        const found = draftKeyFor(element) && findSlot(draft, draftKeyFor(element));
        if (found && !found.slot.locked && !(found.category === 'technician' && found.slot.id !== null)) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'lototo-editor-remove';
            remove.textContent = '×';
            remove.title = `Hapus slot ${CATEGORY_LABELS[found.category]} ${pad(found.slot.position)}`;
            remove.setAttribute('aria-label', remove.title);
            element.append(remove);
        }
        if (!element.querySelector('.lototo-empty-slot') && !element.classList.contains('lototo-empty-slot')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'lototo-editor-add';
        button.textContent = '+';
        button.setAttribute('aria-label', `Isi slot ${CATEGORY_LABELS[element.dataset.slotCategory]} ${pad(element.dataset.slotPosition)}`);
        element.append(button);
    }

    /** The draft slot an element stands for, or null for an empty position no slot holds yet. */
    function draftKeyFor(element) {
        return element.dataset.draftKey || element.dataset.slotId || null;
    }

    function elementFor(key) {
        const { slot } = findSlot(draft, key) ?? {};
        return board.querySelector(`[data-draft-key="${key}"]`) ?? (slot?.id ? board.querySelector(`[data-slot-id="${slot.id}"]`) : null);
    }

    /** Redraws one slot from the draft, in its band and cell, as the card of its (possibly new) category. */
    function renderSlot(key) {
        const found = findSlot(draft, key);
        const current = elementFor(key);
        if (!found || !current) return;
        const card = buildCard(templates, found.category, found.slot, contentOf(found.category, found.slot, layout.options));
        if (current.hasAttribute('data-editor-added')) card.setAttribute('data-editor-added', '');
        current.replaceWith(card);
        decorate(card);
    }

    /** Puts a new draft slot on the board as an extra card in its band. */
    function placeNewSlot(slot, category) {
        const grid = gridOf(page, slot.section);
        if (!grid) return;
        grid.hidden = false;
        const card = buildCard(templates, category, slot, contentOf(category, slot, layout.options));
        card.setAttribute('data-editor-added', '');
        grid.append(card);
        decorate(card);
    }

    function refresh() {
        const lines = changes();
        page.querySelectorAll('.lototo-editor-marker').forEach((element) => element.remove());
        board.querySelectorAll('[data-slot-category]').forEach((element) => {
            element.classList.remove('lototo-editor-slot--changed');
            const key = draftKeyFor(element);
            const found = key && findSlot(draft, key);
            // The + stays only while the slot is still empty in the draft.
            const add = element.querySelector('.lototo-editor-add');
            if (add) add.hidden = Boolean(found && !isEmpty(found.slot));
            if (!found) return;
            const original = saved.get(key);
            const field = assignmentField(found.category);
            const changed = !original || original.category !== found.category || original.position !== found.slot.position
                || original.is_enabled !== found.slot.is_enabled || (original[field] ?? null) !== (found.slot[field] ?? null)
                || (found.category === 'tool' && original.tool_column_span !== found.slot.tool_column_span);
            if (!changed) return;
            const marker = document.createElement('span');
            marker.className = 'lototo-editor-marker';
            marker.textContent = (original && original.category !== found.category ? `Draft · ${CATEGORY_LABELS[found.category]} ${pad(found.slot.position)}: ` : 'Draft: ')
                + slotLabel(found.category, found.slot) + (found.slot.is_enabled ? '' : ' (disabled)');
            element.classList.add('lototo-editor-slot--changed');
            element.append(marker);
        });
        changeList.hidden = lines.length === 0;
        changeList.querySelector('ul').replaceChildren(...lines.map((line) => Object.assign(document.createElement('li'), { textContent: line })));
        toolbar.querySelector('[data-editor-dirty]').hidden = lines.length === 0;
    }

    function openSlot(key) {
        dialog.open({
            draft,
            saved,
            key,
            apply: (values) => { if (saving) return; updateSlot(draft, saved, key, values); renderSlot(key); refresh(); },
            remove: () => {
                if (saving) return;
                const element = elementFor(key);
                const { category, slot } = findSlot(draft, key);
                removeNewSlot(draft, key);
                if (element?.hasAttribute('data-editor-added')) {
                    element.remove();
                } else if (element) {
                    // An empty position that was filled in this draft goes back to an empty position.
                    const empty = buildCard(templates, category, { ...slot, key: '' }, null);
                    delete empty.dataset.draftKey;
                    element.replaceWith(empty);
                    decorate(empty);
                }
                refresh();
            },
        });
    }

    // In Edit Mode a slot opens the popup instead of its activity page or QR form.
    board.addEventListener('click', (event) => {
        if (!isEditing()) return;
        if (event.target.closest('.lototo-editor-drag')) {
            event.preventDefault();
            return;
        }
        const element = event.target.closest('[data-slot-category]');
        if (!element) return;
        event.preventDefault();
        event.stopPropagation();
        if (saving) return;
        if (event.target.closest('.lototo-editor-remove')) {
            removeFromBoard(element);
            return;
        }
        const key = draftKeyFor(element);
        if (key) {
            openSlot(key);
            return;
        }
        // An empty position drawn on the board without a saved slot: the popup fills it right there, in that cell;
        // the slot joins the draft only when the popup is applied.
        const category = element.dataset.slotCategory;
        openPending(category, {
            position: Number(element.dataset.slotPosition),
            cell: element.dataset.gridRow ? { row: Number(element.dataset.gridRow), column: Number(element.dataset.gridColumn) } : null,
            section: element.dataset.slotSection || category,
            element,
        });
    }, true);

    /**
     * The popup for a slot that is not in the draft yet. Apply adds it (number, band and cell as given) with the
     * popup's values; closing the popup in any way leaves the draft and the board as they were.
     */
    function openPending(category, { position = nextPosition(draft, category), cell = null, section = category, element = null }) {
        const place = draft.coordinates ? (cell ?? firstFreeCell(draft, section)) : null;
        const slot = {
            id: null, key: '', position, section, grid_row: place?.row ?? null, grid_column: place?.column ?? null,
            employee_id: null, spare_lock_id: null, tool_id: null, tool_label: null, tool_column_span: 1, is_enabled: true, locked: false,
        };
        dialog.open({
            draft,
            saved,
            key: '',
            pending: { category, slot },
            apply: (values) => {
                if (saving) return;
                const added = addSlot(draft, category, position, place, section);
                try {
                    updateSlot(draft, saved, added.key, values);
                } catch (exception) {
                    removeNewSlot(draft, added.key);
                    throw exception;
                }
                const now = findSlot(draft, added.key);
                if (element) {
                    element.dataset.draftKey = added.key;
                    renderSlot(added.key);
                } else {
                    placeNewSlot(now.slot, now.category);
                }
                refresh();
            },
            remove: () => {},
        });
    }

    /**
     * The remove button of a slot: the slot leaves the draft (a saved one is deleted on Save) and its cell stays
     * empty; no other slot moves. A slot that holds something asks first. Its employee, lock or tool and every
     * LOTOTO record stay.
     */
    function removeFromBoard(element) {
        const key = draftKeyFor(element);
        const found = key && findSlot(draft, key);
        if (!found) return;
        const name = `${CATEGORY_LABELS[found.category]} ${pad(found.slot.position)}`;
        if (!isEmpty(found.slot) && !window.confirm(`Hapus slot ${name} yang berisi ${slotLabel(found.category, found.slot)}? Slot hilang dari board setelah disimpan; data employee, gembok, Tool, dan riwayat LOTOTO tetap ada.`)) return;
        if (!attempt(() => removeSlot(draft, key))) return;
        element.remove();
        refresh();
    }

    // + at the bottom right of a section: one empty slot more in that section (its first free cell).
    board.addEventListener('click', (event) => {
        const button = event.target.closest('[data-section-add]');
        if (!button || !isEditing() || saving) return;
        event.preventDefault();
        const section = button.dataset.sectionAdd;
        let slot;
        if (!attempt(() => { slot = addSlot(draft, section, undefined, null, section); })) return;
        placeNewSlot(slot, section);
        refresh();
    });

    /** The Edit Slot options (layout and draft) for tools / spare locks, after a change in a Kelola popup. */
    function changeOptions(kind, change) {
        [layout.options, draft.options].forEach((options) => { options[kind] = change(options[kind] ?? []).sort((a, b) => a.code.localeCompare(b.code)); });
    }

    /** Draft slots (all categories) holding $id in $field, with their keys. */
    function draftSlotsWith(field, id) {
        return Object.values(draft.slots).flat().filter((slot) => slot[field] === id);
    }

    const manage = createManagePopups({
        toolsDialog: document.querySelector('[data-manage-tools]'),
        spareDialog: document.querySelector('[data-manage-spare]'),
        toolsUrl: page.dataset.toolsUrl,
        spareLocksUrl: page.dataset.spareLocksUrl,
        isBusy: () => saving || !isEditing(),
        // The server checks saved slots; a slot filled only in this draft is checked here.
        draftUse: (kind, item) => {
            if (!isEditing()) return '';
            const slot = draftSlotsWith(kind === 'tool' ? 'tool_id' : 'spare_lock_id', item.id)[0];

            return slot ? `${item.code} dipilih pada slot ${pad(slot.position)} di draft. Kosongkan slot itu lewat Edit Slot terlebih dahulu.` : '';
        },
        onTool: (change, tool) => {
            if (!isEditing()) return;
            dataChanged = true;
            const option = { id: tool.id, code: tool.code, name: tool.name };
            if (change === 'deleted') {
                changeOptions('tool', (list) => list.filter((item) => item.id !== tool.id));
                return;
            }
            changeOptions('tool', (list) => [...list.filter((item) => item.id !== tool.id), option]);
            if (change !== 'updated') return;
            // A renamed tool: its slots show the new name (the server already stored it on the saved slots).
            draftSlotsWith('tool_id', tool.id).forEach((slot) => {
                slot.tool_label = tool.name;
                const original = saved.get(slot.key);
                if (original?.tool_id === tool.id) original.tool_label = tool.name;
                renderSlot(slot.key);
            });
            refresh();
        },
        onSpareLock: (change, lock) => {
            if (!isEditing()) return;
            dataChanged = true;
            changeOptions('spare', (list) => (change === 'deleted' ? list.filter((item) => item.id !== lock.id) : [...list, { id: lock.id, code: lock.code }]));
        },
    });
    toolbar.querySelector('[data-editor-manage="tools"]').addEventListener('click', () => manage.openTools());
    toolbar.querySelector('[data-editor-manage="spare"]').addEventListener('click', () => manage.openSpareLocks());

    // Band order: drag a band by its four-way handle, or move it with the arrow keys on the handle.
    board.addEventListener('dragstart', (event) => {
        const handle = event.target.closest?.('[data-section-handle]');
        if (!isEditing() || saving || !handle) return;
        draggedSection = handle.dataset.sectionHandle;
        handle.parentElement.classList.add('lototo-band--dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedSection);
    });
    board.addEventListener('dragover', (event) => {
        if (!draggedSection || saving) return;
        const target = bandOf(page, event.target);
        if (!target) return;
        event.preventDefault();
        if (target === draggedSection) return;
        const bounds = page.querySelector({ supervisor: '.lototo-supervisor-area', spare: '.lototo-spare-group', other: '.lototo-other-group' }[target]).getBoundingClientRect();
        const order = draft.section_order.filter((section) => section !== draggedSection);
        const before = event.clientY < bounds.top + bounds.height / 2 ? target : (order[order.indexOf(target) + 1] ?? null);
        placeSection(draft, draggedSection, before);
        applyOrder(page, draft.section_order);
    });
    board.addEventListener('drop', (event) => {
        if (!draggedSection) return;
        event.preventDefault();
        endDrag();
    });
    board.addEventListener('dragend', () => {
        if (draggedSection) endDrag();
    });
    const endDrag = () => {
        draggedSection = null;
        board.querySelectorAll('.lototo-band--dragging').forEach((band) => band.classList.remove('lototo-band--dragging'));
        refresh();
    };
    board.addEventListener('keydown', (event) => {
        const handle = event.target.closest?.('[data-section-handle]');
        if (!isEditing() || saving || !handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        if (moveSection(draft, handle.dataset.sectionHandle, event.key === 'ArrowUp' ? -1 : 1)) {
            applyOrder(page, draft.section_order);
            refresh();
        }
    });

    startButton.addEventListener('click', start);

    cancelButton.addEventListener('click', () => {
        if (saving) return;
        if (changes().length && !window.confirm('Buang semua perubahan yang belum disimpan? Konfigurasi board tidak berubah.')) return;
        if (dataChanged) {
            // Tools / spare locks were changed through the popups (already saved): show the board as it now is.
            draft = null;
            window.location.reload();
            return;
        }
        exit();
    });

    saveButton.addEventListener('click', async () => {
        if (!isEditing() || saving) return;
        setBusy(true);
        showMessage('info', 'Menyimpan perubahan…');
        let response;
        let body = {};
        try {
            response = await fetch(page.dataset.saveUrl, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify(toPayload(draft)),
            });
            body = await response.json().catch(() => ({}));
        } catch {
            setBusy(false);
            showMessage('error', 'Tidak dapat menghubungi server. Draft Anda masih ada; periksa koneksi lalu tekan Simpan Perubahan lagi.');
            return;
        }

        if (response.ok) {
            draft = null;
            showMessage('success', `${body.message ?? 'Konfigurasi board berhasil disimpan.'} Memuat ulang board…`);
            window.location.assign(body.redirect ?? window.location.href);
            return;
        }
        setBusy(false);
        if (response.status === 409) {
            const reload = Object.assign(document.createElement('button'), { type: 'button', className: 'lototo-editor-button', textContent: 'Muat ulang board (draft dibuang)' });
            reload.addEventListener('click', () => {
                if (window.confirm('Muat ulang board? Draft Anda akan dibuang dan konfigurasi terbaru ditampilkan.')) {
                    draft = null;
                    window.location.reload();
                }
            });
            showMessage('error', [body.message ?? 'Konfigurasi board sudah diubah oleh pengguna lain.', 'Draft Anda tidak disimpan dan perubahan terbaru tidak ditimpa.'], reload);
        } else if (response.status === 422) {
            const errors = Object.entries(body.errors ?? {}).flatMap(([field, messages]) => {
                const column = field.match(/^columns\.(\w+)$/);
                const label = column ? `Kolom ${CATEGORY_LABELS[column[1]] ?? column[1]}` : (FIELD_LABELS[field.split('.')[0]] ?? field);

                return messages.map((text) => `${label}: ${text}`);
            });
            showMessage('error', ['Perubahan belum disimpan. Perbaiki hal berikut lalu simpan lagi; draft Anda tetap ada.', ...(errors.length ? errors : [body.message ?? 'Data tidak valid.'])]);
        } else if (response.status === 403) {
            showMessage('error', 'Anda tidak memiliki izin untuk mengubah konfigurasi board ini.');
        } else if (response.status === 419) {
            showMessage('error', 'Sesi Anda sudah berakhir. Catat perubahan Anda, lalu muat ulang halaman dan login kembali.');
        } else {
            showMessage('error', `Server mengembalikan kesalahan (${response.status}). Draft Anda masih ada; coba simpan lagi.`);
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (!isEditing() || changes().length === 0) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

const editorPage = document.querySelector('[data-board-editor]');
if (editorPage) {
    initBoardEditor(editorPage);
}
