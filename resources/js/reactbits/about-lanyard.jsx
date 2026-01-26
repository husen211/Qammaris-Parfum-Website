import { createRoot } from 'react-dom/client';
import Lanyard from './lanyard/Lanyard';

const supportsWebGL = () => {
  try {
    const canvas = document.createElement('canvas');
    return !!(
      window.WebGLRenderingContext &&
      (canvas.getContext('webgl') || canvas.getContext('experimental-webgl'))
    );
  } catch (error) {
    return false;
  }
};

const renderFallback = (target) => {
  const src = target.dataset.fallbackImage;
  const alt = target.dataset.fallbackAlt || 'Lanyard preview';

  if (!src) {
    return;
  }

  target.innerHTML = `<img src="${src}" alt="${alt}" class="w-full h-full object-cover" loading="lazy" decoding="async" />`;
};

export const mountAboutLanyard = (target) => {
  if (!target) {
    return;
  }

  if (!supportsWebGL()) {
    renderFallback(target);
    return;
  }

  try {
    const root = createRoot(target);
    root.render(<Lanyard position={[0, 0, 20]} gravity={[0, -40, 0]} />);
  } catch (error) {
    renderFallback(target);
  }
};

export default mountAboutLanyard;
