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

    const syncBrandSummary = () => {
        document.querySelectorAll('[data-catalog-brand-summary]').forEach((summary) => {
            summary.textContent = state.brand.size ? `${state.brand.size} brand dipilih` : 'Semua brand';
        });
    };

    const syncControls = () => {
        Object.entries(state).forEach(([name, value]) => {
            if (name === 'brand') {
                return;
            }

            document.querySelectorAll(`[name="${name}"]`).forEach((control) => {
                const nextValue = String(value ?? '');
                control.disabled = false;

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
                control.disabled = false;
                control.checked = state.brand.has(control.value);
            }
        });
        syncBrandSummary();
    };

    document.querySelectorAll('[data-catalog-form] [name]').forEach((control) => {
        const updateState = () => {
            if (control.name === 'brand[]') {
                state.brand = new Set(
                    [...control.form.querySelectorAll('[name="brand[]"]:checked')].map((brand) => brand.value),
                );
                syncBrandSummary();
            } else if (Object.hasOwn(state, control.name)) {
                state[control.name] = control.value;
            }
        };
        control.addEventListener('input', updateState);
        control.addEventListener('change', updateState);
    });

    document.querySelectorAll('[data-catalog-brand-search]').forEach((search) => {
        search.addEventListener('input', () => {
            const dropdown = search.closest('[data-catalog-dropdown]');
            const query = search.value.trim().toLocaleLowerCase('id');
            const options = [...dropdown.querySelectorAll('[data-catalog-brand-option]')];
            options.forEach((option) => {
                option.hidden = !option.textContent.toLocaleLowerCase('id').includes(query);
            });
            dropdown.querySelector('[data-catalog-brand-empty]').hidden = options.some((option) => !option.hidden);
        });
    });

    document.querySelectorAll('[data-catalog-dropdown]').forEach((dropdown) => {
        dropdown.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !dropdown.open) return;
            event.preventDefault();
            event.stopPropagation();
            dropdown.open = false;
            dropdown.querySelector('summary').focus();
        });
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-catalog-dropdown][open]').forEach((dropdown) => {
            if (!dropdown.contains(event.target)) dropdown.open = false;
        });
    });

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
            state[control.name] = control.value;
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
    window.addEventListener('pageshow', syncControls);
}
