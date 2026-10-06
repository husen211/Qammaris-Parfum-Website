const revealItems = [...document.querySelectorAll('[data-reveal]')].filter((item) =>
    !item.matches('a[href], button, input, select, textarea, nav, [role="menu"], [data-product-card]')
    && !item.closest('a[href], button, nav, [role="menu"], [data-product-card], .catalog-grid')
    && !item.querySelector('a[href], button, input, select, textarea'),
);

if (revealItems.length) {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver(
            (entries, entryObserver) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-visible');
                    entryObserver.unobserve(entry.target);
                });
            },
            { threshold: 0.2, rootMargin: '0px 0px -10% 0px' }
        );

        revealItems.forEach((item) => {
            const delay = item.getAttribute('data-reveal-delay');

            if (delay) {
                item.style.transitionDelay = `${delay}ms`;
            }

            item.classList.add('reveal');
            observer.observe(item);
        });
    }
}
