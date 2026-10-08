<?php

namespace App\Actions\Orders;

use App\Actions\Orders\Concerns\MutatesV2Order;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderActionNotAllowed;
use App\Exceptions\OrderValidationFailed;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Customer data changes that need review (plan §5.7, D5). The customer edits directly only while the order is
 * unpaid and packing has not started; after that a change becomes a request. Staff Order approves recipient and
 * address changes until handover; a change of delivery type can change the cost and needs Super Admin.
 */
class OnlineOrderChangeRequests
{
    use MutatesV2Order;

    public const FIELDS = ['customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'location_url', 'packaging', 'customer_note'];

    private const MAX = ['customer_name' => 100, 'customer_phone' => 20, 'address' => 500, 'postcode' => 5, 'location_url' => 500, 'customer_note' => 300];

    /** Whether a customer change must go through review instead of being applied directly. */
    public static function customerMustRequest(OnlineOrder $order): bool
    {
        return ! $order->customerCanEdit() || $order->preparation_status !== 'not_started';
    }

    /** @param  array<string, ?string>  $changes */
    public function submit(OnlineOrder $order, array $changes, string $source = 'customer', ?User $actor = null): OnlineOrder
    {
        if ($source === 'admin') {
            abort_unless($actor && Gate::forUser($actor)->allows('orders.manage'), 403);
        }

        return $this->mutateV2($order, null, $actor, function (OnlineOrder $order) use ($changes, $source, $actor): bool {
            $this->assertChangeable($order);
            if ($order->changeRequests()->where('status', 'pending')->exists()) {
                throw new InvalidOrderTransition('Masih ada permintaan perubahan yang menunggu ditinjau.');
            }
            $changes = $this->validated($order, $changes);
            $order->changeRequests()->create(['changes' => $changes, 'source' => $source === 'admin' ? 'admin' : 'customer']);
            $this->orderEvent($order, $actor, 'change_requested', 'Perubahan: '.implode(', ', array_keys($changes)));

            return true;
        });
    }

    public function approve(OnlineOrder $order, int $revision, User $actor, int $requestId, ?string $note = null): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);

        return $this->mutateV2($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $requestId, $note): bool {
            $request = $this->pending($order, $requestId);
            $this->assertChangeable($order);
            $changes = $this->validated($order, $request->changes, allowUnchanged: true);
            if (array_key_exists('fulfillment', $changes) && $changes['fulfillment'] !== $order->fulfillment) {
                if (! Gate::forUser($actor)->allows('orders.approve-adjustment')) {
                    throw new OrderActionNotAllowed('Perubahan cara pengiriman bisa mengubah biaya, jadi harus disetujui Super Admin.');
                }
                $started = $order->fulfillment === 'intercity' ? $order->jnt_status !== 'not_requested' : in_array($order->courier_status, ['requested', 'arrived'], true);
                if ($started) {
                    throw new InvalidOrderTransition('Kurir/J&T sudah diminta. Batalkan permintaannya dulu sebelum mengganti cara pengiriman.');
                }
            }
            $order->fill($changes);
            if ($order->fulfillment === 'pickup') {
                $order->address = null;
                $order->postcode = null;
            } elseif ($order->fulfillment === 'local_delivery') {
                $order->postcode = null;
            }
            $request->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note === null ? null : Str::limit(trim($note), 297)]);
            $this->orderEvent($order, $actor, 'change_approved', 'Disetujui: '.implode(', ', array_keys($changes)));

            return true;
        });
    }

    public function reject(OnlineOrder $order, int $revision, User $actor, int $requestId, string $note): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);
        if (mb_strlen(trim($note)) < 5) {
            throw new OrderValidationFailed('Tuliskan alasan penolakan untuk customer.', ['note' => 'Minimal 5 karakter']);
        }

        return $this->mutateV2($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $requestId, $note): bool {
            $this->pending($order, $requestId)->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => Str::limit(trim($note), 297)]);
            $this->orderEvent($order, $actor, 'change_rejected', trim($note));

            return true;
        });
    }

    private function pending(OnlineOrder $order, int $requestId)
    {
        $request = $order->changeRequests()->whereKey($requestId)->first();
        if (! $request || $request->status !== 'pending') {
            throw new InvalidOrderTransition('Permintaan ini tidak sedang menunggu ditinjau.');
        }

        return $request;
    }

    private function assertChangeable(OnlineOrder $order): void
    {
        if (in_array($order->lifecycle, ['cancelled', 'completed'], true) || $order->handover_status === 'handed_over') {
            throw new InvalidOrderTransition('Pesanan sudah diserahkan atau ditutup. Hubungi kami lewat WhatsApp untuk perubahan.');
        }
    }

    /** @return array<string, ?string> only allowed fields that differ from the order (or all, when re-checking) */
    private function validated(OnlineOrder $order, array $changes, bool $allowUnchanged = false): array
    {
        $clean = [];
        $fields = [];
        foreach (array_intersect_key($changes, array_flip(self::FIELDS)) as $field => $value) {
            $value = $value === null ? null : trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
            $value = $value === '' ? null : $value;
            if ($value !== null && isset(self::MAX[$field]) && mb_strlen($value) > self::MAX[$field]) {
                $fields[$field] = 'Terlalu panjang';
            } elseif ($field === 'customer_phone' && PhoneNumber::normalize($value) === null) {
                $fields[$field] = 'Nomor WhatsApp tidak valid';
            } elseif ($field === 'fulfillment' && ! array_key_exists((string) $value, OnlineOrder::FULFILLMENTS)) {
                $fields[$field] = 'Pilihan tidak valid';
            } elseif ($field === 'packaging' && ! array_key_exists((string) $value, OnlineOrder::PACKAGING)) {
                $fields[$field] = 'Pilihan tidak valid';
            } elseif ($field === 'customer_name' && $value === null) {
                $fields[$field] = 'Wajib diisi';
            } elseif ($allowUnchanged || $value !== $order->{$field}) {
                $clean[$field] = $value;
            }
        }
        if ($fields) {
            throw new OrderValidationFailed('Periksa data perubahan.', $fields);
        }
        if ($clean === []) {
            throw new OrderValidationFailed('Tidak ada data yang berubah.', ['changes' => 'Kosong']);
        }

        return $clean;
    }
}
