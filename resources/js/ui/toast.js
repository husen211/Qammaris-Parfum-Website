let toastTimeout;

window.showToast = (message, type = 'success') => {
    const toast = document.getElementById('toast');
    const msgEl = document.getElementById('toastMessage');

    if (!toast || !msgEl) {
        return;
    }

    msgEl.textContent = message;

    toast.classList.remove('opacity-0', 'translate-y-[-10px]');
    toast.classList.add('opacity-100', 'translate-y-0');

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastTimeout = setTimeout(() => {
        toast.classList.remove('opacity-100', 'translate-y-0');
        toast.classList.add('opacity-0', 'translate-y-[-10px]');
    }, 3000);
};
