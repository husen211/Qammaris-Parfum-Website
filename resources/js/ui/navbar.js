import { startCatalogLinkFeedback } from './catalog-link-feedback';

const navCard = document.getElementById('navbar-card');

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0
        || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
        || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
    const destination = new URL(link.href);
    if (destination.origin !== window.location.origin
        || (destination.pathname === window.location.pathname && destination.search === window.location.search)) return;

    link.setAttribute('data-navigation-pending', '');
    startCatalogLinkFeedback(link);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-navigation-pending]').forEach((link) => {
        link.removeAttribute('data-navigation-pending');
    });
});

if (navCard) {
    const toggleNavbar = () => {
        const scrolled = window.scrollY > 12;

        navCard.classList.toggle('mt-4', !scrolled);
        navCard.classList.toggle('mt-2', scrolled);
        navCard.classList.toggle('bg-white/70', !scrolled);
        navCard.classList.toggle('bg-white', scrolled);
        navCard.classList.toggle('border-white/60', !scrolled);
        navCard.classList.toggle('border-gray-100', scrolled);
        navCard.classList.toggle('shadow-[0_8px_24px_rgba(0,0,0,0.06)]', !scrolled);
        navCard.classList.toggle('shadow-md', scrolled);
    };

    toggleNavbar();
    window.addEventListener('scroll', toggleNavbar, { passive: true });
}
