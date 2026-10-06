import assert from 'node:assert/strict';
import test from 'node:test';
import { navigationDestination } from '../../resources/js/ui/navigation-skeleton-state.js';

const current = 'https://qammarisparfum.id/products?page=2';
test('HTML routes select a destination shape without altering navigation URLs', () => {
  const destinations = { '/': 'home', '/products?search=afnan': 'catalog', '/products/hawas': 'product',
    '/cart': 'cart', '/cart/checkout': 'checkout', '/login': 'login', '/logout': 'login',
    '/admin': 'admin-dashboard', '/admin/products?publication=draft': 'admin-list',
    '/admin/products/17/edit': 'admin-form', '/admin/product-imports': 'admin-form',
    '/blog/story': 'article', '/blog/category/2': 'blog', '/blog': 'blog',
    '/fragrance-quiz': 'quiz', '/fragrance-quiz/result': 'quiz-result', '/store/location': 'content' };
  for (const [href, kind] of Object.entries(destinations)) assert.equal(navigationDestination(href, current).kind, kind);
});
test('downloads, JSON, external links and fragments never hide the current page', () => {
  for (const href of ['/admin/product-imports/template', '/admin/product-imports/catalog-snapshot.csv',
    '/admin/product-imports/12/report.csv', '/cart/data', '/cart/add', '/api/products',
    '/integrations/qammaris-app/webhook', 'https://wa.me/123', 'mailto:test@example.com', '#main-content', current]) {
    assert.equal(navigationDestination(href, current), null, href);
  }
});
test('cancelled, modified and new-tab clicks retain native behavior', () => {
  for (const options of [{defaultPrevented:true}, {download:true}, {target:'_blank'}, {button:1},
    {ctrlKey:true}, {metaKey:true}, {shiftKey:true}, {altKey:true}]) {
    assert.equal(navigationDestination('/cart', current, options), null);
  }
});
test('native GET forms and explicit successful reloads can display the same-page skeleton', () => {
  assert.equal(navigationDestination(current, current, {allowSame:true}).kind, 'catalog');
  assert.equal(navigationDestination('/cart/clear', current, {allowSame:true}).kind, 'cart');
});
