let resetTimer;
let pressTimer;
let pendingLink;

export function clearCatalogLinkFeedback() {
    window.clearTimeout(resetTimer);
    window.clearTimeout(pressTimer);
    document.querySelectorAll('[data-catalog-pressed]').forEach((link) => {
        link.removeAttribute('data-catalog-pressed');
    });
    if (pendingLink) {
        pendingLink.removeAttribute('data-navigation-pending');
        pendingLink.removeAttribute('aria-busy');
        pendingLink = null;
    }
    const status = document.querySelector('[data-catalog-navigation-status]');
    if (status) status.hidden = true;
}

export function startCatalogLinkFeedback(link) {
    if (!link.matches('[data-catalog-product], [data-catalog-return]')) return;
    clearCatalogLinkFeedback();
    pendingLink = link;
    link.setAttribute('data-navigation-pending', '');
    link.setAttribute('data-catalog-activated', '');
    link.setAttribute('data-catalog-pressed', '');
    link.setAttribute('aria-busy', 'true');
    const status = document.querySelector('[data-catalog-navigation-status]');
    if (status) status.hidden = false;
    pressTimer = window.setTimeout(() => link.removeAttribute('data-catalog-pressed'), 140);
    // Cancelled navigation must not leave a loading state indefinitely; links stay usable.
    resetTimer = window.setTimeout(clearCatalogLinkFeedback, 10000);
}

document.addEventListener('pointerout', (event) => {
    const link = event.target.closest('[data-catalog-activated]');
    if (link && !link.contains(event.relatedTarget)) {
        link.removeAttribute('data-catalog-activated');
    }
});

window.addEventListener('pageshow', clearCatalogLinkFeedback);
