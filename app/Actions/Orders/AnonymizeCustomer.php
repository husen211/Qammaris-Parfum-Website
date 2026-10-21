<?php

namespace App\Actions\Orders;

use App\Exceptions\OrderActionNotAllowed;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\IntegrationOutbox;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * ORD-05: a Super Admin removes a customer's personal data on request (Owner 2026-10-21; never automatic).
 * Name, number, addresses and notes are cleared on the customer and on every linked order; order codes, items,
 * money and history stay for the books. Each order gets a new revision, so the App receives the cleared copy.
 * Refused while an order is still in progress or a webhook for it is still being delivered.
 */
final class AnonymizeCustomer
{
    public const NAME = 'Pelanggan dihapus';

    public function handle(Customer $customer, User $actor): void
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('customers.delete'), 403);

        DB::transaction(function () use ($customer, $actor): void {
            $customer = Customer::whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            if ($customer->anonymized_at !== null) {
                return;
            }
            $orders = OnlineOrder::where('customer_id', $customer->id)->lockForUpdate()->get();
            if ($orders->contains(fn (OnlineOrder $order) => ! in_array($order->lifecycle, ['completed', 'cancelled'], true))) {
                throw new OrderActionNotAllowed('Pelanggan ini masih punya pesanan yang berjalan. Selesaikan atau batalkan dulu, lalu hapus datanya.');
            }
            $ids = $orders->modelKeys();
            if (IntegrationOutbox::whereIn('online_order_id', $ids)->whereNull('delivered_at')->whereNull('failed_at')->exists()) {
                throw new OrderActionNotAllowed('Pesanan pelanggan ini masih dikirim ke Qammaris App. Coba lagi beberapa menit lagi.');
            }

            foreach ($orders as $order) {
                $order->forceFill([
                    'customer_name' => self::NAME, 'customer_phone' => null, 'address' => null, 'postcode' => null, 'district' => null,
                    'subdistrict' => null, 'location_url' => null, 'customer_note' => null, 'customer_address_id' => null,
                ]);
                $order->revision++;
                $order->save();
                $order->events()->where('kind', 'customer_linked')->update(['note' => self::NAME]);
                $order->events()->create(['kind' => 'customer_anonymized', 'stage' => $order->stage, 'actor_type' => 'admin', 'actor_user_id' => $actor->id, 'note' => 'Data pelanggan dihapus atas permintaan']);
            }
            OnlineOrderChangeRequest::whereIn('online_order_id', $ids)->update(['changes' => '{}']);
            // Earlier webhook bodies carried the recipient; delivered and failed ones are never resent as they are.
            IntegrationOutbox::whereIn('online_order_id', $ids)
                ->where(fn ($rows) => $rows->whereNotNull('delivered_at')->orWhereNotNull('failed_at'))
                ->update(['body' => json_encode(['redacted' => 'customer_anonymized'])]);
            CustomerAddress::where('customer_id', $customer->id)->get()->each(fn (CustomerAddress $address) => $address->forceFill([
                'label' => 'Dihapus', 'address' => '-', 'postcode' => null, 'location_url' => null, 'archived_at' => $address->archived_at ?? now(),
            ])->save());
            $customer->forceFill(['name' => self::NAME, 'phone' => '', 'note' => null, 'anonymized_at' => now(), 'anonymized_by' => $actor->id])->save();
        });
    }
}
