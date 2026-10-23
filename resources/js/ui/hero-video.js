// PERF-01: hero videos start after the page has loaded and only in the columns the current layout shows,
// so phones never download the desktop videos. Reduced motion and Data Saver keep the poster.
export function heroVideoAllowed({ reducedMotion, saveData }) {
    return !reducedMotion && !saveData;
}

export function startVisibleHeroVideos(videos) {
    for (const video of videos) {
        // A display:none column has no client rects.
        if (!video.dataset.src || video.getClientRects().length === 0) continue;
        video.src = video.dataset.src;
        delete video.dataset.src;
        video.play()?.catch(() => {});
    }
}

const videos = typeof document === 'undefined' ? [] : [...document.querySelectorAll('[data-hero-video]')];

if (videos.length && heroVideoAllowed({
    reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
    saveData: navigator.connection?.saveData === true,
})) {
    const start = () => startVisibleHeroVideos(videos);
    if (document.readyState === 'complete') start();
    else window.addEventListener('load', start, { once: true });
    matchMedia('(min-width: 768px)').addEventListener('change', start);
}
