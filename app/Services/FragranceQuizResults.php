<?php

namespace App\Services;

use App\Models\FragranceQuizResult;
use App\Models\Product;
use App\Support\FragrancePreference\ProfileBuilder;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FragranceQuizResults
{
    public function create(array $answers, string $browserHash): FragranceQuizResult
    {
        $result = app(FragrancePreferenceCatalog::class)->recommend($answers);
        foreach (['main', 'alternative'] as $slot) {
            foreach ($result[$slot] as &$row) {
                unset($row['product']);
            }
            unset($row);
        }

        return DB::transaction(fn () => FragranceQuizResult::create(['id' => (string) Str::uuid(), 'browser_hash' => $browserHash, 'question_version' => config('fragrance_preference.question_version'), 'engine_version' => $result['engine_version'], 'engine_fingerprint' => $result['engine_fingerprint'], 'answers' => $result['answers'], 'recommendations' => $result, 'expires_at' => now()->addDays(7)]));
    }

    public function owned(string $id, string $browserHash): FragranceQuizResult
    {
        $result = FragranceQuizResult::whereKey($id)->where('browser_hash', $browserHash)->firstOrFail();
        abort_if($result->expires_at->lte(now()), 410, 'Hasil tes sudah kedaluwarsa. Silakan mulai tes baru.');

        return $result;
    }

    public function display(FragranceQuizResult $stored, bool $readyOnly): array
    {
        $result = $stored->recommendations;
        $ids = collect([...$result['main'], ...$result['alternative']])->pluck('product_id');
        $products = Product::published()->with(['variants', 'brand', 'primaryImage'])->whereIn('id', $ids)->get()->keyBy('id');
        $result['changed'] = false;
        foreach (['main', 'alternative'] as $slot) {
            $visible = [];
            foreach ($result[$slot] as $row) {
                $product = $products->get($row['product_id']);
                $offers = $product?->variants->where('is_active', true);
                $offer = $offers?->count() === 1 ? $offers->first() : null;
                if (! $product || ! $offer || Rupiah::minorUnits($offer->price) < 1 || $offer->volume < 1) {
                    $result['changed'] = true;

                    continue;
                }
                $row['changed'] = (string) $row['price'] !== (string) $offer->price || $row['availability'] !== $product->effective_availability || $row['source_fingerprint'] !== ProfileBuilder::sourceFingerprint(FragranceProfileStore::source($product)) || $row['size_ml'] !== $offer->volume;
                $result['changed'] = $result['changed'] || $row['changed'];
                $row['price'] = $offer->price;
                $row['size_ml'] = $offer->volume;
                $row['availability'] = $product->effective_availability;
                $row['product'] = $product;
                if (! $readyOnly || $row['availability'] === 'available') {
                    $visible[] = $row;
                }
            }
            $result[$slot] = $visible;
        }
        $result['empty'] = ! $result['main'] && ! $result['alternative'];

        return $result;
    }
}
