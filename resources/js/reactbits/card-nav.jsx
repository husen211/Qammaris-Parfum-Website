import { createRoot } from 'react-dom/client';
import CardNav from './card-nav/CardNav';

const mount = document.getElementById('card-nav-root');

if (mount) {
  const items = mount.dataset.items ? JSON.parse(mount.dataset.items) : [];
  const cartCount = Number.parseInt(mount.dataset.cartCount || '0', 10) || 0;

  const root = createRoot(mount);
  root.render(
    <CardNav
      logo={mount.dataset.logo}
      logoAlt={mount.dataset.logoAlt || 'Logo'}
      items={items}
      baseColor={mount.dataset.baseColor || '#fff'}
      menuColor={mount.dataset.menuColor || '#000'}
      buttonBgColor={mount.dataset.buttonBg || '#111'}
      buttonTextColor={mount.dataset.buttonText || '#fff'}
      buttonLabel={mount.dataset.buttonLabel || 'Katalog'}
      buttonHref={mount.dataset.buttonHref || '#'}
      homeHref={mount.dataset.homeHref || '/'}
      activeUrl={mount.dataset.activeUrl || ''}
      cartCount={cartCount}
    />
  );
}
