const navCard = document.getElementById('navbar-card');

if (navCard) {
    const toggleNavbar = () => {
        const scrolled = window.scrollY > 12;

        navCard.classList.toggle('mt-4', !scrolled);
        navCard.classList.toggle('mt-2', scrolled);
        navCard.classList.toggle('bg-white/70', !scrolled);
        navCard.classList.toggle('bg-white', scrolled);
        navCard.classList.toggle('border-white/60', !scrolled);
        navCard.classList.toggle('border-gray-100', scrolled);
        navCard.classList.toggle('shadow-[0_8px_24px_rgba(0,0,0,0.06)]', !scrolled);
        navCard.classList.toggle('shadow-md', scrolled);
    };

    toggleNavbar();
    window.addEventListener('scroll', toggleNavbar, { passive: true });
}
