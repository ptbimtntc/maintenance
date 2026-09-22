import { renderQrCode } from './qrcode-canvas';

const STORAGE_KEY = 'qr-base-url';

/**
 * Lets an admin type a different domain for the QR codes on
 * employees/qr-codes.blade.php - useful when this environment's own
 * address (e.g. a temporary codespace URL) isn't where the QR codes should
 * actually point once deployed. Saved per-browser only (localStorage);
 * nothing changes on the server or for anyone else.
 */
function initQrBaseUrlPanel() {
    const panel = document.getElementById('qr-base-url-panel');

    if (!panel) {
        return;
    }

    const input = document.getElementById('qr-base-url');
    const defaultBaseUrl = panel.dataset.defaultBaseUrl;

    const readStoredBaseUrl = () => {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch {
            return null;
        }
    };

    const storeBaseUrl = (value) => {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            // Private browsing / storage disabled - the typed value still
            // applies for this page view, it just won't be remembered.
        }
    };

    const apply = (rawBaseUrl) => {
        const baseUrl = (rawBaseUrl || defaultBaseUrl).replace(/\/+$/, '');

        document.querySelectorAll('canvas[data-qr-path]').forEach((canvas) => {
            const fullUrl = baseUrl + canvas.dataset.qrPath;
            canvas.dataset.qrcode = fullUrl;
            renderQrCode(canvas, fullUrl);

            const urlText = canvas.closest('div')?.parentElement?.querySelector('[data-qr-url-text]');

            if (urlText) {
                urlText.textContent = fullUrl;
            }
        });
    };

    input.value = readStoredBaseUrl() || defaultBaseUrl;
    apply(input.value);

    document.getElementById('qr-base-url-apply')?.addEventListener('click', () => {
        const value = input.value.trim();
        storeBaseUrl(value);
        apply(value);
    });

    document.getElementById('qr-base-url-reset')?.addEventListener('click', () => {
        input.value = defaultBaseUrl;
        storeBaseUrl(defaultBaseUrl);
        apply(defaultBaseUrl);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('qr-base-url-apply')?.click();
        }
    });
}

document.addEventListener('DOMContentLoaded', initQrBaseUrlPanel);
