<?php

namespace App\Support\OrderApi;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderClaim;
use App\Models\OnlineOrderCost;
use App\Models\OnlineOrderIssue;
use App\Models\OnlineOrderItem;
use App\Support\OnlineOrderState;
use App\Support\Rupiah;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Order API v1 JSON (contract r4.1 §6). Every shape here is validated against the OpenAPI baseline in tests.
 * Customer contact data is minimised: none in lists, only what the fulfillment needs in detail, none 30 days
 * after an order is closed; webhooks carry no order data at all.
 */
final class OrderSerializer
{
    public const RELATIONS = ['items', 'claims', 'costs', 'issues', 'adjustments'];

    public static function order(OnlineOrder $order): array
    {
        $order->loadMissing(self::RELATIONS);
        $openIssues = $order->issues->where('status', 'open')->count();
        $itemsCents = $order->items->sum(fn (OnlineOrderItem $item) => Rupiah::minorUnits($item->lineTotal()));
        $shippingCents = $order->shipping_payer === 'added_to_transfer' && $order->shipping_fee !== null ? Rupiah::minorUnits($order->shipping_fee) : 0;
        $adjustmentCents = $order->adjustments->where('status', 'approved')->sum(fn ($adjustment) => Rupiah::minorUnits($adjustment->amount));

        return [
            'id' => $order->public_id,
            'number' => $order->code,
            'revision' => $order->revision,
            'created_at' => self::time($order->created_at),
            'updated_at' => self::time($order->updated_at),
            'source' => $order->source,
            'lifecycle' => $order->lifecycle,
            'queue' => self::queue($order, $openIssues),
            'currency' => 'IDR',
            'items' => $order->items->map(fn (OnlineOrderItem $item) => [
                'line_id' => $item->line_id,
                'product_id' => (int) $item->product_id,
                'variant_id' => (int) $item->variant_id,
                'brand' => $item->brand_name,
                'name' => $item->product_name,
                'volume_ml' => $item->volume,
                'unit_price' => self::money($item->unit_price),
                'quantity' => $item->quantity,
                'line_total' => self::money($item->lineTotal()),
            ])->values()->all(),
            'packaging' => $order->packaging,
            'totals' => [
                'subtotal' => self::cents($itemsCents),
                'adjustments' => self::cents($adjustmentCents),
                'shipping_charge' => self::cents($shippingCents),
                'total' => self::cents(max(0, $itemsCents + $shippingCents + $adjustmentCents)),
            ],
            'payment' => [
                'status' => $order->payment_status,
                'method' => $order->payment_method,
                'confirmation_source' => $order->payment_confirmation_source,
                'confirmed_at' => self::time($order->payment_confirmed_at),
                'recorded_in_majoo' => (bool) $order->recorded_in_majoo,
            ],
            'fulfillment' => [
                'type' => $order->fulfillment,
                'recipient' => self::recipient($order),
                'note' => $order->customer_note,
                'preparation' => [
                    'status' => $order->preparation_status,
                    'packed_items' => array_values($order->packed_items ?? []),
                    'updated_at' => self::time($order->preparation_status === 'not_started' ? null
                        : $order->events()->whereIn('kind', ['preparation_started', 'packed'])->reorder('id', 'desc')->value('created_at')),
                ],
                'courier' => $order->fulfillment === 'local_delivery' ? [
                    'booking_responsibility' => $order->courier_booking_responsibility ?? 'store',
                    'provider' => $order->courier_provider,
                    'status' => $order->courier_status ?? 'unassigned',
                    'reference' => $order->courier_reference,
                    'requested_at' => self::time($order->courier_requested_at),
                ] : null,
                'jnt' => $order->fulfillment === 'intercity' ? [
                    'status' => $order->jnt_status ?? 'not_requested',
                    'pickup_requested_at' => self::time($order->jnt_pickup_requested_at),
                    'qr_available' => $order->jnt_qr_path !== null,
                    'picked_up_at' => self::time($order->jnt_picked_up_at),
                    'tracking_number' => $order->tracking_number,
                ] : null,
                'handover' => [
                    'status' => $order->handover_status,
                    'handed_to' => $order->handed_to,
                    'handed_over_at' => self::time($order->handed_over_at),
                ],
                'delivery' => [
                    'status' => $order->delivery_status,
                    'delivered_at' => self::time($order->delivered_at),
                    'confirmed_by' => $order->delivery_confirmed_by,
                ],
            ],
            'claims' => self::claims($order),
            'obligations' => self::obligations($order),
            'costs' => $order->costs->map(fn (OnlineOrderCost $cost) => self::cost($cost))->values()->all(),
            'issues' => $order->issues->map(fn (OnlineOrderIssue $issue) => self::issue($issue))->values()->all(),
            'keep' => $order->keep_status === null ? null : [
                'status' => $order->keepState(),
                'until' => self::time($order->keep_until),
                // Website staff are not App actors; `by` stays null for confirmations made in the Admin PWA.
                'stock_set_aside' => ['confirmed' => $order->keep_stock_confirmed_at !== null, 'by' => null, 'at' => self::time($order->keep_stock_confirmed_at)],
            ],
            'flags' => self::flags($order),
            'links' => ['app_task' => rtrim((string) config('orders_api.app_orders_url'), '/').'/'.$order->public_id],
        ];
    }

    public static function summary(OnlineOrder $order): array
    {
        $order->loadMissing(self::RELATIONS);

        return [
            'id' => $order->public_id,
            'number' => $order->code,
            'revision' => $order->revision,
            'updated_at' => self::time($order->updated_at),
            'lifecycle' => $order->lifecycle,
            'queue' => self::queue($order, $order->issues->where('status', 'open')->count()),
            'source' => $order->source,
            'fulfillment_type' => $order->fulfillment,
            'area' => match ($order->fulfillment) {
                'pickup' => 'pickup',
                'local_delivery' => 'palu',
                'intercity' => 'luar_kota',
                default => null,
            },
            'recipient_display' => self::displayName($order->customer_name),
            'item_count' => (int) $order->items->sum('quantity'),
            'payment_status' => $order->payment_status,
            'preparation_status' => $order->preparation_status,
            'handover_status' => $order->handover_status,
            'jnt_status' => $order->fulfillment === 'intercity' ? ($order->jnt_status ?? 'not_requested') : null,
            'claims' => self::claims($order),
            'flags' => self::flags($order),
        ];
    }

    /** `{event_id, type, order_id, revision, occurred_at}`; no order or customer data (contract §12). */
    public static function webhookEvent(string $eventId, OnlineOrder $order, DateTimeInterface $occurredAt, ?int $revision = null): array
    {
        return ['event_id' => $eventId, 'type' => 'order.changed', 'order_id' => $order->public_id, 'revision' => $revision ?? $order->revision, 'occurred_at' => self::time($occurredAt)];
    }

    public static function displayName(?string $name): ?string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts) {
            return null;
        }
        $display = mb_substr($parts[0], 0, 30);
        if (count($parts) > 1) {
            $display .= ' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';
        }

        return $display;
    }

    private static function queue(OnlineOrder $order, int $openIssues): ?string
    {
        return OnlineOrderState::queue($order, $openIssues, $order->claims->contains('task', 'preparation'));
    }

    private static function flags(OnlineOrder $order): array
    {
        return [
            'unpaid' => $order->payment_status === 'unpaid',
            'open_refund' => $order->payment_status === 'refund_pending',
            'open_reimbursement' => $order->needsReimbursement() || $order->costs->contains(fn (OnlineOrderCost $cost) => $cost->reimbursementOpen()),
        ];
    }

    private static function recipient(OnlineOrder $order): ?array
    {
        $closed = in_array($order->lifecycle, ['completed', 'cancelled'], true);
        if ($order->customer_name === null || $order->customer_phone === null
            || ($closed && $order->closed_at?->lt(now()->subDays((int) config('orders_api.closed_recipient_days'))))) {
            return null;
        }

        return [
            'name' => $order->customer_name,
            'phone' => $order->customer_phone,
            'address' => $order->fulfillment === 'pickup' ? null : $order->address,
            'postcode' => $order->fulfillment === 'intercity' ? $order->postcode : null,
            'location_url' => $order->fulfillment === 'local_delivery' ? $order->location_url : null,
        ];
    }

    private static function claims(OnlineOrder $order): array
    {
        $claims = array_fill_keys(OnlineOrderClaim::TASKS, null);
        foreach ($order->claims as $claim) {
            $claims[$claim->task] = [
                'holder' => ['app_user_id' => $claim->holder_app_user_id, 'display_name' => $claim->holder_display_name],
                'claimed_at' => self::time($claim->claimed_at),
            ];
        }

        return $claims;
    }

    /** Refund obligation from the Super Admin decision; reimbursement obligations derived from App costs (R8). */
    private static function obligations(OnlineOrder $order): array
    {
        $obligations = [];
        if ($order->refund_due_amount !== null && Rupiah::minorUnits($order->refund_due_amount) > 0) {
            $obligations[] = [
                'type' => 'refund', 'status' => in_array($order->refund_status, ['pending', 'partial'], true) ? 'open' : 'settled',
                'amount' => self::money($order->refund_due_amount), 'ref' => null, 'blocks_completion' => true,
            ];
        }
        foreach ($order->costs as $cost) {
            if (Rupiah::minorUnits($cost->reimbursement_amount) === 0) {
                continue;
            }
            $obligations[] = [
                'type' => 'reimbursement', 'status' => $cost->reimbursementOpen() ? 'open' : 'settled',
                'amount' => self::money($cost->reimbursement_amount), 'ref' => $cost->expense_ref, 'blocks_completion' => false,
            ];
        }

        return $obligations;
    }

    private static function cost(OnlineOrderCost $cost): array
    {
        return [
            'expense_ref' => $cost->expense_ref,
            'kind' => $cost->kind,
            'status' => $cost->status,
            'amount' => self::money($cost->amount),
            'funding' => array_map(fn (array $part) => ['source' => $part['source'], 'amount' => (int) $part['amount']], $cost->funding),
            'reimbursement' => [
                'status' => $cost->reimbursement_status,
                'amount' => self::money($cost->reimbursement_amount),
                'proof' => $cost->proof,
                'waiver' => $cost->waiver,
                'updated_at' => self::time($cost->reimbursement_updated_at),
            ],
            'reported_by' => ['app_user_id' => $cost->reported_by_app_user_id, 'display_name' => $cost->reported_by_name],
            'reported_at' => self::time($cost->reported_at),
        ];
    }

    private static function issue(OnlineOrderIssue $issue): array
    {
        return [
            'id' => $issue->public_id,
            'type' => $issue->type,
            'status' => $issue->status,
            'note' => $issue->note,
            'line_id' => $issue->line_id,
            'reported_quantity' => $issue->reported_quantity,
            'opened_by' => self::issueOpenedBy($issue),
            'opened_at' => self::time($issue->created_at),
            'resolved_at' => self::time($issue->resolved_at),
        ];
    }

    /**
     * r4.1 requires an App ActorRef here. An issue opened in the Website Admin PWA has no App user; rather than
     * invent an app_user_id, such issues need the contract clarification agreed with the App agent (open item).
     */
    private static function issueOpenedBy(OnlineOrderIssue $issue): array
    {
        if ($issue->opened_by_app_user_id === null) {
            throw new LogicException('Issue opened in the Website cannot be represented in API v1 r4.1 (opened_by gap).');
        }

        return ['app_user_id' => $issue->opened_by_app_user_id, 'display_name' => $issue->opened_by_name];
    }

    private static function money(mixed $amount): int
    {
        return self::cents(Rupiah::minorUnits((string) $amount));
    }

    /** Whole rupiah; order prices are whole rupiah by catalog rule, so no rounding happens here. */
    private static function cents(int $cents): int
    {
        return intdiv($cents, 100);
    }

    private static function time(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
