/**
 * Kelola Tools / Kelola Spare Lock popups of Edit Mode (_board-editor.blade.php). They change board data at once on
 * the server (tools with name and picture, spare locks with a code made by the server) and never place anything on
 * a slot: the editor is told through onTool / onSpareLock so Edit Slot offers the new data. Deleting is refused by
 * the server, with its reason, for anything IN, with history, or still on a slot; the list shows those reasons.
 */

const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

/** fetch() for the popups: JSON answers; a refusal (422) or other failure comes back as { error } with its text. */
async function send(url, { method = 'POST', body = null } = {}) {
    let response;
    try {
        response = await fetch(url, { method, body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }, credentials: 'same-origin' });
    } catch {
        return { error: 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.' };
    }
    const answer = await response.json().catch(() => ({}));
    if (response.ok) return answer;
    const errors = Object.values(answer.errors ?? {}).flat();
    if (errors.length) return { error: errors.join(' ') };
    if (response.status === 403) return { error: 'Anda tidak memiliki izin untuk mengelola data board ini.' };
    if (response.status === 419) return { error: 'Sesi Anda sudah berakhir. Muat ulang halaman lalu login kembali.' };

    return { error: answer.message || `Server mengembalikan kesalahan (${response.status}).` };
}

/** Why an item cannot be deleted, or '' when it can. */
const blockedReason = (item, what) => (item.in ? `${what} sedang IN.` : item.history ? `${what} memiliki riwayat penggunaan.` : item.slot ? `Masih terpasang di slot ${item.slot}; kosongkan lewat Edit Slot dan simpan dulu.` : '');

const statusChips = (item) => [
    item.in ? '<span class="lototo-manage-chip lototo-manage-chip--in">IN</span>' : '<span class="lototo-manage-chip">OUT</span>',
    item.history ? '<span class="lototo-manage-chip">Ada riwayat</span>' : '',
    item.slot ? `<span class="lototo-manage-chip lototo-manage-chip--slot">Slot ${escapeHtml(item.slot)}</span>` : '',
].join('');

export function createManagePopups({ toolsDialog, spareDialog, toolsUrl, spareLocksUrl, isBusy, onTool, onSpareLock, draftUse = () => '' }) {
    let tools = [];
    let spareLocks = [];
    let nextSpareCode = '';
    let pending = false;

    const toolForm = toolsDialog.querySelector('[data-tool-form]');
    const toolListView = toolsDialog.querySelector('[data-tools-list-view]');
    const toolPreview = toolForm.querySelector('[data-tool-preview]');
    const spareConfirm = spareDialog.querySelector('[data-spare-confirm]');
    const spareListView = spareDialog.querySelector('[data-spare-list-view]');
    let editing = null;

    function message(dialog, type, text) {
        const box = dialog.querySelector('[data-manage-message]');
        box.hidden = !text;
        box.dataset.type = type;
        box.textContent = text ?? '';
    }

    function setPending(value) {
        pending = value;
        [...toolsDialog.querySelectorAll('button, input'), ...spareDialog.querySelectorAll('button')]
            .forEach((control) => { if (!control.matches('[data-manage-close]')) control.disabled = value || control.hasAttribute('data-blocked'); });
    }

    function renderTools() {
        const list = toolsDialog.querySelector('[data-tools-list]');
        list.replaceChildren(...tools.map((tool) => {
            const row = document.createElement('li');
            row.className = 'lototo-manage-row';
            row.dataset.toolId = String(tool.id);
            const reason = blockedReason(tool, `Tools ${tool.code}`);
            row.innerHTML = `${tool.image ? `<img class="lototo-manage-thumb" src="${escapeHtml(tool.image)}" alt="">` : '<span class="lototo-manage-thumb lototo-manage-thumb--empty" aria-hidden="true">—</span>'}`
                + `<span class="lototo-manage-name"><strong>${escapeHtml(tool.code)}</strong> ${escapeHtml(tool.name)}<span class="lototo-manage-chips">${statusChips(tool)}</span></span>`
                + '<button type="button" class="lototo-editor-button" data-tool-edit>Edit</button>'
                + `<button type="button" class="lototo-editor-button lototo-editor-button--danger" data-tool-delete ${reason ? 'data-blocked disabled' : ''} title="${escapeHtml(reason || `Hapus Tools ${tool.code}`)}">Hapus</button>`;
            return row;
        }));
        toolsDialog.querySelector('[data-tools-empty]').hidden = tools.length > 0;
    }

    function renderSpareLocks() {
        const list = spareDialog.querySelector('[data-spare-list]');
        list.replaceChildren(...spareLocks.map((lock) => {
            const row = document.createElement('li');
            row.className = 'lototo-manage-row';
            row.dataset.spareLockId = String(lock.id);
            const reason = blockedReason(lock, `Gembok ${lock.code}`);
            row.innerHTML = `<span class="lototo-manage-name"><strong>${escapeHtml(lock.code)}</strong><span class="lototo-manage-chips">${statusChips(lock)}</span></span>`
                + `<button type="button" class="lototo-editor-button lototo-editor-button--danger" data-spare-delete ${reason ? 'data-blocked disabled' : ''} title="${escapeHtml(reason || `Hapus gembok ${lock.code}`)}">Hapus</button>`;
            return row;
        }));
        spareDialog.querySelector('[data-spare-empty]').hidden = spareLocks.length > 0;
        spareDialog.querySelectorAll('[data-spare-next]').forEach((element) => { element.textContent = nextSpareCode; });
    }

    // ---- Tools ----
    function showToolForm(tool) {
        editing = tool;
        toolForm.reset();
        toolForm.querySelector('[data-tool-form-title]').textContent = tool ? `Edit Tools ${tool.code}` : 'Tambah Tools';
        toolForm.querySelector('[data-tool-image-hint]').textContent = tool
            ? 'JPG, PNG, atau WEBP, maks. 2 MB. Kosongkan untuk mempertahankan gambar lama.'
            : 'Opsional. JPG, PNG, atau WEBP, maks. 2 MB.';
        toolForm.elements.name.value = tool?.name ?? '';
        toolPreview.hidden = !tool?.image;
        toolPreview.src = tool?.image ?? '';
        toolForm.hidden = false;
        toolListView.hidden = true;
        message(toolsDialog, '', '');
        toolForm.elements.name.focus();
    }

    function showToolList() {
        editing = null;
        toolForm.hidden = true;
        toolListView.hidden = false;
    }

    toolForm.elements.name.addEventListener('input', () => {
        if (toolsDialog.querySelector('[data-manage-message]').dataset.type === 'error') message(toolsDialog, '', '');
    });

    toolForm.elements.image.addEventListener('change', () => {
        const file = toolForm.elements.image.files[0];
        if (!file) {
            toolPreview.hidden = !editing?.image;
            toolPreview.src = editing?.image ?? '';
            return;
        }
        const reader = new FileReader();
        reader.onload = () => { toolPreview.src = reader.result; toolPreview.hidden = false; };
        reader.readAsDataURL(file);
    });

    toolForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending || isBusy()) return;
        const name = toolForm.elements.name.value.trim();
        if (!name) {
            message(toolsDialog, 'error', 'Nama Tools wajib diisi.');
            return;
        }
        const body = new FormData();
        body.append('name', name);
        if (toolForm.elements.image.files[0]) body.append('image', toolForm.elements.image.files[0]);
        const wasEditing = editing;
        setPending(true);
        const answer = await send(wasEditing ? `${toolsUrl}/${wasEditing.id}` : toolsUrl, { body });
        setPending(false);
        if (answer.error) {
            message(toolsDialog, 'error', answer.error);
            return;
        }
        tools = wasEditing ? tools.map((tool) => (tool.id === answer.tool.id ? answer.tool : tool)) : [...tools, answer.tool].sort((a, b) => a.code.localeCompare(b.code));
        renderTools();
        showToolList();
        message(toolsDialog, 'success', answer.message);
        onTool(wasEditing ? 'updated' : 'created', answer.tool);
    });

    toolsDialog.addEventListener('click', async (event) => {
        if (event.target.closest('[data-manage-close]')) {
            if (!pending) toolsDialog.close();
            return;
        }
        if (pending || isBusy()) return;
        if (event.target.closest('[data-tool-add]')) showToolForm(null);
        if (event.target.closest('[data-tool-cancel]')) { showToolList(); message(toolsDialog, '', ''); }
        const row = event.target.closest('[data-tool-id]');
        const tool = row && tools.find((item) => item.id === Number(row.dataset.toolId));
        if (tool && event.target.closest('[data-tool-edit]')) showToolForm(tool);
        if (tool && event.target.closest('[data-tool-delete]')) {
            const used = draftUse('tool', tool);
            if (used) { message(toolsDialog, 'error', used); return; }
            if (!window.confirm(`Hapus Tools ${tool.code} "${tool.name}" beserta gambarnya? QR ${tool.code} tidak berlaku lagi.`)) return;
            setPending(true);
            const answer = await send(`${toolsUrl}/${tool.id}`, { method: 'DELETE' });
            setPending(false);
            if (answer.error) {
                message(toolsDialog, 'error', answer.error);
                return;
            }
            tools = tools.filter((item) => item.id !== tool.id);
            renderTools();
            message(toolsDialog, 'success', answer.message);
            onTool('deleted', tool);
        }
    });

    // ---- Spare Lock ----
    spareDialog.addEventListener('click', async (event) => {
        if (event.target.closest('[data-manage-close]')) {
            if (!pending) spareDialog.close();
            return;
        }
        if (pending || isBusy()) return;
        if (event.target.closest('[data-spare-add]')) {
            spareConfirm.hidden = false;
            spareListView.hidden = true;
            message(spareDialog, '', '');
        }
        if (event.target.closest('[data-spare-cancel]')) {
            spareConfirm.hidden = true;
            spareListView.hidden = false;
        }
        if (event.target.closest('[data-spare-save]')) {
            setPending(true);
            const answer = await send(spareLocksUrl);
            setPending(false);
            if (answer.error) {
                message(spareDialog, 'error', answer.error);
                return;
            }
            spareLocks = [...spareLocks, answer.spare_lock].sort((a, b) => a.code.localeCompare(b.code));
            nextSpareCode = answer.next_spare_code;
            renderSpareLocks();
            spareConfirm.hidden = true;
            spareListView.hidden = false;
            message(spareDialog, 'success', answer.message);
            onSpareLock('created', answer.spare_lock);
        }
        const row = event.target.closest('[data-spare-lock-id]');
        const lock = row && spareLocks.find((item) => item.id === Number(row.dataset.spareLockId));
        if (lock && event.target.closest('[data-spare-delete]')) {
            const used = draftUse('spare', lock);
            if (used) { message(spareDialog, 'error', used); return; }
            if (!window.confirm(`Hapus gembok Spare ${lock.code}? QR ${lock.code} tidak berlaku lagi. Gembok yang pernah digunakan tidak dapat dihapus.`)) return;
            setPending(true);
            const answer = await send(`${spareLocksUrl}/${lock.id}`, { method: 'DELETE' });
            setPending(false);
            if (answer.error) {
                message(spareDialog, 'error', answer.error);
                return;
            }
            spareLocks = spareLocks.filter((item) => item.id !== lock.id);
            nextSpareCode = answer.next_spare_code;
            renderSpareLocks();
            message(spareDialog, 'success', answer.message);
            onSpareLock('deleted', lock);
        }
    });

    [toolsDialog, spareDialog].forEach((dialog) => dialog.addEventListener('cancel', (event) => { if (pending) event.preventDefault(); }));

    return {
        /** The board's tools and spare locks as the layout endpoint gives them (manage). */
        setData(manage) {
            tools = [...(manage?.tools ?? [])];
            spareLocks = [...(manage?.spare_locks ?? [])];
            nextSpareCode = manage?.next_spare_code ?? '';
            renderTools();
            renderSpareLocks();
        },
        openTools() {
            if (isBusy()) return;
            showToolList();
            message(toolsDialog, '', '');
            toolsDialog.showModal();
        },
        openSpareLocks() {
            if (isBusy()) return;
            spareConfirm.hidden = true;
            spareListView.hidden = false;
            message(spareDialog, '', '');
            spareDialog.showModal();
        },
        close() {
            if (toolsDialog.open) toolsDialog.close();
            if (spareDialog.open) spareDialog.close();
        },
    };
}
