import './bootstrap';
import './ui/cart-drawer';
import './ui/navbar';
import './ui/reveal';
import './ui/toast';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.csrfToken = csrfToken;
}
