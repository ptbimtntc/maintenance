import QRCode from 'qrcode';

/**
 * Renders any QR code canvas client-side (onboarding links, certificate
 * verification links, ...) so the app doesn't need a PHP image extension
 * (GD/Imagick) just to draw a QR code.
 */
function initQrCodes() {
    document.querySelectorAll('canvas[data-qrcode]').forEach((canvas) => {
        if (canvas.dataset.qrcodeInitialized) {
            return;
        }

        canvas.dataset.qrcodeInitialized = 'true';

        QRCode.toCanvas(canvas, canvas.dataset.qrcode, {
            width: 176,
            margin: 1,
            color: { dark: '#211F1C', light: '#FFFFFF' },
        });
    });
}

/**
 * Draws the QR code onto a taller canvas with the employee's name and NIK
 * printed above it, so a downloaded/printed badge is self-identifying
 * without needing the surrounding page for context.
 */
function composeQrWithLabel(qrCanvas, name, nik) {
    const padding = 16;
    const textBlockHeight = name || nik ? 54 : 0;
    const width = qrCanvas.width + padding * 2;
    const height = qrCanvas.height + padding * 2 + textBlockHeight;

    const output = document.createElement('canvas');
    output.width = width;
    output.height = height;

    const ctx = output.getContext('2d');
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, width, height);
    ctx.textAlign = 'center';

    if (name) {
        ctx.fillStyle = '#211F1C';
        ctx.font = 'bold 16px Arial, sans-serif';
        ctx.fillText(name, width / 2, padding + 20, width - padding * 2);
    }

    if (nik) {
        ctx.fillStyle = '#525252';
        ctx.font = '13px Arial, sans-serif';
        ctx.fillText(`NIK: ${nik}`, width / 2, padding + (name ? 42 : 20), width - padding * 2);
    }

    ctx.drawImage(qrCanvas, padding, padding + textBlockHeight);

    return output;
}

/**
 * Wires up "Download PNG" buttons (see employees/qr-codes.blade.php): each
 * button downloads the nearest preceding QR canvas in its own card, with
 * the employee's name/NIK (data-name/data-nik) printed above it.
 */
function initQrDownloadButtons() {
    document.querySelectorAll('button[data-download-qr]').forEach((button) => {
        if (button.dataset.downloadWired) {
            return;
        }

        button.dataset.downloadWired = 'true';

        button.addEventListener('click', () => {
            const canvas = button.closest('div')?.querySelector('canvas[data-qrcode]');

            if (!canvas) {
                return;
            }

            const output = composeQrWithLabel(canvas, button.dataset.name, button.dataset.nik);

            const link = document.createElement('a');
            link.download = `${button.dataset.filename || 'qrcode'}.png`;
            link.href = output.toDataURL('image/png');
            link.click();
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initQrCodes();
    initQrDownloadButtons();
});
