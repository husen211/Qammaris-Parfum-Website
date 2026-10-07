import { animateQuantity } from './cart-motion.js';

const rows = document.querySelectorAll('[data-inquiry-row]');
const feedback = document.querySelector('[data-inquiry-feedback]');
let pending = false;

function setFeedback(message, isError = false) {
  if (!feedback) return;
  feedback.textContent = message;
  feedback.classList.toggle('text-red-700', isError);
}

function refreshControls() {
  rows.forEach(row => {
    const quantity = Number(row.querySelector('input').value);
    row.querySelectorAll('button').forEach(button => {
      button.disabled = pending || (button.dataset.cartQuantityChange === '-1' && quantity <= 1) || (button.dataset.cartQuantityChange === '1' && quantity >= 99);
    });
    row.setAttribute('aria-busy', String(pending));
  });
}

rows.forEach(row => row.querySelectorAll('[data-cart-quantity-change], [data-cart-remove]').forEach(button => {
  button.addEventListener('click', async () => {
    if (pending) return;
    const input = row.querySelector('input');
    const previous = Number(input.value);
    const removing = button.hasAttribute('data-cart-remove');
    const change = Number(button.dataset.cartQuantityChange);
    const quantity = Math.min(99, Math.max(1, previous + change));
    if (!removing && quantity === previous) return;
    pending = true;
    if (!removing) { input.value = quantity; animateQuantity(input, change); }
    refreshControls();
    setFeedback(removing ? 'Menghapus produk…' : 'Memperbarui jumlah…');
    try {
      const response = await fetch(removing ? row.dataset.cartRemoveUrl : row.dataset.cartUpdateUrl, {
        method: removing ? 'DELETE' : 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' },
        ...(removing ? {} : { body: JSON.stringify({ quantity }) }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Keranjang belum dapat diperbarui. Coba lagi.');
      window.dispatchEvent(new CustomEvent('qammaris:page-reload'));
      window.location.reload();
    } catch {
      input.value = previous;
      pending = false;
      refreshControls();
      setFeedback('Keranjang belum dapat diperbarui. Muat ulang halaman atau coba lagi.', true);
    }
  });
}));
refreshControls();
window.addEventListener('pageshow', () => { pending = false; refreshControls(); });
