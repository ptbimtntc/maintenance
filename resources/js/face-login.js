import * as faceapi from 'face-api.js';

const MODEL_URL = '/face-models';
const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions();

let modelsPromise = null;

function loadModels() {
    modelsPromise ??= Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);

    return modelsPromise;
}

async function startCamera(video) {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    video.srcObject = stream;
    await video.play();

    return stream;
}

function stopCamera(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}

/** A single face, well-lit and roughly centered - null if none of that holds. */
async function captureDescriptor(video) {
    const detection = await faceapi
        .detectSingleFace(video, DETECTOR_OPTIONS)
        .withFaceLandmarks()
        .withFaceDescriptor();

    return detection ? Array.from(detection.descriptor) : null;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

/**
 * Wires up the "Enable Face Login" panel on the employee's own Profile page
 * (see profile/partials/face-login-form.blade.php). Capturing a descriptor
 * happens entirely in the browser - only the resulting 128 numbers are ever
 * sent to the server, never the photo/video itself.
 */
function initEnrollPanel() {
    const panel = document.getElementById('face-login-enroll-panel');

    if (!panel) {
        return;
    }

    const video = panel.querySelector('[data-face-video]');
    const startButton = panel.querySelector('[data-face-start]');
    const saveButton = panel.querySelector('[data-face-save]');
    const consentCheckbox = panel.querySelector('[data-face-consent]');
    const statusEl = panel.querySelector('[data-face-status]');
    const enrollUrl = panel.querySelector('[data-face-enroll-url]')?.dataset.faceEnrollUrl;

    let stream = null;
    let lastDescriptor = null;

    const setStatus = (text) => {
        if (statusEl) statusEl.textContent = text;
    };

    startButton?.addEventListener('click', async () => {
        startButton.disabled = true;
        setStatus('Loading face recognition models - this can take up to 15 seconds the first time…');

        try {
            await loadModels();
            stream = await startCamera(video);
            video.classList.remove('hidden');
            setStatus('Look straight at the camera, then click "Capture Face".');
            saveButton.classList.remove('hidden');
            saveButton.disabled = false;
        } catch (error) {
            setStatus('Could not access the camera: ' + error.message);
            startButton.disabled = false;
        }
    });

    saveButton?.addEventListener('click', async () => {
        if (!lastDescriptor) {
            saveButton.disabled = true;
            setStatus('Detecting your face - this can take up to 15 seconds the first time…');

            try {
                let descriptor = null;

                // A single detectSingleFace() call can miss if the video's
                // first frame isn't ready yet - retry a few times instead of
                // giving up after one attempt. Kept short: each attempt is
                // itself a full inference pass, so a device without GPU
                // acceleration can already take several seconds per try.
                for (let attempt = 0; attempt < 5 && !descriptor; attempt++) {
                    descriptor = await captureDescriptor(video);

                    if (!descriptor) {
                        await new Promise((resolve) => setTimeout(resolve, 400));
                    }
                }

                if (!descriptor) {
                    setStatus('No face detected - make sure your face is well-lit and centered, then try again.');
                    saveButton.disabled = false;

                    return;
                }

                lastDescriptor = descriptor;
                setStatus('Face captured. Check the consent box below, then click "Save" to enable Face Login.');
                saveButton.textContent = 'Save';
                saveButton.disabled = !consentCheckbox?.checked;
            } catch (error) {
                setStatus('Something went wrong detecting your face: ' + error.message);
                saveButton.disabled = false;
            }

            return;
        }

        if (!consentCheckbox?.checked) {
            setStatus('Please check the consent box first.');

            return;
        }

        saveButton.disabled = true;
        setStatus('Saving…');

        try {
            const response = await fetch(enrollUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ descriptor: lastDescriptor, consent: true, label: navigator.userAgent }),
            });

            const data = await response.json();
            stopCamera(stream);

            if (!response.ok) {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                setStatus(firstError || data.message || 'Could not save Face Login - please try again.');
                saveButton.disabled = false;

                return;
            }

            window.location.reload();
        } catch (error) {
            stopCamera(stream);
            setStatus('Could not save Face Login: ' + error.message);
            saveButton.disabled = false;
        }
    });

    consentCheckbox?.addEventListener('change', () => {
        if (lastDescriptor) {
            saveButton.disabled = !consentCheckbox.checked;
        }
    });
}

/**
 * Wires up the "Login with Face" button on the login page (see
 * auth/login.blade.php). Only shown at all when this specific browser
 * carries a trusted-device cookie from a prior enrollment (checked via
 * face-login.status) - a fresh/unrecognized device never sees this option
 * and falls back to the password form as normal.
 */
async function initLoginPanel() {
    const panel = document.getElementById('face-login-panel');

    if (!panel) {
        return;
    }

    let statusData;

    try {
        const response = await fetch(panel.dataset.statusUrl, { headers: { Accept: 'application/json' } });
        statusData = await response.json();
    } catch {
        return;
    }

    if (!statusData?.available) {
        return;
    }

    panel.classList.remove('hidden');

    const video = panel.querySelector('[data-face-video]');
    const startButton = panel.querySelector('[data-face-start]');
    const statusEl = panel.querySelector('[data-face-status]');

    const setStatus = (text) => {
        if (statusEl) statusEl.textContent = text;
    };

    startButton?.addEventListener('click', async () => {
        startButton.disabled = true;
        setStatus('Loading face recognition models - this can take up to 15 seconds the first time…');

        let stream = null;

        try {
            await loadModels();
            stream = await startCamera(video);
            video.classList.remove('hidden');
            setStatus('Hold still, looking at the camera…');

            let descriptor = null;

            for (let attempt = 0; attempt < 5 && !descriptor; attempt++) {
                descriptor = await captureDescriptor(video);

                if (!descriptor) {
                    await new Promise((resolve) => setTimeout(resolve, 300));
                }
            }

            if (!descriptor) {
                setStatus('No face detected. Please use your password instead.');
                stopCamera(stream);
                startButton.disabled = false;

                return;
            }

            setStatus('Verifying…');

            const response = await fetch(panel.dataset.attemptUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ descriptor }),
            });

            const data = await response.json();
            stopCamera(stream);

            if (!response.ok) {
                setStatus(data.message || 'Face not recognized. Please use your password.');
                startButton.disabled = false;

                return;
            }

            setStatus('Recognized! Signing you in…');
            window.location.href = data.redirect;
        } catch (error) {
            stopCamera(stream);
            setStatus('Something went wrong: ' + error.message);
            startButton.disabled = false;
        }
    });
}

// Dynamically imported by app.js only once the relevant DOM already exists
// (see the guard there), so there's no need to wait for DOMContentLoaded -
// by the time this module loads, it has already fired.
initEnrollPanel();
initLoginPanel();
