if (document.querySelector('[aria-label="Formulir pemeriksaan LOTOTO"]')) {
    import('./lototo-form');
}

document.querySelectorAll('[data-slot-list]').forEach((list) => {
    let draggedRow = null;

    list.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-drag-handle]');
        if (!handle) {
            event.preventDefault();
            return;
        }

        draggedRow = handle.closest('[data-slot-row]');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedRow.dataset.slotId);
        draggedRow.classList.add('opacity-50');
    });

    list.addEventListener('dragend', () => {
        draggedRow?.classList.remove('opacity-50');
        draggedRow = null;
    });

    list.addEventListener('dragover', (event) => {
        if (!draggedRow) return;
        event.preventDefault();
        const targetRow = event.target.closest('[data-slot-row]');
        if (!targetRow || targetRow === draggedRow) return;

        const bounds = targetRow.getBoundingClientRect();
        const insertAfter = event.clientY > bounds.top + bounds.height / 2;
        list.insertBefore(draggedRow, insertAfter ? targetRow.nextSibling : targetRow);
    });

    list.addEventListener('drop', (event) => {
        if (!draggedRow) return;
        event.preventDefault();
        [...list.querySelectorAll('[data-slot-row]')].forEach((row, index) => {
            const position = row.querySelector('[data-slot-position]');
            position.value = String(index + 1);
        });
    });
});

document.querySelectorAll('form[data-confirm-delete-slot]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Hapus konfigurasi slot kosong ini? Data operasional tidak akan dihapus.')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-confirm-board-status]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const boardName = form.dataset.boardName || 'board ini';
        const nextStatus = form.dataset.nextStatus;
        const message = nextStatus === 'inactive'
            ? `Nonaktifkan board "${boardName}"? Konfigurasi, assignment, spare lock, dan histori tetap tersimpan. Aktivitas yang sedang berjalan tidak akan dihentikan.`
            : `Aktifkan kembali board "${boardName}"? Business Unit dan konfigurasi board akan divalidasi terlebih dahulu.`;
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-confirm-board-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const boardName = form.dataset.boardName || 'board ini';
        const message = `Hapus board "${boardName}"? Konfigurasi slot board ini ikut dihapus. Board yang memiliki histori aktivitas, Spare Lock, atau Tools akan ditolak dan tetap tersimpan.`;
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-confirm-spare-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const code = form.dataset.spareCode || 'ini';
        if (!window.confirm(`Hapus gembok Spare "${code}"? Posisinya pada board menjadi kosong dan QR ${code} tidak berlaku lagi.`)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-confirm-tool-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const code = form.dataset.toolCode || 'ini';
        if (!window.confirm(`Hapus Tools "${code}" beserta gambarnya? Posisinya pada board menjadi kosong dan QR ${code} tidak berlaku lagi.`)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-spare-code-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const currentCode = form.dataset.currentCode;
        const newCode = (form.elements.code?.value || '').trim().toUpperCase();
        if (newCode && newCode !== currentCode
            && !window.confirm(`Ganti kode ${currentCode} menjadi ${newCode}? QR lama tidak berlaku lagi; cetak ulang QR setelah menyimpan.`)) {
            event.preventDefault();
        }
    });
});

const watchedForms =[...document.querySelectorAll('form[data-unsaved-watch]')];
const dirtyForms = new Set();

watchedForms.forEach((form) => {
    form.addEventListener('input', () => { dirtyForms.add(form); });
    form.addEventListener('change', () => { dirtyForms.add(form); });
    form.addEventListener('submit', () => { dirtyForms.delete(form); });
});

document.querySelectorAll('[data-unsaved-back]').forEach((link) => {
    link.addEventListener('click', (event) => {
        if (dirtyForms.size === 0) return;
        if (!window.confirm('Ada perubahan yang belum disimpan. Tinggalkan halaman dan buang perubahan?')) {
            event.preventDefault();
            return;
        }
        dirtyForms.clear();
    });
});

window.addEventListener('beforeunload', (event) => {
    if (dirtyForms.size === 0) return;
    event.preventDefault();
    event.returnValue = '';
});
