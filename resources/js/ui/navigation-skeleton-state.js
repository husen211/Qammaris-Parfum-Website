const labels = {
  home: 'Memuat beranda…', catalog: 'Memuat katalog…', product: 'Memuat produk…',
  cart: 'Memuat keranjang…', checkout: 'Memuat checkout…', login: 'Memuat halaman masuk…',
  content: 'Memuat halaman…', article: 'Memuat artikel…',
  blog: 'Memuat jurnal…', quiz: 'Memuat tes parfum…', 'quiz-result': 'Memuat rekomendasi…',
  'admin-list': 'Memuat daftar…', 'admin-form': 'Memuat formulir…', 'admin-dashboard': 'Memuat dashboard…',
};

export function navigationDestination(href, currentHref, options = {}) {
  if (options.defaultPrevented || options.download || (options.target && options.target !== '_self')
    || options.button > 0 || options.ctrlKey || options.metaKey || options.shiftKey || options.altKey) return null;
  let destination;
  let current;
  try { destination = new URL(href, currentHref); current = new URL(currentHref); } catch { return null; }
  if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== current.origin) return null;
  if (!options.allowSame && destination.pathname === current.pathname && destination.search === current.search) return null;
  const path = destination.pathname.replace(/\/$/, '') || '/';
  if (/\.(?:csv|xlsx?|pdf|zip|png|jpe?g|webp|svg)$/i.test(path)
    || /\/template$/.test(path)) return null;
  let kind;
  if (path === '/') kind = 'home';
  else if (path === '/products') kind = 'catalog';
  else if (/^\/products\/[^/]+$/.test(path)) kind = 'product';
  else if (path === '/cart' || path === '/cart/clear') kind = 'cart';
  else if (path === '/cart/checkout') kind = 'checkout';
  else if (path === '/login' || path === '/logout') kind = 'login';
  else if (path === '/admin') kind = 'admin-dashboard';
  else if (/^\/admin\/(products|brands|categories|blog-posts|app-products|product-imports|product-maintenance)(\/.*)?$/.test(path)) {
    kind = /\/(create|edit|preview)$/.test(path) || /\/product-(imports|maintenance)/.test(path) ? 'admin-form' : 'admin-list';
  } else if (path === '/blog' || /^\/blog\/category\/[^/]+$/.test(path)) kind = 'blog';
  else if (/^\/blog\/[^/]+$/.test(path)) kind = 'article';
  else if (path === '/fragrance-quiz') kind = 'quiz';
  else if (path === '/fragrance-quiz/result') kind = 'quiz-result';
  else if (/^\/store\/(about|location)$/.test(path)) kind = 'content';
  else return null;
  return { kind, label: labels[kind], layout: kind.startsWith('admin-') ? 'admin' : kind === 'login' ? 'auth' : 'public' };
}
