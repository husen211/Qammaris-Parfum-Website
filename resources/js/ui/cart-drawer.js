const drawer = document.getElementById('cartDrawer');

if (drawer) {
    const cartUrl = drawer.dataset.cartUrl;
    const productsUrl = drawer.dataset.productsUrl || '/products';
    const productBaseUrl = productsUrl.replace(/\/$/, '');
    const container = document.getElementById('drawerCartItems');
    const subtotalEl = document.getElementById('drawerSubtotal');

    const setSubtotal = (value) => {
        if (subtotalEl) {
            subtotalEl.innerText = value;
        }
    };

    const renderEmpty = () => {
        if (!container) {
            return;
        }

        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center">
                <span class="text-4xl mb-4">:-)</span>
                <h4 class="font-mayluxa text-xl mb-2">Keranjang masih kosong</h4>
                <a href="${productsUrl}" class="text-xs border-b border-brand-black pb-1 hover:text-brand-gold hover:border-brand-gold uppercase tracking-widest mt-2">Mulai Belanja</a>
            </div>
        `;
        setSubtotal('Rp 0');
    };

    const renderItems = (items) => {
        if (!container) {
            return;
        }

        let html = '<div class="space-y-6">';
        items.forEach((item) => {
            html += `
                <div class="flex gap-4 group">
                    <div class="w-20 h-24 bg-white flex-shrink-0 border border-gray-100">
                        <img src="${item.image}" class="w-full h-full object-cover mix-blend-multiply">
                    </div>
                    <div class="flex-1 flex flex-col justify-between py-1">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400 mb-1">${item.brand_name}</p>
                            <h4 class="font-mayluxa text-sm text-brand-black leading-tight mb-1">
                                <a href="${productBaseUrl}/${item.slug}" class="hover:text-brand-gold transition-colors">${item.product_name}</a>
                            </h4>
                            <p class="text-xs text-gray-500">${item.volume}ml</p>
                        </div>
                        <div class="flex justify-between items-end">
                            <p class="text-sm font-medium text-brand-black">${item.formatted_price}</p>
                            <p class="text-xs text-gray-400">x${item.quantity}</p>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    };

    const fetchCartContent = async () => {
        if (!cartUrl || !container) {
            return;
        }

        try {
            const response = await fetch(cartUrl);
            const data = await response.json();

            if (Array.isArray(data.items) && data.items.length > 0) {
                renderItems(data.items);
                setSubtotal(data.formatted_total ?? 'Rp 0');
                return;
            }

            renderEmpty();
        } catch (error) {
            console.error('Error fetching cart:', error);
        }
    };

    drawer.addEventListener('toggle', () => {
        if (drawer.open) {
            fetchCartContent();
        }
    });
}
