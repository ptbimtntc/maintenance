import { DESIGNS } from './lototo-print-designs';
import { canvasToPng } from './lototo-print-png';

/**
 * "Download QR" popup on LOTOTO Settings (partial _print-designs): one QR record at a time (chosen with the button
 * or the identity list), the five print designs previewed with that record's own name, number, photo and QR, and
 * the ticked designs downloaded as PNG images, one file per design, 600 dpi (sharp enough to print and to scan, with
 * the printed size stored in the file). The preview and the PNG come from the same SVG template.
 */

const DPI = 600;
const KIND_LABELS = { technician: 'Technician', supervisor: 'Supervisor', spare: 'Spare Lock', tool: 'Tools' };
const formatMm = (mm) => String(mm).replace('.', ',');

function initPrintDesigns(dialog) {
    const records = new Map(JSON.parse(document.querySelector('[data-print-records]').textContent).map((record) => [record.key, record]));
    const identity = dialog.querySelector('[data-print-identity]');
    const cards = dialog.querySelector('[data-print-cards]');
    const note = dialog.querySelector('[data-print-note]');
    const message = dialog.querySelector('[data-print-message]');
    const downloadButton = dialog.querySelector('[data-print-download]');
    const photos = new Map();
    let current = null;
    let token = 0;
    let busy = false;

    // One card per design: checkbox, name, printed size, preview.
    DESIGNS.forEach((design) => {
        const card = document.createElement('label');
        card.className = 'lototo-print-card';
        card.dataset.printDesign = String(design.id);
        card.innerHTML = `<span class="lototo-print-card__head"><input type="checkbox" data-print-check value="${design.id}"> <strong>${design.name}</strong>`
            + `<span class="lototo-print-card__size">${formatMm(design.width)} × ${formatMm(design.height)} mm</span></span>`
            + '<span class="lototo-print-card__preview" data-print-preview></span><span class="lototo-print-card__unavailable" data-print-unavailable hidden></span>';
        cards.append(card);
    });
    const checks = () => [...cards.querySelectorAll('[data-print-check]')];

    function showMessage(text) {
        message.hidden = !text;
        message.dataset.type = text ? 'error' : '';
        message.textContent = text ?? '';
    }

    /** The record's photo as a data URL (so the SVG keeps it when drawn on a canvas), or null when there is none. */
    function photoOf(record) {
        if (!record.photo) return Promise.resolve(null);
        if (!photos.has(record.key)) {
            photos.set(record.key, fetch(record.photo, { credentials: 'same-origin' })
                .then((response) => (response.ok ? response.blob() : null))
                .then((blob) => (blob && blob.type.startsWith('image/') ? new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => resolve(null);
                    reader.readAsDataURL(blob);
                }) : null))
                .catch(() => null));
        }

        return photos.get(record.key);
    }

    function updateButtons() {
        const selected = checks().filter((box) => box.checked && !box.disabled).length;
        downloadButton.disabled = busy || selected === 0;
        downloadButton.textContent = busy ? 'Membuat PNG…' : (selected ? `Download PNG (${selected} desain)` : 'Download PNG');
    }

    /** Fills every preview with the chosen record; designs not made for its kind are shown but cannot be ticked. */
    async function render() {
        const record = records.get(identity.value);
        if (!record) return;
        current = record;
        const run = ++token;
        const photo = await photoOf(record);
        if (run !== token) return;
        const data = { ...record, photo };
        const needsPhoto = record.kind !== 'spare';
        note.textContent = needsPhoto && !photo
            ? (record.kind === 'tool' ? `Gambar Tools belum tersedia untuk ${record.number} ${record.name}; area foto dibiarkan kosong.` : `Foto karyawan belum tersedia untuk ${record.number} ${record.name}; area foto memakai siluet.`)
            : `${KIND_LABELS[record.kind]} ${record.number} — ${record.name}`;
        DESIGNS.forEach((design) => {
            const card = cards.querySelector(`[data-print-design="${design.id}"]`);
            const box = card.querySelector('[data-print-check]');
            const available = design.kinds.includes(record.kind);
            box.disabled = !available || busy;
            if (!available) box.checked = false;
            card.classList.toggle('lototo-print-card--unavailable', !available);
            card.querySelector('[data-print-preview]').innerHTML = available ? design.render(data) : '';
            const reason = card.querySelector('[data-print-unavailable]');
            reason.hidden = available;
            reason.textContent = available ? '' : `Desain ini memuat foto karyawan tanpa QR, sehingga tidak dipakai untuk ${KIND_LABELS[record.kind]}.`;
        });
        updateButtons();
    }

    function open(key) {
        if (!records.has(key)) return;
        identity.value = key;
        showMessage('');
        render();
        if (!dialog.open) dialog.showModal();
    }

    /** One design drawn at 600 dpi on a white canvas, as PNG bytes. */
    async function rasterize(design, data) {
        const pixelWidth = Math.round((design.width / 25.4) * DPI);
        const pixelHeight = Math.round((design.height / 25.4) * DPI);
        const url = URL.createObjectURL(new Blob([design.render(data)], { type: 'image/svg+xml' }));
        try {
            const image = new Image();
            image.src = url;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = pixelWidth;
            canvas.height = pixelHeight;
            const context = canvas.getContext('2d');
            context.fillStyle = '#FFFFFF';
            context.fillRect(0, 0, pixelWidth, pixelHeight);
            context.drawImage(image, 0, 0, pixelWidth, pixelHeight);

            return await canvasToPng(canvas, DPI);
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    function setBusy(value) {
        busy = value;
        [identity, ...dialog.querySelectorAll('[data-print-select-all], [data-print-select-none], [data-print-close]')].forEach((control) => { control.disabled = value; });
        checks().forEach((box) => { box.disabled = value || !DESIGNS.find((d) => d.id === Number(box.value)).kinds.includes(current.kind); });
        updateButtons();
    }

    async function download() {
        const chosen = DESIGNS.filter((design) => checks().some((box) => box.checked && !box.disabled && Number(box.value) === design.id));
        if (!chosen.length || busy) return;
        const record = current;
        setBusy(true);
        showMessage('');
        try {
            const data = { ...record, photo: await photoOf(record) };
            // One PNG file per design, made one at a time (a 600 dpi picture is large) and saved one after another.
            // A browser may ask once to allow several downloads from this page.
            for (const [index, design] of chosen.entries()) {
                const png = await rasterize(design, data);
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob([png], { type: 'image/png' }));
                link.download = `lototo-qr-${record.kind}-${record.number}-desain-${design.id}.png`.toLowerCase().replace(/[^a-z0-9.-]+/g, '-');
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(link.href), 60000);
                if (index < chosen.length - 1) await new Promise((resolve) => { setTimeout(resolve, 400); });
            }
        } catch (exception) {
            showMessage(`PNG tidak dapat dibuat: ${exception.message}`);
        } finally {
            setBusy(false);
        }
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-print-open]');
        if (!button) return;
        event.preventDefault();
        open(button.dataset.printOpen);
    });
    identity.addEventListener('change', () => { showMessage(''); render(); });
    cards.addEventListener('change', updateButtons);
    dialog.querySelector('[data-print-select-all]').addEventListener('click', () => {
        checks().forEach((box) => { if (!box.disabled) box.checked = true; });
        updateButtons();
    });
    dialog.querySelector('[data-print-select-none]').addEventListener('click', () => {
        checks().forEach((box) => { box.checked = false; });
        updateButtons();
    });
    dialog.querySelector('[data-print-close]').addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); });
    downloadButton.addEventListener('click', download);
}

const printDialog = document.querySelector('[data-print-designs]');
if (printDialog) {
    initPrintDesigns(printDialog);
}
