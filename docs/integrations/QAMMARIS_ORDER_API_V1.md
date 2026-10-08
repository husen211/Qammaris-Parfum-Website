# Qammaris Order API v1 — kontrak Website ↔ Qammaris App

Status: **BASELINE API v1 — dibekukan (r4.1, OpenAPI `1.0.0-rc.4.1`, 2026-10-08).**
- Agen Qammaris App memberi *conditional sign-off* atas r4 setelah memverifikasi HMAC, 16 contoh JSON, OpenAPI, enum, dan R1–R10. Syaratnya koreksi K-A dan K-B, yang diterapkan di r4.1 (§3, §12).
- Agen App **mengonfirmasi r4.1** tanpa koreksi. Vektor webhook diverifikasi dengan kode signing App sendiri (`order-signing.ts`, branch App `dev/september-2026`).
  - `sha256` raw body dan kedua signature cocok byte demi byte.
  - Header lama ditolak `stale_timestamp` setelah +360 detik; header baru diterima. Toleransi App 300 detik.
  - Konfirmasi App didasarkan pada vektor dan aturan yang dikutip, bukan dari membaca commit Website `a4902ea` (beda repository).
- Endpoint ORD-02e diimplementasikan mengikuti baseline ini dan diuji bersama agen App. Perubahan berikutnya mengikuti [§14](#14-versi).

Riwayat versi:
- r1/r2: draf Tahap 0 dan revisi Owner.
- r3: audit awal App.
- **r4 (2026-10-08)**: menerapkan R1–R10 dari contract review App dan keputusan final Owner R8. Pemetaan per butir ada di [§15](#15-penerapan-r1r10).
  - Review App: `docs/integrations/online-orders-contract-review.md` di repository Qammaris App, commit `6b52426`.
  - Commit itu **masih lokal** di mesin App dan belum ada di GitHub `main`, jadi belum ada tautan yang bisa dibuka.
- **r4.1 (2026-10-08, BASELINE v1)**: koreksi wajib dari sign-off App, dikonfirmasi agen App:
  - **K-A:** `path_with_query` webhook dan vektor uji webhook (§3, §12);
  - **K-B:** setiap retry webhook memakai timestamp dan signature baru (§12).
  Tidak ada perubahan skema payload.
- **Klarifikasi r4.1 (2026-10-09):** aturan klaim per aksi dan `details` pada `403 action_not_allowed` (§8.1). Tidak mengubah skema; OpenAPI tetap `1.0.0-rc.4.1`.
- **Usulan r4.2 (menunggu persetujuan Owner):** `Issue.opened_by` menjadi `ActorRef | null`, ditambah `opened_by_source: "app" | "website"`, agar kendala yang dibuka di Website bisa diserialisasi. Agen App setuju secara teknis. Sampai r4.2 terbit, kendala pesanan V2 hanya dibuka dari App selama integrasi aktif.

Skema mesin: [`qammaris-order-api-v1.openapi.yaml`](qammaris-order-api-v1.openapi.yaml). Markdown dan OpenAPI harus sama. Test `tests/Unit/OrderApiContractTest.php` memvalidasi:
- setiap `$ref` OpenAPI;
- setiap contoh JSON bertanda `<!-- validate: Schema -->` di dokumen ini terhadap skema OpenAPI;
- kesamaan daftar kode error dan enum utama di kedua dokumen;
- setiap operasi punya respons sukses dan error;
- vektor HMAC App → Website dan webhook (termasuk retry melewati toleransi 300 detik), serta path webhook yang sama di Markdown dan OpenAPI.

## 0. Dasar

**Qammaris App** (audit + review App):
- Express + TypeScript + MongoDB. Karyawan login di App. Reimburse memakai akun staf.
- Expense punya ID unik (`exp_<uuid>`) yang mencegah reimburse ganda. Uang kasir dicatat sebagai saran pengeluaran di Tutup Kasir.
- Hosting App **tanpa cron**.

**Keputusan Owner:**
1. Bukti ongkir boleh menyusul. Reimburse tidak bisa disetujui tanpa bukti, kecuali pengecualian Owner yang tercatat.
2. Hanya karyawan aktif dengan izin App `orders.handle` (atau `owner`) yang menangani order. Izin ini dicek App dan **tidak** dipetakan ke Staff Order Website.
3. Deep link WhatsApp mempertahankan tujuan setelah login.
4. Webhook adalah jalur utama.
5. Satu talangan maksimal satu reimburse.
6. **R8 final:** order boleh `completed` walaupun reimburse staf masih `awaiting_proof`/`submitted`/`approved`. Reimburse belum dibayar tetap kewajiban terbuka, tercatat di App dan tampil di detail order Website. Refund customer yang belum selesai tetap terlihat jelas sebagai kasus perlu penanganan.

## 1. Prinsip

1. Website sumber kebenaran **order**; App sumber kebenaran **karyawan, expense, reimburse**.
2. Backend-to-backend. Tidak ada credential di frontend, browser, link, atau log.
3. Otorisasi berlapis:
   - backend App memeriksa karyawan aktif dan (`owner` atau `employee` + `orders.handle`) **sebelum** menandatangani;
   - Website memverifikasi tanda tangan, scope klien, `app_role` untuk aksi khusus owner, dan validitas transisi.
4. Setiap mutasi: tanda tangan, `Idempotency-Key`, aktor, audit; `expected_revision` sesuai §11.
5. Tidak ada klaim sinkron sebelum 2xx dari penerima.

## 2. Arah komunikasi dan rekonsiliasi (R1)

```text
App backend ──(HMAC)──▶ Website /integrations/qammaris-app/orders/v1/*          baca + mutasi
Website outbox ──(HMAC)──▶ POST https://api.qammarisapp.com/api/integrations/website/orders/events   JALUR UTAMA
App (oportunistik) ──▶ GET /orders?updated_since=…  /  GET /orders/{id}         fallback tanpa cron
```

- **Jaminan utama**: webhook Website dikirim segera setelah commit dan **di-retry oleh Website sampai 24 jam** (§12). Website punya worker/cron; App tidak.
- App merekonsiliasi dengan `updated_since` **secara oportunistik**, tanpa jaminan interval:
  - saat halaman Pesanan dibuka;
  - refresh berkala hanya selama halaman aktif (usulan 60 detik, berhenti saat tab tersembunyi);
  - tombol **Sinkronkan**;
  - interval ringan di proses server App bila proses sedang hidup (tidak dijamin).
- Admin Website menampilkan pengiriman webhook yang gagal setelah 24 jam, dengan aksi **Kirim ulang** per order. Itu fitur admin Website, bukan endpoint App.

## 3. Autentikasi layanan

| Header | Isi |
|---|---|
| `X-Qammaris-Client` | ID klien, mis. `qammaris-app-prod` / `qammaris-website-prod` |
| `X-Qammaris-Timestamp` | Unix detik UTC; toleransi ±300 detik |
| `X-Qammaris-Signature` | hex lowercase `HMAC-SHA256(secret, timestamp + "\n" + METHOD + "\n" + path_with_query + "\n" + sha256_hex(raw_body))` |
| `Idempotency-Key` | wajib untuk POST/PUT/DELETE; `[A-Za-z0-9_-]{16,128}` |
| `X-Request-Id` | ID aksi App, untuk korelasi audit dua sisi |

- `path_with_query` = path persis yang dikirim, tanpa host:
  - **App → Website:** **termasuk base** `/integrations/qammaris-app/orders/v1`, dengan query string persis seperti dikirim (urutan dan encoding tidak diubah);
  - **Website → App (webhook, K-A):** `/api/integrations/website/orders/events`, sesuai URL webhook yang disepakati (§12).
- GET/DELETE tanpa body memakai `sha256_hex("")`. Webhook memakai raw body persis yang dikirim.
- Penerima menolak timestamp yang selisihnya lebih dari 300 detik dari jam server (`401 stale_timestamp`).
- Skema ini **berbeda** dari webhook produk lama (`timestamp.rawBody`). Header sama, secret berbeda.
- Secret per arah, rotasi `current` + `previous`: penerima menerima keduanya, pengirim pindah ke yang baru, `previous` dikosongkan setelah 24 jam.
  - Website: `QAMMARIS_ORDER_API_SECRET[_PREVIOUS]` (verifikasi App→Website), `QAMMARIS_ORDER_WEBHOOK_SECRET[_PREVIOUS]` (tanda tangan Website→App).
  - App: `WEBSITE_ORDER_API_URL`, `WEBSITE_ORDER_CLIENT_ID`, `WEBSITE_ORDER_API_SECRET[_PREVIOUS]`, `WEBSITE_ORDER_WEBHOOK_SECRET[_PREVIOUS]`.
  - Nilai secret tidak pernah dikirim lewat chat atau log.
- Body ≤ 64 KiB (QR ≤ 2 MiB). Rate limit awal 120 request/menit per klien.

**Vektor uji** (secret contoh `example-secret-not-real-0123456789`, timestamp `1791427500`):

| Method | `path_with_query` | `sha256_hex(body)` | Signature |
|---|---|---|---|
| POST | `/integrations/qammaris-app/orders/v1/orders/01JABCDE2F3G4H5J6K7M8N9P0Q/claims` dengan body `{"task":"preparation","actor":{"app_user_id":"665f0c2a9b1e4a0012ab34cd","display_name":"Andi","app_role":"employee"}}` | `498cd3c62544bcf5238114dfa5b9696415978066c0941c38b42414f7141a9030` | `5ef1f4b24851124020a94ff92e30dada48125fbbe43d7a75ed1e1ec2f5f4ebdc` |
| GET | `/integrations/qammaris-app/orders/v1/orders?updated_since=2026-10-08T00%3A00%3A00Z&limit=50` (body kosong) | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` | `d222078e2b81f8903c97f732dc99c73c72e6823cd3ae0b0efc1647d5999fd8d9` |

## 4. Identitas aktor

<!-- validate: Actor -->
```json
{ "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" }
```

- `app_user_id`: **24 hex huruf kecil** (Mongo ObjectId). Stabil; akun App tidak pernah dihapus permanen.
- `app_role`: `owner` | `employee`. Role `mitra` App tidak memanggil API order.
- `display_name`: snapshot nama saat aksi; Website menyimpannya apa adanya per event.
- Izin `orders.handle` **tidak dikirim**. App yang memeriksanya sebelum menandatangani (Owner 2). Tidak ada pemetaan ke akun Website.
- **Aksi khusus owner**, yang Website tegakkan sebagai lapisan kedua (`403 action_not_allowed` untuk `employee`):
  - melepas/override klaim milik orang lain (wajib `reason`);
  - menyelesaikan issue yang dibuka orang lain.

## 5. Konvensi data

- Waktu ISO 8601 UTC (`Z`); tampilan WITA urusan UI.
- Uang integer rupiah, `currency: "IDR"`.
- `id` order = ULID publik stabil; `number` = `QAM-0001`. `revision` naik monoton.
- Field/enum tak dikenal: diabaikan, atau ditampilkan sebagai "lainnya".

## 6. Model order

### 6.1 Minimisasi data customer

| Respons | Data penerima |
|---|---|
| `GET /orders` | Tanpa HP/alamat/lokasi. Hanya `recipient_display` (nama depan + inisial) dan `area` |
| `GET /orders/{id}`, order `active` | `pickup`: nama + HP. `local_delivery`: nama, HP, patokan, link lokasi. `intercity`: nama, HP, alamat, kode pos |
| `GET /orders/{id}`, `completed`/`cancelled` | `recipient` = `null` setelah **30 hari** ditutup |
| Webhook | Tidak pernah berisi data order/customer |

App tidak menyimpan `recipient` di database; data penerima diambil langsung saat detail dibuka oleh pengguna berizin dan tidak di-cache/log. Proyeksi lokal App hanya field non-pribadi (KOORDINASI-4 App).

### 6.2 Contoh `GET /orders/{id}`

<!-- validate: Order -->
```json
{
  "id": "01JABCDE2F3G4H5J6K7M8N9P0Q",
  "number": "QAM-0012",
  "revision": 21,
  "created_at": "2026-10-08T01:02:03Z",
  "updated_at": "2026-10-08T09:00:00Z",
  "source": "whatsapp",
  "lifecycle": "completed",
  "queue": "done",
  "currency": "IDR",
  "items": [
    { "line_id": "01JABCDEFX0000000000000001", "product_id": 412, "variant_id": 498, "brand": "Rasasi", "name": "Hawas Fire",
      "volume_ml": 100, "unit_price": 650000, "quantity": 2, "line_total": 1300000 }
  ],
  "packaging": "no_paperbag",
  "totals": { "subtotal": 1300000, "adjustments": 0, "shipping_charge": 15000, "total": 1315000 },
  "payment": { "status": "paid", "method": "qris", "confirmation_source": "majoo", "confirmed_at": "2026-10-08T02:00:00Z", "recorded_in_majoo": true },
  "fulfillment": {
    "type": "local_delivery",
    "recipient": { "name": "Siti Rahma", "phone": "+6281234567890", "address": "Depan Masjid Raya, pagar hijau", "postcode": null, "location_url": "https://maps.app.goo.gl/ContohLokasi" },
    "note": "Kirim sore",
    "preparation": { "status": "packed", "packed_items": [ { "line_id": "01JABCDEFX0000000000000001", "quantity": 2 } ], "updated_at": "2026-10-08T02:30:00Z" },
    "courier": { "booking_responsibility": "store", "provider": "maxim", "status": "arrived", "reference": "MX-889211", "requested_at": "2026-10-08T02:40:00Z" },
    "jnt": null,
    "handover": { "status": "handed_over", "handed_to": "courier", "handed_over_at": "2026-10-08T03:05:00Z" },
    "delivery": { "status": "unconfirmed", "delivered_at": null, "confirmed_by": null }
  },
  "claims": {
    "preparation": { "holder": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi" }, "claimed_at": "2026-10-08T02:20:00Z" },
    "courier_booking": { "holder": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi" }, "claimed_at": "2026-10-08T02:35:00Z" },
    "handover": null
  },
  "obligations": [
    { "type": "reimbursement", "status": "open", "amount": 5000, "ref": "exp_9b2f4c1e8a7d4b6f9c0e1a2b3c4d5e6f", "blocks_completion": false }
  ],
  "costs": [
    { "expense_ref": "exp_9b2f4c1e8a7d4b6f9c0e1a2b3c4d5e6f", "kind": "actual_shipping", "status": "active", "amount": 20000,
      "funding": [ { "source": "customer_cash_held", "amount": 15000 }, { "source": "staff_advance", "amount": 5000 } ],
      "reimbursement": { "status": "awaiting_proof", "amount": 5000, "proof": "pending", "waiver": null, "updated_at": "2026-10-08T03:10:00Z" },
      "reported_by": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi" }, "reported_at": "2026-10-08T03:10:00Z" }
  ],
  "issues": [],
  "keep": null,
  "flags": { "unpaid": false, "open_refund": false, "open_reimbursement": true },
  "links": { "app_task": "https://qammarisapp.com/orders/01JABCDE2F3G4H5J6K7M8N9P0Q" }
}
```

Order di atas sudah `completed` walaupun reimburse staf masih `awaiting_proof` (R8). Kewajiban tetap terbuka dan ditandai `flags.open_reimbursement`.

### 6.3 Contoh `GET /orders`

<!-- validate: OrderListPage -->
```json
{
  "data": [
    { "id": "01JABCDE2F3G4H5J6K7M8N9P0Q", "number": "QAM-0012", "revision": 21, "updated_at": "2026-10-08T09:00:00Z",
      "lifecycle": "completed", "queue": "done", "source": "whatsapp", "fulfillment_type": "local_delivery", "area": "palu",
      "recipient_display": "Siti R.", "item_count": 2, "payment_status": "paid", "preparation_status": "packed",
      "handover_status": "handed_over", "jnt_status": null,
      "claims": { "preparation": null, "courier_booking": null, "handover": null },
      "flags": { "unpaid": false, "open_refund": false, "open_reimbursement": true } }
  ],
  "next_cursor": "eyJ1IjoiMjAyNi0xMC0wOFQwOTowMDowMFoiLCJpZCI6IjAxSkFCQ0RFIn0",
  "has_more": false
}
```

`next_cursor` adalah string opak. App mengirimnya kembali tanpa ditafsirkan. Urutan selalu `(updated_at, id)` naik.

## 7. Status dan transisi

| Dimensi | Nilai | Pengubah / aturan |
|---|---|---|
| `lifecycle` | `draft` → `awaiting_customer` → `active` → `completed` / `cancelled` | Website. `completed` bila handover selesai, `payment=paid`, tanpa issue terbuka, dan **tanpa refund terbuka**. Reimburse staf yang belum dibayar **tidak** menahan (R8) |
| `payment.status` | `unpaid`, `paid`, `refund_pending`, `refunded` | Website saja. `refund_pending` = kewajiban refund terbuka, ditampilkan sebagai kasus perlu penanganan |
| `preparation.status` | `not_started` → `preparing` → `packed` | Pemegang klaim `preparation`. `packed` wajib `packed_items` (R4) |
| `courier.status` | `not_needed`, `unassigned` → `requested` → `arrived` | Pemegang klaim `courier_booking`. Bila `booking_responsibility=customer`, toko tidak memesan |
| `jnt.status` | `not_requested` → `pickup_requested` → `qr_available` → `picked_up` | Tiga status terpisah. **Resi tidak wajib.** `picked_up` sekaligus menyetel handover (R5) |
| `handover.status` | `pending` → `handed_over` (`courier`, `customer`, `customer_courier`, `jnt`) | Tugas staf selesai di sini |
| `delivery.status` | `unconfirmed` → `delivered` | Opsional, terpisah dari handover |
| `obligations[]` | `refund`, `reimbursement` | Refund dari pembayaran, reimbursement diturunkan dari biaya. `blocks_completion`: `true` untuk refund, `false` untuk reimbursement (R8) |
| `issues[]` | `open` → `resolved` | Issue terbuka menahan `completed` dan menempatkan order di antrean `has_issue` |

Transisi tidak valid → `409 invalid_transition`. Transisi ke status yang sudah tercapai dengan payload identik → no-op 200 (tanpa event baru).

## 8. Endpoint

Base `https://<website>/integrations/qammaris-app/orders/v1`.

| Method | Path | Request schema | `expected_revision` |
|---|---|---|---|
| GET | `/orders?updated_since=&cursor=&limit=&lifecycle=&queue=` | — | — |
| GET | `/orders/{id}` | — | — |
| POST | `/orders/{id}/claims` | `ClaimRequest` | opsional (§8.1) |
| DELETE | `/orders/{id}/claims/{task}` | `ReleaseClaimRequest` | opsional |
| POST | `/orders/{id}/preparation` | `PreparationRequest` | wajib |
| POST | `/orders/{id}/courier-requests` | `CourierRequest` | wajib |
| POST | `/orders/{id}/jnt` | `JntRequest` | wajib |
| PUT | `/orders/{id}/jnt/qr` | multipart (png/jpeg ≤2 MiB) | wajib |
| GET | `/orders/{id}/jnt/qr` | — | — |
| POST | `/orders/{id}/handover` | `HandoverRequest` | wajib |
| POST | `/orders/{id}/delivery` | `DeliveryRequest` | wajib |
| POST | `/orders/{id}/issues` | `IssueRequest` | opsional |
| POST | `/orders/{id}/issues/{issue_id}/resolve` | `ResolveIssueRequest` | opsional |
| PUT | `/orders/{id}/costs/{expense_ref}` | `CostRequest` | opsional; konflik lewat `source_version` (§8.5) |

Semua mutasi sukses mengembalikan `MutationResponse` `{ order, event_id }`. Replay idempotent mengembalikan respons tersimpan.

### 8.1 Klaim atomik (R2)

- Satu pemegang aktif per `(order_id, task)`; `task` ∈ `preparation`, `courier_booking`, `handover`. Website memakai update bersyarat + unique constraint, bukan cek-lalu-tulis.
- Bila tugas sudah dipegang orang lain → **`409 task_already_claimed`** dengan `details.holder`. Kode ini **diprioritaskan di atas** `revision_conflict`, walaupun `expected_revision` juga berbeda.
- Klaim ulang oleh pemegang sama → no-op 200. Klaim tidak kedaluwarsa otomatis.
- Melepas klaim orang lain hanya oleh `app_role=owner` dengan `reason`, atau Super Admin di Website.
- **Klarifikasi r4.1 (2026-10-09, tanpa perubahan skema; disepakati agen Website dan App):** aksi App membutuhkan klaim yang dipegang aktor itu sendiri.

  | Aksi | Klaim yang harus dipegang |
  |---|---|
  | `POST /preparation` | `preparation` |
  | `POST /courier-requests`; `POST /jnt` dengan status `pickup_requested`, `qr_available`, atau hanya `tracking`; `PUT /jnt/qr` | `courier_booking` |
  | `POST /handover`; `POST /jnt` dengan status `picked_up` | `handover` |
  | `/delivery`, `/issues`, `/issues/{issue_id}/resolve`, `PUT /costs/{expense_ref}` | tanpa klaim |

  - Aturan ini juga berlaku untuk `app_role=owner`.
  - Bila klaim belum ada atau dipegang orang lain, Website menjawab `403 action_not_allowed` dengan `details.task`. Bila klaim dipegang orang lain, `details.holder` (`app_user_id`, `display_name`) ikut disertakan.
  - Pengguna Website (Admin PWA) tidak dibatasi klaim, karena Admin PWA adalah jalur cadangan.
  - App mengklaim lebih dulu, lalu memakai `revision` hasil klaim sebagai `expected_revision`.

<!-- validate: ClaimRequest -->
```json
{ "task": "preparation", "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

<!-- validate: Error -->
```json
{ "error": { "code": "task_already_claimed", "message": "Tugas preparation sedang dipegang Ikrar.",
  "details": { "task": "preparation", "holder": { "app_user_id": "6660a1b2c3d4e5f601234567", "display_name": "Ikrar" }, "claimed_at": "2026-10-08T02:19:58Z" } },
  "request_id": "01JABCF1QWERTYZXPASDFGHJKM" }
```

<!-- validate: ReleaseClaimRequest -->
```json
{ "reason": "Ikrar sakit, tugas dipindahkan", "actor": { "app_user_id": "6650aaaabbbbccccddddeeee", "display_name": "Owner", "app_role": "owner" } }
```

### 8.2 Konfirmasi packing (R4)

- `status=packed` wajib `packed_items` yang mencakup **setiap** `line_id` order, dengan `quantity` **sama persis** dengan jumlah order pada `expected_revision` yang dikirim. Selisih atau baris kurang → `422 validation_failed` (`details.fields`). Revision berbeda → `409 revision_conflict`, karena jumlah harus dikonfirmasi terhadap order terbaru.
- Barang kurang **bukan** `packed`: staf membuka issue `stock_problem` dengan `line_id` dan `reported_quantity`.

<!-- validate: PreparationRequest -->
```json
{ "status": "packed", "packed_items": [ { "line_id": "01JABCDEFX0000000000000001", "quantity": 2 } ], "expected_revision": 15,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

<!-- validate: IssueRequest -->
```json
{ "type": "stock_problem", "note": "Stok Hawas Fire hanya 1", "line_id": "01JABCDEFX0000000000000001", "reported_quantity": 1,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

### 8.3 Kurir lokal

<!-- validate: CourierRequest -->
```json
{ "provider": "maxim", "reference": "MX-889211", "status": "requested", "expected_revision": 16,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

Bila `booking_responsibility=customer`, staf langsung mencatat handover `customer_courier`.

<!-- validate: HandoverRequest -->
```json
{ "handed_to": "customer_courier", "occurred_at": "2026-10-08T03:05:00Z", "expected_revision": 17,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

### 8.4 J&T (R5)

- `pickup_requested`, `qr_available`, dan `picked_up` adalah status terpisah. Request di luar 09.00–16.00 WITA hanya peringatan UI; server tidak menolak.
- `PUT /jnt/qr` menyimpan QR dan **otomatis** menyetel `qr_available`. Bila status masih `not_requested`, `pickup_requested_at` ikut terisi. Event dan revision tetap satu.
- `status=picked_up` menyetel `jnt.status=picked_up` **dan** `handover={handed_over, jnt}` dalam **satu revision dan satu event**. `POST /handover {handed_to: jnt}` diperlakukan sama.
- `tracking_number` opsional di semua status, dan boleh dikirim sendiri belakangan.

<!-- validate: JntRequest -->
```json
{ "status": "picked_up", "occurred_at": "2026-10-08T07:10:00Z", "expected_revision": 18,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

<!-- validate: JntRequest -->
```json
{ "tracking_number": "JX1234567890", "expected_revision": 19,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

### 8.5 Biaya dan reimburse (R6, R7, R9)

- `expense_ref` = **ID expense asli dari App**: string opak `[A-Za-z0-9_-]{8,64}` (App memakai `exp_<uuid>`). Website tidak menafsirkan formatnya. ID ini stabil, kunci upsert, dan **terikat ke satu order**; ID sama untuk order lain → `409 expense_already_linked`.
- `funding[]` (R6) untuk sumber uang campuran. Satu sumber = satu elemen, dan **Σ `funding.amount` = `amount`** (`422` bila tidak).

  | `source` | Arti |
  |---|---|
  | `cashier_cash` | Uang kasir; di App menjadi saran baris Tutup Kasir |
  | `owner_fund` | Dana dari Owner (r3: `owner_transfer`) |
  | `staff_advance` | Talangan pribadi staf; satu-satunya sumber yang di-reimburse |
  | `customer_cash_held` | Uang customer yang dipegang staf, dipakai dulu |
  | `customer_direct` | Customer membayar driver langsung; toko tidak mengeluarkan uang, tidak ada reimburse |
- `status`: `active` | `void`. Koreksi salah catat diberi status void, tidak dihapus.
- `reimbursement` (R7), App sebagai sumber:
  - `status` ∈ `not_applicable`, `awaiting_proof`, `submitted`, `approved`, `paid`, `rejected`, `cancelled`;
  - `amount` = total bagian `staff_advance` (0 dan `not_applicable` bila tidak ada talangan);
  - `proof` ∈ `pending`, `attached`, `waived`.
- **Aturan bukti (Owner 1, divalidasi kedua sisi, `422 proof_required`)**:
  - `approved`/`paid` hanya bila `proof=attached`, atau `proof=waived` dengan `waiver` (`by` owner, `reason`, `at`);
  - `awaiting_proof` berarti `proof=pending`.
- Konkurensi: `source_version` integer naik dari App. Versi lebih lama atau sama → no-op 200. `expected_revision` boleh dikirim, dan bila dikirim divalidasi. Ini supaya pembaruan reimburse mingguan tidak gagal hanya karena order berubah.
- `obligations[type=reimbursement]` **diturunkan Website** dari `costs` (App tidak menulis obligations):
  - `open` saat `awaiting_proof`, `submitted`, `approved`;
  - `settled` saat `paid`, `rejected`, `cancelled`, `not_applicable`, atau biaya `void`.
  `blocks_completion=false` (R8).

<!-- validate: CostRequest -->
```json
{ "kind": "actual_shipping", "status": "active", "amount": 20000,
  "funding": [ { "source": "customer_cash_held", "amount": 15000 }, { "source": "staff_advance", "amount": 5000 } ],
  "reimbursement": { "status": "awaiting_proof", "amount": 5000, "proof": "pending", "waiver": null, "updated_at": "2026-10-08T03:10:00Z" },
  "source_version": 1,
  "actor": { "app_user_id": "665f0c2a9b1e4a0012ab34cd", "display_name": "Andi", "app_role": "employee" } }
```

<!-- validate: CostRequest -->
```json
{ "kind": "actual_shipping", "status": "active", "amount": 20000,
  "funding": [ { "source": "customer_cash_held", "amount": 15000 }, { "source": "staff_advance", "amount": 5000 } ],
  "reimbursement": { "status": "approved", "amount": 5000, "proof": "waived",
    "waiver": { "by": { "app_user_id": "6650aaaabbbbccccddddeeee", "display_name": "Owner" }, "reason": "Struk Maxim hilang, sudah dicek di aplikasi", "at": "2026-10-09T02:00:00Z" },
    "updated_at": "2026-10-09T02:00:00Z" },
  "source_version": 3,
  "actor": { "app_user_id": "6650aaaabbbbccccddddeeee", "display_name": "Owner", "app_role": "owner" } }
```

## 9. Antrean `queue` (R10)

Website menghitung `queue` sebagai **satu sumber aturan**. App menampilkan 7 filter dari field ini, tanpa menghitung sendiri. Aturan dievaluasi **berurutan**; yang pertama cocok dipakai:

| # | Kondisi | `queue` |
|---|---|---|
| 1 | `lifecycle` ∈ `draft`, `awaiting_customer`, `cancelled` | `null` (tidak tampil di antrean App) |
| 2 | Ada `issues[].status=open` | `has_issue` |
| 3 | `lifecycle=completed` | `done` |
| 4 | `handover=handed_over` dan (`fulfillment.type=pickup` atau `delivery=delivered`) | `done` |
| 5 | `handover=handed_over` | `in_delivery` |
| 6 | `preparation=packed` dan (`courier.status` ∈ `requested`, `arrived` atau `jnt.status` ∈ `pickup_requested`, `qr_available`) | `awaiting_pickup` |
| 7 | `preparation=packed` | `ready` |
| 8 | `preparation=preparing` atau klaim `preparation` aktif | `preparing` |
| 9 | Selain itu | `needs_handling` |

Nilai `queue`: `needs_handling`, `preparing`, `ready`, `awaiting_pickup`, `in_delivery`, `done`, `has_issue`.

`flags.unpaid` dan `flags.open_refund` ditampilkan sebagai lencana di semua antrean dan tidak mengubah `queue`.

## 10. Error

<!-- validate: Error -->
```json
{ "error": { "code": "revision_conflict", "message": "Order sudah berubah.", "details": { "current_revision": 16 } }, "request_id": "01JABCF2QWERTYZXPASDFGHJKM" }
```

| HTTP | `code` | Tindakan App |
|---|---|---|
| 400 | `bad_request` | Perbaiki request |
| 401 | `invalid_signature` | Jangan retry tanpa perbaikan |
| 401 | `stale_timestamp` | Periksa jam server |
| 401 | `unknown_client` | Periksa konfigurasi |
| 403 | `action_not_allowed` | Aksi owner-only oleh `employee`, atau di luar scope |
| 404 | `order_not_found` | Order salah atau belum dibagikan |
| 409 | `revision_conflict` | Baca ulang; bila diulang = aksi baru, key baru (§11) |
| 409 | `task_already_claimed` | Tampilkan pemegang, jangan retry |
| 409 | `invalid_transition` | Tampilkan pesan, jangan retry |
| 409 | `expense_already_linked` | Bug data, laporkan |
| 409 | `idempotency_key_reused` | Key sama, payload beda: bug App |
| 413 | `payload_too_large` | Perkecil berkas |
| 422 | `validation_failed` | Perbaiki input (`details.fields`) |
| 422 | `proof_required` | Lampirkan bukti atau pengecualian owner |
| 429 | `rate_limited` | Tunggu `Retry-After` |
| 500 | `server_error` | Retry jaringan, key sama |
| 503 | `unavailable` | Retry jaringan, key sama |

## 11. Idempotency dan revision (R3)

- **Key unik per aksi logis.** Website menyimpan `(client, Idempotency-Key)` → hash payload (termasuk `expected_revision`), status, dan body respons selama 7 hari.
- **Retry jaringan** (timeout, 5xx, 429): key **sama** + payload **identik**. Hasilnya respons tersimpan, tanpa efek ganda. App retry terbatas (±3 kali) hanya selama request staf berjalan; sisanya "Belum terkirim — Kirim ulang" dengan key yang sama. Tidak ada retry latar karena App tanpa cron.
- **Setelah `409 revision_conflict`**: App membaca ulang order. Bila staf/App memutuskan mengulang, itu **aksi logis baru**: key **baru** dan `expected_revision` **baru**. Memakai key lama dengan payload baru → `409 idempotency_key_reused`.
- `expected_revision` wajib untuk preparation, courier, J&T, QR, handover, delivery. Klaim/issue opsional (§8.1). Biaya memakai `source_version` (§8.5).
- Audit Website per event: `app_user_id`, `display_name`, `app_role`, `Idempotency-Key`, `X-Request-Id`. Replay tidak menambah event.

## 12. Webhook Website → App

<!-- validate: WebhookEvent -->
```json
{ "event_id": "01JABCF0QWERTYZXPASDFGHJKM", "type": "order.changed", "order_id": "01JABCDE2F3G4H5J6K7M8N9P0Q", "revision": 15, "occurred_at": "2026-10-08T03:15:00Z" }
```

- Tujuan `POST https://api.qammarisapp.com/api/integrations/website/orders/events` (staging: host API staging App).
- Header HMAC §3 dengan secret webhook. **`path_with_query` = `/api/integrations/website/orders/events`** (tanpa host, tanpa query) (K-A).
- Dikirim segera setelah commit. Retry oleh Website: segera, 1m, 5m, 15m, 1j, 6j, sampai 24 jam; setelah itu tampil di admin Website dengan aksi Kirim ulang.
- **Setiap pengiriman, termasuk retry dan Kirim ulang manual, menghitung `X-Qammaris-Timestamp` dan `X-Qammaris-Signature` baru dari jam saat itu (K-B).**
  - `event_id` dan raw body peristiwa **tidak berubah**, supaya App bisa deduplikasi.
  - Timestamp atau signature lama tidak pernah dipakai ulang; retry setelah 5 menit dengan header lama pasti ditolak `stale_timestamp`.
  - `Idempotency-Key` webhook = `event_id`.

Raw body yang ditandatangani (byte persis):

<!-- hmac-raw-body: webhook -->
```text
{"event_id":"01JABCF0QWERTYZXPASDFGHJKM","type":"order.changed","order_id":"01JABCDE2F3G4H5J6K7M8N9P0Q","revision":15,"occurred_at":"2026-10-08T03:15:00Z"}
```

**Vektor uji webhook** (secret contoh `example-webhook-secret-not-real-9876543210`, `POST`, path `/api/integrations/website/orders/events`):

| Pengiriman | `X-Qammaris-Timestamp` | `sha256_hex(body)` | Signature |
|---|---|---|---|
| Pertama | `1791427500` | `0cd0c66644296d3a2a6e8292c42646ba2ae0c7d8ee0a1aef040f9c0f2ea65fa0` | `3ace8b1498db0e559e75e962f36370db46f2bd3233f928c269b175d144b399b7` |
| Retry +360 detik | `1791427860` | `0cd0c66644296d3a2a6e8292c42646ba2ae0c7d8ee0a1aef040f9c0f2ea65fa0` | `4acc135964061b49f09ae8b6d2a5121450983c535840286716c17b1de8173f6b` |

Pada retry +360 detik, header pertama sudah di luar toleransi 300 detik dan harus ditolak. Header baru diterima. Body dan `event_id` sama.
- App menyimpan event secara durable (unik per `event_id`) **sebelum** membalas 2xx, mengabaikan revision ≤ yang dimiliki, lalu mengambil `GET /orders/{id}`.

## 13. Deep link

`https://qammarisapp.com/orders/<order_id>`:
- hanya berisi ULID order publik;
- bila belum login, karyawan login lalu **kembali ke `/orders/<id>`**; tujuan divalidasi sebagai path internal App;
- tanpa izin → "Tidak punya akses";
- order `draft`/`awaiting_customer` → "Pesanan belum siap ditangani";
- order `completed`/`cancelled` hanya baca di riwayat.

## 14. Versi

Breaking change hanya di `/v2`. Header `X-Qammaris-Api-Version: 1`.

**Kontrol perubahan baseline:**
- Perubahan **breaking** pada v1 (field/enum/kode error dihapus atau diubah artinya, field wajib baru, aturan signature/retry berubah) membutuhkan persetujuan **kedua agen**, dan dicatat sebagai revisi baru di riwayat versi.
- Perubahan non-breaking juga memerlukan pemberitahuan ke agen App.
- `tests/Unit/OrderApiContractTest.php` mengunci SHA-256 file OpenAPI baseline. Setiap perubahan file itu membuat test gagal sampai hash baseline diperbarui bersama catatan revisi dan persetujuannya.

Urutan:
1. r4 conditional sign-off App, r4.1 dikonfirmasi agen App (selesai, 2026-10-08);
2. implementasi Website ORD-02e dan App mengikuti kontrak final;
3. tes kontrak bersama agen App memakai contoh payload dan vektor HMAC resmi dokumen ini;
4. staging bersama;
5. rilis setelah persetujuan Owner.

## 15. Penerapan R1–R10

| # | Review App | r4 |
|---|---|---|
| R1 | Rekonsiliasi oportunistik tanpa cron | §2, §11 |
| R2 | `task_already_claimed` diprioritaskan di atas revision | §8.1 |
| R3 | Key baru setelah revision conflict; retry jaringan key sama | §11 |
| R4 | `packed_items` per baris, divalidasi terhadap revision terbaru | §8.2 |
| R5 | `picked_up` menyetel handover atomik; `PUT qr` → `qr_available` | §8.4 |
| R6 | `funding[]` campuran, `customer_cash_held`, `owner_fund`, biaya `void` | §8.5 |
| R7 | `awaiting_proof`, `cancelled` | §8.5 |
| R8 | Reimburse tertunda tidak menahan `completed` (**keputusan Owner final**); refund tetap menahan dan ditandai | §7, §8.5 |
| R9 | `expense_ref` = ID asli App, opak, stabil | §8.5 |
| R10 | Field `queue` turunan Website, pemetaan berurutan | §9 |
| K-A | `path_with_query` webhook + vektor uji webhook (r4.1) | §3, §12 |
| K-B | Timestamp/signature baru setiap retry webhook; `event_id` dan body tetap (r4.1) | §12 |

Klarifikasi review lain yang ikut diterapkan:
- contoh `GET /orders` (§6.3);
- `X-Request-Id` = ID aksi App;
- issue dengan `line_id`/`reported_quantity`;
- vektor uji HMAC (§3);
- URL webhook App (§12);
- `app_user_id` 24 hex dan `app_role` owner/employee (§4);
- izin `orders.handle` tidak dikirim (§4);
- `recipient` null setelah 30 hari (§6.1).
