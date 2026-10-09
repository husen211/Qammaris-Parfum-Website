<?php

namespace App\Actions\Orders;

use App\Actions\Orders\Concerns\MutatesV2Order;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderValidationFailed;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\Rupiah;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Price adjustments after the order was agreed (ORD-02c slice 5). Anyone with order access may request one;
 * only Super Admin approves. An approved discount that leaves the customer overpaid is shown as such — a refund
 * still needs its own Super Admin decision (ADR-040), nothing is returned automatically.
 */
class OnlineOrderAdjustments
{
    use MutatesV2Order;

    public function request(OnlineOrder $order, int $revision, User $actor, string $amount, string $reason): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);

        return $this->mutateV2($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $amount, $reason): bool {
            $this->assertOpen($order);
            $amount = trim($amount);
            $fields = [];
            if (! preg_match('/^-?\d{1,9}(\.\d{1,2})?$/', $amount) || Rupiah::minorUnits($amount) === 0) {
                $fields['amount'] = 'Nominal rupiah selain 0; minus untuk potongan';
            }
            if (mb_strlen(trim($reason)) < 5) {
                $fields['reason'] = 'Minimal 5 karakter';
            }
            if ($fields) {
                throw new OrderValidationFailed('Periksa penyesuaian harga.', $fields);
            }
            $order->adjustments()->create(['amount' => $amount, 'reason' => Str::limit(trim($reason), 297), 'requested_by' => $actor->id]);
            $this->orderEvent($order, $actor, 'adjustment_requested', Rupiah::format($amount).' · '.trim($reason));

            return true;
        });
    }

    public function approve(OnlineOrder $order, int $revision, User $actor, int $adjustmentId, ?string $note = null): OnlineOrder
    {
        return $this->decide($order, $revision, $actor, $adjustmentId, 'approved', $note);
    }

    public function reject(OnlineOrder $order, int $revision, User $actor, int $adjustmentId, string $note): OnlineOrder
    {
        if (mb_strlen(trim($note)) < 5) {
            throw new OrderValidationFailed('Tuliskan alasan penolakan.', ['note' => 'Minimal 5 karakter']);
        }

        return $this->decide($order, $revision, $actor, $adjustmentId, 'rejected', $note);
    }

    /** Whole cents received above the current customer total (0 when not overpaid). */
    public static function overpaidCents(OnlineOrder $order): int
    {
        $refundable = OnlineOrderMoney::totals($order)['received'] - OnlineOrderMoney::totals($order)['refunded'];

        return max(0, $refundable - Rupiah::minorUnits($order->customerTotal()));
    }

    private function decide(OnlineOrder $order, int $revision, User $actor, int $adjustmentId, string $status, ?string $note): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.approve-adjustment'), 403);

        return $this->mutateV2($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $adjustmentId, $status, $note): bool {
            $this->assertOpen($order);
            $adjustment = $order->adjustments()->whereKey($adjustmentId)->first();
            if (! $adjustment || $adjustment->status !== 'pending') {
                throw new InvalidOrderTransition('Penyesuaian ini tidak sedang menunggu persetujuan.');
            }
            if ($status === 'approved' && Rupiah::minorUnits($order->customerTotal()) + Rupiah::minorUnits($adjustment->amount) < 0) {
                throw new OrderValidationFailed('Total pesanan tidak boleh di bawah 0.', ['amount' => 'Terlalu besar']);
            }
            $adjustment->update(['status' => $status, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $note === null ? null : Str::limit(trim($note), 297)]);
            $label = $status === 'approved' ? 'adjustment_approved' : 'adjustment_rejected';
            $overpaid = $status === 'approved' ? self::overpaidCents($order) : 0;
            $this->orderEvent($order, $actor, $label, Rupiah::format($adjustment->amount).($overpaid > 0 ? ' · lebih bayar '.Rupiah::format(Rupiah::decimal($overpaid)) : ''));

            return true;
        });
    }

    private function assertOpen(OnlineOrder $order): void
    {
        if (in_array($order->lifecycle, ['cancelled', 'completed'], true)) {
            throw new InvalidOrderTransition('Harga pesanan yang sudah ditutup tidak dapat disesuaikan.');
        }
    }
}
