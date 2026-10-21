<?php

namespace App\Actions\Orders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Str;

/**
 * ORD-05: customers are remembered automatically. The same WhatsApp number is the same customer (Owner
 * 2026-10-21); staff can still move an order to another customer by hand. Runs inside the caller's order
 * transaction, and the caller saves the order.
 */
final class RememberCustomer
{
    /**
     * Links a V2 order with a name and a valid number to its customer, creating the customer when new.
     * $actorType names who caused it in the history when there is no signed-in actor (the backfill uses "admin").
     */
    public function link(OnlineOrder $order, ?User $actor, ?string $actorType = null): ?Customer
    {
        $phone = PhoneNumber::normalize($order->customer_phone);
        if (! $order->isV2() || $order->customer_id !== null || blank($order->customer_name) || $phone === null) {
            return null;
        }
        $customer = Customer::where('phone', $phone)->whereNull('anonymized_at')->orderByDesc('updated_at')->orderByDesc('id')->first();
        $created = $customer === null;
        $customer ??= Customer::create(['name' => Str::limit($order->customer_name, 100, ''), 'phone' => $phone, 'created_by' => $actor?->id ?? $order->created_by]);
        $customer->touch();
        $order->customer_id = $customer->id;
        $order->customer_address_id = null;
        $order->events()->create([
            'kind' => 'customer_linked', 'stage' => $order->stage, 'actor_type' => $actorType ?? ($actor ? 'admin' : 'customer'), 'actor_user_id' => $actor?->id,
            'note' => Str::limit(($created ? 'Pelanggan baru tersimpan: ' : 'Pelanggan: ').$customer->name, 197),
        ]);

        return $customer;
    }

    /**
     * Saves the delivery address once the order is handed over, when it has proven correct. An identical address
     * is reused (only its last use moves); a new one gets a short label from the order.
     */
    public function rememberAddress(OnlineOrder $order): void
    {
        $text = trim((string) $order->address);
        if ($order->customer_id === null || $order->fulfillment === 'pickup' || ($text === '' && blank($order->location_url))) {
            return;
        }
        $type = $order->fulfillment === 'intercity' ? 'intercity' : 'local';
        $text = Str::limit($text !== '' ? $text : 'Lokasi Sharelok', 500, '');
        $address = CustomerAddress::where('customer_id', $order->customer_id)->whereNull('archived_at')->where('type', $type)
            ->where('address', $text)->where(fn ($query) => $order->postcode === null ? $query->whereNull('postcode') : $query->where('postcode', $order->postcode))
            ->first();
        if ($address === null) {
            $address = new CustomerAddress([
                'label' => Str::limit(($type === 'intercity' ? 'Luar kota' : 'Dalam kota').' · '.$order->code, 40, ''),
                'type' => $type, 'address' => $text, 'postcode' => $order->postcode, 'location_url' => $order->location_url,
            ]);
            $address->customer_id = $order->customer_id;
            $order->events()->create(['kind' => 'address_saved', 'stage' => $order->stage, 'actor_type' => 'admin', 'note' => Str::limit($address->label, 197)]);
        }
        $address->last_used_at = now();
        $address->location_url ??= $order->location_url;
        $address->save();
        $order->customer_address_id = $address->id;
    }
}
