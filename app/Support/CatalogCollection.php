<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * SEO-03: a permanent catalog page (config/catalog_collections.php) — the normal catalog with one filter fixed by
 * its address. Gender pages live at /koleksi/{key}, brand pages at /brand/{slug}.
 */
final class CatalogCollection
{
    private function __construct(
        /** Context key carried to product pages so "Kembali" returns here: "parfum-pria" or "brand-afnan". */
        public readonly string $key,
        public readonly string $name,
        public readonly string $eyebrow,
        public readonly string $title,
        public readonly string $description,
        /** @var list<string> paragraphs; "{store}" and "{unisex}" become links */
        public readonly array $intro,
        /** @var array{gender?: string, brand?: list<int>} */
        public readonly array $locked,
        private readonly string $routeName,
        private readonly array $routeParameters,
    ) {}

    public static function gender(string $key): ?self
    {
        $config = config("catalog_collections.genders.$key");
        if (! is_array($config)) {
            return null;
        }

        return new self($key, $config['name'], 'Koleksi Qammaris', $config['title'], $config['description'], $config['intro'],
            ['gender' => $config['gender']], 'products.collection', ['collection' => $key]);
    }

    public static function brand(Brand $brand): ?self
    {
        $config = config("catalog_collections.brands.{$brand->slug}");
        if (! is_array($config) || ! $brand->is_active) {
            return null;
        }
        $name = $config['name'];

        return new self('brand-'.$brand->slug, 'Parfum '.$name, 'Brand', "Parfum {$name} Original di Palu - Qammaris Perfumes",
            "Parfum {$name} original di Qammaris Palu: harga, ukuran, dan stok terbaru. Coba tester di toko atau pesan online, kirim ke seluruh Indonesia.",
            [$config['intro'], "Semua parfum {$name} di sini bisa dicoba langsung di {store} sebelum membeli. Pesan online juga bisa, kami kirim ke seluruh Indonesia."],
            ['brand' => [(int) $brand->id]], 'products.brand', ['brand' => $brand->slug]);
    }

    /** The collection a product page was opened from (`?koleksi=`), if it still exists. */
    public static function fromContext(mixed $key): ?self
    {
        if (! is_string($key) || $key === '') {
            return null;
        }
        if (str_starts_with($key, 'brand-')) {
            $brand = Brand::where('slug', substr($key, 6))->first();

            return $brand ? self::brand($brand) : null;
        }

        return self::gender($key);
    }

    /**
     * Footer and sitemap links: every gender page, and brand pages whose brand is active and on sale.
     *
     * @return Collection<int, array{name: string, url: string, group: string, slug: string}>
     */
    public static function links(): Collection
    {
        $genders = collect(config('catalog_collections.genders'))
            ->map(fn (array $config, string $key) => ['name' => $config['name'], 'url' => route('products.collection', $key), 'group' => 'gender', 'slug' => $key]);
        $configured = config('catalog_collections.brands');
        // Which configured brands are active and on sale changes rarely; the footer asks on every page.
        $onSale = cache()->remember('catalog_collection_brands', 3600, fn () => Brand::active()
            ->whereIn('slug', array_keys($configured))
            ->whereHas('products', fn ($products) => $products->published())
            ->pluck('slug')->all());
        $brands = collect(array_keys($configured))
            ->filter(fn (string $slug) => in_array($slug, $onSale, true))
            ->map(fn (string $slug) => ['name' => $configured[$slug]['name'], 'url' => route('products.brand', $slug), 'group' => 'brand', 'slug' => $slug]);

        return $genders->values()->concat($brands->values());
    }

    public function url(array $query = []): string
    {
        return route($this->routeName, $this->routeParameters + $query);
    }

    /** Escaped intro paragraphs with the store and unisex links filled in. */
    public function introHtml(): array
    {
        $links = [
            '{store}' => '<a href="'.e(route('store.location')).'">toko kami di Palu</a>',
            '{unisex}' => '<a href="'.e(route('products.collection', 'parfum-unisex')).'">koleksi parfum unisex</a>',
        ];

        return array_map(fn (string $paragraph) => strtr(e($paragraph), $links), $this->intro);
    }

    /** Names of the first few products on the default first page, for a short "most wanted" line. */
    public static function highlights(iterable $products, int $count = 3): string
    {
        $names = collect($products)->take($count)->map(fn (Product $product) => $product->name)->values();

        return match (true) {
            $names->count() < 2 => '',
            $names->count() === 2 => $names->implode(' dan '),
            default => $names->slice(0, -1)->implode(', ').', dan '.$names->last(),
        };
    }
}
