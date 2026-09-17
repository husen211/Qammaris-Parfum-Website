const drawer = document.getElementById('cartDrawer');

if (drawer) {
    const cartUrl = drawer.dataset.cartUrl;
    const productsUrl = drawer.dataset.productsUrl || '/products';
    const container = document.getElementById('drawerCartItems');
    const estimateEl = document.getElementById('drawerSubtotal');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;',
    }[character]));

    const setEstimate = (value) => {
        if (estimateEl) {
            estimateEl.innerText = value;
        }
    };

    const renderEmpty = () => {
        if (!container) return;

        container.innerHTML = `
            <div class="flex h-full flex-col items-center justify-center text-center">
                <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full border border-gray-200 text-2xl text-gray-300" aria-hidden="true">?</div>
                <h4 class="font-mayluxa text-xl">Daftar inquiry masih kosong</h4>
                <p class="mt-2 max-w-64 text-sm leading-6 text-gray-500">Tambahkan parfum yang ingin Anda tanyakan kepada admin.</p>
                <a href="${escapeHtml(productsUrl)}" class="mt-5 inline-flex min-h-11 items-center border-b border-brand-black text-xs font-semibold uppercase tracking-widest hover:border-brand-gold hover:text-brand-gold">Lihat katalog</a>
            </div>
        `;
        setEstimate('Rp 0');
    };

    const renderError = (message, cartPageUrl) => {
        if (!container) return;

        container.innerHTML = `
            <div class="flex h-full flex-col items-center justify-center text-center" role="alert">
                <h4 class="font-mayluxa text-xl">Daftar perlu ditinjau</h4>
                <p class="mt-2 max-w-72 text-sm leading-6 text-gray-500">${escapeHtml(message)}</p>
                <button type="button" data-retry-inquiry class="mt-5 min-h-11 border border-brand-black px-5 text-xs font-semibold uppercase tracking-widest hover:bg-brand-black hover:text-white">Coba lagi</button>
                <a href="${escapeHtml(cartPageUrl)}" class="mt-3 inline-flex min-h-11 items-center text-xs font-semibold uppercase tracking-widest underline underline-offset-4">Buka daftar</a>
            </div>
        `;
        setEstimate('—');
        container.querySelector('[data-retry-inquiry]')?.addEventListener('click', fetchInquiryContent);
    };

    const renderItems = (items) => {
        if (!container) return;

        const html = items.map((item) => `
            <article class="flex gap-4 border-b border-gray-200 pb-5 last:border-b-0" data-inquiry-item>
                <a href="${escapeHtml(item.product_url)}" class="h-24 w-20 shrink-0 overflow-hidden bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">
                    <img src="${escapeHtml(item.image)}" alt="" width="80" height="96" class="h-full w-full object-contain" loading="lazy" decoding="async">
                </a>
                <div class="min-w-0 flex-1 py-0.5">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">${escapeHtml(item.brand_name)}</p>
                    <h4 class="mt-1 font-mayluxa text-base leading-tight text-brand-black">
                        <a href="${escapeHtml(item.product_url)}" class="hover:text-brand-gold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black">${escapeHtml(item.product_name)}</a>
                    </h4>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span>${escapeHtml(item.volume)} ml</span>
                        <span>${escapeHtml(item.availability_label)}</span>
                    </div>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <p class="text-sm font-medium text-brand-black">${escapeHtml(item.formatted_price)}</p>
                        <p class="text-xs text-gray-500">Jumlah ${escapeHtml(item.quantity)}</p>
                    </div>
                </div>
            </article>
        `).join('');

        container.innerHTML = `<div class="space-y-5">${html}</div>`;
    };

    async function fetchInquiryContent() {
        if (!cartUrl || !container) return;

        container.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(cartUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            if (!response.ok) {
                renderError(data.message || 'Daftar inquiry belum dapat dimuat.', data.cart_url || '/cart');
                return;
            }

            if (Array.isArray(data.items) && data.items.length > 0) {
                renderItems(data.items);
                setEstimate(data.formatted_total ?? 'Rp 0');
                return;
            }

            renderEmpty();
        } catch (error) {
            renderError('Koneksi bermasalah. Periksa jaringan lalu coba lagi.', '/cart');
        } finally {
            container.removeAttribute('aria-busy');
        }
    }

    drawer.addEventListener('toggle', () => {
        if (drawer.open) {
            fetchInquiryContent();
        }
    });

    drawer.addEventListener('close', () => {
        document.getElementById('cart-drawer-trigger')?.focus();
    });
}
