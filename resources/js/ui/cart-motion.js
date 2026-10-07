// Quadratic flight adapted from motiondotdev's 21st.dev motion-add-to-basket.
export function basketFlightFrames(start, end) {
  const control = { x: start.x + (end.x - start.x) * 0.25, y: Math.max(24, Math.min(start.y, end.y) - 100) };
  return Array.from({ length: 31 }, (_, index) => {
    const t = index / 30;
    const x = (1 - t) ** 2 * start.x + 2 * (1 - t) * t * control.x + t ** 2 * end.x;
    const y = (1 - t) ** 2 * start.y + 2 * (1 - t) * t * control.y + t ** 2 * end.y;
    return { offset: t, transform: `translate(${x}px, ${y}px) scale(${1 - 0.7 * t})`, opacity: t < 0.9 ? 1 : (1 - t) * 10 };
  });
}

export function animateQuantity(input, change) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !input.animate) return;
  input.getAnimations().forEach(animation => animation.cancel());
  input.animate([{ opacity: 0.45, transform: `translateY(${change > 0 ? 6 : -6}px)` }, { opacity: 1, transform: 'translateY(0)' }], { duration: 160, easing: 'ease-out' });
}

export function flyIntoBasket(button) {
  const target = document.querySelector('[data-cart-link]');
  const image = document.getElementById('mainImage');
  if (!target || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !button.animate) return;
  const destination = target.getBoundingClientRect();
  const origin = button.getBoundingClientRect();
  const photo = image?.getBoundingClientRect();
  // The product photo is above the button on mobile; start beside the tapped CTA if offscreen.
  const visiblePhoto = photo && photo.top >= 90 && photo.bottom <= window.innerHeight && image.naturalWidth > 0;
  const start = visiblePhoto ? { x: photo.left + photo.width / 2 - 28, y: photo.top + photo.height / 2 - 28 } : { x: origin.left + 12, y: origin.top - 12 };
  const end = { x: destination.left + destination.width / 2 - 28, y: destination.top + destination.height / 2 - 28 };
  const tile = document.createElement('div');
  tile.className = 'cart-flight';
  tile.setAttribute('aria-hidden', 'true');
  if (image?.complete && image.naturalWidth > 0) {
    const copy = document.createElement('img');
    copy.src = image.currentSrc || image.src;
    copy.alt = '';
    tile.append(copy);
  } else {
    const icon = button.querySelector('[data-cart-state="idle"] svg');
    if (icon) tile.append(icon.cloneNode(true));
  }
  document.body.append(tile);
  const flight = tile.animate(basketFlightFrames(start, end), { duration: 650, easing: 'cubic-bezier(.35,0,.8,1)', fill: 'forwards' });
  const cleanup = () => { tile.remove(); window.removeEventListener('pagehide', cleanup); };
  window.addEventListener('pagehide', cleanup, { once: true });
  flight.oncancel = cleanup;
  flight.onfinish = () => {
    cleanup();
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    target.animate([{ boxShadow: '0 0 0 0 rgba(26,26,26,.22)' }, { boxShadow: '0 0 0 10px rgba(26,26,26,0)' }], { duration: 400, easing: 'ease-out' });
  };
}
