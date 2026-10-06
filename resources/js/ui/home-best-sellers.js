const section = document.querySelector('[data-home-best-sellers]');

if (section) {
    const rail = section.querySelector('[data-best-seller-rail]');
    const previous = section.querySelector('[data-best-seller-previous]');
    const next = section.querySelector('[data-best-seller-next]');

    if (rail && previous && next) {
        const updateControls = () => {
            previous.disabled = rail.scrollLeft <= 1;
            next.disabled = rail.scrollLeft >= rail.scrollWidth - rail.clientWidth - 1;
        };
        const move = (direction) => {
            const card = rail.firstElementChild;
            const gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
            rail.scrollBy({
                left: direction * (card.getBoundingClientRect().width + gap),
                behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        };

        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        rail.addEventListener('scroll', updateControls, { passive: true });
        window.addEventListener('resize', updateControls);
        window.addEventListener('pageshow', updateControls);
        updateControls();
    }
}
