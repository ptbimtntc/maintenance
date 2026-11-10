import { CATEGORY_LABELS, pad, spanOf } from './lototo-board-draft';

/**
 * Live preview of the Edit Mode draft on the board itself: cards redrawn from the board's own card templates
 * (_board-editor.blade.php, data-card-template), band widths and band order. Only the DOM changes; the draft is
 * the source and Cancel puts the saved board back.
 */

const BAND_SELECTORS = { supervisor: '.lototo-supervisor-area', spare: '.lototo-spare-group', other: '.lototo-other-group' };
const GRID_SELECTORS = { technician: '.lototo-technician-grid', supervisor: '.lototo-supervisor-grid', spare: '.lototo-spare-group .lototo-grid', tool: '.lototo-other-grid' };

const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

const initialsOf = (name) => String(name ?? '').trim().split(/\s+/).map((part) => part.charAt(0)).slice(0, 2).join('');

/** What a slot shows: null when the board would draw it empty. */
export function contentOf(category, slot, options) {
    if (!slot.is_enabled) return null;
    if (category === 'tool') {
        const name = (options.tool ?? []).find((tool) => tool.id === slot.tool_id)?.name ?? slot.tool_label;
        return (name ?? '').trim() ? { tool: name.trim() } : null;
    }
    if (category === 'spare') {
        if (!slot.spare_lock_id) return null;
        return { code: (options.spare ?? []).find((lock) => lock.id === slot.spare_lock_id)?.code ?? `#${slot.spare_lock_id}` };
    }
    if (!slot.employee_id) return null;
    const employee = (options[category] ?? []).find((item) => item.id === slot.employee_id);

    return { name: employee?.name ?? `#${slot.employee_id}`, number: employee?.lototo_number ?? '' };
}

/** A card element for a draft slot, built from the template the board uses for that kind of slot. */
export function buildCard(templates, category, slot, content) {
    const template = templates.querySelector(`[data-card-template="${category}-${content ? 'filled' : 'empty'}"]`);
    const html = template.innerHTML
        .replaceAll('__NAMA__', escapeHtml(content?.name))
        .replaceAll('__NO__', escapeHtml(content?.number))
        .replaceAll('__KODE__', escapeHtml(content?.code))
        .replaceAll('__TOOL__', escapeHtml(content?.tool));
    const holder = document.createElement('div');
    holder.innerHTML = html.trim();
    const element = holder.firstElementChild;
    const foreign = slot.section !== category;

    element.dataset.slotCategory = category;
    element.dataset.slotPosition = String(slot.position);
    element.dataset.gridRow = String(slot.grid_row ?? '');
    element.dataset.gridColumn = String(slot.grid_column ?? '');
    element.dataset.draftKey = slot.key;
    if (slot.id) element.dataset.slotId = String(slot.id); else delete element.dataset.slotId;
    if (foreign) element.dataset.slotSection = slot.section; else delete element.dataset.slotSection;
    element.style.gridColumn = category === 'tool' ? `${slot.grid_column} / span ${spanOf(slot, category)}` : String(slot.grid_column);
    element.style.gridRow = String(slot.grid_row);
    element.removeAttribute('href');

    const card = element.matches('.lototo-card') ? element : element.querySelector('.lototo-card');
    card.removeAttribute('href');
    [...card.classList].filter((name) => name.startsWith('lototo-card--as-')).forEach((name) => card.classList.remove(name));
    if (foreign) card.classList.add(`lototo-card--as-${category}`);
    card.querySelector('.lototo-position-index').textContent = pad(slot.position);
    card.setAttribute('aria-label', (card.getAttribute('aria-label') ?? '').replace(/posisi 0\b/, `posisi ${slot.position}`));
    const initials = card.querySelector('.lototo-photo--initials');
    if (initials) initials.textContent = initialsOf(content?.name);

    return element;
}

/** Board width follows the widest visible band; each band gets its own column count. Nothing moves. */
export function applyColumns(page, columns, components) {
    const visible = { technician: components.technician, supervisor: components.supervisor, spare: components.spare, tool: components.other };
    const widest = Math.max(...Object.keys(visible).filter((band) => visible[band]).map((band) => columns[band]), columns.technician);
    page.querySelectorAll('.lototo-board, .lototo-board-content, .lototo-main-area').forEach((element) => element.style.setProperty('--lototo-board-columns', String(widest)));
    page.querySelector(GRID_SELECTORS.technician)?.style.setProperty('--lototo-section-columns', String(columns.technician));
    page.querySelector(GRID_SELECTORS.supervisor)?.style.setProperty('--lototo-supervisor-columns', String(columns.supervisor));
    page.querySelector(GRID_SELECTORS.spare)?.style.setProperty('--lototo-section-columns', String(columns.spare));
    page.querySelector(GRID_SELECTORS.tool)?.style.setProperty('--lototo-section-columns', String(columns.tool));
    page.classList.toggle('lototo-page--wide', widest > 20);
}

/** Band order below Technician (which stays first). */
export function applyOrder(page, order) {
    order.forEach((section, index) => {
        const band = page.querySelector(BAND_SELECTORS[section]);
        if (band) band.style.order = String(index + 2);
    });
}

export function gridOf(page, section) {
    return page.querySelector(GRID_SELECTORS[section]);
}

/** A drag handle (four-way arrow) on each orderable band. */
export function addDragHandles(page) {
    Object.entries(BAND_SELECTORS).forEach(([section, selector]) => {
        const band = page.querySelector(selector);
        if (!band) return;
        const handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'lototo-editor-drag';
        handle.draggable = true;
        handle.dataset.sectionHandle = section;
        handle.title = `Seret untuk memindah pita ${CATEGORY_LABELS[section === 'other' ? 'tool' : section]} (atau panah atas/bawah)`;
        handle.setAttribute('aria-label', handle.title);
        handle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M3 12h18M12 3 9 6m3-3 3 3M12 21l-3-3m3 3 3-3M3 12l3-3m-3 3 3 3M21 12l-3-3m3 3-3 3"/></svg>';
        band.append(handle);
    });
}

export function bandOf(page, element) {
    return Object.entries(BAND_SELECTORS).find(([, selector]) => element.closest(selector))?.[0] ?? null;
}

/** Puts every card of the board on its draft cell (after the column count packed the bands again). */
export function placeCards(board, draft, keyOf) {
    board.querySelectorAll('[data-slot-category]').forEach((element) => {
        const key = keyOf(element);
        const found = key && CATEGORY_KEYS.map((category) => ({ category, slot: draft.slots[category].find((slot) => slot.key === key) })).find((entry) => entry.slot);
        if (!found) return;
        const { category, slot } = found;
        element.dataset.gridRow = String(slot.grid_row);
        element.dataset.gridColumn = String(slot.grid_column);
        element.style.gridColumn = category === 'tool' ? `${slot.grid_column} / span ${spanOf(slot, category)}` : String(slot.grid_column);
        element.style.gridRow = String(slot.grid_row);
    });
    Object.entries(GRID_SELECTORS).forEach(([section, selector]) => {
        const grid = board.querySelector(selector);
        if (!grid) return;
        const rows = Math.max(1, ...CATEGORY_KEYS.flatMap((category) => draft.slots[category].filter((slot) => slot.section === section).map((slot) => slot.grid_row ?? 1)));
        grid.style.setProperty('--lototo-grid-rows', String(rows));
    });
}

const CATEGORY_KEYS = ['technician', 'supervisor', 'spare', 'tool'];

/**
 * Edit Mode "+" at the bottom right of each section, below its grid (so it never covers a card): one empty slot
 * more in that section only.
 */
export function addSlotButtons(page) {
    Object.entries({ technician: '.lototo-technician-group', ...BAND_SELECTORS }).forEach(([band, selector]) => {
        const content = page.querySelector(`${selector} .lototo-group-content`);
        if (!content) return;
        const section = band === 'other' ? 'tool' : band;
        const strip = document.createElement('div');
        strip.className = 'lototo-editor-addstrip';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'lototo-editor-addslot';
        button.dataset.sectionAdd = section;
        button.textContent = '+';
        button.title = `Tambah 1 slot kosong di section ${CATEGORY_LABELS[section]}`;
        button.setAttribute('aria-label', button.title);
        strip.append(button);
        content.append(strip);
    });
}
