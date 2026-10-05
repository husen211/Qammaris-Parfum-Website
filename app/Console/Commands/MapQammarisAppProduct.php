<?php

namespace App\Console\Commands;

use App\Actions\Products\ApplyQammarisAppAvailability;
use App\Actions\Products\MapExternalProductIdentity;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MapQammarisAppProduct extends Command
{
    protected $signature = 'qammaris-app:map {product_id} {external_id} {--confirm : Apply the exact mapping after reviewing the preview}';

    protected $description = 'Preview or apply an explicit UUID mapping; never match by name/SKU';

    public function handle(MapExternalProductIdentity $map, ApplyQammarisAppAvailability $apply): int
    {
        $product = Product::query()->find($this->argument('product_id'));
        $source = DB::table('qammaris_app_products')->where('id', $this->argument('external_id'))->first();
        if (! $product || ! $source) {
            $this->error('Product or synchronized source UUID not found.');

            return self::FAILURE;
        }
        $snapshot = json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->table(['Website ID', 'Website name', 'Source UUID', 'Source name', 'Availability', 'Hidden'], [[
            $product->id, $product->name, $source->id, $snapshot['name'], $snapshot['availability'], $snapshot['hidden'] ? 'yes' : 'no',
        ]]);
        if (! $this->option('confirm')) {
            $this->info('Preview only. Review identity and rerun with --confirm. Prices, publication, URLs and media are preserved.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($product, $source, $map, $apply): void {
            $lockedSource = DB::table('qammaris_app_products')->where('id', $source->id)->lockForUpdate()->first();
            $map->handle($product, ProductExternalIdentity::PROVIDER_QAMMARIS_APP, $source->id);
            $apply->handle($product, json_decode($lockedSource->snapshot, true, flags: JSON_THROW_ON_ERROR));
        }, 3);
        $this->info('Exact mapping applied with availability audit.');

        return self::SUCCESS;
    }
}
