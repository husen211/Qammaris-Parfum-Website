<?php

namespace App\Actions\Orders;

use App\Exceptions\OnlineOrderRejected;
use App\Models\OnlineOrder;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOnlineOrder
{
    /**
     * @param  array<int, int>  $lines  variant ID => quantity
     * @param  array<string, mixed>|null  $customer  optional details typed by the admin from the chat
     * @return array{0: OnlineOrder, 1: string, 2: string} order, raw customer token, raw staff token
     */
    public function handle(User $actor, array $lines, ?array $customer = null): array
    {
        abort_unless($actor->exists && $actor->role === 'admin', 403);
        if ($lines === []) {
            throw new OnlineOrderRejected('Pilih minimal satu produk.');
        }

        return DB::transaction(function () use ($actor, $lines, $customer): array {
            // Admins confirm stock in the chat, so any published offer with a valid price may be ordered.
            $variants = ProductVariant::with('product.brand')->active()
                ->whereHas('product', fn ($query) => $query->published())
                ->whereIn('id', array_keys($lines))->get()->keyBy('id');
            if ($variants->count() !== count($lines)) {
                throw new OnlineOrderRejected('Ada produk yang tidak lagi tersedia di katalog. Pilih ulang produknya.');
            }

            $customerToken = Str::random(40);
            $staffToken = Str::random(40);
            $order = new OnlineOrder;
            $order->customer_token_hash = OnlineOrder::tokenHash($customerToken);
            $order->customer_token_encrypted = $customerToken;
            $order->staff_token_hash = OnlineOrder::tokenHash($staffToken);
            $order->staff_token_encrypted = $staffToken;
            $order->customer_link_expires_at = now()->addDays(OnlineOrder::CUSTOMER_LINK_DAYS);
            $order->stage = OnlineOrder::STAGE_AWAITING_CUSTOMER;
            $order->created_by = $actor->id;
            $order->save();
            $order->code = 'QAM-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($lines as $variantId => $quantity) {
                $variant = $variants->get($variantId);
                if (Rupiah::minorUnits($variant->price) <= 0) {
                    throw new OnlineOrderRejected('Produk '.$variant->product->name.' belum memiliki harga yang valid.');
                }
                // The agreed price is a snapshot; later catalog changes never rewrite this order.
                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'brand_name' => $variant->product->brand?->name ?? 'Brand belum diisi',
                    'product_name' => $variant->product->name,
                    'volume' => $variant->volume,
                    'unit_price' => $variant->price,
                    'quantity' => $quantity,
                ]);
            }
            $order->events()->create(['kind' => 'created', 'stage' => OnlineOrder::STAGE_AWAITING_CUSTOMER, 'actor_type' => 'admin', 'actor_user_id' => $actor->id]);

            if ($customer !== null) {
                $order->fill($customer)->save();
                $order->stage = OnlineOrder::STAGE_DETAILS_RECEIVED;
                $order->save();
                $order->events()->create(['kind' => 'advance', 'stage' => OnlineOrder::STAGE_DETAILS_RECEIVED, 'actor_type' => 'admin', 'actor_user_id' => $actor->id]);
            }

            return [$order->fresh(), $customerToken, $staffToken];
        });
    }
}
