/*
 * UX-02: Distributor Resmi carousel. Shows a row of brands, waits, then moves exactly one brand and waits again.
 * Loops with clones of the first visible brands. Never runs with reduced motion, while hovered/focused,
 * off screen, or in a hidden tab. Without JS the row simply scrolls.
 */
export const PAUSE_MS = 3000;
const MOVE_MS = 700;

/** Index after one step; reaching `count` means "showing the clones", which resets to 0 after the move. */
export function nextStep(index, count) {
    return count > 0 ? (index + 1) % (count + 1) : 0;
}

function start(root) {
    const track = root.querySelector('[data-brand-track]');
    const items = [...(track?.children ?? [])];
    if (!track || items.length < 2 || matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const visible = () => Math.max(1, Math.round(root.clientWidth / items[0].getBoundingClientRect().width));
    if (items.length <= visible()) {
        return;
    }
    items.slice(0, visible() + 1).forEach((item) => {
        const clone = item.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.querySelectorAll('img').forEach((image) => { image.alt = ''; });
        track.append(clone);
    });
    root.classList.add('is-running');

    let index = 0;
    let timer = null;
    let held = false;
    let onScreen = true;
    const place = (animate) => {
        track.style.transition = animate ? '' : 'none';
        track.style.transform = `translateX(${-track.children[index].offsetLeft}px)`;
    };
    const schedule = () => {
        clearTimeout(timer);
        if (!held && onScreen && !document.hidden) {
            timer = setTimeout(step, PAUSE_MS);
        }
    };
    function step() {
        index = nextStep(index, items.length);
        place(true);
        if (index === items.length) {
            // The clones look exactly like the first brands: jump back without animation once the move ends.
            setTimeout(() => { index = 0; place(false); schedule(); }, MOVE_MS);
            return;
        }
        schedule();
    }

    const hold = (value) => () => { held = value; schedule(); };
    root.addEventListener('pointerenter', hold(true));
    root.addEventListener('pointerleave', hold(false));
    root.addEventListener('focusin', hold(true));
    root.addEventListener('focusout', hold(false));
    document.addEventListener('visibilitychange', schedule);
    window.addEventListener('resize', () => place(false));
    new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting; schedule(); }).observe(root);
    place(false);
    schedule();
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-brand-carousel]').forEach(start);
}
