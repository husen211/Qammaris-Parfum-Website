// Height and opacity follow anshuman008's 21st FAQ Chat Accordion (demo 1517).
// Keep native details as the no-JS fallback, enhance only after the API is available.
if (typeof Element.prototype.animate === 'function') {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const controllers = [];

    document.querySelectorAll('.q-faq').forEach((group) => {
        const items = [];
        group.querySelectorAll('.q-faq-item').forEach((details) => {
            const summary = details.querySelector('.q-faq-question');
            const panel = details.querySelector('.q-faq-panel');
            if (!summary || !panel) return;

            let expanded = details.open;
            let animation;
            const sync = () => {
                details.dataset.faqExpanded = String(expanded);
                summary.setAttribute('aria-expanded', String(expanded));
                panel.inert = !expanded;
                panel.setAttribute('aria-hidden', String(!expanded));
            };
            const settle = () => {
                animation?.cancel();
                animation = undefined;
                details.open = expanded;
            };
            const setExpanded = (next) => {
                if (next === expanded) return;
                const fromHeight = details.open ? panel.getBoundingClientRect().height : 0;
                const fromOpacity = details.open ? Number.parseFloat(getComputedStyle(panel).opacity) : 0;
                expanded = next;
                animation?.cancel();
                animation = undefined;
                sync();

                if (reducedMotion.matches) {
                    details.open = expanded;
                    return;
                }

                // Native exclusivity is removed only for enhanced items: it would
                // hide the previous answer before its closing animation can run.
                details.open = true;
                const toHeight = expanded ? panel.scrollHeight : 0;
                const current = panel.animate([
                    {height: `${fromHeight}px`, opacity: fromOpacity},
                    {height: `${toHeight}px`, opacity: expanded ? 1 : 0},
                ], {duration: 400, easing: 'cubic-bezier(.42, 0, .58, 1)', fill: 'both'});
                animation = current;
                current.onfinish = () => {
                    if (animation === current) settle();
                };
            };
            const controller = {setExpanded, settle};
            items.push(controller);
            controllers.push(controller);
            details.removeAttribute('name');
            sync();
            summary.addEventListener('click', (event) => {
                event.preventDefault();
                const next = !expanded;
                if (next) items.forEach((item) => { if (item !== controller) item.setExpanded(false); });
                setExpanded(next);
            });
        });
    });

    // Finish to natural height if motion preferences/viewport change or BFCache saves the page.
    const settleAll = () => controllers.forEach((controller) => controller.settle());
    reducedMotion.addEventListener('change', settleAll);
    window.addEventListener('resize', settleAll, {passive: true});
    window.addEventListener('pagehide', settleAll);
}
