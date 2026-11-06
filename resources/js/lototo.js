if (document.querySelector('[aria-label="Formulir pemeriksaan LOTOTO"]')) {
    import('./lototo-form');
}

if (document.querySelector('[data-board-editor]')) {
    import('./lototo-board-editor');
}

if (document.querySelector('[data-print-designs]')) {
    import('./lototo-print');
}

document.querySelectorAll('form[data-confirm-board-status]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const boardName = form.dataset.boardName || 'board ini';
        const nextStatus = form.dataset.nextStatus;
        const openActivities = Number(form.dataset.openActivities || 0);
        const message = nextStatus === 'inactive'
            ? `Nonaktifkan board "${boardName}"? Board tidak menerima aktivitas LOTOTO baru. Konfigurasi, assignment, Tools, Spare Lock, dan histori tetap tersimpan.`
                + (openActivities > 0 ? `\n\nMasih ada ${openActivities} aktivitas IN di board ini; aktivitas tersebut tetap dapat diselesaikan dengan OUT atau Handover.` : '')
            : `Aktifkan kembali board "${boardName}"? Business Unit dan konfigurasi board akan divalidasi terlebih dahulu.`;
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-confirm-board-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const boardName = form.dataset.boardName || 'board ini';
        const message = `Hapus board "${boardName}"? Slot, gembok Spare, dan Tools yang belum pernah digunakan ikut dihapus. Board dengan histori aktivitas, atau gembok/Tools yang sedang IN atau punya riwayat, akan ditolak dan tetap tersimpan.`;
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
