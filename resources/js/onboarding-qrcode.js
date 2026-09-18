import QRCode from 'qrcode';

/**
 * Renders the onboarding QR code shown on an employee's profile (see
 * employees/show.blade.php) client-side, so the app doesn't need a PHP
 * image extension (GD/Imagick) just to draw a QR code.
 */
function initOnboardingQrCodes() {
    document.querySelectorAll('canvas[data-onboarding-qrcode]').forEach((canvas) => {
        if (canvas.dataset.qrcodeInitialized) {
            return;
        }

        canvas.dataset.qrcodeInitialized = 'true';

        QRCode.toCanvas(canvas, canvas.dataset.onboardingQrcode, {
            width: 176,
            margin: 1,
            color: { dark: '#211F1C', light: '#FFFFFF' },
        });
    });
}

document.addEventListener('DOMContentLoaded', initOnboardingQrCodes);
