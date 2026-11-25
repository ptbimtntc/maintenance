// Employees "Gallery" view: scroll-driven 3D card stack.
//
// The section is tall (one --step of scroll per employee) and its stage is
// sticky, so scrolling moves a virtual "current" index through the cards.
// Upcoming cards wait in a diagonal stack behind (back-left), the current
// card is lifted in the middle, and passed cards slide forward-right and fade
// out. The index eases towards the scroll position for smooth motion, and a
// short staggered fly-in plays on load.
//
// Only cards near the current index are updated, shown, or have their photo
// requested. Reduced motion (or a single employee) keeps the plain grid.

const AHEAD = 1.1; // passed cards still visible while they fade out
const EASE = 0.14; // how quickly the stack follows the scroll position
const INTRO_MS = 900;
const INTRO_STAGGER_MS = 75;

const clamp = (v, min, max) => Math.min(Math.max(v, min), max);
const easeOut = (t) => 1 - (1 - t) ** 3;
const pad = (n) => String(n).padStart(2, '0');

function loadPhoto(card) {
    const img = card.querySelector('img[data-src]');

    if (img) {
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
    }
}

function initGallery(section) {
    const cards = [...section.querySelectorAll('[data-gallery-card]')];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || cards.length < 2) {
        cards.forEach((card) => {
            const img = card.querySelector('img[data-src]');
            if (img) img.loading = 'lazy';
            loadPhoto(card);
        });
        return;
    }

    section.classList.add('is-3d');

    const scroller = section.closest('main') ?? document.scrollingElement;
    const viewport = section.querySelector('.employee-gallery__viewport');
    const hint = section.querySelector('.employee-gallery__hint');
    const caption = {
        name: section.querySelector('[data-gallery-name]'),
        nik: section.querySelector('[data-gallery-nik]'),
        position: section.querySelector('[data-gallery-position]'),
        current: section.querySelector('[data-gallery-current]'),
        bar: section.querySelector('[data-gallery-bar]'),
    };

    const last = cards.length - 1;
    let step = 120;
    let cardW = 200;
    let stickyTop = 16;
    let behind = 6; // upcoming cards visible in the stack
    let spread = 0.5; // horizontal gap between stacked cards, in card widths
    let offsetX = 0; // shifts the whole stack so it sits centred
    let current = 0;
    let target = 0;
    let shownIndex = -1;
    let frame = null;
    const introStart = performance.now();
    const visible = new Set();

    function measure() {
        const style = getComputedStyle(section);
        step = parseFloat(style.getPropertyValue('--step')) || 120;
        cardW = cards[0].offsetWidth || 200;
        stickyTop = parseFloat(getComputedStyle(viewport).top) || 0;

        // Fit the stack to the stage: roomy on desktop, tighter on phones.
        const stageW = viewport.clientWidth || 1000;
        behind = stageW < 640 ? 4 : 6;
        spread = clamp((stageW * 0.8 - cardW) / ((behind - 1) * cardW), 0.16, 0.55);
        offsetX = (behind - 1) * spread * cardW * 0.5;
    }

    function readTarget() {
        const scrollerTop = scroller === document.scrollingElement ? 0 : scroller.getBoundingClientRect().top;
        const scrolled = scrollerTop + stickyTop - section.getBoundingClientRect().top;
        target = clamp(scrolled / step, 0, last);
    }

    function place(card, d, introAge) {
        const lift = Math.max(0, 1 - Math.abs(d));
        let x;
        let y;
        let z;
        let opacity = 1;
        let shade = 0;

        if (d >= 0) {
            // Upcoming: recede diagonally to the back-left.
            x = -d * cardW * spread;
            y = -d * cardW * 0.11;
            z = -d * cardW * 0.55;
            shade = Math.min(0.55, d * 0.1);
            opacity = d > behind - 1 ? clamp(behind - d, 0, 1) : 1;
        } else {
            // Passed: slide forward-right and fade out.
            x = -d * cardW * 0.95;
            y = -d * cardW * 0.18;
            z = -d * cardW * 0.6;
            opacity = clamp(1 + d / AHEAD, 0, 1);
        }

        x += offsetX;
        y -= lift * cardW * 0.14;
        z += lift * cardW * 0.18;

        // Staggered fly-in on load, nearest card first.
        if (introAge !== null) {
            const t = easeOut(clamp((introAge - Math.max(d, 0) * INTRO_STAGGER_MS) / INTRO_MS, 0, 1));
            y += (1 - t) * cardW * 1.4;
            z -= (1 - t) * cardW * 0.8;
            opacity *= t;
        }

        card.style.transform = `translate3d(${x}px, ${y}px, ${z}px) rotateY(28deg) rotateX(4deg) rotateZ(-2deg)`;
        card.style.opacity = opacity;
        card.style.zIndex = String(1000 - Math.round(d * 10));
        card.firstElementChild.style.setProperty('--shade', shade.toFixed(3));
    }

    function render(now) {
        frame = null;
        current += (target - current) * EASE;

        if (Math.abs(target - current) < 0.001) {
            current = target;
        }

        const introAge = now - introStart;
        const intro = introAge < INTRO_MS + behind * INTRO_STAGGER_MS ? introAge : null;
        const from = Math.max(0, Math.floor(current - AHEAD));
        const to = Math.min(last, Math.ceil(current + behind));

        visible.forEach((i) => {
            if (i < from || i > to) {
                cards[i].style.visibility = 'hidden';
                visible.delete(i);
            }
        });

        for (let i = from; i <= to; i++) {
            const card = cards[i];

            if (! visible.has(i)) {
                visible.add(i);
                card.style.visibility = 'visible';
                loadPhoto(card);
            }

            place(card, i - current, intro);
        }

        // Pre-load the next few photos so they are ready when they arrive.
        for (let i = to + 1; i <= Math.min(last, to + 3); i++) {
            loadPhoto(cards[i]);
        }

        updateCaption();

        if (current !== target || intro !== null) {
            frame = requestAnimationFrame(render);
        }
    }

    function updateCaption() {
        const index = Math.round(current);
        caption.bar.style.transform = `scaleX(${last ? current / last : 1})`;
        hint?.classList.toggle('is-hidden', current > 0.3);

        if (index === shownIndex) {
            return;
        }

        shownIndex = index;
        const card = cards[index].dataset;
        caption.name.textContent = card.name;
        caption.nik.textContent = card.nik;
        caption.position.textContent = card.position ? `· ${card.position}` : '';
        caption.current.textContent = pad(index + 1);
    }

    function schedule() {
        readTarget();
        frame ??= requestAnimationFrame(render);
    }

    // Tabbing to a card scrolls it to the front of the stack.
    section.addEventListener('focusin', (event) => {
        const card = event.target.closest?.('[data-gallery-card]');

        if (! card) {
            return;
        }

        const scrollerTop = scroller === document.scrollingElement ? 0 : scroller.getBoundingClientRect().top;
        const sectionTop = section.getBoundingClientRect().top - scrollerTop - stickyTop + scroller.scrollTop;
        scroller.scrollTo({ top: sectionTop + Number(card.dataset.index) * step, behavior: 'smooth' });
    });

    (scroller === document.scrollingElement ? window : scroller).addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', () => {
        measure();
        schedule();
    }, { passive: true });

    measure();
    readTarget();
    current = target;
    frame = requestAnimationFrame(render);
}

document.querySelectorAll('#employee-gallery').forEach(initGallery);
