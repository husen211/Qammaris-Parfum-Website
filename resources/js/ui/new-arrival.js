/*
 * HOME-NEW-ARRIVAL (Owner 2026-11-17): the "Baru Tiba" stage rotates every INTERVAL_MS with a crossfade and a slow
 * scale-in. A mouse over a list row switches the stage at once and holds it while the pointer stays in the section;
 * keyboard focus does the same. Touch swipes the stage. Reduced motion never autoplays. Without JS the stage shows
 * product 01 and the rows are plain links.
 */
export const INTERVAL_MS = 4000;
export const ZOOM_TO = 1.06;
const SWIPE_PX = 40;

export function wrapIndex(index, count) {
    return count > 0 ? ((index % count) + count) % count : 0;
}

/** Pure rotation state: the shown index, the reasons it is held, and the user's pause button. */
export function createRotation(count, { reducedMotion = false } = {}) {
    let index = 0;
    let stopped = false;
    const holds = new Set();

    return {
        get index() { return index; },
        get autoplay() { return !reducedMotion && count > 1; },
        get stopped() { return stopped; },
        get held() { return holds.size > 0; },
        get running() { return this.autoplay && !stopped && holds.size === 0; },
        show(target) { index = wrapIndex(target, count); return index; },
        next() { return this.show(index + 1); },
        previous() { return this.show(index - 1); },
        hold(reason) { holds.add(reason); },
        release(reason) { return holds.delete(reason); },
        toggle() { stopped = !stopped; return !stopped; },
    };
}

/** Horizontal swipe direction (1 next, -1 previous, 0 none) from a gesture's movement. */
export function swipeDirection(dx, dy) {
    if (Math.abs(dx) < SWIPE_PX || Math.abs(dx) <= Math.abs(dy)) return 0;
    return dx < 0 ? 1 : -1;
}

function currentScale(element) {
    const transform = getComputedStyle(element).transform;
    const match = transform && transform !== 'none' ? transform.match(/matrix\(([^,]+)/) : null;
    return match ? parseFloat(match[1]) || 1 : 1;
}

function start(root) {
    const items = [...root.querySelectorAll('[data-new-arrival-item]')];
    const layers = [...root.querySelectorAll('[data-new-arrival-layer]')];
    const stage = root.querySelector('[data-new-arrival-stage]');
    const toggle = root.querySelector('[data-new-arrival-toggle]');
    const field = (name) => root.querySelector(`[data-new-arrival-${name}]`);
    const number = field('number');
    const label = field('label');
    const brand = field('brand');
    const name = field('name');
    const size = field('size');

    root.querySelectorAll('img[data-fallback-src]').forEach((image) => {
        const fallback = () => {
            if (image.getAttribute('src') !== image.dataset.fallbackSrc) image.src = image.dataset.fallbackSrc;
        };
        image.addEventListener('error', fallback);
        if (image.complete && !image.naturalWidth) fallback();
    });
    if (!stage || items.length < 2 || layers.length !== items.length) return;

    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;
    const rotation = createRotation(items.length, { reducedMotion });
    const zooms = [];
    let progress = null;
    let onScreen = true;

    const render = () => {
        const index = rotation.index;
        const data = items[index].dataset;
        layers.forEach((layer, i) => {
            const active = i === index;
            layer.classList.toggle('opacity-100', active);
            layer.classList.toggle('opacity-0', !active);
            if (active) layer.removeAttribute('aria-hidden'); else layer.setAttribute('aria-hidden', 'true');
        });
        items.forEach((item, i) => { item.dataset.active = String(i === index); });
        stage.href = items[index].href;
        number.textContent = data.number;
        label.textContent = data.label;
        brand.textContent = data.brand;
        name.textContent = data.name;
        size.textContent = data.size;
    };

    const sync = () => {
        const play = rotation.running && onScreen && !document.hidden;
        [progress, zooms[rotation.index]].forEach((animation) => {
            if (play) animation?.play(); else animation?.pause();
        });
    };

    // The progress line is the timer: when it finishes, the next product shows.
    const restart = ({ fresh = true } = {}) => {
        progress?.cancel();
        progress = null;
        if (!rotation.autoplay) return;
        const index = rotation.index;
        const layer = layers[index];
        const from = fresh ? 1 : Math.min(currentScale(layer), ZOOM_TO);
        zooms[index]?.cancel();
        zooms[index] = layer.animate([{ transform: `scale(${from})` }, { transform: `scale(${ZOOM_TO})` }],
            { duration: INTERVAL_MS, easing: 'linear', fill: 'forwards' });
        progress = items[index].querySelector('[data-new-arrival-progress]')
            ?.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: INTERVAL_MS, easing: 'linear' }) ?? null;
        if (progress) progress.onfinish = () => go(index + 1);
        sync();
    };

    const go = (target) => {
        const previous = rotation.index;
        rotation.show(target);
        if (rotation.index !== previous) zooms[previous]?.pause();
        render();
        restart({ fresh: rotation.index !== previous });
    };

    const hold = (reason) => { rotation.hold(reason); sync(); };
    const release = (reason) => {
        if (rotation.release(reason) && !rotation.held) restart({ fresh: false });
    };

    if (finePointer) {
        root.addEventListener('pointerenter', (event) => { if (event.pointerType === 'mouse') hold('pointer'); });
        root.addEventListener('pointerleave', () => release('pointer'));
        items.forEach((item, i) => item.addEventListener('mouseenter', () => {
            hold('pointer');
            if (rotation.index !== i) go(i);
        }));
    }

    const holdsFocus = (element) => element instanceof Element
        && (element === stage || items.includes(element));
    root.addEventListener('focusin', (event) => {
        if (!holdsFocus(event.target)) return;
        hold('focus');
        const i = items.indexOf(event.target);
        if (i >= 0 && rotation.index !== i) go(i);
    });
    root.addEventListener('focusout', (event) => {
        if (!holdsFocus(event.relatedTarget)) release('focus');
    });

    let swipe = null;
    let swipedAt = 0;
    stage.addEventListener('pointerdown', (event) => {
        swipe = event.pointerType === 'mouse' ? null : { x: event.clientX, y: event.clientY };
    });
    stage.addEventListener('pointercancel', () => { swipe = null; });
    stage.addEventListener('pointerup', (event) => {
        if (!swipe) return;
        const direction = swipeDirection(event.clientX - swipe.x, event.clientY - swipe.y);
        swipe = null;
        if (!direction) return;
        swipedAt = performance.now();
        go(rotation.index + direction);
    });
    // A swipe is not a tap: the link stays where it is.
    stage.addEventListener('click', (event) => {
        if (performance.now() - swipedAt < 500) event.preventDefault();
    });

    if (toggle && rotation.autoplay) {
        const pauseIcon = toggle.querySelector('[data-new-arrival-pause-icon]');
        const playIcon = toggle.querySelector('[data-new-arrival-play-icon]');
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const playing = rotation.toggle();
            toggle.setAttribute('aria-label', playing ? 'Jeda pergantian produk' : 'Putar pergantian produk');
            pauseIcon?.classList.toggle('hidden', !playing);
            playIcon?.classList.toggle('hidden', playing);
            sync();
        });
    }

    new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting; sync(); }).observe(root);
    document.addEventListener('visibilitychange', sync);
    render();
    restart();
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-new-arrival]').forEach(start);
}
