<?php

namespace App\Actions\Orders;

use App\Exceptions\OnlineOrderRejected;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\Rupiah;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Single authority for order edits and step transitions by admin, customer link and staff link.
 * Every mutation locks the row, validates the current state and records its event in one transaction.
 */
class OnlineOrderWorkflow
{
    private const CUSTOMER_FIELDS = ['customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'packaging', 'customer_note'];

    private const STAFF_STAGES = [OnlineOrder::STAGE_SHIPPED, OnlineOrder::STAGE_COMPLETED];

    private const FINANCIAL_FIELDS = ['shipping_fee', 'shipping_payer', 'driver_funding', 'staff_advance_amount', 'staff_advance_by'];

    private const RECIPIENT_FIELDS = ['customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'location_url', 'packaging', 'customer_note'];

    private const STALE = 'Status pesanan sudah berubah. Muat ulang halaman untuk melihat data terbaru.';

    public function submitCustomerDetails(OnlineOrder $order, array $details): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($details): void {
            if (! $order->customerLinkUsable() || ! $order->customerCanEdit()) {
                throw new OnlineOrderRejected('Data pesanan ini sudah dikunci karena pembayaran telah dikonfirmasi. Hubungi kami lewat WhatsApp untuk perubahan.');
            }
            $order->fill(array_intersect_key($details, array_flip(self::CUSTOMER_FIELDS)));
            if ($order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER) {
                $order->stage = OnlineOrder::STAGE_DETAILS_RECEIVED;
                $this->event($order, 'advance', 'customer', stage: OnlineOrder::STAGE_DETAILS_RECEIVED);
            } elseif ($order->isDirty()) {
                $this->event($order, 'details_updated', 'customer');
            }
        });
    }

    public function update(OnlineOrder $order, int $revision, array $data, User $actor): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($revision, $data, $actor): void {
            $this->assertAdmin($actor);
            if ($order->revision !== $revision) {
                throw new OnlineOrderRejected('Pesanan ini baru saja diubah (oleh customer, staf, atau tab lain). Muat ulang lalu ulangi perubahan Anda.');
            }
            $order->fill($data);
            if ($order->driver_funding !== 'staff_advance' && $order->staff_reimbursed_at === null) {
                $order->staff_advance_amount = null;
                $order->staff_advance_by = null;
            }
            if (! Gate::forUser($actor)->allows('orders.finance')) {
                // Owner D5: Staff Order may correct recipient/address details until handover, never money.
                if ($order->isDirty(self::FINANCIAL_FIELDS)) {
                    throw new OnlineOrderRejected('Perubahan ongkir atau pendanaan driver hanya dapat dilakukan Super Admin.');
                }
                if ($order->isDirty(self::RECIPIENT_FIELDS) && in_array($order->stage, [OnlineOrder::STAGE_SHIPPED, OnlineOrder::STAGE_COMPLETED, OnlineOrder::STAGE_CANCELLED], true)) {
                    throw new OnlineOrderRejected('Data penerima tidak dapat diubah setelah pesanan diserahkan atau ditutup. Hubungi Super Admin.');
                }
            }
            if (! $order->isDirty()) {
                return;
            }
            $this->event($order, 'details_updated', 'admin', $actor);
            if ($order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER && $order->hasCustomerDetails() && $order->customer_phone && $order->packaging) {
                $order->stage = OnlineOrder::STAGE_DETAILS_RECEIVED;
                $this->event($order, 'advance', 'admin', $actor, OnlineOrder::STAGE_DETAILS_RECEIVED);
            }
        });
    }

    /**
     * Replaying a step that already happened is a no-op, so double taps and resubmits are safe.
     *
     * @param  array{payment_method?: ?string, courier?: ?string, tracking_number?: ?string}  $extra
     */
    public function advance(OnlineOrder $order, string $from, string $to, string $actorType, ?User $actor = null, ?string $staffName = null, array $extra = []): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($from, $to, $actorType, $actor, $staffName, $extra): void {
            $this->assertLegacy($order);
            if ($actorType === 'admin') {
                $this->assertAdmin($actor);
            } elseif ($actorType === 'customer') {
                // Customers may only confirm receipt of a shipped delivery; staff/admin can confirm it for them.
                if ($to !== OnlineOrder::STAGE_COMPLETED || $order->fulfillment === 'pickup') {
                    throw new OnlineOrderRejected('Langkah ini dikonfirmasi oleh toko.');
                }
            } elseif (! in_array($to, self::STAFF_STAGES, true)) {
                throw new OnlineOrderRejected('Langkah ini hanya dapat ditandai oleh admin.');
            }
            if ($order->stage === $to) {
                return;
            }
            if ($order->stage !== $from || $order->nextStage() !== $to) {
                throw new OnlineOrderRejected(self::STALE);
            }

            $fields = match ($actorType) {
                'admin' => ['payment_method', 'courier', 'tracking_number'],
                'staff' => ['courier', 'tracking_number'],
                default => [],
            };
            foreach ($fields as $field) {
                if (filled($extra[$field] ?? null)) {
                    $order->{$field} = $extra[$field];
                }
            }
            match ($to) {
                OnlineOrder::STAGE_DETAILS_RECEIVED => $order->hasCustomerDetails() && $order->customer_phone && $order->packaging
                    ?: throw new OnlineOrderRejected('Lengkapi nama, nomor HP, cara menerima, dan kemasan terlebih dahulu.'),
                OnlineOrder::STAGE_PAID => $order->payment_method
                    ?: throw new OnlineOrderRejected('Pilih metode pembayaran sebelum menandai Dibayar.'),
                OnlineOrder::STAGE_SHIPPED => ($order->courier ?: throw new OnlineOrderRejected('Pilih kurir sebelum menandai dikirim.'))
                    && ($order->courier !== 'jnt' || $order->tracking_number
                        ?: throw new OnlineOrderRejected('Isi nomor resi J&T sebelum menandai dikirim.')),
                default => true,
            };

            $order->stage = $to;
            if ($to === OnlineOrder::STAGE_COMPLETED) {
                $order->closed_at = now();
            }
            $this->event($order, 'advance', $actorType, $actor, $to, $staffName);
        });
    }

    /** Correct a mistaken mark: steps back once, or restores a cancelled order to its previous step. */
    public function revert(OnlineOrder $order, string $from, User $actor): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($from, $actor): void {
            $this->assertLegacy($order);
            $this->assertAllowed($actor, 'orders.finance');
            if ($order->stage !== $from) {
                throw new OnlineOrderRejected(self::STALE);
            }
            if ($order->stage === OnlineOrder::STAGE_CANCELLED) {
                $previous = $order->events()->where('kind', 'cancel')->latest('id')->value('stage');
            } else {
                $previous = $order->steps()[$order->stepIndex() - 1] ?? null;
            }
            if ($previous === null || $order->stage === OnlineOrder::STAGE_DETAILS_RECEIVED) {
                throw new OnlineOrderRejected('Langkah ini tidak dapat dibatalkan. Ubah datanya langsung bila perlu.');
            }
            $this->event($order, 'revert', 'admin', $actor, $order->stage);
            $order->stage = $previous;
            $order->closed_at = null;
        });
    }

    public function cancel(OnlineOrder $order, string $reason, User $actor): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($reason, $actor): void {
            $this->assertLegacy($order);
            $this->assertAllowed($actor, 'orders.manage');
            if ($order->stage !== OnlineOrder::STAGE_CANCELLED && ! Gate::forUser($actor)->allows('orders.cancel', $order)) {
                throw new OnlineOrderRejected('Pesanan yang sudah dibayar atau diserahkan hanya dapat dibatalkan oleh Super Admin.');
            }
            if ($order->stage === OnlineOrder::STAGE_CANCELLED) {
                return;
            }
            if ($order->stage === OnlineOrder::STAGE_COMPLETED) {
                throw new OnlineOrderRejected('Pesanan yang sudah selesai tidak dapat dibatalkan.');
            }
            // The cancel event keeps the previous step so a mistaken cancel can be restored.
            $this->event($order, 'cancel', 'admin', $actor, $order->stage, note: $reason);
            $order->stage = OnlineOrder::STAGE_CANCELLED;
            $order->cancel_reason = $reason;
            $order->closed_at = now();
        });
    }

    public function recordStaffAdvance(OnlineOrder $order, string $amount, string $staffName): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($amount, $staffName): void {
            $this->assertLegacy($order);
            if ($order->stage === OnlineOrder::STAGE_CANCELLED || $order->staff_reimbursed_at !== null) {
                throw new OnlineOrderRejected('Talangan untuk pesanan ini sudah ditutup. Hubungi admin bila perlu koreksi.');
            }
            $order->driver_funding = 'staff_advance';
            $order->staff_advance_amount = $amount;
            $order->staff_advance_by = $staffName;
            if ($order->isDirty()) {
                $this->event($order, 'advance_recorded', 'staff', staffName: $staffName, note: Rupiah::format($amount));
            }
        });
    }

    public function markReimbursed(OnlineOrder $order, User $actor): OnlineOrder
    {
        return $this->mutate($order, function (OnlineOrder $order) use ($actor): void {
            $this->assertLegacy($order);
            $this->assertAllowed($actor, 'orders.finance');
            if (! $order->needsReimbursement()) {
                return;
            }
            $order->staff_reimbursed_at = now();
            $this->event($order, 'reimbursed', 'admin', $actor, note: Rupiah::format($order->staff_advance_amount).' ke '.$order->staff_advance_by);
        });
    }

    /** @return string the new raw token; the old link stops working immediately. */
    public function regenerateLink(OnlineOrder $order, string $audience, User $actor): string
    {
        $token = Str::random(40);
        $this->mutate($order, function (OnlineOrder $order) use ($audience, $actor, $token): void {
            $this->assertAdmin($actor);
            $prefix = $audience === 'staff' ? 'staff' : 'customer';
            $order->{$prefix.'_token_hash'} = OnlineOrder::tokenHash($token);
            $order->{$prefix.'_token_encrypted'} = $token;
            if ($prefix === 'customer') {
                $order->customer_link_expires_at = now()->addDays(OnlineOrder::CUSTOMER_LINK_DAYS);
            }
            $this->event($order, 'link_regenerated', 'admin', $actor, note: $prefix === 'staff' ? 'Link staf' : 'Link customer');
        });

        return $token;
    }

    private function mutate(OnlineOrder $order, Closure $change): OnlineOrder
    {
        return DB::transaction(function () use ($order, $change): OnlineOrder {
            $locked = OnlineOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $change($locked);
            if ($locked->isDirty()) {
                $locked->revision++;
                $locked->save();
            }

            return $locked;
        });
    }

    private function event(OnlineOrder $order, string $kind, string $actorType, ?User $actor = null, ?string $stage = null, ?string $staffName = null, ?string $note = null): void
    {
        $order->events()->create([
            'kind' => $kind,
            'stage' => $stage,
            'actor_type' => $actorType,
            'actor_user_id' => $actor?->id,
            'staff_name' => $staffName,
            'note' => $note === null ? null : Str::limit($note, 197),
        ]);
    }

    /** ORD-01 step operations never touch V2 orders; V2 state has its own operations (cutover per order at creation). */
    private function assertLegacy(OnlineOrder $order): void
    {
        if ($order->isV2()) {
            throw new OnlineOrderRejected('Pesanan ini memakai alur status baru. Gunakan tombol di halaman pesanan yang baru.');
        }
    }

    private function assertAdmin(?User $actor): void
    {
        $this->assertAllowed($actor, 'orders.manage');
    }

    private function assertAllowed(?User $actor, string $ability): void
    {
        abort_unless($actor?->exists && Gate::forUser($actor)->allows($ability), 403);
    }
}
