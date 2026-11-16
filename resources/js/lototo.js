if (document.querySelector('[aria-label="Formulir pemeriksaan LOTOTO"]')) {
    import('./lototo-form');
}

if (document.querySelector('[data-board-editor]')) {
    import('./lototo-board-editor');
}

if (document.querySelector('[data-print-designs]')) {
    import('./lototo-print');
}

/**
 * An employee photo that cannot be loaded (the file was removed, the connection dropped) is replaced by the employee's
 * initials, so a card or page never shows a broken-image icon. The server only emits an <img> for a file that exists
 * (LototoEmployeePhoto); this covers what happens after that. Images that failed before this ran are handled too.
 */
function photoFallback(image) {
    if (!image.isConnected || !image.classList.contains('lototo-photo-img')) return;
    const initials = document.createElement(image.classList.contains('lototo-photo') ? 'span' : 'div');
    initials.textContent = image.dataset.initials ?? '';
    initials.className = image.classList.contains('lototo-photo')
        ? 'lototo-photo lototo-photo--initials'
        : 'flex h-12 w-12 shrink-0 items-center sm:h-16 sm:w-16 justify-center border border-neutral-400 bg-neutral-200 text-lg font-bold text-neutral-700';
    image.replaceWith(initials);
}

document.addEventListener('error', (event) => {
    if (event.target instanceof HTMLImageElement) photoFallback(event.target);
}, true);
document.querySelectorAll('img.lototo-photo-img').forEach((image) => {
    if (image.complete && image.naturalWidth === 0) photoFallback(image);
});

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
