<?php

namespace App\Actions\Orders;

use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Repeat customers and saved addresses (ORD-02c slice 4). Nothing is linked or reused automatically:
 * an admin picks the customer, and picks a confirmed address, which is then copied into the order.
 */
class OnlineOrderCustomers
{
    /** @return Collection<int, Customer> existing customers with this WhatsApp number (may be several) */
    public function matches(?string $phone): Collection
    {
        $normalized = PhoneNumber::normalize($phone);

        return $normalized === null ? collect() : Customer::where('phone', $normalized)->orderBy('name')->get();
    }

    /** Links the order to an existing customer, or creates one from the order's own name and number. */
    public function link(OnlineOrder $order, int $revision, User $actor, ?int $customerId): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $customerId): void {
            if ($customerId !== null) {
                $customer = Customer::find($customerId) ?? throw new OrderValidationFailed('Pelanggan tidak ditemukan.', ['customer_id' => 'Tidak ada']);
            } else {
                $phone = PhoneNumber::normalize($order->customer_phone);
                if ($order->customer_name === null || $phone === null) {
                    throw new OrderValidationFailed('Isi nama dan nomor WhatsApp yang valid sebelum menyimpan pelanggan.', ['customer_phone' => 'Wajib']);
                }
                $customer = Customer::create(['name' => $order->customer_name, 'phone' => $phone, 'created_by' => $actor->id]);
            }
            if ($order->customer_id === $customer->id) {
                return;
            }
            // A saved address belongs to the previous customer; the order keeps its own copied address.
            $order->customer_address_id = null;
            $order->customer_id = $customer->id;
            $this->event($order, $actor, 'customer_linked', $customer->name);
        });
    }

    /** Copies a confirmed address into the order. Only before handover, and only the order's own customer. */
    public function useAddress(OnlineOrder $order, int $revision, User $actor, int $addressId): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $addressId): void {
            $address = CustomerAddress::whereKey($addressId)->where('customer_id', $order->customer_id)->whereNull('archived_at')->first();
            if (! $order->customer_id || ! $address) {
                throw new OrderValidationFailed('Alamat ini bukan milik pelanggan pesanan ini.', ['customer_address_id' => 'Tidak valid']);
            }
            if (in_array($order->lifecycle, ['cancelled', 'completed'], true) || $order->handover_status === 'handed_over') {
                throw new InvalidOrderTransition('Alamat tidak dapat diganti setelah pesanan diserahkan atau ditutup.');
            }
            $order->fulfillment = CustomerAddress::TYPES[$address->type];
            $order->address = $address->address;
            $order->postcode = $address->type === 'intercity' ? $address->postcode : null;
            $order->location_url = $address->location_url;
            $order->customer_address_id = $address->id;
            if (! $order->isDirty()) {
                return;
            }
            $address->forceFill(['last_used_at' => now()])->save();
            $this->event($order, $actor, 'address_reused', $address->label);
        });
    }

    /** Saves the order's current delivery address for this customer once an admin has confirmed it. */
    public function saveAddress(OnlineOrder $order, int $revision, User $actor, string $label): OnlineOrder
    {
        return $this->mutate($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $label): void {
            $type = array_search($order->fulfillment, CustomerAddress::TYPES, true);
            if (! $order->customer_id || $type === false || blank($order->address)) {
                throw new OrderValidationFailed('Hubungkan pelanggan dan isi alamat pengiriman dulu.', ['address' => 'Wajib']);
            }
            $label = trim($label);
            if ($label === '' || mb_strlen($label) > 40) {
                throw new OrderValidationFailed('Beri nama alamat, misalnya Rumah atau Kantor.', ['label' => 'Wajib, maksimal 40 karakter']);
            }
            $same = CustomerAddress::where('customer_id', $order->customer_id)->whereNull('archived_at')
                ->where('type', $type)->where('address', $order->address)->where('postcode', $type === 'intercity' ? $order->postcode : null)->first();
            if ($same) {
                if ($order->customer_address_id === $same->id) {
                    return;
                }
                $order->customer_address_id = $same->id;
                $this->event($order, $actor, 'address_saved', $same->label.' (sudah tersimpan)');

                return;
            }
            $address = CustomerAddress::forceCreate([
                'customer_id' => $order->customer_id, 'label' => $label, 'type' => $type, 'address' => $order->address,
                'postcode' => $type === 'intercity' ? $order->postcode : null, 'location_url' => $order->location_url,
                'confirmed_by' => $actor->id, 'last_used_at' => now(),
            ]);
            $order->customer_address_id = $address->id;
            $this->event($order, $actor, 'address_saved', $label);
        });
    }

    /** Archive instead of delete; orders that used it keep their own copy. */
    public function archiveAddress(CustomerAddress $address, User $actor): void
    {
        abort_unless(Gate::forUser($actor)->allows('orders.manage'), 403);
        if ($address->archived_at === null) {
            $address->forceFill(['archived_at' => now()])->save();
        }
    }

    private function mutate(OnlineOrder $order, int $revision, User $actor, Closure $change): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);

        return DB::transaction(function () use ($order, $revision, $change): OnlineOrder {
            $locked = OnlineOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->revision !== $revision) {
                throw new OrderRevisionConflict($locked->revision);
            }
            $change($locked);
            if ($locked->isDirty()) {
                $locked->revision++;
                $locked->save();
            }

            return $locked;
        });
    }

    private function event(OnlineOrder $order, User $actor, string $kind, string $note): void
    {
        $order->events()->create(['kind' => $kind, 'stage' => $order->stage, 'actor_type' => 'admin', 'actor_user_id' => $actor->id, 'note' => Str::limit($note, 197)]);
    }
}
