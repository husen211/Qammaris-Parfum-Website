# Analytics — Cloudflare Web Analytics + Google Search Console (ANALYTICS-01)

**Choice (Owner, 2026-10-12):**
- Cloudflare Web Analytics measures visitors, daily/monthly trends, top pages, referrers, countries and devices. It is cookieless and loads about 5 KB, deferred.
- Google Search Console shows the Google searches that bring visitors, indexing and the sitemap. It loads no script.
- Google Analytics 4 is not used: it is heavier on phones, sets cookies, and would only add demographics.

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
