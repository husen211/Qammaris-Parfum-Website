# ORD-27 Ubah pesanan — local verification 2026-11-17

Owner 2026-11-17: staff must be able to swap a sold-out product, change the delivery after a website order (J&T → instant), with the difference to pay or return, also after payment; "staff boleh, jangan kasih batasan staff yang bikin ribet admin". [ADR-062](../../architecture/decisions/ADR-062-staff-change-orders.md).

## Browser (local, synthetic data removed afterwards)

Synthetic paid website order QAM-0031 (2 products, ambil di toko, Rp 1.598.000) and a temporary Staff Order account; both, the auto-created customer and the order's rows were deleted after the check (local orders back to 21, no `example.test` users).

1. Order page (375): "Ubah pesanan" under Produk dipesan — [screenshot](pesanan-375-tombol-ubah.jpg).
2. Ubah pesanan (375): products with Ganti/Hapus and catalog search — [screenshot](ubah-375-produk.jpg). "Ganti" on the second product, search "oud", pick a replacement: the summary showed "Kembalikan ke customer Rp 520.000".
3. Delivery switched to Dalam kota with a pasted point; "Hitung ongkir otomatis" returned real Biteship prices (GrabExpress Rp 11.000, GoSend Rp 15.000); GrabExpress chosen — [screenshot](ubah-375-ongkir-ringkasan.jpg).
4. Saved: the order page showed the WhatsApp message (new products, GrabExpress, total Rp 1.089.000, lebih bayar Rp 509.000) and the "Customer lebih bayar" box; "Sudah dikembalikan" recorded the refund entry.
5. Quantity raised again: message "Kurang bayar Rp 799.000" — [screenshot](hasil-375-kurang-bayar.jpg). Found and fixed: the summary card's "Kurang bayar" ignored the returned amount (showed Rp 290.000); it now nets refunds like the message. A long link in the message overflowed the phone width; it now wraps.
6. Back to the paid amount: "Tidak ada selisih pembayaran", no overflow at 375 — [screenshot](hasil-375-tanpa-selisih.jpg). Desktop edit page — [screenshot](ubah-desktop.jpg).

## Tests

- New `OrderChangeTest` (6): swap after payment and remaining due on the customer link; cheaper change returned by staff and a later increase asks again; J&T → instant keeps packing, a product change repacks; Biteship price bound to the edited lines; refusals (foreign line, no product, after handover); staff cancel a paid order with refund and record it.
- Updated tests that locked the old Super Admin-only refund/cancel rule (Owner decision).
- Full suite and build: see the PR.

## Not verified

- Not on production; no real customer change yet.
- The App was checked by reading its code (lines are not stored; detail re-read per revision), not by an end-to-end run.
