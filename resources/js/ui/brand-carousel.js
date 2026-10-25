/*
 * UI-BRANDS-01 (Owner 2026-10-25): the brand row drifts continuously to the left, about one brand every
 * SECONDS_PER_BRAND seconds, instead of pausing between steps. The brands are cloned once so the loop is
 * seamless. It holds while hovered with a mouse or focused, rests off screen, and never moves with reduced
 * motion. Without JS the row simply scrolls.
 */
export const SECONDS_PER_BRAND = 1.8;

/** Milliseconds for one full loop of `count` brands. */
export function loopDuration(count) {
    return Math.max(1, count) * SECONDS_PER_BRAND * 1000;
}

function start(root) {
    const track = root.querySelector('[data-brand-track]');
    const items = [...(track?.children ?? [])];
    if (!track || items.length < 2 || matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    const visible = Math.max(1, Math.round(root.clientWidth / items[0].getBoundingClientRect().width));
    if (items.length <= visible) {
        return;
    }
    items.forEach((item) => {
        const clone = item.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.querySelectorAll('img').forEach((image) => { image.alt = ''; });
        track.append(clone);
    });
    root.classList.add('is-running');

    let animation = null;
    let held = false;
    let onScreen = true;
    const sync = () => {
        if (held || !onScreen) animation?.pause();
        else animation?.play();
    };
    const run = () => {
        const duration = loopDuration(items.length);
        const progress = animation?.effect.getComputedTiming().progress ?? 0;
        animation?.cancel();
        // The first clone sits exactly one full set of brands to the right of the start.
        const distance = track.children[items.length].offsetLeft;
        animation = track.animate(
            [{ transform: 'translateX(0)' }, { transform: `translateX(${-distance}px)` }],
            { duration, iterations: Infinity },
        );
        animation.currentTime = progress * duration;
        sync();
    };

    const hold = (value) => () => { held = value; sync(); };
    if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
        root.addEventListener('pointerenter', hold(true));
        root.addEventListener('pointerleave', hold(false));
    }
    root.addEventListener('focusin', hold(true));
    root.addEventListener('focusout', hold(false));
    let resizeTimer = null;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(run, 150); });
    new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting; sync(); }).observe(root);
    run();
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-brand-carousel]').forEach(start);
}
