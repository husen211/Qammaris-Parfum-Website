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
        $current = $order->stepIndex($order->stage === OnlineOrder::STAGE_CANCELLED
            ? $events->where('kind', 'cancel')->last()?->stage
            : $order->stage);
        $items = [];

        foreach ($order->steps() as $index => $stage) {
            $event = self::effectiveEvent($events, $stage);
            $status = match (true) {
                $index <= $current => 'completed',
                $index === $current + 1 && $order->stage !== OnlineOrder::STAGE_CANCELLED => 'active',
                default => 'pending',
            };
            $items[] = [
                'title' => $stage === OnlineOrder::STAGE_AWAITING_CUSTOMER ? 'Pesanan dibuat' : $order->stageLabel($stage),
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

    /** The latest advance into a stage, ignoring one later reverted by an admin correction. */
    private static function effectiveEvent($events, string $stage): ?OnlineOrderEvent
    {
        $advance = $events->where('stage', $stage)->whereIn('kind', ['advance', 'created'])->last();
        $revert = $events->where('stage', $stage)->where('kind', 'revert')->last();

        return $advance && (! $revert || $revert->id < $advance->id) ? $advance : null;
    }

    private static function description(OnlineOrder $order, string $stage, string $status, string $audience): ?string
    {
        $pickup = $order->fulfillment === 'pickup';
        $jnt = $order->courier === 'jnt';
        $courier = OnlineOrder::COURIERS[$order->courier] ?? 'kurir';

        if ($status === 'completed') {
            return match ($stage) {
                OnlineOrder::STAGE_DETAILS_RECEIVED => $audience === 'customer' ? 'Data penerima sudah kami terima.' : null,
                OnlineOrder::STAGE_PAID => 'Pembayaran sudah dikonfirmasi'.($order->payment_method ? ' ('.OnlineOrder::PAYMENT_METHODS[$order->payment_method].').' : '.'),
                OnlineOrder::STAGE_COURIER_BOOKED => $pickup ? 'Pesanan siap diambil di toko.' : ($jnt ? 'Menunggu J&T menjemput paket.' : $courier.' sedang menuju toko.'),
                OnlineOrder::STAGE_SHIPPED => $jnt && $order->tracking_number ? 'No. resi: '.$order->tracking_number : 'Pesanan dalam perjalanan.',
                default => null,
            };
        }
        if ($status !== 'active') {
            return null;
        }

        return match ($stage) {
            OnlineOrder::STAGE_DETAILS_RECEIVED => 'Menunggu data penerima.',
            OnlineOrder::STAGE_PAID => $audience === 'customer' ? 'Ongkir dan pembayaran dikonfirmasi lewat WhatsApp.' : 'Menunggu pembayaran.',
            OnlineOrder::STAGE_COURIER_BOOKED => $pickup ? 'Pesanan sedang disiapkan.' : 'Pesanan sedang disiapkan untuk dikirim.',
            OnlineOrder::STAGE_SHIPPED => $jnt ? 'Menunggu paket dijemput J&T.' : 'Menunggu driver menjemput pesanan.',
            OnlineOrder::STAGE_COMPLETED => $pickup ? 'Menunggu pesanan diambil.' : 'Pesanan sedang diantar.',
            default => null,
        };
    }
}
