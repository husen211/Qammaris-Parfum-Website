// ORD-13: shared by the checkout map and the order-link map (ORD-20).
/** Accessible search-as-you-type list (ARIA combobox): arrow keys, Enter, Escape, tap; results are text only. */
export function combobox({ input, list, idPrefix, search, onChoose, onInput, failed }) {
    let items = [];
    let active = -1;
    let timer;
    let controller;
    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const highlight = (index) => {
        active = index;
        [...list.children].forEach((item, position) => item.setAttribute('aria-selected', String(position === index)));
        if (index >= 0) {
            input.setAttribute('aria-activedescendant', `${idPrefix}-${index}`);
            list.children[index]?.scrollIntoView({ block: 'nearest' });
        }
    };
    const choose = (index) => {
        const item = items[index];
        if (!item) return;
        input.value = item.label;
        close();
        onChoose(item);
    };
    const show = (found, note) => {
        items = found;
        list.replaceChildren(...(found.length ? found.map((entry, index) => {
            const item = document.createElement('li');
            item.id = `${idPrefix}-${index}`;
            item.setAttribute('role', 'option');
            item.className = 'min-h-12 cursor-pointer border-b border-gray-100 px-3 py-2.5 text-base leading-6 text-brand-black last:border-b-0 aria-selected:bg-[#FAF8F3]';
            item.append(Object.assign(document.createElement('span'), { className: 'block', textContent: entry.label }));
            if (entry.detail) item.append(Object.assign(document.createElement('span'), { className: 'block text-sm leading-5 text-gray-600', textContent: entry.detail }));
            item.addEventListener('pointerdown', (event) => event.preventDefault());
            item.addEventListener('click', () => choose(index));

            return item;
        }) : [Object.assign(document.createElement('li'), { className: 'px-3 py-3 text-sm leading-6 text-gray-600', textContent: note })]));
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(-1);
    };
    // ORD-17: "Mencari…" shows at once; the answer follows after a short pause in typing.
    const searching = () => {
        items = [];
        list.replaceChildren(Object.assign(document.createElement('li'), { className: 'px-3 py-3 text-sm leading-6 text-gray-600', textContent: 'Mencari…' }));
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };
    let pendingEnter = false;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        pendingEnter = false;
        onInput?.();
        const query = input.value.trim();
        if (query.length < 3) {
            close();

            return;
        }
        searching();
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            try {
                const result = await search(query, controller.signal);
                show(result.items, result.note);
                if (result.items.length) highlight(0);
                if (pendingEnter && result.items.length) choose(0);
                pendingEnter = false;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    close();
                    failed?.();
                }
            }
        }, 250);
    });
    // Tapping the box again shows the last results instead of making the customer type again.
    input.addEventListener('focus', () => {
        if (items.length && input.value.trim().length >= 3) show(items, '');
    });
    input.addEventListener('keydown', (event) => {
        // The keyboard's Search key never sends the checkout form: it picks the highlighted (first) result,
        // or the first one as soon as the search answers.
        if (event.key === 'Enter') {
            event.preventDefault();
            if (items.length) choose(Math.max(active, 0));
            else if (input.value.trim().length >= 3) pendingEnter = true;

            return;
        }
        if (list.hidden || !items.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active + (event.key === 'ArrowDown' ? 1 : items.length - 1) + (active < 0 && event.key === 'ArrowUp' ? 1 : 0)) % items.length);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    });
    input.addEventListener('blur', () => setTimeout(close, 150));
}
