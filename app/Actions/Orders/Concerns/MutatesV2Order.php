<?php

namespace App\Actions\Orders\Concerns;

use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderRevisionConflict;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderV2State;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Lock + expected revision + money/completion refresh for V2 order changes that live in child tables. */
trait MutatesV2Order
{
    /** @param  Closure(OnlineOrder): bool  $change  returns true when it changed something */
    protected function mutateV2(OnlineOrder $order, ?int $revision, ?User $actor, Closure $change): OnlineOrder
    {
        return DB::transaction(function () use ($order, $revision, $actor, $change): OnlineOrder {
            $locked = OnlineOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isV2()) {
                throw new InvalidOrderTransition('Fitur ini hanya untuk pesanan dengan alur status baru.');
            }
            if ($revision !== null && $locked->revision !== $revision) {
                throw new OrderRevisionConflict($locked->revision);
            }
            if (! $change($locked) && ! $locked->isDirty()) {
                return $locked;
            }
            OnlineOrderMoney::apply($locked, OnlineOrderMoney::basePaymentFromLedger($locked));
            if ($locked->lifecycle === 'active' && OnlineOrderV2State::settle($locked)) {
                $this->orderEvent($locked, $actor, 'completed', 'Pesanan selesai');
            }
            $locked->revision++;
            $locked->save();

            return $locked;
        });
    }

    protected function orderEvent(OnlineOrder $order, ?User $actor, string $kind, string $note): void
    {
        $order->events()->create([
            'kind' => $kind, 'stage' => $order->stage, 'actor_type' => $actor ? 'admin' : 'customer',
            'actor_user_id' => $actor?->id, 'note' => Str::limit($note, 197),
        ]);
    }
}
