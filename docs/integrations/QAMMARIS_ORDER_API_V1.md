# Qammaris Order API v1 — kontrak Website ↔ Qammaris App

Status: **PROPOSED r3 — menunggu contract review agen Qammaris App. Belum final dan belum diimplementasikan.** Endpoint integrasi tidak dibuat sebelum kedua pihak menyepakati v1 (keputusan Owner 2026-10-08). Bagian bertanda **[KOORDINASI]** masih perlu jawaban App.

Riwayat versi:
- r1: draf Tahap 0.
- r2: revisi Owner — webhook jalur utama, otorisasi wajib di backend App, handover/delivered/kewajiban finansial dipisah.
- **r3 (2026-10-08)**: diselaraskan dengan audit awal App dan keputusan Owner:
  - App tanpa cron → webhook + sinkron saat halaman dibuka/aktif;
  - izin App `orders.handle`;
  - expense ID unik + status reimbursement dengan bukti menyusul/pengecualian Owner;
  - klaim atomik;
  - deep link yang mempertahankan tujuan setelah login.

Pemilik di sisi Website: ORD-02 ([rencana](../planning/ORD-02_PLAN.md), [audit](../audits/2026-10-08-ord-02-online-orders.md)). Skema mesin: [`qammaris-order-api-v1.openapi.yaml`](qammaris-order-api-v1.openapi.yaml).

## 0. Fakta App yang menjadi dasar (audit agen App, 2026-10-08)

- Backend Express + TypeScript + MongoDB; karyawan login di App. Reimbursement memakai akun staf yang login.
- Expense punya **ID unik** yang mencegah reimbursement ganda.
- Belum ada ledger kas keluar terpisah; pengeluaran kasir terkait **Tutup Kasir**.
- Hosting App **tidak punya cron**. Sinkronisasi App hanya bisa dipicu webhook masuk atau aktivitas pengguna (halaman dibuka/aktif).
- Deep link order dari WhatsApp harus **mempertahankan tujuan setelah login**.

Keputusan Owner terkait:
- Hanya karyawan dengan izin App `orders.handle` yang boleh menangani order.
- Role Staff Order Website dan izin operasional App **terpisah**; tidak ada pemetaan otomatis antar keduanya.
- Bukti ongkir boleh menyusul. **Approval reimbursement mensyaratkan bukti, atau pengecualian Owner yang diaudit.**

## 1. Prinsip

1. **Website adalah sumber kebenaran order**: item, harga snapshot, penerima, pembayaran, status persiapan dan pengiriman.
2. **App adalah sumber kebenaran karyawan, expense, dan reimbursement.** Website menyimpan referensi expense dan status yang dilaporkan App.
3. **Backend-to-backend.** Tidak ada credential di frontend App, browser, atau link.
4. **Otorisasi berlapis.** Backend App memastikan karyawan login, aktif, dan punya `orders.handle` **sebelum** menandatangani request. Website memverifikasi tanda tangan, scope klien, `actor.permission`, dan validitas transisi. Website tidak menggantikan otorisasi App.
5. **Setiap mutasi**: tanda tangan, `Idempotency-Key`, identitas aktor, dan audit. `expected_revision` dipakai untuk perubahan state order (§10).
6. **Tidak ada klaim sinkron sebelum konfirmasi.** Website menganggap notifikasi terkirim hanya setelah App membalas 2xx. App menganggap perubahan diterapkan hanya setelah Website membalas 2xx dengan revision baru.

## 2. Arah komunikasi

```text
App backend ──(HTTPS, ditandatangani)──▶ Website /integrations/qammaris-app/orders/v1/*        (baca + mutasi)
Website outbox ──(HTTPS, ditandatangani)──▶ App webhook  order.changed {order_id, revision}  (JALUR UTAMA, segera setelah commit, retry oleh Website)
App saat halaman dibuka/aktif ──▶ GET /orders/{id}  atau  GET /orders?updated_since=…        (rekonsiliasi tanpa cron App)
```

- Website mengirim notifikasi **segera** setelah commit lewat worker queue. Website (punya cron/worker) yang menanggung **retry**, karena App tidak bisa menjadwalkan polling.
- Notifikasi hanya berisi ID + revision, bukan data order, sehingga data pribadi tidak masuk log webhook.
- Rekonsiliasi App terjadi **saat karyawan membuka/mengaktifkan halaman**:
  - daftar order: `GET /orders?updated_since=<checkpoint terakhir App>`;
  - detail order: selalu `GET /orders/{id}` sebelum menampilkan aksi.
  Tidak ada asumsi polling berkala di App.

**[KOORDINASI-1]** URL webhook App dan nama event. Usulan: `POST <app>/api/integrations/website/order-events`, event `order.changed`.

## 3. Autentikasi layanan

| Header | Isi |
|---|---|
| `X-Qammaris-Client` | ID klien tetap, mis. `qammaris-app-prod` |
| `X-Qammaris-Timestamp` | Unix detik UTC; toleransi ±300 detik |
| `X-Qammaris-Signature` | hex lowercase `HMAC-SHA256(secret, timestamp + "\n" + METHOD + "\n" + path_with_query + "\n" + sha256_hex(raw_body))` |
| `Idempotency-Key` | wajib untuk POST/PUT/PATCH/DELETE; 16–128 karakter `[A-Za-z0-9_-]` |
| `X-Request-Id` | opsional, korelasi log |

- Secret order **terpisah** dari API key feed produk dan webhook produk; satu secret per arah (`QAMMARIS_ORDER_API_SECRET` App→Website, `QAMMARIS_ORDER_WEBHOOK_SECRET` Website→App). Rotasi dengan dua secret aktif (`current`, `previous`).
- Klien App **tidak bisa** menandai Lunas, mengubah harga/item/penerima, refund, koreksi pembayaran, atau membatalkan order. Semua itu di Website (Staff Order/Super Admin).
- Body ≤ 64 KiB (QR ≤ 2 MiB). Rate limit awal 120 request/menit per klien.

**[KOORDINASI-2]** Konfirmasi HMAC per request (pola yang sudah dipakai kedua repo), lokasi penyimpanan secret di App, dan prosedur rotasi.

## 4. Identitas karyawan dan izin

Setiap mutasi menyertakan `actor` yang **sudah diverifikasi backend App**:

```json
{ "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee", "permission": "orders.handle" } }
```

- `app_user_id`: ID karyawan App yang **stabil dan tidak pernah dipakai ulang** (usulan: ObjectId MongoDB 24 hex). `display_name` hanya untuk tampilan dan boleh berubah; audit Website menyimpan keduanya per event.
- `permission` wajib bernilai `orders.handle`. Website menolak mutasi tanpa nilai ini (`403 action_not_allowed`). Ini **bukan** pengganti cek di App, tetapi bukti eksplisit bahwa App sudah mengeceknya, dan tercatat di audit.
- Website tidak memetakan karyawan App ke akun Website. Role Staff Order Website dan `orders.handle` App terpisah (keputusan Owner).
- Website tidak menyimpan email/HP karyawan.
- Aksi override (lepas klaim milik orang lain) mensyaratkan `actor.app_role` yang disepakati, atau dilakukan Super Admin di Website.

**[KOORDINASI-3]** Daftar `app_role` App dan role mana yang boleh override klaim (usulan: `owner`).

## 5. Konvensi data

- Waktu: ISO 8601 UTC dengan `Z`. Tampilan WITA (Asia/Makassar) urusan UI.
- Uang: **integer rupiah** + `currency: "IDR"`.
- ID order: `id` = ULID publik stabil (bukan ID database); `number` = tampilan `QAM-0001`.
- `revision`: integer naik monoton setiap perubahan yang terlihat oleh App.
- Field tak dikenal diabaikan penerima; enum tak dikenal ditampilkan sebagai "lainnya".

## 6. Model order

### 6.1 Minimisasi data customer

| Respons | Data penerima |
|---|---|
| `GET /orders` (daftar) | **Tidak ada** HP/alamat/lokasi. Hanya `recipient_display` (nama depan + inisial, mis. "Siti R.") dan `area` (`pickup`/`palu`/`luar_kota`) |
| `GET /orders/{id}` untuk order `active` | `recipient` lengkap **sesuai kebutuhan tugas**: `pickup` → nama + HP; `local_delivery` → nama, HP, patokan, link lokasi; `intercity` → nama, HP, alamat lengkap, kode pos |
| `GET /orders/{id}` untuk order `completed`/`cancelled` | Setelah 7 hari ditutup: `recipient` = `null` |

Webhook tidak pernah membawa data customer. **[KOORDINASI-4]** App tidak menyimpan `recipient` permanen. Usulan: hanya cache memori/sesi saat halaman dibuka, tidak masuk koleksi Mongo dan tidak masuk log.

### 6.2 Contoh `GET /orders/{id}`

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
    { "line_id": "01JABCDEFX0000000000000001", "product_id": 412, "variant_id": 498, "brand": "Rasasi", "name": "Hawas Fire",
      "volume_ml": 100, "unit_price": 650000, "quantity": 1, "line_total": 650000 }
  ],
  "packaging": "no_paperbag",
  "totals": { "subtotal": 650000, "adjustments": 0, "shipping_charge": 15000, "total": 665000 },
  "payment": { "status": "paid", "method": "qris", "confirmation_source": "majoo", "confirmed_at": "2026-10-08T02:00:00Z", "recorded_in_majoo": true },
  "fulfillment": {
    "type": "local_delivery",
    "recipient": { "name": "Siti Rahma", "phone": "+6281234567890", "address": "Depan Masjid Raya, pagar hijau", "postcode": null, "location_url": "https://maps.app.goo.gl/…" },
    "note": "Kirim sore",
    "preparation": { "status": "packed", "updated_at": "2026-10-08T02:30:00Z" },
    "courier": { "booking_responsibility": "store", "provider": "maxim", "status": "requested", "reference": "MX-889211", "requested_at": "2026-10-08T02:40:00Z" },
    "jnt": null,
    "handover": { "status": "pending", "handed_to": null, "handed_over_at": null },
    "delivery": { "status": "unconfirmed", "delivered_at": null, "confirmed_by": null }
  },
  "claims": {
    "preparation": { "holder": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi" }, "claimed_at": "2026-10-08T02:20:00Z" },
    "courier_booking": null,
    "handover": null
  },
  "obligations": [ { "type": "reimbursement", "status": "open", "amount": 15000, "ref": "EXP-2026-0042" } ],
  "costs": [
    { "expense_id": "EXP-2026-0042", "kind": "actual_shipping", "amount": 15000, "paid_by": "staff_advance",
      "proof": { "status": "pending" },
      "reimbursement": { "status": "submitted", "updated_at": "2026-10-08T02:45:00Z" },
      "reported_by": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi" }, "reported_at": "2026-10-08T02:45:00Z" }
  ],
  "issues": [],
  "keep": null,
  "links": { "app_task": "https://<app-frontend>/orders/01JABCDE2F3G4H5J6K7M8N9P0Q" }
}
```

- `jnt` (bila `type=intercity`): `{ "status": "not_requested|pickup_requested|qr_available|picked_up", "pickup_requested_at": "…", "qr_available": true, "qr_url": "/integrations/qammaris-app/orders/v1/orders/{id}/jnt/qr", "picked_up_at": null, "tracking_number": null }`.
- `keep` (bila keep): `{ "status": "active|converted|expired|released", "until": "…", "stock_set_aside": { "confirmed": true, "by": {…}, "at": "…" } }`. Konfirmasi manual, bukan reservasi stok.

## 7. Status dan transisi

Setiap dimensi berdiri sendiri; pembayaran tidak mengunci persiapan.

| Dimensi | Nilai | Pengubah |
|---|---|---|
| `lifecycle` | `draft` → `awaiting_customer` → `active` → `completed` / `cancelled` | Website. `completed` bila handover selesai + `payment=paid` + tanpa issue/kewajiban terbuka; **tidak** menunggu `delivered` |
| `payment.status` | `unpaid`, `paid`, `refund_pending`, `refunded` | Website saja |
| `preparation.status` | `not_started` → `preparing` → `packed` | App (pemegang klaim `preparation`) atau Website |
| `courier.status` (lokal) | `not_needed`, `unassigned` → `requested` → `arrived` | App (pemegang klaim `courier_booking`) atau Website. `booking_responsibility`: `store`/`customer`; bila `customer`, toko tidak memesan kurir |
| `jnt.status` | `not_requested` → `pickup_requested` → `qr_available` → `picked_up` | App/Website. **Tiga status terpisah**; `pickup_requested` dan `qr_available` bukan bukti diambil. **Nomor resi tidak wajib** di status mana pun dan bisa ditambahkan kapan saja. Request di luar 09.00–16.00 WITA tetap diterima (peringatan UI) |
| `handover.status` | `pending` → `handed_over` (`courier`, `customer`, `customer_courier`, `jnt`) | App/Website. Tugas staf selesai di sini. Untuk J&T, `picked_up` = handover |
| `delivery.status` | `unconfirmed` → `delivered` | Opsional, terpisah; customer (link), Website, atau App |
| `obligations[]` | refund (Website/Super Admin), reimbursement (status dari App) | Kewajiban terbuka mencegah `completed` |
| `issues[]` | `open` → `resolved` | App/Website |

Transisi tidak valid → `409 invalid_transition`. Transisi ke status yang sudah tercapai dengan payload identik → no-op 200.

## 8. Endpoint

Base: `https://<website>/integrations/qammaris-app/orders/v1`.

| Method | Path | Fungsi | `expected_revision` |
|---|---|---|---|
| GET | `/orders?updated_since=&cursor=&limit=&lifecycle=` | Daftar incremental, tanpa data kontak | — |
| GET | `/orders/{id}` | Detail sesuai §6.1 | — |
| POST | `/orders/{id}/claims` | Klaim tugas secara atomik | opsional |
| DELETE | `/orders/{id}/claims/{task}` | Lepas klaim | opsional |
| POST | `/orders/{id}/preparation` | `preparing` / `packed` | wajib |
| POST | `/orders/{id}/courier-requests` | Catat request kurir lokal | wajib |
| POST | `/orders/{id}/jnt` | `pickup_requested`, `qr_available`, `picked_up`, tambah/ubah `tracking_number` | wajib |
| PUT | `/orders/{id}/jnt/qr` | Unggah QR (png/jpeg ≤2 MiB) | wajib |
| GET | `/orders/{id}/jnt/qr` | Ambil QR (bertanda tangan) | — |
| POST | `/orders/{id}/handover` | Penyerahan ke kurir/customer/J&T | wajib |
| POST | `/orders/{id}/delivery` | Konfirmasi diterima (opsional) | wajib |
| POST | `/orders/{id}/issues` | Catat kendala | opsional |
| POST | `/orders/{id}/issues/{issue_id}/resolve` | Selesaikan kendala | opsional |
| PUT | `/orders/{id}/costs/{expense_id}` | Upsert biaya + status bukti/reimbursement | tidak dipakai (lihat §8.5) |

### 8.1 Siapa boleh apa

Setiap mutasi memerlukan `actor.permission = orders.handle`. Mutasi tugas hanya oleh **pemegang klaim** tugas itu, atau oleh aktor override yang disepakati: `preparation` untuk `preparation`, `courier_booking` untuk `courier-requests`, dan `handover` untuk `handover`/`jnt`. Bila belum ada klaim, mutasi tugas mengklaim otomatis secara atomik (§8.2).

### 8.2 Klaim atomik

- Satu pemegang per `(order, task)`; `task` ∈ `preparation`, `courier_booking`, `handover`.
- Website menerapkannya dengan **compare-and-set dalam transaksi + unique constraint** pada klaim aktif `(order_id, task)`. Dua klaim bersamaan menghasilkan tepat satu sukses; yang lain `409 task_already_claimed` dengan identitas pemegang.
- Klaim ulang oleh pemegang yang sama = no-op 200. Klaim **tidak kedaluwarsa otomatis** (App tanpa cron; Website tidak melepas diam-diam). Klaim yang macet dilepas oleh pemegangnya, override App yang disepakati, atau Super Admin di Website. Semuanya tercatat.
- `expected_revision` opsional untuk klaim. Bila dikirim dan tidak cocok → 409 `revision_conflict`. Bila tidak dikirim, keamanan dijamin oleh compare-and-set klaim. Ini supaya perubahan pembayaran di Website tidak menggagalkan klaim karyawan.

```http
POST /orders/01JABCDE…/claims
Idempotency-Key: 665f0c2a-01JABCDE-claim-preparation-1
{ "task": "preparation", "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee", "permission": "orders.handle" } }
```

```json
HTTP/1.1 409 Conflict
{ "error": { "code": "task_already_claimed", "message": "Tugas preparation sedang dipegang Ikrar.",
  "details": { "task": "preparation", "holder": { "app_user_id": "6660a1b2c3d4e5f601234567", "display_name": "Ikrar" }, "claimed_at": "2026-10-08T02:19:58Z" } },
  "request_id": "01JABCF1…" }
```

### 8.3 Kurir lokal

```json
{ "provider": "maxim", "reference": "MX-889211", "booking_responsibility": "store", "expected_revision": 15, "actor": { "…": "…" } }
```

Bila `booking_responsibility=customer`, endpoint ini tidak dipakai; staf langsung mencatat `handover` dengan `handed_to=customer_courier`.

### 8.4 J&T

```json
{ "status": "pickup_requested", "occurred_at": "2026-10-08T03:00:00Z", "expected_revision": 16, "actor": { "…": "…" } }
{ "status": "qr_available", "occurred_at": "2026-10-08T03:01:00Z", "expected_revision": 17, "actor": { "…": "…" } }
{ "status": "picked_up", "occurred_at": "2026-10-08T07:10:00Z", "expected_revision": 18, "actor": { "…": "…" } }
{ "tracking_number": "JX1234567890", "expected_revision": 19, "actor": { "…": "…" } }
```

- `picked_up` boleh tanpa `tracking_number`. Resi dikirim belakangan, termasuk keesokan hari, dengan request terpisah.
- `qr_available` boleh disertai unggahan QR (`PUT …/jnt/qr`) atau tanpa file bila QR hanya ditunjukkan dari HP. **[KOORDINASI-8]**

### 8.5 Biaya dan reimbursement (expense ID unik)

```http
PUT /orders/{id}/costs/EXP-2026-0042
Idempotency-Key: exp-EXP-2026-0042-v3
{ "kind": "actual_shipping", "amount": 15000, "paid_by": "staff_advance",
  "proof": { "status": "attached", "attached_at": "2026-10-08T05:00:00Z" },
  "reimbursement": { "status": "approved", "updated_at": "2026-10-08T09:00:00Z",
                     "approved_by": { "app_user_id": "6650…", "display_name": "Owner" }, "proof_exception": null },
  "source_version": 3,
  "actor": { "…": "…" } }
```

- `expense_id` = **ID expense App, unik dan tidak berubah**. Satu expense hanya bisa terikat ke **satu** order. Mengirim ID yang sama untuk order lain → `409 expense_already_linked`. Inilah pencegah reimbursement ganda di sisi Website.
- Upsert: App adalah sumber; Website menyimpan versi dengan `source_version` (integer naik dari App) dan **mengabaikan versi yang lebih lama** (no-op 200). `expected_revision` order tidak dipakai, supaya pembaruan status reimbursement tidak gagal karena order berubah.
- `paid_by`: `cashier_cash` (terkait Tutup Kasir di App), `owner_transfer`, `staff_advance`, `customer_direct`.
- `proof.status`: `pending` (boleh menyusul), `attached`, `waived`. `reimbursement.status`: `submitted`, `approved`, `paid`, `rejected`, `not_applicable`.
- **Aturan Owner, divalidasi kedua sisi**: `approved`/`paid` hanya bila `proof.status=attached`, **atau** `proof.status=waived` dengan `proof_exception` (`approved_by` Owner, `reason`, `at`). Website menolak payload yang melanggar (`422 proof_required`) dan menyimpan pengecualian di audit.
- Kewajiban `reimbursement` di order terbuka sampai `paid`/`rejected`/`not_applicable`.

**[KOORDINASI-5]** Format `expense_id`, enum final, dan apakah App memakai `source_version` atau `updated_at` monoton.

## 9. Error

```json
{ "error": { "code": "revision_conflict", "message": "Order sudah berubah.", "details": { "current_revision": 16 } }, "request_id": "…" }
```

| HTTP | `code` | Tindakan App |
|---|---|---|
| 400 | `bad_request` | Perbaiki request |
| 401 | `invalid_signature`, `stale_timestamp`, `unknown_client` | Jangan retry tanpa perbaikan |
| 403 | `action_not_allowed` (termasuk `permission` ≠ `orders.handle`, bukan pemegang klaim) | Tampilkan pesan |
| 404 | `order_not_found` | Order salah atau belum dibagikan (draft) |
| 409 | `revision_conflict` | Ambil ulang order, tampilkan ke karyawan, ulangi bila masih relevan |
| 409 | `task_already_claimed`, `invalid_transition`, `expense_already_linked` | Tampilkan pesan, jangan retry otomatis |
| 409 | `idempotency_key_reused` | Key sama dengan payload berbeda; bug di App |
| 413 | `payload_too_large` | — |
| 422 | `validation_failed`, `proof_required` | Perbaiki input |
| 429 | `rate_limited` (+ `Retry-After`) | Retry setelahnya |
| 500/503 | `server_error`, `unavailable` | Retry dengan backoff dan **key yang sama** |

## 10. Idempotency dan revision

- Website menyimpan `(client, Idempotency-Key)` → hash payload + status + body respons selama **7 hari**. Retry dengan payload sama mengembalikan respons tersimpan tanpa efek ganda; payload berbeda → `409 idempotency_key_reused`.
- App membuat key **per aksi karyawan**, menyimpannya bersama aksi sebelum request dikirim, dan memakai ulang saat retry. Karena App tanpa cron, retry terjadi saat karyawan menekan "Coba lagi" atau saat halaman dibuka ulang, tetap dengan key yang sama.
- `expected_revision` **wajib** untuk perubahan status order (preparation, courier, J&T, handover, delivery, QR). Bila tidak cocok → `409 revision_conflict` + `current_revision`; App mengambil ulang order dan menampilkan data terbaru sebelum karyawan mengulang.
- Pengecualian yang disengaja: klaim (§8.2) dan biaya/reimbursement (§8.5) memakai mekanisme konflik sendiri, compare-and-set klaim dan `source_version`.
- Event audit Website mencatat `app_user_id`, `display_name`, `permission`, `Idempotency-Key`, dan `request_id`. Replay idempotent tidak menambah event.

## 11. Notifikasi dan rekonsiliasi

```json
POST <app_webhook_url>
X-Qammaris-Client: qammaris-website-prod
X-Qammaris-Timestamp: …
X-Qammaris-Signature: …
{ "event_id": "01JABCF0…", "type": "order.changed", "order_id": "01JABCDE…", "revision": 15, "occurred_at": "2026-10-08T03:15:00Z" }
```

- Website mengirim dari outbox **segera setelah commit**. Retry dilakukan Website (worker): segera, 1m, 5m, 15m, 1j, 6j, sampai 24 jam. Setelah itu ditandai gagal dan tampil di admin Website. Tidak ada ketergantungan pada cron App.
- App membalas 2xx **setelah** menyimpan notifikasi secara durable, dedup berdasarkan `event_id`, dan mengabaikan revision ≤ yang sudah dimiliki.
- Rekonsiliasi App tanpa cron:
  - saat daftar order dibuka/kembali aktif: `GET /orders?updated_since=<checkpoint>` (overlap 2 menit; checkpoint maju hanya setelah halaman diproses);
  - saat detail dibuka: `GET /orders/{id}`;
  - sebelum setiap aksi, App memakai revision terbaru dari detail.
- Event akibat mutasi App sendiri tetap dikirim (App boleh mengabaikan berdasarkan revision).

## 12. Deep link tugas staf

Instruksi grup WhatsApp memuat link App, bukan link bearer Website:

```text
https://<app-frontend>/orders/<order_id>
```

- Link **hanya berisi ID order publik**, tanpa token, nama, atau alamat.
- Bila belum login, App menyimpan tujuan dan **mengembalikan karyawan ke order tersebut setelah login**. Tujuan divalidasi sebagai path internal App, bukan URL bebas, untuk mencegah open redirect.
- Setelah login, App memeriksa `orders.handle`. Tanpa izin, App menampilkan "Tidak punya akses" tanpa memuat data order dari Website.
- Order `draft`/`awaiting_customer` tetap bisa dibuka tetapi tanpa aksi.

**[KOORDINASI-6]** Path final App dan perilaku setelah login.

## 13. Versi dan perubahan

- Breaking change hanya lewat `/v2`; penambahan field/enum tidak breaking. Header respons `X-Qammaris-Api-Version: 1`.
- Urutan: contract review App → kontrak v1 disepakati (r-final) → implementasi Website ORD-02e + App → staging bersama → rilis setelah persetujuan Owner.

## Daftar koordinasi

| ID | Topik | Status r3 |
|---|---|---|
| KOORDINASI-1 | URL webhook App + event | Menunggu App (usulan di §2) |
| KOORDINASI-2 | HMAC per request, penyimpanan & rotasi secret | Menunggu App |
| KOORDINASI-3 | `app_role` dan override klaim | Sebagian: `app_user_id` stabil + `orders.handle` (Owner). Role override menunggu App |
| KOORDINASI-4 | Data penerima tidak disimpan permanen di App | Menunggu App |
| KOORDINASI-5 | Format `expense_id`, enum, `source_version` | Sebagian: expense ID unik (audit App), aturan bukti (Owner) |
| KOORDINASI-6 | Deep link + kembali setelah login | Sebagian: kebutuhan disepakati (audit App); path final menunggu App |
| KOORDINASI-7 | Override klaim | Lihat KOORDINASI-3 |
| KOORDINASI-8 | QR J&T: file atau hanya status | Menunggu App |
| KOORDINASI-9 | Penanda `delivered` | Usulan: customer, Website, App |
| KOORDINASI-10 | Contract review & sign-off | **Wajib sebelum implementasi endpoint** |
