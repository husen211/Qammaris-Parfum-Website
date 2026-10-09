<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderCustomers;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderActor;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ORD-02c slice 4: repeat customers and confirmed saved addresses, never linked or reused automatically. */
class OnlineOrderCustomersTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private OnlineOrderCustomers $customers;

    private int $variant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['orders.v2_enabled' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->customers = app(OnlineOrderCustomers::class);
        $brand = Brand::create(['name' => 'Customer Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'Customer Synthetic', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 100000,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
            'availability_status' => 'available', 'availability_checked_at' => now(),
        ]);
        $this->variant = $product->variants()->create(['volume' => 50, 'price' => 100000, 'stock' => 0, 'is_active' => true])->id;
    }

    public function test_phone_numbers_are_normalized_like_checkout(): void
    {
        foreach (['0812-3456-7890' => '6281234567890', '+62 812 3456 7890' => '6281234567890', '6281234567890' => '6281234567890', '123' => null, '' => null] as $input => $expected) {
            $this->assertSame($expected, PhoneNumber::normalize($input), $input);
        }
    }

    public function test_customers_are_linked_only_on_request_and_may_share_a_number(): void
    {
        $order = $this->order('local_delivery', '0812 3456 7890');
        $this->assertNull($order->customer_id, 'Creating an order never links a customer by itself');
        $this->assertCount(0, $this->customers->matches('081234567890'));

        $order = $this->customers->link($order, $order->revision, $this->staff, null);
        $customer = Customer::sole();
        $this->assertSame(['Siti Sintetis', '6281234567890', $customer->id], [$customer->name, $customer->phone, $order->customer_id]);
        $this->assertSame('customer_linked', $order->events()->reorder('id', 'desc')->value('kind'));

        // A family member with the same number is a separate customer; matches() lists both for an explicit choice.
        $second = $this->order('pickup', '+62 812-3456-7890', 'Budi Sintetis');
        $second = $this->customers->link($second, $second->revision, $this->staff, null);
        $this->assertCount(2, $this->customers->matches('0812 3456 7890'));
        $third = $this->order('pickup', '081234567890');
        $third = $this->customers->link($third, $third->revision, $this->staff, $customer->id);
        $this->assertSame($customer->id, $third->customer_id);
        $same = $this->customers->link($third, $third->revision, $this->staff, $customer->id);
        $this->assertSame($third->revision, $same->revision, 'Linking again is a no-op');

        $this->assertRejected(fn () => $this->customers->link($third, $third->revision, $this->staff, 999), OrderValidationFailed::class);
        $this->assertRejected(fn () => $this->customers->link($third, $third->revision - 1, $this->staff, null), OrderRevisionConflict::class);
    }

    public function test_confirmed_addresses_are_saved_once_and_copied_into_later_orders(): void
    {
        $first = $this->order('intercity', '081234567890');
        $first = $this->customers->link($first, $first->revision, $this->staff, null);
        $first = $this->customers->saveAddress($first, $first->revision, $this->staff, 'Rumah Makassar');
        $address = CustomerAddress::sole();
        $this->assertSame(['intercity', 'Jl. Contoh 1', '94111', $address->id], [$address->type, $address->address, $address->postcode, $first->customer_address_id]);
        $again = $this->customers->saveAddress($first, $first->revision, $this->staff, 'Rumah lagi');
        $this->assertSame(1, CustomerAddress::count(), 'The same address is not stored twice');
        $this->assertSame($first->revision, $again->revision);

        $next = $this->order('pickup', '081234567890');
        $next = $this->customers->link($next, $next->revision, $this->staff, $first->customer_id);
        $next = $this->customers->useAddress($next, $next->revision, $this->staff, $address->id);
        $this->assertSame(['intercity', 'Jl. Contoh 1', '94111', 'not_requested'], [$next->fulfillment, $next->address, $next->postcode, $next->jnt_status],
            'The saved address is copied, and the V2 J&T defaults follow the new fulfillment');
        $this->assertNotNull($address->fresh()->last_used_at);

        // Editing the order later never changes the saved address.
        $next->forceFill(['address' => 'Jl. Lain 2'])->save();
        $this->assertSame('Jl. Contoh 1', $address->fresh()->address);
    }

    public function test_address_reuse_is_scoped_and_locked_after_handover(): void
    {
        $owner = $this->order('local_delivery', '081234567890');
        $owner = $this->customers->link($owner, $owner->revision, $this->staff, null);
        $owner = $this->customers->saveAddress($owner, $owner->revision, $this->staff, 'Kantor');
        $address = CustomerAddress::sole();

        $stranger = $this->order('pickup', '085200000000', 'Orang Lain');
        $stranger = $this->customers->link($stranger, $stranger->revision, $this->staff, null);
        $this->assertRejected(fn () => $this->customers->useAddress($stranger, $stranger->revision, $this->staff, $address->id), OrderValidationFailed::class);

        $this->customers->archiveAddress($address, $this->staff);
        $this->assertNotNull($address->fresh()->archived_at);
        $this->assertSame(1, CustomerAddress::count(), 'Archived, not deleted');
        $this->assertRejected(fn () => $this->customers->useAddress($owner, $owner->revision, $this->staff, $address->id), OrderValidationFailed::class);

        $address->forceFill(['archived_at' => null])->save();
        $ops = app(OnlineOrderFulfillment::class);
        $actor = OrderActor::user($this->staff);
        $owner = $ops->pack($owner, $owner->revision, $actor, $owner->items->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all());
        $owner = $ops->requestCourier($owner, $owner->revision, $actor, 'maxim', 'arrived');
        $owner = $ops->handover($owner, $owner->revision, $actor, 'courier');
        $this->assertRejected(fn () => $this->customers->useAddress($owner, $owner->revision, $this->staff, $address->id), InvalidOrderTransition::class);
    }

    private function order(string $fulfillment, string $phone, string $name = 'Siti Sintetis'): OnlineOrder
    {
        [$order] = app(CreateOnlineOrder::class)->handle($this->staff, [$this->variant => 1], [
            'customer_name' => $name, 'customer_phone' => $phone, 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? null : 'Jl. Contoh 1', 'postcode' => $fulfillment === 'intercity' ? '94111' : null,
            'packaging' => 'no_paperbag', 'customer_note' => null,
        ]);

        return $order->fresh(['items']);
    }

    private function assertRejected(callable $attempt, string $class): void
    {
        try {
            $attempt();
            $this->fail("Expected {$class}");
        } catch (\Throwable $error) {
            $this->assertInstanceOf($class, $error, $error->getMessage());
        }
    }
}
