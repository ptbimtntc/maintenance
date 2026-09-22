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
 * Wires up "Download PNG" buttons (see employees/qr-codes.blade.php): each
 * button downloads the nearest preceding QR canvas in its own card.
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

            const link = document.createElement('a');
            link.download = `${button.dataset.filename || 'qrcode'}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initQrCodes();
    initQrDownloadButtons();
});
