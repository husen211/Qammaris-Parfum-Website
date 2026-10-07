import { navigationDestination } from './navigation-skeleton-state.js';

const loader = document.querySelector('[data-navigation-loader]');
const content = document.querySelector('[data-navigation-content]');
const status = document.querySelector('[data-navigation-status]');
let timer;
let restoreContent;
let previousFocus;

function reset() {
  clearTimeout(timer);
  restoreContent?.();
  restoreContent = null;
  document.body.removeAttribute('data-navigation-loading');
  document.querySelectorAll('[data-navigation-pending]').forEach(link => link.removeAttribute('data-navigation-pending'));
  if (!loader) return;
  loader.hidden = true;
  loader.querySelector('[data-navigation-shape]').replaceChildren();
  loader.querySelector('[data-navigation-recovery]').hidden = true;
  if (status) status.textContent = '';
}

function start(destination) {
  const template = document.querySelector(`template[data-navigation-skeleton="${destination.kind}"]`);
  if (!loader || !content || !template) return;
  if (!restoreContent) {
    previousFocus = document.activeElement;
    const wasInert = content.inert;
    const wasBusy = content.getAttribute('aria-busy');
    restoreContent = () => {
      content.inert = wasInert;
      if (wasBusy === null) content.removeAttribute('aria-busy');
      else content.setAttribute('aria-busy', wasBusy);
    };
  }
  document.querySelectorAll('dialog[open]').forEach(dialog => dialog.close());
  document.querySelectorAll('header details[open]').forEach(details => { details.open = false; });
  content.inert = true;
  content.setAttribute('aria-busy', 'true');
  loader.dataset.layout = destination.layout;
  loader.querySelector('[data-navigation-shape]').replaceChildren(template.content.cloneNode(true));
  loader.querySelector('[data-navigation-label]').textContent = destination.label;
  loader.querySelector('[data-navigation-recovery]').hidden = true;
  loader.hidden = false;
  loader.scrollTop = 0;
  document.body.setAttribute('data-navigation-loading', destination.layout);
  if (status) status.textContent = destination.label;
  window.dispatchEvent(new CustomEvent('qammaris:navigation-start'));
  clearTimeout(timer);
  timer = setTimeout(() => {
    loader.querySelector('[data-navigation-label]').textContent = 'Halaman masih memuat. Tunggu sebentar atau kembali.';
    loader.querySelector('[data-navigation-recovery]').hidden = false;
    if (status) status.textContent = 'Halaman masih memuat. Tombol Kembali tersedia.';
  }, 12000);
}

document.addEventListener('click', event => {
  const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
  if (!link) return;
  const destination = navigationDestination(link.href, window.location.href, {
    defaultPrevented: event.defaultPrevented, download: link.hasAttribute('download'), target: link.target,
    button: event.button, ctrlKey: event.ctrlKey, metaKey: event.metaKey, shiftKey: event.shiftKey, altKey: event.altKey,
  });
  if (!destination) return;
  start(destination);
  link.setAttribute('data-navigation-pending', '');
});

document.addEventListener('submit', event => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
  const submitter = event.submitter;
  const method = (submitter?.getAttribute('formmethod') || form.method).toLowerCase();
  if (method === 'dialog') return;
  const action = submitter?.getAttribute('formaction') || form.action;
  const destination = navigationDestination(action, window.location.href, {
    allowSame: true, target: submitter?.getAttribute('formtarget') || form.target,
  });
  if (destination) {
    const shape = method !== 'get' && !['/logout', '/cart/clear'].includes(new URL(action, window.location.href).pathname)
      ? navigationDestination(window.location.href, window.location.href, { allowSame: true }) : destination;
    start(shape || destination);
  }
});

// Explicit successful reloads use the same feedback; async mutations keep their own pending state.
window.addEventListener('qammaris:page-reload', () => {
  const destination = navigationDestination(window.location.href, window.location.href, { allowSame: true });
  if (destination) start(destination);
});
loader?.querySelector('[data-navigation-cancel]').addEventListener('click', () => {
  window.stop();
  reset();
  if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
});
window.addEventListener('pageshow', reset);
