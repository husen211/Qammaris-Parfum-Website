<?php

namespace App\Services;

use App\Models\FragranceProfile;
use App\Models\Product;
use App\Support\FragrancePreference\ProfileBuilder;
use App\Support\FragrancePreference\RecommendationEngine;
use DomainException;
use Illuminate\Support\Facades\Schema;

final class FragrancePreferenceCatalog
{
    // Called only by future PREF-03 adapter. No routes/controller changes in PREF-02.
    public function recommend(array $answers, bool $readyOnly = false): array
    {
        if (! config('fragrance_preference.enabled') || ! Schema::hasTable('fragrance_profiles')) {
            throw new DomainException('Preference engine is disabled.');
        }
        $products = Product::published()->with(['variants', 'brand', 'primaryImage'])->orderBy('id')->limit(5001)->get();
        if ($products->count() > 5000) {
            throw new DomainException('Catalog exceeds recommendation bound.');
        }
        $profiles = FragranceProfile::whereIn('product_id', $products->modelKeys())->get()->keyBy('product_id');
        $store = new FragranceProfileStore;
        $rows = [];
        foreach ($products as $product) {
            $offers = $product->variants->where('is_active', true);
            $offer = $offers->count() === 1 ? $offers->first() : null;
            $profile = $profiles->get($product->id);
            $rows[] = [...FragranceProfileStore::source($product), 'name' => $product->name, 'price' => $offer?->price, 'size_ml' => $offer?->volume, 'gender' => $product->gender, 'availability' => $product->effective_availability, 'active_offer_count' => $offers->count(), 'public' => $product->isPubliclyVisible(), 'hidden' => (bool) $product->qammaris_app_hidden, 'archived' => $product->archived_at !== null, 'profile' => $profile ? $store->effective($profile, $product) : null];
        }
        $result = (new RecommendationEngine(ProfileBuilder::fromFiles()))->recommend($answers, $rows, $readyOnly);
        foreach (['main', 'alternative'] as $slot) {
            foreach ($result[$slot] as &$row) {
                $product = $products->find($row['product_id']);
                $row['product'] = $product;
                $row['brand'] = $product->brand?->name;
                $row['slug'] = $product->slug;
            }
            unset($row);
        }

        return $result;
    }
}
