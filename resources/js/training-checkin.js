import * as faceapi from 'face-api.js';

const MODEL_URL = '/face-models';
const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions();
const RESCAN_DELAY_MS = 2500;

let modelsPromise = null;

function loadModels() {
    modelsPromise ??= Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);

    return modelsPromise;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

/**
 * Wires up the kiosk panel on a training session's Face Check-in page (see
 * training/sessions/check-in.blade.php). Runs a continuous detect-match-
 * pause loop rather than a single attempt, since the whole point is that
 * one device stays open at the venue while a queue of people check in.
 */
function initCheckInPanel() {
    const panel = document.getElementById('training-checkin-panel');

    if (!panel) {
        return;
    }

    const video = panel.querySelector('[data-face-video]');
    const startButton = panel.querySelector('[data-face-start]');
    const statusEl = panel.querySelector('[data-face-status]');
    const attemptUrl = panel.dataset.attemptUrl;

    let running = false;

    const setStatus = (text) => {
        if (statusEl) statusEl.textContent = text;
    };

    async function loop() {
        while (running) {
            let descriptor = null;

            try {
                const detection = await faceapi
                    .detectSingleFace(video, DETECTOR_OPTIONS)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                descriptor = detection ? Array.from(detection.descriptor) : null;
            } catch {
                descriptor = null;
            }

            if (!descriptor) {
                setStatus('Looking for a face…');
                await new Promise((resolve) => setTimeout(resolve, 500));
                continue;
            }

            setStatus('Verifying…');

            try {
                const response = await fetch(attemptUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ descriptor }),
                });

                const data = await response.json();

                if (!response.ok) {
                    setStatus(data.message || 'Face not recognized.');
                } else if (data.already) {
                    setStatus(data.message);
                } else {
                    setStatus('✓ '+data.message);
                }
            } catch (error) {
                setStatus('Could not reach the server: ' + error.message);
            }

            await new Promise((resolve) => setTimeout(resolve, RESCAN_DELAY_MS));
        }
    }

    startButton?.addEventListener('click', async () => {
        startButton.disabled = true;
        startButton.classList.add('hidden');
        setStatus('Loading face recognition models - this can take up to 15 seconds the first time…');

        try {
            await loadModels();

            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            video.srcObject = stream;
            await video.play();
            video.classList.remove('hidden');

            running = true;
            loop();
        } catch (error) {
            setStatus('Could not access the camera: ' + error.message);
            startButton.disabled = false;
            startButton.classList.remove('hidden');
        }
    });
}

initCheckInPanel();
