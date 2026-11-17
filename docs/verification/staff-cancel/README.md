# Staff cancel in the Konfirmasi step — 2026-11-17

Owner: a website order whose stock is missing could not be cancelled by staff; the Konfirmasi step offered only "Konfirmasi pesanan".

Found: Staff Order was already allowed to cancel an order without any payment and not handed over (`orders.cancel`), but the form sat only in "Opsi lanjutan", closed at the bottom of the page. On a phone the staff saw only the hint "Batalkan lewat Opsi lanjutan".

Change: the Konfirmasi step shows "Stok habis atau pesanan tidak jadi? Batalkan pesanan" with the same form (reason, "Ya, batalkan pesanan" with a confirm dialog), shared with Opsi lanjutan through `admin/orders/v2/_cancel-form.blade.php`. Shown only when the user may cancel and no money was received; otherwise the old hint stays. Permissions are unchanged: a paid order is still cancelled by Super Admin, who decides the refund.

Checks:
- `php artisan test` WebsiteCheckoutOrderTest, OnlineOrderV2OperationsTest, CheckoutShippingQuoteTest, OrderPaymentGateTest: 47 passed. The staff cancel test now also asserts the form is inside the Konfirmasi step.
- Local browser as a temporary Staff Order account (removed afterwards) on website draft QAM-0024: 375×812 and desktop, no horizontal overflow. The order was not cancelled in the browser; the POST is covered by the test.

Screenshots: [375](konfirmasi-375.jpg), [desktop](konfirmasi-desktop.jpg).
