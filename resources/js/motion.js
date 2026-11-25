// Scroll-driven motion, declared in markup (see resources/css/motion.css):
//   <div data-motion>        opt-in container; only reveals inside it are animated
//   <div data-reveal>        fades/slides in the first time it scrolls into view;
//                            elements revealed together are staggered
//   <p data-countup>12.5%</p> number counts up from 0 to the text it already shows
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

function initMotion() {
    const root = document.documentElement;
    window.__motionBooted = true;

    if (! root.classList.contains('motion-ok') || ! ('IntersectionObserver' in window)) {
        root.classList.remove('motion-ok');
        return;
    }

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
