// TOUR-01: virtual tour block. The page only shows a cover; the three.js viewer and the panoramas
// load when a visitor taps "Masuk", inside a full-screen native dialog.
document.querySelectorAll('[data-virtual-tour]').forEach(setup);

function setup(root) {
    const tour = JSON.parse(root.querySelector('[data-tour-data]').textContent);
    const enter = root.querySelector('[data-tour-enter]');
    const cover = root.querySelector('[data-tour-cover]');
    const dialog = root.querySelector('[data-tour-dialog]');
    const fallback = root.querySelector('[data-tour-fallback]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const supported = typeof dialog.showModal === 'function' && hasWebGL();

    // Slow pan of the panoramic cover while it is on screen, as a hint that the photo goes all around.
    if (!reduceMotion && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => cover.classList.toggle('is-panning', entry.isIntersecting)).observe(cover);
    }

    let viewer = null;
    let loading = null;
    const load = () => (loading ??= import('./virtual-tour/viewer.js').catch((error) => {
        loading = null;
        throw error;
    }));

    async function open() {
        if (dialog.open) return;
        if (!supported) {
            fallback.hidden = false;
            fallback.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            return;
        }
        root.classList.add('is-entering');
        document.documentElement.classList.add('vt-lock');
        dialog.showModal();
        // Phone back button leaves the tour instead of the page.
        history.pushState({ virtualTour: true }, '');
        try {
            const { createViewer } = await load();
            if (dialog.open) viewer = createViewer(dialog, tour);
        } catch {
            dialog.querySelector('[data-tour-status]').hidden = true;
            const error = dialog.querySelector('[data-tour-error]');
            error.hidden = false;
            dialog.querySelector('[data-tour-retry]').addEventListener('click', () => {
                error.hidden = true;
                dialog.close();
                open();
            }, { once: true });
        }
    }

    // A mouse over "Masuk" warms up the viewer code and the walk-in photo before the click.
    enter.addEventListener('pointerenter', () => {
        if (!supported) return;
        load().catch(() => {});
        new Image().src = dialog.querySelector('[data-tour-intro]').src;
    }, { once: true });
    enter.addEventListener('click', open);
    cover.addEventListener('click', (event) => {
        if (!event.target.closest('button')) open();
    });
    dialog.querySelector('[data-tour-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        viewer?.destroy();
        viewer = null;
        document.documentElement.classList.remove('vt-lock');
        root.classList.remove('is-entering');
        if (history.state?.virtualTour) history.back();
        enter.focus({ preventScroll: true });
    });
    window.addEventListener('popstate', () => {
        if (dialog.open) dialog.close();
    });
}

function hasWebGL() {
    try {
        const canvas = document.createElement('canvas');
        return Boolean(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch {
        return false;
    }
}
