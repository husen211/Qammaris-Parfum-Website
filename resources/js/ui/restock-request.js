// WAIT-01: "Kabari saya jika sudah ready" — move focus into the opened form and prevent double submits.
document.querySelectorAll('[data-restock-request]').forEach((details) => {
    details.addEventListener('toggle', () => {
        if (details.open) {
            (details.querySelector('[aria-invalid="true"]') ?? details.querySelector('input[name="name"]'))?.focus();
        }
    });

    details.querySelector('[data-restock-form]')?.addEventListener('submit', (event) => {
        const button = event.currentTarget.querySelector('[data-restock-submit]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Menyimpan…';
        }
    });
});
