<?php

namespace App\Support;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderEvent;
use Illuminate\Support\Carbon;

/** Builds timeline items per audience; customers never receive staff names, funding or internal notes. */
final class OnlineOrderTimeline
{
    /**
     * @return array<int, array{title: string, description: ?string, time: ?Carbon, status: string, actor: ?string}>
     */
    public static function items(OnlineOrder $order, string $audience): array
    {
        $events = $order->relationLoaded('events') ? $order->events : $order->events()->with('actor')->get();
        $current = $order->stepIndex(match (true) {
            $order->stage === OnlineOrder::STAGE_CANCELLED => $events->where('kind', 'cancel')->last()?->stage,
            // V2: a paid order completes at handover; "Diterima" waits for the delivery confirmation.
            $order->isV2() && $order->stage === OnlineOrder::STAGE_COMPLETED && $order->fulfillment !== 'pickup' && $order->delivery_status !== 'delivered' => OnlineOrder::STAGE_SHIPPED,
            default => $order->stage,
        });
        $items = [];

        foreach ($order->steps() as $index => $stage) {
            $event = self::effectiveEvent($events, $stage);
            $status = match (true) {
                $index <= $current => 'completed',
                $index === $current + 1 && $order->stage !== OnlineOrder::STAGE_CANCELLED => 'active',
                default => 'pending',
            };
            $items[] = [
                'title' => match (true) {
                    $stage === OnlineOrder::STAGE_AWAITING_CUSTOMER => 'Pesanan dibuat',
                    // ORD-04: a website checkout already has the recipient; this step is the store's confirmation.
                    // ORD-25: when the customer may pay at once, this is where they pay.
                    $stage === OnlineOrder::STAGE_DETAILS_RECEIVED && $order->source === 'website' && $order->created_by === null => $audience === 'customer' && self::paysFirst($order) ? 'Pembayaran' : 'Dikonfirmasi toko',
                    default => $order->stageLabel($stage),
                },
                'description' => self::description($order, $stage, $status, $audience),
                'time' => $status === 'completed' ? $event?->created_at : null,
                'status' => $status,
                'actor' => $audience !== 'customer' && $status === 'completed' && $event ? $event->actorLabel() : null,
            ];
        }

        if ($order->stage === OnlineOrder::STAGE_CANCELLED) {
            $cancel = $events->where('kind', 'cancel')->last();
            $items = array_values(array_filter($items, fn ($item) => $item['status'] === 'completed'));
            $items[] = [
                'title' => 'Dibatalkan',
                'description' => $audience === 'customer' ? 'Hubungi kami lewat WhatsApp bila ada pertanyaan.' : $order->cancel_reason,
                'time' => $cancel?->created_at,
                'status' => 'error',
                'actor' => $audience !== 'customer' ? $cancel?->actorLabel() : null,
            ];
        }

        return $items;
    }

    /**
     * ORD-25: a website checkout the customer may pay before the store confirms it (ORD-13 payment step: automatic
     * ongkir, final total, one piece per product), whether or not it is confirmed yet.
     */
    private static function paysFirst(OnlineOrder $order): bool
    {
        return $order->isV2() && $order->source === 'website' && $order->created_by === null
            && (bool) config('orders.shipping_quotes.enabled') && OnlineOrderStepper::totalIsFinal($order)
            && $order->items->every(fn ($item) => $item->quantity === 1);
    }

    /** The latest advance into a stage, ignoring one later reverted by an admin correction. */
    private static function effectiveEvent($events, string $stage): ?OnlineOrderEvent
    {
        $advance = $events->where('stage', $stage)->whereIn('kind', ['advance', 'created', 'website_confirmed'])->last();
        $revert = $events->where('stage', $stage)->where('kind', 'revert')->last();

        return $advance && (! $revert || $revert->id < $advance->id) ? $advance : null;
    }

    private static function description(OnlineOrder $order, string $stage, string $status, string $audience): ?string
    {
        $pickup = $order->fulfillment === 'pickup';
        $jnt = $order->isV2() ? $order->fulfillment === 'intercity' : $order->courier === 'jnt';
        $courier = OnlineOrder::COURIERS[$order->isV2() ? $order->courier_provider : $order->courier] ?? 'kurir';

        $websiteCheckout = $order->source === 'website' && $order->created_by === null;
        // ORD-25 (Owner 2026-11-15): the customer reads one next step. A locked ongkir is never "confirmed" again.
        $ongkirOpen = ! OnlineOrderStepper::totalIsFinal($order);
        if ($websiteCheckout && $stage === OnlineOrder::STAGE_DETAILS_RECEIVED) {
            if ($audience === 'customer' && self::paysFirst($order)) {
                return match ($status) {
                    'completed' => 'Pesanan dikonfirmasi toko.',
                    'active' => 'Bayar lewat transfer atau QRIS, lalu kirim bukti bayar di WhatsApp. Admin mengecek pembayaran dan stok.',
                    default => null,
                };
            }

            return match ($status) {
                'completed' => $ongkirOpen ? 'Stok dan ongkir sudah dikonfirmasi.' : 'Stok sudah dikonfirmasi.',
                'active' => 'Admin mengecek stok'.($ongkirOpen ? ' dan ongkir' : '').' lewat WhatsApp. Setelah itu Anda bisa membayar.',
                default => null,
            };
        }
        if ($status === 'completed') {
            return match ($stage) {
                OnlineOrder::STAGE_DETAILS_RECEIVED => $audience === 'customer' ? 'Data penerima sudah kami terima.' : null,
                OnlineOrder::STAGE_PAID => 'Pembayaran sudah dikonfirmasi'.($order->payment_method ? ' ('.OnlineOrder::PAYMENT_METHODS[$order->payment_method].').' : '.'),
                OnlineOrder::STAGE_SHIPPED => $jnt
                    ? 'Paket dikirim lewat J&T'.($order->tracking_number ? '. No. resi: '.$order->tracking_number : '.')
                    : $courier.' sudah dipesan, pesanan segera diantar.',
                default => null,
            };
        }
        if ($status !== 'active') {
            return null;
        }

        return match ($stage) {
            OnlineOrder::STAGE_DETAILS_RECEIVED => 'Menunggu data penerima.',
            OnlineOrder::STAGE_PAID => $audience !== 'customer' ? 'Menunggu pembayaran.'
                : ($ongkirOpen ? 'Ongkir dan pembayaran dikonfirmasi lewat WhatsApp.' : 'Bayar lewat transfer atau QRIS, lalu kirim bukti bayar di WhatsApp.'),
            OnlineOrder::STAGE_SHIPPED => 'Pesanan sedang disiapkan untuk dikirim.',
            OnlineOrder::STAGE_COMPLETED => $pickup ? 'Pesanan siap diambil di toko.'
                : ($audience === 'customer' ? 'Menunggu konfirmasi penerimaan dari Anda.' : 'Pesanan dalam perjalanan.'),
            default => null,
        };
    }
}
