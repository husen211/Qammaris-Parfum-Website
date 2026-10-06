import './bootstrap';
import './ui/cart-drawer';
import './ui/catalog-discovery';
import './ui/catalog-navigation';
import './ui/product-gallery';
import './ui/catalog-images';
import './ui/home-best-sellers';
import './ui/navbar';
import './ui/reveal';
import './ui/toast';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.csrfToken = csrfToken;
}
