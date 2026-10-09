<?php

namespace Tests\Concerns;

use App\Actions\Orders\CreateOnlineOrder;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderClaim;
use App\Models\Product;
use App\Models\User;

/** Synthetic V2 orders for API tests. */
trait BuildsV2Orders
{
    protected ?User $orderOwner = null;

    /** @var array<int, int> variant ID => default quantity */
    protected array $orderLines = [];

    protected function v2Order(?string $fulfillment = 'local_delivery', array $details = []): OnlineOrder
    {
        config(['orders.v2_enabled' => true]);
        $this->orderOwner ??= User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        if ($this->orderLines === []) {
            $brand = Brand::create(['name' => 'API Brand', 'is_active' => true]);
            $category = Category::create(['name' => 'EDP']);
            foreach ([['API Alpha', 150000, 2], ['API Beta', 100000, 1]] as [$name, $price, $quantity]) {
                $product = Product::create([
                    'name' => $name, 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
                    'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => $price,
                    'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
                    'availability_status' => 'available', 'availability_checked_at' => now(),
                ]);
                $this->orderLines[$product->variants()->create(['volume' => 50, 'price' => $price, 'stock' => 0, 'is_active' => true])->id] = $quantity;
            }
        }
        $customer = $fulfillment === null ? null : array_merge([
            'customer_name' => 'E2E Siti Rahma', 'customer_phone' => '081234567890', 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? null : 'Jl. Sintetis 1', 'postcode' => $fulfillment === 'intercity' ? '90111' : null,
            'packaging' => 'no_paperbag', 'customer_note' => null,
        ], $details);
        [$order] = app(CreateOnlineOrder::class)->handle($this->orderOwner, $this->orderLines, $customer);

        return $order->fresh(['items']);
    }

    /**
     * Makes one order unserialisable on every driver: its claim's timestamp turns unparseable in memory when loaded
     * (MySQL/MariaDB reject such a value in the column itself, so the database row stays valid).
     */
    protected function makeUnreadable(OnlineOrder $order): void
    {
        $order->claims()->create(['task' => 'preparation', 'holder_app_user_id' => '665f0c2a9b1e4a0012ab34cd', 'holder_display_name' => 'Andi', 'claimed_at' => now()]);
        OnlineOrderClaim::retrieved(function (OnlineOrderClaim $claim) use ($order) {
            if ($claim->online_order_id === $order->id) {
                $claim->setRawAttributes(['claimed_at' => 'not-a-date'] + $claim->getAttributes());
            }
        });
    }

    protected function actor(string $id = '665f0c2a9b1e4a0012ab34cd', string $name = 'Andi', string $role = 'employee'): array
    {
        return ['app_user_id' => $id, 'display_name' => $name, 'app_role' => $role];
    }
}
