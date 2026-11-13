# ADR-060: The Website passes the App's Biteship events on to the App

Status: accepted (ORD-22, Owner 2026-11-13). Released only on the Owner's explicit go.

## Context

The App (Qammaris App, `api.qammarisapp.com`) books GoSend/GrabExpress drivers for grouped Shopee parcels on the same Biteship account as the Website, with `reference_id` starting with `qrm-`. When the Owner tried to add a second webhook for the App, the Biteship dashboard refused it with "Event order.status already been submitted": one account has one webhook per event, and the Website's ORD-14 webhook holds it. Biteship's webhook body has no `reference_id` (documented fields for order.status: event, order_id, status, order_price, courier_* and driver fields), so the Website cannot tell an App order from its body.

## Decision

- An event whose `order_id` the Website does not know (no order with that `courier_reference` and a booking) is passed on to `APP_BITESHIP_FORWARD_URL` with its body as it came and the header `X-Qammaris-Biteship-Secret: APP_BITESHIP_FORWARD_SECRET`. Both values come from the Website environment only. The URL must be https and the secret at least 32 characters, otherwise nothing is passed on.
- Passing on runs in a queued job (`ForwardBiteshipWebhook`, the integration queue): 5-second timeout, 3 attempts on a network error, 5xx or 429; a 4xx is logged and not retried. The log holds only the HTTP status or the error class, never the body (driver phone) or the secret.
- The Website's answer to Biteship does not change: 200 for an unknown order, and the existing 503 while one of the Website's own bookings is in flight (ORD-14 early-event retry). Within that window the App may get the same event more than once.
- Biteship's own URL check (empty body), the Website's own orders, and bodies over 16 KB are not passed on.
- The App re-reads every order from Biteship and ignores orders that are not its own (including the Website's `QAM-…` orders), so the body is only a hint.

## Consequences

- The App's driver statuses depend on the Website being up and its queue worker running. The App keeps its own ~20-second status check as the fallback.
- The webhook's 120/minute throttle is shared by both systems' events.
- The webhook URL in the Biteship dashboard stays the Website's. Changing or removing it also cuts the App off, so both runbooks say so.
- Rejected alternative: asking Biteship for each unknown order (`GET /v1/orders/:id`) to read `reference_id` would add a synchronous Biteship call per App event inside the webhook.
