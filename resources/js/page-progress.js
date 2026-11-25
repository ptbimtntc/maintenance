// Thin progress bar at the top of the window while the next page loads
// (styles: .page-progress in resources/css/page-transitions.css).
//
// Starts on same-tab, same-origin link clicks and form submits that no other
// handler cancelled. It only appears if the navigation takes longer than a
// moment, so fast pages don't flash. Opt out per element with
// data-no-progress (on the link/form or an ancestor).
//
// Downloads (exports, templates, PDFs) never unload the page, so the bar
// finishes and hides itself after a while instead of hanging.

const SHOW_DELAY_MS = 120;
const GIVE_UP_MS = 8000;

let bar = null;
let showTimer = null;
let trickleTimer = null;
let giveUpTimer = null;
let progress = 0;

function ensureBar() {
    if (! bar) {
        bar = document.createElement('div');
        bar.className = 'page-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
    }

    return bar;
}

function setProgress(value) {
    progress = value;
    ensureBar().style.transform = `scaleX(${value})`;
}

function clearTimers() {
    clearTimeout(showTimer);
    clearInterval(trickleTimer);
    clearTimeout(giveUpTimer);
}

function start() {
    clearTimers();

    showTimer = setTimeout(() => {
        const el = ensureBar();
        el.classList.remove('is-done');
        el.classList.add('is-active');
        setProgress(0.15);

        // Ease towards 90% but never reach it until the page actually changes.
        trickleTimer = setInterval(() => {
            setProgress(progress + (0.9 - progress) * 0.12);
        }, 300);
    }, SHOW_DELAY_MS);

    giveUpTimer = setTimeout(done, GIVE_UP_MS);
}

function done() {
    clearTimers();

    if (! bar || ! bar.classList.contains('is-active')) {
        return;
    }

    setProgress(1);
    bar.classList.add('is-done');
    bar.classList.remove('is-active');
    setTimeout(() => {
        if (bar && ! bar.classList.contains('is-active')) setProgress(0);
    }, 500);
}

function isDownloadUrl(url) {
    return /\/(export|template|download)(\/|$|\?)|\.(pdf|xlsx|csv|png|zip)$/i.test(url.pathname);
}

function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest?.('a[href]');

    if (! link || link.closest('[data-no-progress]') || link.hasAttribute('download') || link.getAttribute('href').startsWith('#')) {
        return;
    }

    if (link.target && link.target !== '_self') {
        return;
    }

    const url = new URL(link.href, location.href);

    if (url.origin !== location.origin || ! /^https?:$/.test(url.protocol) || isDownloadUrl(url)) {
        return;
    }

    // Same page, only the #hash differs: no navigation happens.
    if (url.pathname === location.pathname && url.search === location.search && url.hash) {
        return;
    }

    start();
}

function onSubmit(event) {
    const form = event.target;

    if (event.defaultPrevented || ! (form instanceof HTMLFormElement) || form.closest('[data-no-progress]')) {
        return;
    }

    const target = event.submitter?.getAttribute('formtarget') ?? form.target;

    if (target && target !== '_self') {
        return;
    }

    const action = new URL(event.submitter?.getAttribute('formaction') ?? form.action, location.href);

    if (action.origin !== location.origin || isDownloadUrl(action)) {
        return;
    }

    start();
}

// Bubble phase on document, so handlers that cancel the event (Alpine
// @click.prevent, the confirm dialog in ui.js, JS-driven forms) run first.
document.addEventListener('click', onClick);
document.addEventListener('submit', onSubmit);

// Back/forward cache restores the old page with the bar still running.
window.addEventListener('pageshow', done);
