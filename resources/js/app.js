

import Alpine from 'alpinejs';
import './dashboard-charts';
import './qrcode-canvas';
import './qr-base-url';

// face-login.js pulls in face-api.js/TensorFlow.js, a large dependency -
// dynamically imported so pages that never touch Face Login (i.e. almost
// all of them) don't pay for it.
if (document.getElementById('face-login-panel') || document.getElementById('face-login-enroll-panel')) {
    import('./face-login');
}

window.Alpine = Alpine;

Alpine.start();
