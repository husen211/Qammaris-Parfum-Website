export const CONSENT_KEY = 'qammaris.ga4-consent.v1';
const DENIED = { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' };

export function safePageContext(href, referrer) {
    const page = new URL(href);
    let origin = '';
    try {
        const previous = new URL(referrer);
        if (['http:', 'https:'].includes(previous.protocol)) origin = previous.origin;
    } catch { /* An empty or malformed referrer carries no useful analytics data. */ }
    return { page_location: `${page.origin}${page.pathname}`, page_referrer: origin, page_title: 'Qammaris Website' };
}

export function readConsent(storage) {
    try {
        const choice = storage.getItem(CONSENT_KEY);
        return ['granted', 'denied'].includes(choice) ? choice : null;
    } catch { return null; }
}

export function createTracker(id, doc, win) {
    let loaded = false;
    let pageViewSent = false;
    const valid = /^G-[A-Z0-9]{10}$/.test(id);
    const disableKey = `ga-disable-${id}`;
    // Match Google's command queue contract; arrays are interpreted as data-layer method calls.
    function command() { win.dataLayer.push(arguments); }

    const allow = () => {
        if (!valid) return false;
        win[disableKey] = false;
        if (!loaded) {
            loaded = true;
            win.dataLayer = win.dataLayer || [];
            win.gtag = command;
            command('consent', 'default', { ...DENIED });
            command('consent', 'update', { ...DENIED, analytics_storage: 'granted' });
            command('js', new Date());
            const context = safePageContext(win.location.href, doc.referrer);
            command('config', id, { ...context, send_page_view: false, allow_google_signals: false, allow_ad_personalization_signals: false });
            const script = doc.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${id}`;
            doc.head.appendChild(script);
        } else {
            command('consent', 'update', { ...DENIED, analytics_storage: 'granted' });
        }
        if (!pageViewSent) {
            pageViewSent = true;
            command('event', 'page_view', { ...safePageContext(win.location.href, doc.referrer), send_to: id });
        }
        return true;
    };

    const deny = () => {
        win[disableKey] = true;
        if (loaded) command('consent', 'update', { ...DENIED });
        // Remove only GA cookies; sessions, order history and public-PWA storage stay intact.
        for (const cookie of doc.cookie.split(';')) {
            const name = cookie.split('=')[0].trim();
            if (name !== '_ga' && !name.startsWith('_ga_')) continue;
            doc.cookie = `${name}=; Max-Age=0; path=/`;
            const parts = win.location.hostname.split('.');
            for (let i = 0; i < parts.length - 1; i++) {
                const domain = parts.slice(i).join('.');
                doc.cookie = `${name}=; Max-Age=0; path=/; domain=${domain}`;
                doc.cookie = `${name}=; Max-Age=0; path=/; domain=.${domain}`;
            }
        }
    };
    return { allow, deny };
}

export function bootGa4(doc = document, win = window) {
    const banner = doc.querySelector('[data-ga4-consent]');
    const preferences = doc.querySelector('[data-ga4-preferences]');
    if (!banner || !preferences) return;
    let storage;
    try { storage = win.localStorage; } catch { storage = null; }
    const tracker = createTracker(banner.dataset.measurementId, doc, win);
    const choose = (choice) => {
        try { storage?.setItem(CONSENT_KEY, choice); } catch { /* Keep the current-page choice when storage is unavailable. */ }
        if (choice === 'granted') tracker.allow(); else tracker.deny();
        banner.hidden = true;
        preferences.hidden = false;
    };
    const stored = readConsent(storage);
    if (stored) choose(stored); else banner.hidden = false;
    banner.querySelector('[data-ga4-accept]').addEventListener('click', () => choose('granted'));
    banner.querySelector('[data-ga4-decline]').addEventListener('click', () => choose('denied'));
    preferences.addEventListener('click', () => {
        preferences.hidden = true;
        banner.hidden = false;
        banner.querySelector('[data-ga4-decline]').focus();
    });
}

if (typeof document !== 'undefined') bootGa4();
