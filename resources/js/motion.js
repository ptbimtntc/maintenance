// Scroll-driven motion, declared in markup (see resources/css/motion.css):
//   <div data-motion>        opt-in container; only reveals inside it are animated
//   <div data-reveal>        fades/slides in the first time it scrolls into view;
//                            elements revealed together are staggered
//   <p data-countup>12.5%</p> number counts up from 0 to the text it already shows
//   <img data-parallax>      drifts slower than the page while scrolling (inside
//                            an overflow-hidden frame taller than the image box)
//   <div data-read-progress="#article">  scaleX 0..1 as #article scrolls past;
//                            runs even with reduced motion (it is information)
//
// Only active while html.motion-ok is set, which the layout's inline head
// script does unless the OS asks for reduced motion.

const STAGGER_MS = 70;
const MAX_STAGGER_MS = 420;
const COUNT_MS = 1100;

const easeOutCubic = (t) => 1 - (1 - t) ** 3;

function countUp(el) {
    const original = el.textContent;
    const match = original.match(/-?\d[\d,]*(?:\.\d+)?/);

    if (! match) {
        return;
    }

    const raw = match[0];
    const target = parseFloat(raw.replace(/,/g, ''));

    if (! Number.isFinite(target) || target === 0) {
        return;
    }

    const decimals = (raw.split('.')[1] || '').length;
    const grouped = raw.includes(',');
    const before = original.slice(0, match.index);
    const after = original.slice(match.index + raw.length);
    const format = (n) => n.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
        useGrouping: grouped,
    });

    const delay = parseFloat(el.closest('[data-reveal]')?.style.getPropertyValue('--reveal-delay')) || 0;
    let start = null;

    const step = (now) => {
        start ??= now + delay;
        const t = Math.min(Math.max((now - start) / COUNT_MS, 0), 1);
        el.textContent = before + format(target * easeOutCubic(t)) + after;

        if (t < 1) {
            requestAnimationFrame(step);
        } else {
            el.textContent = original;
        }
    };

    el.textContent = before + format(0) + after;
    requestAnimationFrame(step);
}

// Scroll listeners must cover both the window and the app's <main> scroller.
function onAnyScroll(callback) {
    let queued = false;
    const run = () => {
        queued = false;
        callback();
    };
    const schedule = () => {
        if (! queued) {
            queued = true;
            requestAnimationFrame(run);
        }
    };

    document.addEventListener('scroll', schedule, { capture: true, passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    run();
}

function initReadProgress() {
    const bars = [...document.querySelectorAll('[data-read-progress]')]
        .map((bar) => [bar, document.querySelector(bar.dataset.readProgress)])
        .filter(([, target]) => target);

    if (! bars.length) {
        return;
    }

    onAnyScroll(() => {
        bars.forEach(([bar, target]) => {
            const rect = target.getBoundingClientRect();
            const travel = rect.height - window.innerHeight * 0.6;
            const read = travel > 0 ? (window.innerHeight * 0.4 - rect.top) / travel : (rect.top < window.innerHeight * 0.4 ? 1 : 0);
            bar.style.transform = `scaleX(${Math.min(Math.max(read, 0), 1)})`;
        });
    });
}

function initParallax() {
    const items = [...document.querySelectorAll('[data-motion] [data-parallax]')];

    if (! items.length) {
        return;
    }

    onAnyScroll(() => {
        items.forEach((el) => {
            const frame = el.parentElement.getBoundingClientRect();

            if (frame.bottom < 0 || frame.top > window.innerHeight) {
                return;
            }

            // 0 when the frame's top is at the top of the viewport.
            const shift = Math.max(Math.min(-frame.top * 0.25, frame.height * 0.1), -frame.height * 0.1);
            el.style.transform = `translate3d(0, ${shift}px, 0)`;
        });
    });
}

function initMotion() {
    const root = document.documentElement;
    window.__motionBooted = true;

    initReadProgress();

    if (! root.classList.contains('motion-ok') || ! ('IntersectionObserver' in window)) {
        root.classList.remove('motion-ok');
        return;
    }

    initParallax();

    const observer = new IntersectionObserver((entries) => {
        let index = 0;

        entries.forEach((entry) => {
            if (! entry.isIntersecting) {
                return;
            }

            const el = entry.target;
            const delay = Math.min(index * STAGGER_MS, MAX_STAGGER_MS);
            el.style.setProperty('--reveal-delay', `${delay}ms`);
            index++;
            el.classList.add('is-revealed');
            setTimeout(() => el.classList.add('reveal-done'), delay + 750);
            el.querySelectorAll('[data-countup]').forEach(countUp);
            observer.unobserve(el);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('[data-motion] [data-reveal]').forEach((el) => observer.observe(el));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotion);
} else {
    initMotion();
}
