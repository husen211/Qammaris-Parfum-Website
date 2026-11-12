# ORD-18 verification: kecamatan/kota search card and reliable shipping prices (local, 2026-11-11)

Owner feedback on ORD-17 (rated 6/10):
- don't make the search full screen; use a card in the middle;
- don't list Palu first;
- the results could not scroll;
- the screen glitched with the keyboard;
- "Jakarta" gave only 8 results;
- Enter/Search picked the top result by itself;
- "Ongkir otomatis sedang tidak bisa dihitung" appeared after an area was chosen ("jangan sampai ada kejadian kayak gitu"; offer a refresh).

## Change

- **Card, not a screen.**
  - The search is a card with an X close button.
  - While the phone keyboard is open, the card sits near the top and fits the space above the keyboard. The size comes from `visualViewport`, so the page does not jump.
  - With the keyboard closed, the card is centred and never taller than about 72% of the screen. On desktop it is a centred card of at most 512 px.
  - Results scroll inside the card (`overscroll-contain`), so the page behind does not move.
- **No Palu list first.** Before typing, the card only explains what to type, with the example placeholder "Palu Barat, Menteng, 94111".
- **All results.**
  - The site now shows everything Biteship returns. Biteship sends at most 10 per search; the site used to cut this to 8.
  - With 10 results the card adds a hint: type more precisely, for example "Menteng Jakarta".
- **Enter/Search never picks.**
  - On a phone it searches now and closes the keyboard, so the list can be read and tapped.
  - With a physical keyboard, a result highlighted with the arrow keys is taken on Enter. Otherwise the customer always taps the result.
- **"Ongkir tidak bisa dihitung", root cause.** The session kept only the last 40 searched areas. A customer who searched a few times (10 results each) and then tapped an older result got an area the server no longer knew, so the price failed. Now:
  - the session keeps 300 areas;
  - every searched area's Biteship label is cached site-wide for 30 days by its Biteship id;
  - the label still always comes from Biteship, never from the browser.
- **Transient failures.**
  - A dropped connection to Biteship is retried automatically. This applies only to the area search and the price check, never to courier booking.
  - The price limit per visitor is now 30/min (was 12/min).
  - If the price still fails, the customer sees **"Hitung ulang ongkir"** next to the existing "Ongkir dikonfirmasi admin" choice.

Sources for the keyboard behaviour:
- [Chrome: viewport resize behaviour with the on-screen keyboard](https://developer.chrome.com/blog/viewport-resize-behavior);
- [HTMHell: interactive-widget and the virtual keyboard](https://htmhell.dev/adventcalendar/2024/4/).

The card follows the visual viewport, which works on both iOS Safari and Chrome Android, instead of relying on `interactive-widget`, which Safari ignores.

## Checks (headless Chrome, local, Biteship test key)

| Check | Result |
| --- | --- |
| Card opens, 390×844 | Card, 141 px high, no Palu list, prompt text only ([intro](after-390-card-intro.jpg)) |
| "Jakarta" + Enter, 390 | "Mencari…" at once; 10 results; the list scrolls; the dialog stays open and the area is **not** chosen; "type more" hint shown ([results](after-390-card-results.jpg): centred, 607 of 844 px) |
| Tap "Menteng 10310" | Dialog closes; area "Menteng, Jakarta Pusat, DKI Jakarta. 10310"; J&T Rp26.500 (struck Rp38.000); instant hidden outside Palu ([J&T](after-390-jakarta-jnt.jpg)) |
| Same at 1440×900 | Centred 512 px card; 10 results fit without scrolling ([intro](after-1440-card-intro.jpg), [results](after-1440-card-results.jpg), [J&T](after-1440-jakarta-jnt.jpg)) |
| Price check fails once (simulated 503) | "Ongkir otomatis sedang tidak bisa dihitung …" plus **Hitung ulang ongkir** ([retry](after-390-retry-button.jpg)); tapping it priced J&T Rp26.500 ([priced](after-390-retry-priced.jpg)) |
| Area from an earlier search after the session lost it | Still priced from the site-wide cache; a dropped connection is retried (`CheckoutShippingQuoteTest`) |

## Later the same day: delivery choices and shorter cart/checkout text (Owner)

- **Ambil di toko** has a storefront icon. Its description no longer repeats the title: "Datang setelah pesanan dikonfirmasi. Tanpa ongkir."
- **Dikirim ke alamat** now reads "Diantar langsung oleh kurir." The Gojek, Grab and J&T logos stay.
- **Cart:**
  - removed "Harga dan ketersediaan mengikuti katalog saat ini.", "Berdasarkan harga yang tampil saat ini." and "Ongkir dan pembayaran dilanjutkan di WhatsApp.";
  - the summary is just "Subtotal produk" and the amount.
- **Checkout:**
  - the intro is "Lengkapi data penerima.";
  - "Nama dan nomor HP wajib diisi. Alamat hanya untuk pengiriman." stays;
  - the summary says **Total**, with no "Total sementara / belum termasuk ongkir";
  - under the button: "Bayar dengan transfer atau QRIS di langkah berikutnya.", or "Stok dicek admin dulu, lalu pesanan bisa dibayar." when stock must be checked.

| Check | Result |
| --- | --- |
| Delivery choices, 390 and 1440 | [390](after-390-metode-pengiriman.jpg), [1440](after-1440-metode-pengiriman.jpg) |
| Cart | [390](after-390-cart.jpg), [1440](after-1440-cart.jpg) |
| Checkout | [top 390](after-390-checkout-top.jpg), [total 390](after-390-checkout-total.jpg), [top 1440](after-1440-checkout-top.jpg), [total 1440](after-1440-checkout-total.jpg): label "Total", the short hint |

## Tests

- PHP: the full suite gave 863 passed, 10 skipped and 3 failed. The 3 failures are the known local-only ones (J&T handover in `OrderApiWriteTest` and `OrderOperationalUxTest`, and `ReleaseCheckTest`), the same as in ORD-17.
- After the text changes, the checkout, cart and polish tests were re-run: 28 passed.
- JS: 109 passed. Build OK. Pint is clean on the changed files.

The "before" state is in [ORD-17 verification](../ord-17/README.md):
- full-screen panel;
- Palu quick picks;
- Enter picked the first result.

The keyboard itself cannot be shown in headless Chrome. The Owner should check on a phone: open the card, type, then press Search.

## Live (qammarisparfum.id, after PR #171)

- The cart has none of the removed texts.
- Checkout shows "Diantar langsung oleh kurir.", "Datang setelah pesanan dikonfirmasi." and no "Total sementara".
- In the search card, "Jakarta" + Enter kept the card open and picked nothing ([card](live-390-area-card.jpg)). Tapping Menteng priced J&T from Biteship live at Rp26.500, struck Rp38.000 ([J&T](live-390-jnt.jpg)).
- No order was placed.
- Found: "Jakarta" still listed 8 places. That search was cached before ORD-18, when results were cut to 8. The forward fix gives the area-search cache a new key (`biteship-areas-v2`), so every search is fetched once more with all results.
- After PR #172, live "Jakarta" lists 10 places ([live](live-390-jakarta-after-172.jpg)).

## Owner: back to full screen (same day)

- The Owner tried the card on a phone and preferred the full-screen ORD-17 screen, so it is back on phones (still a centred panel on desktop).
- What stays from ORD-18:
  - no Palu list first;
  - all 10 results, scrolling;
  - Enter/Search never picks a result;
  - the "type more precisely" hint;
  - the retry button.
- To stop the keyboard glitch, the screen's height on phones follows the visible area above the keyboard, and the screen follows that area if the browser shifts it (`visualViewport` resize and scroll).
- Checks: [390](after-390-full-screen.jpg) is full screen 390×844 with 10 results and nothing picked after Enter; [1440](after-1440-full-screen.jpg) is a 512 px panel. JS 109 passed; checkout and polish tests 23 passed; build OK.
- Live after PR #173: full screen at 390×844, "Jakarta" + Enter listed 10 places and picked nothing ([live](live-390-full-screen.jpg)).
