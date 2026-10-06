const catalog = document.querySelector('[data-catalog-discovery]');

if (catalog) {
    const filterTrigger = document.querySelector('[data-catalog-filter-trigger]');
    const filterDialog = document.getElementById('mobileFilter');
    const state = {
        search: catalog.dataset.search ?? '',
        brand: new Set((catalog.dataset.brandIds ?? '').split(',').filter(Boolean)),
        category: catalog.dataset.category ?? '',
        gender: catalog.dataset.gender ?? '',
        price_min: catalog.dataset.priceMin ?? '',
        price_max: catalog.dataset.priceMax ?? '',
        availability: catalog.dataset.availability ?? '',
        sort: catalog.dataset.sort ?? 'latest',
    };

    const syncControls = () => {
        Object.entries(state).forEach(([name, value]) => {
            if (name === 'brand') {
                return;
            }

            document.querySelectorAll(`[name="${name}"]`).forEach((control) => {
                const nextValue = String(value ?? '');

                if (control instanceof HTMLInputElement && control.type === 'radio') {
                    control.checked = control.value === nextValue;
                    return;
                }

                control.value = nextValue;

                if (control instanceof HTMLInputElement) {
                    control.setAttribute('value', nextValue);
                }
            });
        });

        document.querySelectorAll('[name="brand[]"]').forEach((control) => {
            if (control instanceof HTMLInputElement) {
                control.checked = state.brand.has(control.value);
            }
        });
    };

    const syncHiddenControls = (form) => {
        Object.entries(state).forEach(([name, value]) => {
            if (name === 'brand') {
                if (form.querySelector('[name="brand[]"]:not([type="hidden"])')) {
                    return;
                }

                form.querySelectorAll('[name="brand[]"][type="hidden"]').forEach((control) => {
                    control.remove();
                });

                state.brand.forEach((brandId) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'brand[]';
                    input.value = brandId;
                    form.append(input);
                });

                return;
            }

            form.querySelectorAll(`input[type="hidden"][name="${name}"]`).forEach((control) => {
                control.value = String(value ?? '');
            });
        });
    };

    filterTrigger?.addEventListener('click', () => {
        syncControls();

        if (filterDialog instanceof HTMLDialogElement && !filterDialog.open) {
            filterTrigger.setAttribute('aria-expanded', 'true');
            filterDialog.showModal();
        }
    });

    document.querySelector('[data-catalog-filter-close]')?.addEventListener('click', () => {
        if (filterDialog instanceof HTMLDialogElement) {
            filterDialog.close();
        }
    });

    filterDialog?.addEventListener('close', () => {
        filterTrigger?.setAttribute('aria-expanded', 'false');
        filterTrigger?.focus();
    });

    document.querySelectorAll('[data-catalog-sort]').forEach((control) => {
        control.addEventListener('change', () => {
            state.sort = control.value;
            syncControls();
            control.form?.requestSubmit();
        });
    });

    document.querySelectorAll('[data-catalog-autosubmit]').forEach((control) => {
        control.addEventListener('change', () => {
            if (control.name === 'brand[]') {
                state.brand = new Set(
                    [...control.form.querySelectorAll('[name="brand[]"]:checked')].map((brand) => brand.value),
                );
            } else {
                state[control.name] = control.value;
            }

            syncControls();
            control.form?.requestSubmit();
        });
    });

    document.querySelectorAll('[data-catalog-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            syncHiddenControls(form);

            form.querySelectorAll('[name]').forEach((control) => {
                if (control.value === '' || (control.name === 'sort' && control.value === 'latest')) {
                    control.disabled = true;
                }
            });
        });
    });

    syncControls();
    window.addEventListener('pageshow', syncControls, { once: true });
}
