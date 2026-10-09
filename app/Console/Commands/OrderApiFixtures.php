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
 * creates fresh ones. Recipient names start with "E2E " and phones/addresses are fake. Contract r4.2 adds
 * `websiteIssue` (one open issue opened in the Admin PWA), created only when QAMMARIS_ORDER_API_WEBSITE_ISSUES is on.
 */
class OrderApiFixtures extends Command
{
    protected $signature = 'qammaris:order-api:fixtures {--actor= : Username or email of the admin the fixtures are attributed to} {--reset : Cancel earlier fixtures and create six fresh ones}';

    protected $description = 'Create six synthetic, unclaimed V2 orders for Qammaris App staging tests (never production)';

    public const SCENARIOS = [
        'local' => ['fulfillment' => 'local_delivery', 'lines' => [1, 1], 'phone' => '080000000001'],
        'intercity' => ['fulfillment' => 'intercity', 'lines' => [1, 0], 'phone' => '080000000002'],
        'customerCourier' => ['fulfillment' => 'local_delivery', 'lines' => [1, 0], 'phone' => '080000000003', 'customer_courier' => true],
        'pickup' => ['fulfillment' => 'pickup', 'lines' => [0, 1], 'phone' => '080000000004'],
        'issue' => ['fulfillment' => 'local_delivery', 'lines' => [2, 0], 'phone' => '080000000005'],
        'costs' => ['fulfillment' => 'local_delivery', 'lines' => [0, 1], 'phone' => '080000000006'],
        'websiteIssue' => ['fulfillment' => 'pickup', 'lines' => [1, 0], 'phone' => '080000000007', 'website_issue' => true],
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
            if (($spec['website_issue'] ?? false) && ! config('orders_api.website_issues')) {
                continue; // r4.2 only: needs the Admin PWA issue path open while the API is on.
            }
            $existing = $this->fixtures($scenario);
            if ($this->option('reset')) {
                foreach ($existing->whereIn('lifecycle', ['awaiting_customer', 'active']) as $old) {
                    $orders->cancel($old, $old->revision, OrderActor::user($actor), 'E2E reset');
                }
                $existing = collect();
            }
            $order = $existing->first(fn (OnlineOrder $order) => self::problems($order, $scenario) === [])
                ?? $this->createFixture($create, $orders, $actor, $variants, $scenario, $spec);
            // Keyed like the App's E2E env (local, intercity, customerCourier, pickup, issue, costs, websiteIssue).
            $result[$scenario] = ['id' => $order->public_id, 'number' => $order->code, 'revision' => $order->fresh()->revision, 'fulfillment' => $order->fulfillment];
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
        if ($spec['website_issue'] ?? false) {
            $orders->openIssue($order->fresh(), null, OrderActor::user($actor), 'stock_problem', 'E2E kendala dari Admin PWA (sintetis)');
        }

        return $order->fresh();
    }

    private function fixtures(string $scenario)
    {
        return OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)->where('staff_note', '[e2e-fixture:'.$scenario.']')->latest('id')->get();
    }

    /**
     * What keeps a fixture from matching the App preflight (empty = clean): active, unclaimed, preparation not started,
     * no costs, recipient "E2E …"; no issue, except `websiteIssue`, which has exactly one open issue opened in the Website.
     *
     * @return list<string>
     */
    public static function problems(OnlineOrder $order, string $scenario): array
    {
        $issues = $order->issues()->get();
        $issueOk = ($scenario === 'websiteIssue')
            ? $issues->count() === 1 && $issues->first()->status === 'open' && $issues->first()->opened_by_app_user_id === null
            : $issues->isEmpty();

        return array_keys(array_filter([
            'tidak aktif' => $order->lifecycle !== 'active', 'diklaim' => $order->claims()->exists(),
            'packing dimulai' => $order->preparation_status !== 'not_started', 'sudah diserahkan' => $order->handover_status !== 'pending',
            'ada biaya' => $order->costs()->exists(), 'kendala tidak sesuai' => ! $issueOk,
            'nama bukan E2E' => ! str_starts_with((string) $order->customer_name, 'E2E '),
        ]));
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
