// Marks list-table scroll wrappers with .is-scrolled while their content is
// scrolled vertically, so the sticky header can cast a shadow
// (styles in resources/css/ui-polish.css).

function update(wrapper) {
    wrapper.classList.toggle('is-scrolled', wrapper.scrollTop > 0);
}

document.addEventListener('scroll', (event) => {
    const wrapper = event.target;

    if (wrapper instanceof HTMLElement && wrapper.querySelector(':scope > table.divide-y')) {
        update(wrapper);
    }
}, { capture: true, passive: true });
