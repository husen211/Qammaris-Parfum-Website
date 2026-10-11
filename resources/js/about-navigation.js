// Adapted from ddoemonn/ScrollSpy23554 and cnippet-dev/ScrollProgress18715.
// Keep native fragment/history links; never scroll the page when centering the active chip.
const nav = document.querySelector('[data-about-spy]');
if (nav) {
    const links = [...nav.querySelectorAll('a[href^="#"]')];
    const sections = links.map((link) => document.getElementById(link.hash.slice(1)));
    const indicator = nav.querySelector('.about-nav-indicator');
    const progress = document.querySelector('[data-about-progress]');
    let frame = 0;
    let active;
    function measure() {
        frame = 0;
        const max = Math.max(0, document.documentElement.scrollHeight - innerHeight);
        const ratio = max ? Math.min(1, Math.max(0, scrollY / max)) : 1;
        const offset = nav.parentElement.getBoundingClientRect().bottom + 20;
        const line = offset + ratio * Math.max(0, innerHeight - offset - 1);
        let selected = 0;
        sections.forEach((section, index) => { if (section?.getBoundingClientRect().top <= line + 1) selected = index; });
        if (scrollY + innerHeight >= document.documentElement.scrollHeight - 2) selected = links.length - 1;
        if (progress) progress.style.transform = `scaleX(${ratio})`;
        links.forEach((link, index) => {
            if (index === selected) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        const link = links[selected];
        indicator.hidden = false;
        indicator.style.width = `${link.offsetWidth}px`;
        indicator.style.transform = `translateX(${link.offsetLeft}px)`;
        if (selected !== active) {
            active = selected;
            const left = link.offsetLeft;
            if (left < nav.scrollLeft || left + link.offsetWidth > nav.scrollLeft + nav.clientWidth) {
                nav.scrollTo({left: Math.max(0, left - (nav.clientWidth - link.offsetWidth) / 2), behavior: 'auto'});
            }
        }
    }
    const sync = () => { if (!frame) frame = requestAnimationFrame(measure); };
    window.addEventListener('scroll', sync, {passive: true});
    window.addEventListener('resize', sync);
    window.addEventListener('pageshow', sync);
    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(sync);
        observer.observe(document.documentElement);
        sections.forEach((section) => { if (section) observer.observe(section); });
    }
    sync();
}
