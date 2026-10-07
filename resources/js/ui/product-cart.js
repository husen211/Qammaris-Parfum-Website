import { animateQuantity, flyIntoBasket } from './cart-motion.js';

// Keep successful feedback behind the existing server's availability/quantity validation.
export async function addCartItem(url, payload, token, fetcher = fetch) {
  const response = await fetcher(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  let data;
  try { data = await response.json(); } catch { throw new Error('Produk belum dapat ditambahkan. Muat ulang halaman dan coba lagi.'); }
  if (!response.ok || !data.success) throw new Error(data.message || 'Produk belum dapat ditambahkan. Coba lagi.');
  return data;
}

export function initProductCart() {
  const button = document.querySelector('[data-add-to-cart]');
  const quantity = document.getElementById('quantity');
  if (!button || !quantity) return;
  const feedback = document.querySelector('[data-cart-feedback]');
  const controls = [...document.querySelectorAll('[data-quantity-change]')];
  let resetTimer;
  const setPhase = phase => {
    button.dataset.phase = phase;
    button.disabled = phase !== 'idle';
    button.setAttribute('aria-busy', String(phase === 'loading'));
    const labels = { idle: 'Tambah ke keranjang', loading: 'Menambahkan…', added: 'Ditambahkan ke keranjang' };
    button.setAttribute('aria-label', labels[phase]);
    button.querySelectorAll('[data-cart-state]').forEach(element => { element.hidden = element.dataset.cartState !== phase; });
    controls.forEach(control => { control.disabled = phase === 'loading' || (control.dataset.quantityChange === '-1' ? quantity.value <= 1 : quantity.value >= 99); });
  };
  controls.forEach(control => control.addEventListener('click', () => {
    const change = Number(control.dataset.quantityChange);
    quantity.value = Math.min(99, Math.max(1, Number(quantity.value) + change));
    animateQuantity(quantity, change);
    controls.forEach(item => { item.disabled = item.dataset.quantityChange === '-1' ? quantity.value <= 1 : quantity.value >= 99; });
  }));
  button.addEventListener('click', async () => {
    if (button.disabled) return;
    clearTimeout(resetTimer);
    setPhase('loading');
    if (feedback) { feedback.textContent = 'Menambahkan produk…'; feedback.classList.remove('text-red-700'); }
    try {
      const data = await addCartItem(button.dataset.cartAddUrl, { variant_id: Number(button.dataset.variantId), quantity: Number(quantity.value) }, document.querySelector('meta[name="csrf-token"]')?.content || '');
      if (Number.isSafeInteger(data.cart_count) && data.cart_count >= 0) window.dispatchEvent(new CustomEvent('qammaris:cart-updated', { detail: { count: data.cart_count } }));
      setPhase('added');
      if (feedback) feedback.textContent = 'Produk ditambahkan. Buka keranjang untuk melanjutkan.';
      // Decoration must never turn a completed mutation into a retry/error.
      try { flyIntoBasket(button); } catch { /* Successful addition remains visible without motion. */ }
      resetTimer = setTimeout(() => setPhase('idle'), 1600);
    } catch (error) {
      setPhase('idle');
      if (feedback) { feedback.textContent = error.message || 'Produk belum dapat ditambahkan. Coba lagi.'; feedback.classList.add('text-red-700'); }
    }
  });
  window.addEventListener('pageshow', () => { clearTimeout(resetTimer); setPhase('idle'); });
  setPhase('idle');
}

if (typeof document !== 'undefined') initProductCart();
