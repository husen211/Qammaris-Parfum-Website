# Analytics — Cloudflare, Search Console and optional GA4

**Choice (Owner, 2026-10-12):**
- Cloudflare Web Analytics measures visitors, daily/monthly trends, top pages, referrers, countries and devices. It is cookieless and loads about 5 KB, deferred.
- Google Search Console shows the Google searches that bring visitors, indexing and the sitemap. It loads no script.
- Owner subsequently approved GA4 (ANALYTICS-02, 2026-10-12) to report approximate visitor cities. Cloudflare and Search Console remain in use.

**What the code does:** `resources/views/partials/analytics.blade.php` runs only when all of these hold:
- the public layout is in use;
- `APP_ENV=production`;
- the matching value in `.env` is set.

Pesanan Online pages (`/pesanan/{token}`, Pesanan Saya) and the Admin never load it, because order links carry private tokens in the URL.

## Owner setup

1. **Cloudflare Web Analytics.**
   - Cloudflare dashboard → Analytics & Logs → Web Analytics → Add a site → `qammarisparfum.id`.
   - Choose the JS snippet, not the automatic setup; the domain does not have to use Cloudflare DNS.
   - Copy only the `token` value from the snippet into the server `.env`: `CLOUDFLARE_WEB_ANALYTICS_TOKEN=<token>`.
2. **Google Search Console.**
   - Add property → URL prefix `https://qammarisparfum.id/`.
   - Choose "HTML tag" and copy only the `content` value into `.env`: `GOOGLE_SITE_VERIFICATION=<content>`.
   - A Domain property verified through a DNS TXT record in hPanel also works and needs no `.env` value.
3. On the server: `php artisan config:cache`.
4. In Search Console: press Verify, then Sitemaps → submit `https://qammarisparfum.id/sitemap.xml`.
5. Check: the home page source contains `static.cloudflareinsights.com/beacon.min.js`, and Cloudflare shows visits within minutes. Search Console data appears after a few days.

**Turn off:** empty the variable and run `php artisan config:cache`.

## GA4 city reports (ANALYTICS-02)

GA4 is optional and defaults off. Set `GA4_MEASUREMENT_ID=G-XXXXXXXXXX` in the private server environment, then refresh configuration cache. The code accepts only a valid measurement ID in production. Do not put environment values in Git.

The public visitor chooses **Izinkan** or **Lewati**. No Google script loads and no Google analytics data is sent before permission. The choice is remembered on that browser; after either choice the banner disappears and no floating preference button remains. **Pilihan analytics** is an ordinary footer control for changing the choice later. Withdrawal disables the tracker and removes only GA cookies, preserving login, cart, order-history and PWA storage.

Only home, product listings/details, blog listings/articles/categories and store information can load GA4. Cart, checkout, fragrance forms/results, customer/staff bearer links, Pesanan Saya and all Admin pages are excluded. Page URLs lose query strings/fragments; referrers contain only their origin; titles are fixed rather than user-supplied. No customer identity, order, payment or custom event data is sent.

Google setup:

1. Standard GA4 account/property **Qammaris Website**, Web stream `https://qammarisparfum.id`; report timezone GMT+7 and currency IDR.
2. Disable **Enhanced measurement**, including automatic form, outbound, search and history-based page views. The code sends one sanitized page view per accepted page load with automatic page views disabled. Do not enable additional tag destinations, Google Signals or advertising personalization.
3. Optional account data-sharing settings stay off. Google Terms require Owner confirmation.
4. After release, visit a public page and choose **Izinkan**, then check **Reports → Realtime**. Standard reports may need up to 48 hours. Test **Lewati** and inspect excluded pages as well.

To see cities: **Reports → User attributes → Demographic details**, select the dimension **City/Kota** and the date range (one day or a month). If the menu differs, use Analytics search for “Demographic details”. City is estimated from network location, not GPS; VPN/mobile networks and privacy thresholds can produce unknown or inaccurate cities. These reports include consenting visitors from activation onward and will differ from Cloudflare totals. Google search keywords remain in Search Console **Performance → Queries**.

**Disable GA4 alone:** empty `GA4_MEASUREMENT_ID`, refresh configuration cache and verify no `data-ga4-consent` or GA4 asset remains in public HTML. Existing Google scripts on an already-open page stop after withdrawal; reload after a server kill switch. No database rollback or migration is needed.
