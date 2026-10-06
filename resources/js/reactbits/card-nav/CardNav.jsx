import { useEffect, useRef, useState } from 'react';

const ArrowIcon = () => (
  <svg className="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M7 17L17 7M8 7h9v9" />
  </svg>
);

const CardNav = ({
  logo,
  logoAlt = 'Logo',
  items = [],
  className = '',
  baseColor = '#fff',
  menuColor,
  buttonBgColor,
  buttonTextColor,
  buttonLabel = 'Katalog',
  buttonHref = '#',
  cartCount = 0,
  activeUrl,
  homeHref = '/'
}) => {
  const [isHamburgerOpen, setIsHamburgerOpen] = useState(false);
  const [isExpanded, setIsExpanded] = useState(false);
  const menuButtonRef = useRef(null);
  useEffect(() => {
    if (!isExpanded) return undefined;
    const closeOnEscape = event => {
      if (event.key !== 'Escape') return;
      setIsExpanded(false);
      setIsHamburgerOpen(false);
      menuButtonRef.current?.focus();
    };
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [isExpanded]);

  const toggleMenu = () => {
    setIsExpanded(open => !open);
    setIsHamburgerOpen(open => !open);
  };

  const handleCartClick = () => {
    const dialog = document.getElementById('cartDrawer');
    if (dialog?.showModal) {
      dialog.showModal();
    }
  };

  const isActiveLink = href => {
    if (!href || !activeUrl) return false;
    try {
      const hrefPath = new URL(href, window.location.origin).pathname;
      const activePath = new URL(activeUrl, window.location.origin).pathname;
      return hrefPath === activePath;
    } catch {
      return false;
    }
  };

  const cards = (items || []).slice(0, 3);

  return (
    <div
      className={`card-nav-container fixed left-1/2 -translate-x-1/2 w-[92%] max-w-[1100px] z-[99] top-4 md:top-6 ${className}`}
    >
      <nav
        className={`card-nav ${isExpanded ? 'open' : ''} block ${isExpanded ? 'h-auto' : 'h-[60px]'} p-0 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.08)] relative overflow-hidden`}
        style={{ backgroundColor: baseColor }}
      >
        <div className="card-nav-top relative h-[60px] flex items-center justify-between p-2 pl-5 z-[2]">
          <button
            ref={menuButtonRef}
            type="button"
            className={`hamburger-menu ${isHamburgerOpen ? 'open' : ''} group h-full w-11 flex flex-col items-center justify-center cursor-pointer gap-[6px] order-2 md:order-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black`}
            onClick={toggleMenu}
            aria-label={isExpanded ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
            aria-expanded={isExpanded}
            aria-controls="primary-navigation-menu"
            style={{ color: menuColor || '#000' }}
          >
            <div
              className={`hamburger-line w-[30px] h-[2px] bg-current transition-[transform,opacity,margin] duration-300 ease-linear [transform-origin:50%_50%] ${
                isHamburgerOpen ? 'translate-y-[4px] rotate-45' : ''
              } group-hover:opacity-75`}
            />
            <div
              className={`hamburger-line w-[30px] h-[2px] bg-current transition-[transform,opacity,margin] duration-300 ease-linear [transform-origin:50%_50%] ${
                isHamburgerOpen ? '-translate-y-[4px] -rotate-45' : ''
              } group-hover:opacity-75`}
            />
          </button>

          <div className="logo-container flex items-center md:absolute md:left-1/2 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 order-1 md:order-none">
            {logo ? (
              <a href={homeHref} aria-label="Kembali ke beranda" className="inline-flex min-h-11 min-w-11 items-center justify-center">
                <img src={logo} alt={logoAlt} width="30" height="30" className="logo h-[28px] w-[28px] md:h-[30px] md:w-[30px]" />
              </a>
            ) : null}
          </div>

          <div className="flex items-center gap-2 order-3">
            <a
              href={buttonHref}
              aria-label="Cari parfum di katalog"
              className="hidden md:inline-flex items-center justify-center h-11 w-11 border border-black/10 hover:border-black/30 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </a>
            <button
              id="cart-drawer-trigger"
              type="button"
              aria-label="Buka keranjang"
              onClick={handleCartClick}
              className="relative inline-flex items-center justify-center h-11 w-11 border border-black/10 hover:border-black/30 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
              {cartCount > 0 ? (
                <span className="absolute -top-2 -right-2 bg-brand-emerald text-white text-[10px] font-bold h-4 w-4 flex items-center justify-center">
                  {cartCount}
                </span>
              ) : null}
            </button>
            <a
              href={buttonHref}
              className="hidden md:inline-flex border-0 px-4 items-center min-h-11 font-medium cursor-pointer transition-colors duration-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
              style={{ backgroundColor: buttonBgColor, color: buttonTextColor }}
            >
              {buttonLabel}
            </a>
          </div>
        </div>

        <div
          id="primary-navigation-menu"
          className={`card-nav-content max-h-[calc(100dvh-112px)] overflow-y-auto overscroll-contain p-2 flex-col items-stretch gap-2 justify-start z-[1] ${
            isExpanded ? 'flex' : 'hidden'
          } md:flex-row md:items-end md:gap-[12px]`}
          aria-hidden={!isExpanded}
        >
          {cards.map((item, idx) => (
            <div
              key={`${item.label}-${idx}`}
              className="nav-card select-none relative flex flex-col gap-2 p-[12px_16px] min-w-0 flex-[1_1_auto] h-auto min-h-[60px] md:h-full md:min-h-0 md:flex-[1_1_0%]"
              style={{ backgroundColor: item.bgColor, color: item.textColor }}
            >
              <div className="nav-card-label font-normal tracking-[-0.5px] text-[18px] md:text-[22px]">
                {item.label}
              </div>
              <div className="nav-card-links mt-auto flex flex-col gap-[2px]">
                {item.links?.map((lnk, i) => (
                  <a
                    key={`${lnk.label}-${i}`}
                    className={`nav-card-link min-h-11 inline-flex items-center gap-[6px] no-underline cursor-pointer transition-opacity duration-300 text-[15px] md:text-[16px] ${
                      isActiveLink(lnk.href) ? 'opacity-100 font-semibold' : 'opacity-80 hover:opacity-100'
                    }`}
                    href={lnk.href}
                    aria-label={lnk.ariaLabel}
                    aria-current={isActiveLink(lnk.href) ? 'page' : undefined}
                  >
                    <ArrowIcon />
                    {lnk.label}
                  </a>
                ))}
              </div>
            </div>
          ))}
        </div>
      </nav>
    </div>
  );
};

export default CardNav;
