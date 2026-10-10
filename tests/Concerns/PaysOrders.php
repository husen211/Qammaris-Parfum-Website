<?php

namespace Tests\Concerns;

use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\Rupiah;

/** ORD-04 P0: fulfillment needs a fully paid order, so tests that pack or ship record the payment first. */
trait PaysOrders
{
    protected function payInFull(OnlineOrder $order, ?User $by = null, string $method = 'transfer'): OnlineOrder
    {
        $order = $order->fresh();
        $due = Rupiah::minorUnits($order->customerTotal()) - OnlineOrderMoney::totals($order)['received'];
        if ($due <= 0) {
            return $order->fresh(['items']);
        }
        $by ??= User::query()->where('role', User::ROLE_SUPER_ADMIN)->first() ?? User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();

        return app(RecordOnlineOrderMoney::class)->recordPayment($order, $order->revision, $by, Rupiah::decimal($due), $method, 'proof_in_chat')->fresh(['items']);
    }
}
