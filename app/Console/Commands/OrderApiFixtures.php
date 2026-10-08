<?php

namespace App\Console\Commands;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\OrderActor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Synthetic V2 orders for joint staging tests with Qammaris App (ORD-02e). Refuses production. Re-runnable:
 * clean fixtures (active, no claim/cost/issue) are reused; `--reset` cancels earlier fixtures (never deletes) and
 * creates six fresh ones. Recipient names start with "E2E " and phones/addresses are fake.
 */
class OrderApiFixtures extends Command
{
    protected $signature = 'qammaris:order-api:fixtures {--actor= : Username or email of the admin the fixtures are attributed to} {--reset : Cancel earlier fixtures and create six fresh ones}';

    protected $description = 'Create six synthetic, unclaimed V2 orders for Qammaris App staging tests (never production)';

    private const SCENARIOS = [
        'local' => ['fulfillment' => 'local_delivery', 'lines' => [1, 1], 'phone' => '080000000001'],
        'intercity' => ['fulfillment' => 'intercity', 'lines' => [1, 0], 'phone' => '080000000002'],
        'customerCourier' => ['fulfillment' => 'local_delivery', 'lines' => [1, 0], 'phone' => '080000000003', 'customer_courier' => true],
        'pickup' => ['fulfillment' => 'pickup', 'lines' => [0, 1], 'phone' => '080000000004'],
        'issue' => ['fulfillment' => 'local_delivery', 'lines' => [2, 0], 'phone' => '080000000005'],
        'costs' => ['fulfillment' => 'local_delivery', 'lines' => [0, 1], 'phone' => '080000000006'],
    ];

    public function handle(CreateOnlineOrder $create, OnlineOrderFulfillment $orders): int
    {
        if (app()->environment('production')) {
            $this->error('Ditolak: fixture E2E tidak boleh dibuat di environment production.');

            return self::FAILURE;
        }
        $actor = User::query()->where('username', $this->option('actor'))->orWhere('email', $this->option('actor'))->first();
        if (! $actor || $actor->is_active === false || ! $actor->isSuperAdmin()) {
            $this->error('Isi --actor dengan username/email Super Admin aktif (untuk atribusi audit).');

            return self::FAILURE;
        }

        config(['orders.v2_enabled' => true]);
        $variants = $this->syntheticVariants($actor);
        $result = [];
        foreach (self::SCENARIOS as $scenario => $spec) {
            $existing = $this->fixtures($scenario);
            if ($this->option('reset')) {
                foreach ($existing->whereIn('lifecycle', ['awaiting_customer', 'active']) as $old) {
                    $orders->cancel($old, $old->revision, OrderActor::user($actor), 'E2E reset');
                }
                $existing = collect();
            }
            $order = $existing->first(fn (OnlineOrder $order) => $this->clean($order)) ?? $this->createFixture($create, $orders, $actor, $variants, $scenario, $spec);
            $result[] = ['scenario' => $scenario, 'id' => $order->public_id, 'number' => $order->code, 'revision' => $order->fresh()->revision, 'fulfillment' => $order->fulfillment];
        }
        $this->line(json_encode(['fixtures' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function createFixture(CreateOnlineOrder $create, OnlineOrderFulfillment $orders, User $actor, array $variants, string $scenario, array $spec): OnlineOrder
    {
        $lines = array_filter([$variants[0]->id => $spec['lines'][0], $variants[1]->id => $spec['lines'][1]]);
        [$order] = $create->handle($actor, $lines, [
            'customer_name' => 'E2E '.ucfirst($scenario).' Sintetis', 'customer_phone' => $spec['phone'], 'fulfillment' => $spec['fulfillment'],
            'address' => $spec['fulfillment'] === 'pickup' ? null : 'Jl. E2E Sintetis No. '.strlen($scenario).', Kota Uji',
            'postcode' => $spec['fulfillment'] === 'intercity' ? '90000' : null, 'packaging' => 'no_paperbag', 'customer_note' => null,
        ], null, ['source' => 'manual', 'fixture' => true]);
        $order->forceFill(['staff_note' => '[e2e-fixture:'.$scenario.']'])->save();
        if ($spec['customer_courier'] ?? false) {
            $order = $orders->setCourierResponsibility($order->fresh(), $order->fresh()->revision, OrderActor::user($actor), 'customer');
        }

        return $order->fresh();
    }

    private function fixtures(string $scenario)
    {
        return OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)->where('staff_note', '[e2e-fixture:'.$scenario.']')->latest('id')->get();
    }

    private function clean(OnlineOrder $order): bool
    {
        return $order->lifecycle === 'active' && $order->claims()->doesntExist() && $order->costs()->doesntExist() && $order->issues()->doesntExist()
            && $order->preparation_status === 'not_started' && $order->handover_status === 'pending';
    }

    /** @return array{0: ProductVariant, 1: ProductVariant} draft products of the synthetic brand; never public */
    private function syntheticVariants(User $actor): array
    {
        return DB::transaction(function () {
            $brand = Brand::firstOrCreate(['name' => CreateOnlineOrder::FIXTURE_BRAND], ['is_active' => false]);
            $category = Category::firstOrCreate(['name' => 'E2E']);

            return array_map(function (array $spec) use ($brand, $category) {
                [$name, $price] = $spec;
                $product = Product::firstOrCreate(['name' => $name, 'brand_id' => $brand->id], [
                    'category_id' => $category->id, 'description' => 'Produk sintetis untuk uji E2E. Tidak dijual.',
                    'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => $price,
                    'publication_status' => 'draft', 'is_active' => false, 'availability_source' => 'manual',
                    'availability_status' => 'available', 'availability_checked_at' => now(),
                ]);

                return $product->variants()->firstOrCreate(['volume' => 50], ['price' => $price, 'stock' => 0, 'is_active' => true]);
            }, [['E2E Parfum Alfa', 150000], ['E2E Parfum Beta', 100000]]);
        });
    }
}
