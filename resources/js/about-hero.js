// Native port of ravikatiyar162/hero-section-2's nested Framer Motion variants.
const hero = document.querySelector('[data-about-split-hero]');
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
if (hero && !reducedMotion.matches && typeof hero.animate === 'function') {
    const animations = [];
    const tween = (element, keyframes, duration, delay = 0, easing = 'cubic-bezier(0,0,.58,1)') => {
        animations.push(element.animate(keyframes, { duration, delay, easing, fill: 'backwards' }));
    };
    tween(hero, [{ opacity: 0 }, { opacity: 1 }], 300);
    tween(hero.querySelector('[data-split-main]'), [{ opacity: 0 }, { opacity: 1 }], 300, 350);
    const delays = { brand: 200, title: 550, rule: 700, subtitle: 850, cta: 1000, contacts: 500 };
    hero.querySelectorAll('[data-split-item]').forEach((element) => {
        tween(element, [{ opacity: 0, transform: 'translateY(20px)' }, { opacity: 1, transform: 'none' }], 500, delays[element.dataset.splitItem]);
    });
    // Framer's circOut = sqrt(1-(t-1)^2); sample the exact curve for native clip-path.
    const clipFrames = Array.from({ length: 121 }, (_, i) => {
        const t = i / 120;
        const progress = Math.sqrt(1 - (t - 1) ** 2);
        return { offset: t, clipPath: 'polygon(' + (100 - 75 * progress) + '% 0,100% 0,100% 100%,' + (100 - 100 * progress) + '% 100%)' };
    });
    tween(hero.querySelector('[data-split-photo]'), clipFrames, 1200, 0, 'linear');
    const settle = () => animations.forEach((animation) => animation.cancel());
    reducedMotion.addEventListener('change', settle, { once: true });
    window.addEventListener('pagehide', settle, { once: true });
    Promise.all(animations.map((animation) => animation.finished.catch(() => {}))).then(() => {
        reducedMotion.removeEventListener('change', settle);
        window.removeEventListener('pagehide', settle);
    });
}
