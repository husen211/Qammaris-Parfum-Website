const revealItems = document.querySelectorAll('[data-reveal]');

if (revealItems.length) {
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
