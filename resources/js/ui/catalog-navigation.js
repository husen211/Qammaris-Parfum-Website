import { catalogLocationKey, matchingPosition } from './catalog-navigation-state';

const storageKey = 'qammaris.catalog-return.v1';
const catalog = document.querySelector('[data-catalog-discovery]');
const returnLink = document.querySelector('[data-catalog-return]');

const readPosition = () => {
    try {
        return JSON.parse(sessionStorage.getItem(storageKey));
    } catch {
        return null;
    }
};

const writePosition = (record) => {
    try {
        sessionStorage.setItem(storageKey, JSON.stringify(record));
    } catch {
        // Browser storage can be unavailable; ordinary catalog links still work.
    }
};

const clearPosition = () => {
    try {
        sessionStorage.removeItem(storageKey);
    } catch {
        // Restoration is progressive enhancement, not a navigation prerequisite.
    }
};

const sameTabClick = (event) => event.button === 0
    && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;

if (catalog) {
    const catalogUrl = catalog.dataset.catalogUrl;
    const sidebar = document.querySelector('.catalog-sidebar');
    let sizingFrame = null;
    const sizeSidebar = () => {
        if (!sidebar || !window.matchMedia('(min-width: 1024px)').matches) return;
        const height = Math.max(44, window.innerHeight - sidebar.getBoundingClientRect().top - 20);
        sidebar.style.setProperty('--catalog-sidebar-height', `${height}px`);
    };
    const queueSidebarSize = () => {
        if (sizingFrame !== null) return;
        sizingFrame = requestAnimationFrame(() => {
            sizeSidebar();
            sizingFrame = null;
        });
    };
    sizeSidebar();
    window.addEventListener('resize', queueSidebarSize);
    window.addEventListener('scroll', queueSidebarSize, { passive: true });

    catalog.addEventListener('click', (event) => {
        const link = event.target.closest('[data-catalog-product]');
        if (!link || !sameTabClick(event)) return;

        writePosition({
            url: catalogLocationKey(catalogUrl),
            productId: link.dataset.catalogProduct,
            y: window.scrollY,
            sidebarY: sidebar?.scrollTop ?? 0,
            brandY: sidebar?.querySelector('[data-catalog-brand-list]')?.scrollTop ?? 0,
            keyboard: event.detail === 0,
            pending: false,
        });
    });

    document.querySelectorAll('[data-catalog-form]').forEach((form) => {
        form.addEventListener('submit', clearPosition);
    });

    window.addEventListener('pageshow', (event) => {
        const record = matchingPosition(readPosition(), catalogUrl);
        const historyReturn = event.persisted
            || performance.getEntriesByType('navigation')[0]?.type === 'back_forward';
        if (!record || (!record.pending && !historyReturn)) return;

        clearPosition();
        // Cards reserve image geometry; two frames let the returned layout settle.
        requestAnimationFrame(() => requestAnimationFrame(() => {
            const link = [...catalog.querySelectorAll('[data-catalog-product]')]
                .find((candidate) => candidate.dataset.catalogProduct === record.productId);
            // Pointer returns restore position without drawing a keyboard focus ring.
            if (record.keyboard === true) link?.focus({ preventScroll: true });
            else if (link && document.activeElement === link) link.blur();
            if (sidebar) sidebar.scrollTop = record.sidebarY;
            const brands = sidebar?.querySelector('[data-catalog-brand-list]');
            if (brands && Number.isFinite(record.brandY)) brands.scrollTop = record.brandY;
            window.scrollTo({ top: record.y, behavior: 'instant' });
            // A pointer can still sit over the returned card: keep it neutral until it leaves.
            if (record.keyboard !== true) link?.setAttribute('data-catalog-activated', '');
        }));
    });
}

returnLink?.addEventListener('click', (event) => {
    if (!sameTabClick(event)) return;
    const record = matchingPosition(readPosition(), returnLink.href);
    if (record) {
        writePosition({ ...record, keyboard: event.detail === 0, pending: true });
    }
});
