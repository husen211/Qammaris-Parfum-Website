# Release ORD-13 + ORD-14 (automatic ongkir, Biteship courier) — Owner runbook

Status: prepared 2026-11-11, **not approved for release yet**. PR [#161](https://github.com/husen211/Qammaris-Parfum-Website/pull/161) (draft, branch `ongkir-otomatis`, CI green). The agent does the Git/GitHub steps (build package, merge, revert) when the Owner says so. Steps marked **(server)** need the Owner's SSH or hPanel; **(Biteship)** is the Owner's Biteship dashboard. No secret or key is ever pasted in chat, Git or a screenshot.

Only the Website changes. The App and the Order API contract do not change.

## 1. Before (Owner)

| Item | Status 2026-11-11 |
|---|---|
| Biteship personal verification | "Selesai" on the verification page; the dashboard banner is gone |
| Biteship balance | Rp0. Top up before step 5: courier fees plus API fees (cek ongkir Rp5, area search Rp2) |
| Biteship **live** API key | Not created yet. Mode Testing **off** → Integrasi → Kunci API → Tambah (Rates, Order, Tracking) |
| Official bank account in production | BRI, verified and in use (checked read-only in the admin) |
| Static QRIS | Coming from the bank; until uploaded the customer sees "QRIS · Segera tersedia" (disabled). Never upload the local sample QRIS |
| Driver pickup contact | The phone in **Info Toko** = the store admin's phone held at the store (Owner 2026-11-11). Check it in production before step 5 |
| Website DB backup | Owner 2026-11-11: no manual dump for this release. The three migrations only add empty nullable columns and change no existing row, and the code rollback leaves them in place. Hostinger's automatic backup (hPanel → Files → Backups) is the fallback; check its date before step 2 |

## 2. Migrations (automatic since OPS-DEPLOY-02)

Owner 2026-11-11: no SSH step. The three migrations are listed in `tools/hostinger/auto-migrations.txt`, so the deploy runs them itself before the switch, while the old code stays live ([ADR-059](../architecture/decisions/ADR-059-deploy-runs-listed-migrations.md)):
- `2026_11_11_100001_add_shipping_quote_to_online_orders`
- `2026_11_12_100001_add_qris_image_to_store_info`
- `2026_11_12_100002_add_courier_booking_to_online_orders`

The manual build-package procedure ([RELEASE_ORD_PWA_RC §5a](RELEASE_ORD_PWA_RC.md)) stays the fallback. It was prepared (package `c90e725`) but not run, because the SSH connection was refused.

## 3. Merge with the switch off

1. **(server)** Confirm the switch is off without printing values: `grep -c '^ORDERS_SHIPPING_QUOTES=true' "$shared/.env"` must print `0`.
2. Agent, on the Owner's go: mark PR #161 ready and merge. CI deploys: listed migrations, then the switch, the health check with automatic rollback, and a worker restart.

What customers and staff see at once, even with the switch off:
- the order page shows the payment choice (transfer, QRIS "Segera tersedia") and no Sharelok request when the order already has a location;
- Super Admin can pack;
- a website order's Pembayaran card shows only the total, the chosen method and Tandai Lunas.

The checkout is unchanged until step 5.

## 4. Keys and webhook (server, then Biteship)

From `$root/current`, with `shared=$root/shared/qammaris-website`. The commands write straight into `.env` and print nothing secret except the webhook URL, which is copied once into Biteship.

```bash
grep -cE '^BITESHIP_(API_KEY|WEBHOOK_SECRET)=' "$shared/.env"     # must be 0 before adding
read -rs -p 'Kunci live Biteship: ' key; echo; printf 'BITESHIP_API_KEY=%s\n' "$key" >> "$shared/.env"; unset key
secret=$(php -r 'echo bin2hex(random_bytes(24));'); printf 'BITESHIP_WEBHOOK_SECRET=%s\n' "$secret" >> "$shared/.env"
echo "https://qammarisparfum.id/integrations/biteship/webhook/$secret"; unset secret
php artisan config:cache
curl -s -o /dev/null -w '%{http_code}\n' -X POST -H 'Content-Type: application/json' -d '{}' "<the URL above>"   # 200
```

**(Biteship)** With Mode Testing off: Pengaturan → API → Webhook URL. Paste the URL; Biteship sends a test with an empty body and the site answers 200. Then clear the terminal scrollback.

## 5. Turn on, check prices, first customer order is the courier test

Owner 2026-11-11 ("cara A"): no test order of our own. The Owner checks prices on the live checkout, and the first real customer order with an instant courier is the booking test, with staff briefed. Every failure has a fallback:
- no price → "Ongkir dikonfirmasi admin";
- booking refused → Biteship's reason and "Pesan manual di aplikasi kurir";
- no webhook → "Perbarui status".

1. **(server)** `ORDERS_SHIPPING_QUOTES=true` in `.env`, then `php artisan config:cache`.
2. From the Owner's phone (Android, and an iPhone if one is available), on the real site, **without placing an order**:
   - Checkout → Kurir instan: the location pop-up, the map, and GoSend/GrabExpress prices with the discount;
   - J&T: search a city and the price shows.

   Each price check costs Rp5 from the Biteship balance.
3. Brief staff (§6) before the store opens.
4. On the first customer order with an instant courier, staff:
   - press **Pesan kurir sekarang** and check that the statuses change by themselves (otherwise the webhook is not arriving: use "Perbarui status" and tell the agent);
   - **note whether the driver's number accepts WhatsApp or only calls** (masked number check).
5. After pickup, check that the order shows "Diserahkan ke kurir", that the courier cost is "saldo Biteship (Owner)" with no reimbursement, and that the Biteship balance went down by the same amount.

If anything is wrong: switch off (§7). Checkout goes back to "Ongkir dikonfirmasi staf" at once.

## 6. Staff guide (Andi, Ikrar)

Pesanan website dengan kurir instan:
1. Konfirmasi pesanan → cek bukti bayar di WhatsApp → **Tandai Lunas** (rekening/QRIS sudah tampil di halaman customer, tidak perlu kirim instruksi).
2. Packing seperti biasa.
3. Pengiriman → **Pesan kurir sekarang**. Kurir pilihan customer sudah terpilih; ganti hanya bila driver tidak ditemukan.
4. Setelah driver ditemukan:
   - **Kabari customer + link lacak**;
   - bila ada kendala: **Chat driver** atau **Telepon driver**.
   - Driver juga punya nomor HP admin toko.
5. Tidak perlu menekan "Sudah diserahkan" atau "Diterima". Status berubah sendiri saat driver mengambil dan mengantar paket. Bila lama tidak berubah, tekan **Perbarui status**.
6. Driver tidak ditemukan: pesan ulang (bisa ganti GoSend/GrabExpress). Mau batal sebelum diambil: **Batalkan kurir** dengan alasan.
7. Tulisan **"belum pasti"**: cari kode pesanan (misalnya `QAM-0040-1`) di dashboard Biteship dulu. Centang "Sudah saya cek" hanya bila memang belum ada pesanannya.
8. Biteship menolak (misalnya saldo habis): kabari Owner, atau pakai **Pesan manual di aplikasi kurir** seperti dulu.
9. Pesanan buatan admin (customer WhatsApp), J&T, dan ambil di toko: tetap seperti sebelumnya.

## 7. Rollback

- **Switch off (instant):** `ORDERS_SHIPPING_QUOTES=false` → `php artisan config:cache`. Checkout returns to staff-confirmed ongkir, and the Biteship button disappears. Already locked ongkir and booked couriers stay on their orders; cancel a booked driver in the Biteship dashboard if needed.
- **Webhook only:** remove the Webhook URL in Biteship; staff use "Perbarui status".
- **Code:** agent reverts the merge commit on `main`; CI deploys the previous code. The three columns stay; the old code ignores them. Never `migrate:rollback` on production.

## 8. Release record (2026-11-11)

- PR #161 squash-merged as `99d1dbc` (Owner: "rilis otomatis aja"). CI on `main` green; Production release run `43035914664` (build + deploy) green.
- The deploy ran the three listed migrations itself (OPS-DEPLOY-02): `add_shipping_quote` 15 ms, `add_qris_image` 4 ms, `add_courier_booking` 5 ms. No manual dump (Owner decision); the build-package/SSH fallback was not used.
- Live checks:
  - `/up` 200, home 200, cart 200;
  - `/cart/checkout/wilayah` 404, as expected with the switch off;
  - the admin "Rekening pembayaran" shows the verified BRI account and the new "QRIS statis" card ("Belum ada QRIS").
- Activation, same evening:
  - Biteship balance Rp10.000 topped up; Mode Testing off; the live key was created by the Owner;
  - the Owner added `BITESHIP_API_KEY`, `BITESHIP_WEBHOOK_SECRET` and `ORDERS_SHIPPING_QUOTES=true` to the shared `.env` through the hPanel File Manager (no SSH);
  - this docs merge redeploys so `config:cache` reads them.
- **Was not done yet before activation (Owner):** Biteship balance and live key, `BITESHIP_WEBHOOK_SECRET`, the webhook URL in Biteship, the QRIS upload, then §5 (switch on, price check on the phone, first customer order as the courier test).
