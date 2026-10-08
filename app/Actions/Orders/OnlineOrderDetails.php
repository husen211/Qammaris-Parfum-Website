<?php

namespace App\Actions\Orders;

use App\Actions\Orders\Concerns\MutatesV2Order;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderActionNotAllowed;
use App\Models\OnlineOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Recipient/order details and the customer link for V2 orders (ORD-02d). V2 orders never go through the
 * ORD-01 workflow; this is the V2 counterpart of its data operations.
 */
class OnlineOrderDetails
{
    use MutatesV2Order;

    public const CUSTOMER_FIELDS = ['customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'packaging', 'customer_note'];

    private const FINANCIAL_FIELDS = ['shipping_fee', 'shipping_payer', 'driver_funding'];

    private const ADMIN_FIELDS = [...self::CUSTOMER_FIELDS, 'location_url', 'recorded_in_majoo', 'staff_note', ...self::FINANCIAL_FIELDS];

    public function __construct(private readonly OnlineOrderChangeRequests $changeRequests) {}

    /**
     * Customer form. Applied directly while unpaid and packing has not started; otherwise it becomes a change
     * request for admin review. Returns whether a review is needed.
     */
    public function submitByCustomer(OnlineOrder $order, array $details): bool
    {
        if (! $order->customerLinkUsable()) {
            throw new InvalidOrderTransition('Link pesanan ini sudah tidak berlaku. Hubungi kami lewat WhatsApp.');
        }
        if ($order->isV2() && OnlineOrderChangeRequests::customerMustRequest($order) && $order->lifecycle !== 'awaiting_customer') {
            $this->changeRequests->submit($order, array_intersect_key($details, array_flip(self::CUSTOMER_FIELDS)));

            return true;
        }
        $this->mutateV2($order, null, null, function (OnlineOrder $order) use ($details): bool {
            $order->fill(array_intersect_key($details, array_flip(self::CUSTOMER_FIELDS)));
            if ($order->lifecycle === 'awaiting_customer') {
                $order->stage = OnlineOrder::STAGE_DETAILS_RECEIVED;
                $this->orderEvent($order, null, 'advance', 'Data penerima dikirim customer');
            } elseif ($order->isDirty()) {
                $this->orderEvent($order, null, 'details_updated', 'Data diubah customer');
            }

            return false;
        });

        return false;
    }

    /** Owner D5: Staff Order corrects recipient details until handover; money fields are Super Admin only. */
    public function updateByAdmin(OnlineOrder $order, int $revision, User $actor, array $data): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);

        return $this->mutateV2($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $data): bool {
            $order->fill(array_intersect_key($data, array_flip(self::ADMIN_FIELDS)));
            if (! Gate::forUser($actor)->allows('orders.finance')) {
                if ($order->isDirty(self::FINANCIAL_FIELDS)) {
                    throw new OrderActionNotAllowed('Perubahan ongkir atau pendanaan driver hanya dapat dilakukan Super Admin.');
                }
                if ($order->isDirty([...self::CUSTOMER_FIELDS, 'location_url'])
                    && (in_array($order->lifecycle, ['completed', 'cancelled'], true) || $order->handover_status === 'handed_over')) {
                    throw new OrderActionNotAllowed('Data penerima tidak dapat diubah setelah pesanan diserahkan atau ditutup. Hubungi Super Admin.');
                }
            }
            if ($order->isDirty('fulfillment') && $order->handover_status === 'handed_over') {
                throw new InvalidOrderTransition('Cara pengiriman tidak dapat diubah setelah diserahkan.');
            }
            if (! $order->isDirty()) {
                return false;
            }
            $this->orderEvent($order, $actor, 'details_updated', 'Diubah: '.implode(', ', array_keys($order->getDirty())));
            if ($order->lifecycle === 'awaiting_customer' && $order->hasCustomerDetails() && $order->customer_phone && $order->packaging) {
                $order->stage = OnlineOrder::STAGE_DETAILS_RECEIVED;
            }

            return true;
        });
    }

    /** New customer link (7 days); the old link stops working immediately. Returns the raw token. */
    public function regenerateCustomerLink(OnlineOrder $order, User $actor): string
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);
        $token = Str::random(40);
        $this->mutateV2($order, null, $actor, function (OnlineOrder $order) use ($actor, $token): bool {
            $order->customer_token_hash = OnlineOrder::tokenHash($token);
            $order->customer_token_encrypted = $token;
            $order->customer_link_expires_at = now()->addDays(OnlineOrder::CUSTOMER_LINK_DAYS);
            $this->orderEvent($order, $actor, 'link_regenerated', 'Link customer');

            return true;
        });

        return $token;
    }
}
