// Adapted from jahed/CinematicHero (21st demo11494): GSAP card expansion,
// perspective entrance and pullback. Bound the pin; leave real actions/readable copy visible.
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);
const hero = document.querySelector('[data-cinematic-hero]');
let media;
function enhanceHero() {
    if (!hero || media) return;
    media = gsap.matchMedia();
    media.add('(min-width: 1100px) and (min-height: 850px) and (prefers-reduced-motion: no-preference)', () => {
        const stage = hero.querySelector('[data-hero-stage]');
        const card = hero.querySelector('[data-hero-card]');
        const photo = hero.querySelector('[data-hero-photo]');
        if (stage.offsetHeight > innerHeight - 125) return;
        const timeline = gsap.timeline({scrollTrigger: {
            trigger: stage, start: 'top 110px', end: '+=480', pin: true,
            scrub: .5, anticipatePin: 1, invalidateOnRefresh: true,
        }});
        timeline.fromTo(card, {scale: .94, borderRadius: 28}, {scale: 1, borderRadius: 10, duration: 1, ease: 'power2.inOut'}, 0)
            .fromTo(photo, {y: 22, rotationX: 8, rotationY: -6, scale: .94}, {y: 0, rotationX: 0, rotationY: 0, scale: 1, duration: 1, ease: 'power3.out'}, 0)
            .to(card, {scale: .96, borderRadius: 28, duration: .6, ease: 'power2.inOut'}, 1.2);
    }, hero);
}
enhanceHero();
window.addEventListener('pagehide', () => { media?.revert(); media = null; });
window.addEventListener('pageshow', (event) => { if (event.persisted) enhanceHero(); });
