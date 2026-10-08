# Qammaris Order API v1 — kontrak Website ↔ Qammaris App

Status: **PROPOSED — menunggu contract review agen Qammaris App. Belum final dan belum diimplementasikan.** Owner telah mereview Tahap 0 (2026-10-08) dan menetapkan bahwa kontrak tidak boleh difinalkan sebelum review App. Setiap bagian bertanda **[KOORDINASI]** harus disetujui agen/pemilik Qammaris App sebelum kode dibuat.

Versi dokumen: 2026-10-08 r2 (revisi Owner: webhook sebagai jalur utama, otorisasi wajib di backend App, pemisahan handover/delivered/kewajiban finansial). Pemilik kontrak di sisi Website: ORD-02 ([rencana](../planning/ORD-02_PLAN.md), [audit](../audits/2026-10-08-ord-02-online-orders.md)). Skema mesin: [`qammaris-order-api-v1.openapi.yaml`](qammaris-order-api-v1.openapi.yaml).

## 1. Prinsip

1. **Website adalah sumber kebenaran order**: item, harga snapshot, penerima, pembayaran, status persiapan dan pengiriman. App tidak menyimpan versi order yang bisa menimpa Website.
2. **App adalah sumber kebenaran karyawan dan reimbursement.** Identitas karyawan, autentikasi karyawan, expense, dan status reimbursement dikelola App. Website hanya menyimpan referensi dan status yang dilaporkan App.
3. **Komunikasi backend-to-backend.** Tidak ada credential di frontend App, di browser, maupun di link. Karyawan login ke App; backend App yang memanggil Website.
4. **Setiap mutasi**: autentikasi layanan, otorisasi aksi, `Idempotency-Key`, `expected_revision`, dan audit dengan identitas aktor.
4a. **Backend App wajib memvalidasi** bahwa karyawan sudah login, aktif, punya role yang sesuai, dan berhak atas aksi tersebut **sebelum** menandatangani request ke Website. Website menjadi lapisan kedua: membatasi aksi per klien dan memvalidasi transisi, tetapi tidak menggantikan otorisasi karyawan di App.
5. **Tidak ada klaim sinkron sebelum ada konfirmasi.** Website menganggap App sudah tahu hanya setelah App membalas 2xx. App menganggap perubahan berhasil hanya setelah Website membalas 2xx dengan revision baru.
6. **Konsisten dengan integrasi produk yang sudah berjalan** (HMAC `X-Qammaris-*`), tetapi dengan **credential terpisah** dan scope order.

## 2. Arah komunikasi

```text
Qammaris App backend ──(HTTPS, ditandatangani)──▶ Website /integrations/qammaris-app/orders/v1/*   (baca + mutasi)
Website outbox ──(HTTPS, ditandatangani)──▶ App webhook  order.changed {order_id, revision}            (JALUR UTAMA, segera setelah commit)
App reconciler ──(tiap 5 menit)──▶ GET /orders?updated_since=…                                          (fallback bila webhook gagal)
```

- Notifikasi Website→App hanya berisi ID dan revision, **bukan data order**. App mengambil detail lewat `GET /orders/{id}`, sehingga data pribadi tidak tersebar di log webhook.
- **Webhook/outbox adalah jalur notifikasi cepat dan utama**: Website mengirim notifikasi segera setelah transaksi commit (worker queue, bukan menunggu jadwal).
- Rekonsiliasi 5 menit dengan `updated_since` + cursor **hanya fallback** bila webhook gagal/terlewat. Notifikasi yang hilang tidak membuat data tertinggal.

**[KOORDINASI-1]** URL webhook App, nama event, dan interval rekonsiliasi (usulan: 5 menit).

## 3. Autentikasi dan otorisasi layanan

Setiap request App → Website membawa header:

| Header | Isi |
|---|---|
| `X-Qammaris-Client` | ID klien tetap, mis. `qammaris-app-prod` |
| `X-Qammaris-Timestamp` | Unix detik (UTC); toleransi ±300 detik |
| `X-Qammaris-Signature` | hex lowercase `HMAC-SHA256(secret, timestamp + "\n" + METHOD + "\n" + path_with_query + "\n" + sha256_hex(raw_body))` |
| `Idempotency-Key` | wajib untuk POST/PUT/PATCH; 16–128 karakter `[A-Za-z0-9_-]`, unik per mutasi logis |
| `X-Request-Id` | opsional, untuk korelasi log |

- Secret order (`QAMMARIS_ORDER_API_SECRET`) **berbeda** dari API key feed produk dan webhook secret produk. Rotasi dilakukan dengan dua secret aktif sementara (`current`, `previous`).
- Notifikasi Website → App memakai skema tanda tangan yang sama dengan secret terpisah (`QAMMARIS_ORDER_WEBHOOK_SECRET`).
- Otorisasi berlapis: (1) backend App memvalidasi user, role, dan izin aksi sebelum menandatangani (§1 butir 4a); (2) Website memvalidasi tanda tangan, scope klien, dan transisi. Klien App hanya boleh memanggil endpoint di dokumen ini. Klien App **tidak bisa** menandai Lunas, mengubah harga, item, penerima, refund, atau koreksi pembayaran; semua itu dilakukan di Website oleh Staff Order/Super Admin.
- Body ≤ 64 KiB (kecuali unggahan QR, ≤ 2 MiB). Rate limit awal 120 request/menit per klien.

**[KOORDINASI-2]** Setuju HMAC per request (dipilih karena Website tidak memakai Sanctum dan pola ini sudah ada di kedua repo) atau bearer token berotasi. Juga perlu disepakati penyimpanan secret di App dan prosedur rotasi.

## 4. Identitas aktor

Setiap mutasi menyertakan objek `actor` dari karyawan yang sudah diautentikasi App:

```json
{ "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

- Website **mempercayai** aktor karena request ditandatangani backend App. Website mencatat `app_user_id` + `display_name` di audit dan tidak memetakannya ke akun Website.
- `app_user_id` harus stabil dan tidak dipakai ulang. Website tidak menyimpan email/HP karyawan.
- Dari sisi Website, yang dicatat adalah "App: Andi". Akun Website (Super Admin/Staff Order) tetap terpisah.

**[KOORDINASI-3]** Format `app_user_id`, daftar `app_role`, dan apakah ada aksi yang hanya boleh dilakukan role App tertentu (mis. hanya `owner` boleh override claim).

## 5. Konvensi data

- Waktu: ISO 8601 UTC dengan `Z` (`2026-10-08T03:15:00Z`). Tampilan WITA (Asia/Makassar) urusan UI.
- Uang: **integer rupiah** (`650000`) + `currency: "IDR"` di level order. Website menolak order dengan pecahan rupiah.
- ID order: `id` = ULID publik stabil (bukan ID integer database); `number` = nomor tampilan `QAM-0001`.
- `revision`: integer naik monoton setiap perubahan yang terlihat oleh App.
- Field yang tidak diketahui **diabaikan** oleh penerima (forward compatible). Enum baru hanya ditambahkan dengan pemberitahuan; penerima menampilkan nilai tak dikenal sebagai "lainnya", bukan error.

## 6. Model order (respons `GET /orders/{id}`)

```json
{
  "id": "01JABCDE2F3G4H5J6K7M8N9P0Q",
  "number": "QAM-0012",
  "revision": 14,
  "created_at": "2026-10-08T01:02:03Z",
  "updated_at": "2026-10-08T03:15:00Z",
  "source": "whatsapp",
  "lifecycle": "active",
  "currency": "IDR",
  "items": [
    {
      "line_id": "01JABCDEFX0000000000000001",
      "product_id": 412,
      "variant_id": 498,
      "brand": "Rasasi",
      "name": "Hawas Fire",
      "volume_ml": 100,
      "unit_price": 650000,
      "quantity": 1,
      "line_total": 650000
    }
  ],
  "packaging": "no_paperbag",
  "totals": { "subtotal": 650000, "adjustments": 0, "shipping_charge": 15000, "total": 665000 },
  "payment": {
    "status": "paid",
    "method": "qris",
    "confirmation_source": "majoo",
    "confirmed_at": "2026-10-08T02:00:00Z",
    "recorded_in_majoo": true
  },
  "fulfillment": {
    "type": "local_delivery",
    "recipient": { "name": "Siti Rahma", "phone": "+6281234567890", "address": "Depan Masjid Raya, pagar hijau", "postcode": null, "location_url": "https://maps.app.goo.gl/…" },
    "note": "Kirim sore",
    "preparation": { "status": "packed", "claimed_by": { "app_user_id": "665f…", "display_name": "Andi" }, "updated_at": "2026-10-08T02:30:00Z" },
    "courier": {
      "booking_responsibility": "store",
      "assignee": { "app_user_id": "665f…", "display_name": "Andi" },
      "provider": "maxim",
      "status": "requested",
      "reference": "MX-889211",
      "requested_at": "2026-10-08T02:40:00Z"
    },
    "jnt": null,
    "handover": { "status": "pending", "handed_to": null, "handed_over_at": null },
    "delivery": { "status": "unconfirmed", "delivered_at": null, "confirmed_by": null }
  },
  "obligations": [
    { "type": "reimbursement", "status": "open", "amount": 15000, "ref": "EXP-2026-0042" }
  ],
  "costs": [
    {
      "expense_ref": "EXP-2026-0042",
      "kind": "actual_shipping",
      "amount": 15000,
      "paid_by": "staff_advance",
      "reported_at": "2026-10-08T02:45:00Z",
      "reimbursement": { "status": "submitted", "updated_at": "2026-10-08T02:45:00Z" }
    }
  ],
  "issues": [],
  "keep": null,
  "links": { "website_admin": "https://<website>/admin/orders/01JABCDE…" }
}
```

Catatan:
- `fulfillment.recipient` hanya dikirim untuk order dengan `lifecycle=active` dan `type` ≠ `pickup` (minimisasi data). Untuk `pickup` hanya `name` + `phone`. Order selesai/batal > 30 hari: `recipient` = `null`. **[KOORDINASI-4]** retensi data penerima di App.
- `jnt` (bila `type=intercity`):
  `{ "status": "pickup_requested|qr_available|picked_up", "pickup_requested_at": "…", "qr_available": true, "qr_url": "/integrations/qammaris-app/orders/v1/orders/{id}/jnt/qr", "picked_up_at": null, "tracking_number": null }`.
  `tracking_number` opsional dan boleh ditambahkan kapan saja.
- `keep` (bila order adalah keep):
  `{ "status": "active|converted|expired|released", "until": "…", "stock_set_aside": { "confirmed": true, "by": {…}, "at": "…" } }`.
  `stock_set_aside` adalah konfirmasi manual staf, **bukan** reservasi stok sistem.

## 7. Status dan transisi

Setiap dimensi berdiri sendiri; pembayaran tidak mengunci persiapan.

| Dimensi | Nilai | Siapa mengubah |
|---|---|---|
| `lifecycle` | `draft` → `awaiting_customer` → `active` → `completed` / `cancelled` | Website. `completed` otomatis bila handover selesai **dan** `payment.status=paid` **dan** tidak ada issue/kewajiban finansial terbuka. **Tidak** menunggu `delivered` |
| `payment.status` | `unpaid`, `paid`, `refund_pending`, `refunded` | Website saja (Staff Order: `paid`; koreksi/refund: Super Admin) |
| `preparation.status` | `not_started` → `preparing` → `packed` | App (claim + progres) atau Website |
| `courier.status` (lokal) | `not_needed` (pickup), `unassigned` → `assigned` → `requested` → `arrived` | App/Website. `booking_responsibility`: `store` atau `customer` |
| `jnt.status` (luar kota) | `not_requested` → `pickup_requested` → `qr_available` → `picked_up` | App/Website; resi opsional. `pickup_requested`/`qr_available` **bukan** bukti paket diambil; hanya `picked_up` yang menandai handover. Request di luar 09.00–16.00 WITA tetap diterima (peringatan di UI) |
| `handover.status` | `pending` → `handed_over` (`handed_to`: `courier`, `customer`, `customer_courier`, `jnt`) | App/Website. Tugas staf selesai di sini |
| `delivery.status` | `unconfirmed` → `delivered` | Terpisah dari handover dan opsional; dari tombol customer, Website, atau App |
| `obligations[]` | `{type: refund\|reimbursement, status: open\|settled, amount, ref}` | Refund: Website (Super Admin). Reimbursement: dilaporkan App. Kewajiban terbuka mencegah `completed` |
| `issues[].status` | `open` → `resolved` | App membuka, Website/App menyelesaikan |

Transisi tidak valid menghasilkan `409 invalid_transition`. Transisi yang sudah tercapai dengan payload identik adalah no-op dan mengembalikan 200 dengan revision saat ini.

## 8. Endpoint

Base: `https://<website>/integrations/qammaris-app/orders/v1`. Semua respons `application/json; charset=utf-8`.

| Method | Path | Fungsi |
|---|---|---|
| GET | `/orders?updated_since=&cursor=&limit=&lifecycle=` | Daftar incremental (urut `updated_at, id`), `limit` 1–100 (default 50) |
| GET | `/orders/{id}` | Detail order |
| POST | `/orders/{id}/claims` | Klaim tugas `preparation` atau `courier_booking` |
| DELETE | `/orders/{id}/claims/{task}` | Lepas klaim (aktor sendiri, atau override bila diizinkan) |
| POST | `/orders/{id}/preparation` | Progres packing: `preparing` / `packed` |
| POST | `/orders/{id}/courier-requests` | Catat request kurir lokal (provider, referensi) |
| POST | `/orders/{id}/jnt` | Catat `pickup_requested`, `qr_available` (+QR), `picked_up`, `tracking_number` |
| PUT | `/orders/{id}/jnt/qr` | Unggah gambar QR (image/png\|jpeg, ≤2 MiB) |
| GET | `/orders/{id}/jnt/qr` | Ambil QR (ditandatangani; tidak publik) |
| POST | `/orders/{id}/handover` | Catat penyerahan ke kurir/customer/J&T |
| POST | `/orders/{id}/delivery` | Konfirmasi diterima (opsional, terpisah dari handover) |
| POST | `/orders/{id}/issues` | Catat kendala |
| POST | `/orders/{id}/issues/{issue_id}/resolve` | Selesaikan kendala |
| PUT | `/orders/{id}/costs/{expense_ref}` | Upsert ringkasan biaya + status reimbursement dari App |

Contoh mutasi:

```http
POST /integrations/qammaris-app/orders/v1/orders/01JABCDE…/claims
X-Qammaris-Client: qammaris-app-prod
X-Qammaris-Timestamp: 1791427500
X-Qammaris-Signature: 9f2c…
Idempotency-Key: claim-665f-01JABCDE-prep-1
Content-Type: application/json

{ "task": "preparation", "expected_revision": 14,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

```json
HTTP/1.1 200 OK
{ "order": { "id": "01JABCDE…", "revision": 15, "…": "…" }, "event_id": "01JABCF0…" }
```

Klaim gagal karena sudah diklaim orang lain:

```json
HTTP/1.1 409 Conflict
{ "error": { "code": "task_already_claimed", "message": "Tugas preparation sudah diklaim oleh Ikrar.",
  "details": { "task": "preparation", "claimed_by": { "app_user_id": "6660…", "display_name": "Ikrar" }, "current_revision": 15 } },
  "request_id": "01JABCF1…" }
```

Request kurir dan J&T:

```json
{ "provider": "maxim", "reference": "MX-889211", "booking_responsibility": "store", "expected_revision": 15, "actor": { "…": "…" } }
{ "status": "pickup_requested", "requested_at": "2026-10-08T03:00:00Z", "expected_revision": 16, "actor": { "…": "…" } }
{ "status": "picked_up", "picked_up_at": "2026-10-08T07:10:00Z", "tracking_number": null, "expected_revision": 18, "actor": { "…": "…" } }
```

Biaya dan reimbursement (App sebagai sumber):

```json
PUT /orders/{id}/costs/EXP-2026-0042
{ "kind": "actual_shipping", "amount": 15000, "paid_by": "staff_advance",
  "reimbursement": { "status": "approved", "updated_at": "2026-10-08T09:00:00Z" },
  "expected_revision": 19, "actor": { "…": "…" } }
```

`kind`: `actual_shipping`, `packaging`, `other`. `paid_by`: `cashier_cash`, `owner_transfer`, `staff_advance`, `customer_direct`. `reimbursement.status` mengikuti App. **[KOORDINASI-5]** enum final status reimbursement App (usulan: `not_applicable`, `submitted`, `approved`, `paid`, `rejected`).

## 9. Error

```json
{ "error": { "code": "revision_conflict", "message": "Order sudah berubah.", "details": { "current_revision": 16 } }, "request_id": "…" }
```

| HTTP | `code` | Arti / tindakan App |
|---|---|---|
| 400 | `bad_request` | Body bukan JSON / header kurang |
| 401 | `invalid_signature`, `stale_timestamp`, `unknown_client` | Jangan retry tanpa memperbaiki |
| 403 | `action_not_allowed` | Aksi di luar scope App |
| 404 | `order_not_found` | ID salah atau order tidak dibagikan ke App (draft) |
| 409 | `revision_conflict` | Ambil ulang order, tampilkan ke karyawan, ulangi bila masih relevan |
| 409 | `invalid_transition`, `task_already_claimed` | Tampilkan pesan, jangan retry otomatis |
| 409 | `idempotency_key_reused` | Key sama dengan payload berbeda |
| 413 | `payload_too_large` | — |
| 422 | `validation_failed` (+ `details.fields`) | Perbaiki input |
| 429 | `rate_limited` (+ `Retry-After`) | Retry setelahnya |
| 500/503 | `server_error`, `unavailable` | Retry dengan backoff + key yang sama |

## 10. Idempotency dan revision

- Website menyimpan `(client, Idempotency-Key)` → hash payload + status + body respons selama **7 hari**.
- Retry dengan key + payload sama mengembalikan respons tersimpan (tanpa efek ganda). Key sama dengan payload berbeda → `409 idempotency_key_reused`.
- Key dibuat App **per aksi karyawan** (bukan per percobaan HTTP), disimpan sebelum request dikirim, dan dipakai ulang saat retry.
- `expected_revision` wajib di semua mutasi. Bila revision berbeda tetapi transisi masih valid dan tidak menimpa perubahan lain (mis. klaim pada order yang hanya berubah pembayarannya), Website **tetap menolak** dengan 409. App memutuskan ulang setelah membaca data terbaru. Aturan ini sederhana dan mencegah dua orang menimpa satu sama lain.

## 11. Notifikasi dan rekonsiliasi

Website → App:

```json
POST <app_webhook_url>
X-Qammaris-Client: qammaris-website-prod
X-Qammaris-Timestamp: …
X-Qammaris-Signature: …
{ "event_id": "01JABCF0…", "type": "order.changed", "order_id": "01JABCDE…", "revision": 15, "occurred_at": "2026-10-08T03:15:00Z" }
```

- Dikirim dari tabel outbox setelah transaksi commit. Retry backoff eksponensial (1m, 5m, 15m, 1j, 6j) sampai 24 jam, lalu ditandai gagal dan ditampilkan di admin.
- App dedup berdasarkan `event_id` dan mengabaikan revision ≤ revision yang sudah dimilikinya. App membalas 2xx **setelah** menyimpan notifikasi secara durable.
- Rekonsiliasi App: `GET /orders?updated_since=<checkpoint>` tiap 5 menit dengan overlap 2 menit; checkpoint hanya maju setelah halaman diproses.
- Event yang dipicu oleh mutasi App sendiri tetap dikirim (App dapat mengabaikannya berdasarkan revision).

## 12. Link tugas staf

Instruksi grup WhatsApp memuat link ke App, bukan link bearer Website:

```text
https://<app-frontend>/orders/<order_id>      (karyawan login di App bila belum)
```

**[KOORDINASI-6]** format deep link App, perilaku saat karyawan belum login, dan apakah App menampilkan order yang belum `active`.

## 13. Versi dan perubahan

- Perubahan breaking hanya lewat `/v2`. Penambahan field/enum tidak breaking (lihat §5).
- Header respons `X-Qammaris-Api-Version: 1`.
- Lingkungan: staging dulu (`qammaris-app-staging` ↔ website staging), production setelah persetujuan Owner.

## Daftar koordinasi

| ID | Topik | Usulan Website |
|---|---|---|
| KOORDINASI-1 | URL webhook App, event, interval rekonsiliasi | `order.changed`, 5 menit |
| KOORDINASI-2 | Skema autentikasi | HMAC per request, secret terpisah per arah |
| KOORDINASI-3 | Identitas aktor & role App | `app_user_id` stabil, `display_name`, `app_role` |
| KOORDINASI-4 | Minimisasi & retensi data penerima di App | Kirim hanya saat aktif; hapus/anonimkan setelah 30 hari selesai |
| KOORDINASI-5 | Enum biaya & reimbursement | Lihat §8 |
| KOORDINASI-6 | Deep link tugas staf di App | `/orders/<id>` |
| KOORDINASI-7 | Override claim (karyawan tidak hadir) | Hanya `app_role=owner`, atau Super Admin di Website |
| KOORDINASI-8 | Penyimpanan QR J&T | Website menyimpan file di disk privat, App mengunggah lewat API |
| KOORDINASI-9 | Siapa menandai `delivered` | Customer (link), Website, dan App; terpisah dari handover |
| KOORDINASI-10 | Contract review & sign-off | Agen App mereview seluruh dokumen + OpenAPI; perubahan dicatat sebagai r3 sebelum implementasi ORD-02e |
