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

document.addEventListener('DOMContentLoaded', initQrCodes);
