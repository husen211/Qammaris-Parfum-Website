const ROOT_ID = 'about-lanyard';
const LOAD_THRESHOLD = 0.2;
const IDLE_TIMEOUT_MS = 2000;
const FALLBACK_DELAY_MS = 1200;

const mount = document.getElementById(ROOT_ID);

const loadLanyard = async (target) => {
  if (!target || target.dataset.lanyardLoaded === 'true') {
    return;
  }

  target.dataset.lanyardLoaded = 'true';

  try {
    const module = await import('./about-lanyard.jsx');
    const mountFn = module.mountAboutLanyard || module.default;

    if (typeof mountFn === 'function') {
      mountFn(target);
    }
  } catch (error) {
    target.dataset.lanyardLoaded = 'false';
  }
};

const scheduleFallbackLoad = (target) => {
  if ('requestIdleCallback' in window) {
    window.requestIdleCallback(() => loadLanyard(target), { timeout: IDLE_TIMEOUT_MS });
    return;
  }

  window.setTimeout(() => loadLanyard(target), FALLBACK_DELAY_MS);
};

if (mount) {
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            observer.disconnect();
            loadLanyard(mount);
          }
        });
      },
      { threshold: LOAD_THRESHOLD }
    );

    observer.observe(mount);
  } else {
    scheduleFallbackLoad(mount);
  }
}
