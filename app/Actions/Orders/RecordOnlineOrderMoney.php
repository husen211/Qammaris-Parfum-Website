<?php

namespace App\Actions\Orders;

use App\Exceptions\OnlineOrderRejected;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderPayment;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderV2State;
use App\Support\Rupiah;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Payments, refunds and their corrections (ORD-02c slice 2). The ledger is append-only; every operation checks
 * the expected revision so a double tap cannot record money twice, and writes an audit event.
 * Refund decisions, refund payouts, reversals and reconciliation are Super Admin only (`orders.refund`).
 */
class RecordOnlineOrderMoney
{
    public const CONFIRMATION_SOURCES = ['proof_in_chat', 'proof_uploaded', 'majoo'];

    public function recordPayment(OnlineOrder $order, int $revision, User $actor, string $amount, string $method, ?string $source, ?string $reference = null, ?string $note = null): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, 'orders.manage', function (OnlineOrder $order) use ($actor, $amount, $method, $source, $reference, $note): void {
            if ($order->lifecycle === 'cancelled') {
                throw new OnlineOrderRejected('Pesanan yang dibatalkan tidak menerima pembayaran baru.');
            }
            if (! array_key_exists($method, OnlineOrder::PAYMENT_METHODS) || ($source !== null && ! in_array($source, self::CONFIRMATION_SOURCES, true))) {
                throw new OnlineOrderRejected('Pilih metode dan sumber konfirmasi pembayaran yang valid.');
            }
            $cents = $this->cents($amount);
            $this->entry($order, $actor, OnlineOrderPayment::TYPE_PAYMENT, $cents, ['method' => $method, 'confirmation_source' => $source, 'reference' => $reference, 'note' => $note]);
            $order->payment_method ??= $method;
            $order->payment_confirmation_source = $source;
            $this->event($order, $actor, 'payment_recorded', Rupiah::format(Rupiah::decimal($cents)).' · '.OnlineOrder::PAYMENT_METHODS[$method]);
        });
    }

    /** Super Admin decides how much must be returned (0 = nothing). A later change is a correction with its own reason. */
    public function decideRefund(OnlineOrder $order, int $revision, User $actor, string $due, string $reason): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, 'orders.refund', function (OnlineOrder $order) use ($actor, $due, $reason): void {
            if ($order->refund_status === 'needs_reconciliation') {
                throw new OnlineOrderRejected('Riwayat pembayaran pesanan ini belum lengkap. Lakukan rekonsiliasi dulu.');
            }
            ['received' => $received, 'refunded' => $refunded] = OnlineOrderMoney::totals($order);
            $cents = $this->cents($due, allowZero: true);
            if ($received === 0) {
                throw new OnlineOrderRejected('Belum ada pembayaran yang tercatat, jadi tidak ada yang perlu dikembalikan.');
            }
            if ($cents > $received) {
                throw new OnlineOrderRejected('Nominal refund melebihi pembayaran yang diterima ('.Rupiah::format(Rupiah::decimal($received)).').');
            }
            if ($cents < $refunded) {
                throw new OnlineOrderRejected('Nominal refund tidak boleh lebih kecil dari yang sudah dikembalikan ('.Rupiah::format(Rupiah::decimal($refunded)).'). Batalkan entri refund dulu bila salah catat.');
            }
            $corrected = $order->refund_due_amount !== null;
            $this->decide($order, $actor, $cents, $this->reason($reason));
            $this->event($order, $actor, $corrected ? 'refund_corrected' : 'refund_decided', Rupiah::format(Rupiah::decimal($cents)).' · '.$order->refund_reason);
        });
    }

    public function recordRefund(OnlineOrder $order, int $revision, User $actor, string $amount, string $method, ?string $reference = null, ?string $note = null): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, 'orders.refund', function (OnlineOrder $order) use ($actor, $amount, $method, $reference, $note): void {
            if ($order->refund_due_amount === null) {
                throw new OnlineOrderRejected('Tentukan dulu nominal yang harus dikembalikan.');
            }
            if (! array_key_exists($method, OnlineOrder::PAYMENT_METHODS)) {
                throw new OnlineOrderRejected('Pilih metode pengembalian yang valid.');
            }
            $cents = $this->cents($amount);
            $remaining = Rupiah::minorUnits($order->refund_due_amount) - OnlineOrderMoney::totals($order)['refunded'];
            if ($cents > $remaining) {
                throw new OnlineOrderRejected('Nominal melebihi sisa refund ('.Rupiah::format(Rupiah::decimal(max(0, $remaining))).').');
            }
            $this->entry($order, $actor, OnlineOrderPayment::TYPE_REFUND, $cents, ['method' => $method, 'reference' => $reference, 'note' => $note]);
            $this->event($order, $actor, 'refund_recorded', Rupiah::format(Rupiah::decimal($cents)).' · '.OnlineOrder::PAYMENT_METHODS[$method]);
        });
    }

    /** Cancels a mistaken ledger entry by appending a reversal; the original stays visible. */
    public function reverse(OnlineOrder $order, int $revision, User $actor, int $entryId, string $reason): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, 'orders.refund', function (OnlineOrder $order) use ($actor, $entryId, $reason): void {
            $entry = OnlineOrderPayment::where('online_order_id', $order->id)->whereKey($entryId)->first();
            if (! $entry || $entry->basis === 'reversal' || OnlineOrderPayment::where('reverses_id', $entry->id)->exists()) {
                throw new OnlineOrderRejected('Entri ini tidak dapat dibatalkan.');
            }
            $reason = $this->reason($reason);
            $totals = OnlineOrderMoney::totals($order);
            $cents = Rupiah::minorUnits($entry->amount);
            $received = $totals['received'] - ($entry->type === OnlineOrderPayment::TYPE_PAYMENT ? $cents : 0);
            if ($order->refund_due_amount !== null && $received < Rupiah::minorUnits($order->refund_due_amount)) {
                throw new OnlineOrderRejected('Pembayaran tidak boleh lebih kecil dari nominal refund. Koreksi nominal refund dulu.');
            }
            $this->entry($order, $actor, $entry->type, $cents, ['basis' => 'reversal', 'reverses_id' => $entry->id, 'note' => $reason]);
            $label = $entry->type === OnlineOrderPayment::TYPE_REFUND ? 'refund' : 'pembayaran';
            $this->event($order, $actor, 'ledger_reversed', 'Batal '.$label.' '.Rupiah::format($entry->amount).' · '.$reason);
        });
    }

    /**
     * Super Admin confirms what really happened for an order whose history is incomplete (ORD-01 cancel after
     * payment). Nothing is assumed: received, owed and already-returned amounts are all stated explicitly.
     */
    public function reconcile(OnlineOrder $order, int $revision, User $actor, string $received, string $due, string $alreadyRefunded, string $note): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, 'orders.refund', function (OnlineOrder $order) use ($actor, $received, $due, $alreadyRefunded, $note): void {
            if ($order->refund_status !== 'needs_reconciliation') {
                throw new OnlineOrderRejected('Pesanan ini tidak sedang menunggu rekonsiliasi.');
            }
            $note = $this->reason($note);
            [$receivedCents, $dueCents, $refundedCents] = [$this->cents($received), $this->cents($due, allowZero: true), $this->cents($alreadyRefunded, allowZero: true)];
            $totals = OnlineOrderMoney::totals($order);
            if ($totals['refunded'] > 0) {
                throw new OnlineOrderRejected('Pesanan ini sudah punya catatan refund. Periksa ledger sebelum rekonsiliasi.');
            }
            if ($totals['received'] > 0 && $totals['received'] !== $receivedCents) {
                throw new OnlineOrderRejected('Nominal diterima harus sama dengan pembayaran yang sudah tercatat ('.Rupiah::format(Rupiah::decimal($totals['received'])).').');
            }
            if ($dueCents > $receivedCents || $refundedCents > $dueCents) {
                throw new OnlineOrderRejected('Periksa nominal: refund maksimal sebesar pembayaran, dan yang sudah dikembalikan maksimal sebesar refund.');
            }
            if ($totals['received'] === 0) {
                $this->entry($order, $actor, OnlineOrderPayment::TYPE_PAYMENT, $receivedCents, ['basis' => 'reconciled', 'note' => $note]);
            }
            if ($refundedCents > 0) {
                $this->entry($order, $actor, OnlineOrderPayment::TYPE_REFUND, $refundedCents, ['basis' => 'reconciled', 'note' => $note]);
            }
            $order->refund_status = null;
            $this->decide($order, $actor, $dueCents, $note);
            $this->event($order, $actor, 'reconciled', 'Diterima '.Rupiah::format(Rupiah::decimal($receivedCents)).', refund '.Rupiah::format(Rupiah::decimal($dueCents))
                .', sudah kembali '.Rupiah::format(Rupiah::decimal($refundedCents)).' · '.$note);
        });
    }

    private function mutate(OnlineOrder $order, int $revision, User $actor, string $ability, Closure $change): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows($ability), 403);

        return DB::transaction(function () use ($order, $revision, $actor, $change): OnlineOrder {
            $locked = OnlineOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->revision !== $revision) {
                throw new OnlineOrderRejected('Pesanan sudah berubah. Muat ulang halaman lalu periksa sebelum mengulang.');
            }
            $change($locked);
            if ($locked->isV2()) {
                OnlineOrderMoney::apply($locked, OnlineOrderMoney::basePaymentFromLedger($locked));
                if ($locked->lifecycle === 'active' && OnlineOrderV2State::settle($locked)) {
                    $locked->events()->create(['kind' => 'completed', 'stage' => OnlineOrder::STAGE_COMPLETED, 'actor_type' => 'admin', 'actor_user_id' => $actor->id]);
                }
            }
            // Legacy rows recompute money state in the saving hook.
            $locked->revision++;
            $locked->save();

            return $locked;
        });
    }

    private function decide(OnlineOrder $order, User $actor, int $cents, string $reason): void
    {
        $order->refund_due_amount = Rupiah::decimal($cents);
        $order->refund_reason = $reason;
        $order->refund_decided_by = $actor->id;
        $order->refund_decided_at = now();
    }

    private function entry(OnlineOrder $order, User $actor, string $type, int $cents, array $attributes): void
    {
        $order->payments()->create(['type' => $type, 'amount' => Rupiah::decimal($cents), 'recorded_by' => $actor->id] + $attributes);
    }

    private function event(OnlineOrder $order, User $actor, string $kind, string $note): void
    {
        $order->events()->create(['kind' => $kind, 'stage' => $order->stage, 'actor_type' => 'admin', 'actor_user_id' => $actor->id, 'note' => Str::limit($note, 197)]);
    }

    private function cents(string $amount, bool $allowZero = false): int
    {
        if (! preg_match('/^\d{1,9}(\.\d{1,2})?$/', trim($amount))) {
            throw new OnlineOrderRejected('Nominal harus angka rupiah yang valid.');
        }
        $cents = Rupiah::minorUnits(trim($amount));
        if ($cents === 0 && ! $allowZero) {
            throw new OnlineOrderRejected('Nominal harus lebih dari 0.');
        }

        return $cents;
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new OnlineOrderRejected('Tuliskan alasan minimal 5 karakter.');
        }

        return Str::limit($reason, 297);
    }
}
