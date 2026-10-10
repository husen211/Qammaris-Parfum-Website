<?php

namespace App\Services;

use App\Models\FragranceProfile;
use App\Models\FragranceProfileRevision;
use App\Models\Product;
use App\Models\User;
use App\Support\FragrancePreference\PreferenceAnswers;
use App\Support\FragrancePreference\ProfileBuilder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class FragranceProfileStore
{
    private ?ProfileBuilder $profileBuilder = null;

    private function builder(): ProfileBuilder
    {
        return $this->profileBuilder ??= ProfileBuilder::fromFiles();
    }

    public static function source(Product $product): array
    {
        return ['id' => $product->id, 'description' => $product->description, 'notes' => $product->fragrance_notes];
    }

    public function preview(): array
    {
        return $this->plan(false);
    }

    public function apply(string $previewFingerprint, User $actor): array
    {
        $this->authorize($actor);
        if (! Schema::hasTable('fragrance_profiles') || ! Schema::hasTable('fragrance_profile_revisions')) {
            throw new DomainException('Profile migration is required before apply.');
        }

        return DB::transaction(function () use ($previewFingerprint, $actor) {
            $plan = $this->plan(true);
            if (! hash_equals($plan['fingerprint'], $previewFingerprint)) {
                throw new DomainException('Preview changed; run preview again.');
            }
            $builder = $this->builder();
            $changed = 0;
            foreach ($plan['rows'] as $row) {
                $existing = FragranceProfile::where('product_id', $row['product_id'])->lockForUpdate()->first();
                if ($row['action'] === 'unchanged') {
                    continue;
                }
                $product = Product::findOrFail($row['product_id']);
                $derived = $builder->build(self::source($product));
                $profile = $existing ?? new FragranceProfile(['product_id' => $product->id]);
                $profile->fill(['source_fingerprint' => $derived['source_fingerprint'], 'parser_fingerprint' => $derived['parser_fingerprint'], 'parser_version' => $derived['parser_version'], 'derived' => $derived, 'revision' => $existing ? $existing->revision + 1 : 1]);
                // Keep stale corrections for review; effective() never reapplies them silently.
                $profile->save();
                $this->revision($profile, $actor, 'build', ['source_fingerprint' => $profile->source_fingerprint, 'parser_fingerprint' => $profile->parser_fingerprint, 'stale_overrides' => $row['stale_overrides']]);
                $changed++;
            }

            return ['changed' => $changed, 'unchanged' => count($plan['rows']) - $changed, 'preview_fingerprint' => $previewFingerprint];
        });
    }

    private function plan(bool $lock): array
    {
        $builder = $this->builder();
        $query = Product::published()->orderBy('id')->limit(5001);
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get();
        if ($products->count() > 5000) {
            throw new DomainException('Catalog exceeds profile preview bound.');
        }
        $profiles = collect();
        if (Schema::hasTable('fragrance_profiles')) {
            $profileQuery = FragranceProfile::whereIn('product_id', $products->modelKeys());
            if ($lock) {
                $profileQuery->lockForUpdate();
            }
            $profiles = $profileQuery->get()->keyBy('product_id');
        }
        $rows = [];
        foreach ($products as $product) {
            $derived = $builder->build(self::source($product));
            $profile = $profiles->get($product->id);
            $unchanged = $profile && $profile->source_fingerprint === $derived['source_fingerprint'] && $profile->parser_fingerprint === $derived['parser_fingerprint'] && ProfileBuilder::hash($profile->derived) === ProfileBuilder::hash($derived);
            $rows[] = ['product_id' => $product->id, 'action' => $unchanged ? 'unchanged' : 'build', 'source_fingerprint' => $derived['source_fingerprint'], 'parser_fingerprint' => $derived['parser_fingerprint'], 'prior_revision' => $profile?->revision, 'prior_derived_fingerprint' => $profile ? ProfileBuilder::hash($profile->derived) : null, 'override_fingerprint' => ProfileBuilder::hash($profile?->overrides), 'stale_overrides' => count(array_filter($profile?->overrides ?? [], fn ($override) => ($override['source_fingerprint'] ?? '') !== $derived['source_fingerprint'] || ($override['parser_fingerprint'] ?? '') !== $derived['parser_fingerprint'])), 'review_issues' => $derived['review_issues'], 'unknowns' => $derived['unknowns']];
        }

        return ['schema' => 'qammaris-profile-preview-v1', 'rows' => $rows, 'fingerprint' => ProfileBuilder::hash($rows), 'writes' => false];
    }

    public function review(int $productId, int $expectedRevision, string $expectedSource, array $changes, string $evidence, User $actor): FragranceProfile
    {
        $this->authorize($actor);
        $this->validateCorrection($changes, $evidence);

        return DB::transaction(function () use ($productId, $expectedRevision, $expectedSource, $changes, $evidence, $actor) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $profile = FragranceProfile::where('product_id', $productId)->lockForUpdate()->firstOrFail();
            $builder = $this->builder();
            if ($profile->revision !== $expectedRevision || $profile->source_fingerprint !== $expectedSource || $expectedSource !== ProfileBuilder::sourceFingerprint(self::source($product)) || $profile->parser_fingerprint !== $builder->parserFingerprint()) {
                throw new DomainException('Profile/source changed; rebuild and review again.');
            }
            $overrides = $profile->overrides ?? [];
            foreach ($changes as $key => $value) {
                $overrides[$key] = ['value' => $value, 'evidence' => $evidence, 'actor_id' => $actor->id, 'source_fingerprint' => $expectedSource, 'parser_fingerprint' => $profile->parser_fingerprint];
            }
            if (ProfileBuilder::hash($overrides) === ProfileBuilder::hash($profile->overrides ?? [])) {
                return $profile;
            }
            $profile->overrides = $overrides;
            $profile->revision++;
            $profile->save();
            $this->revision($profile, $actor, 'review', ['changes' => $changes, 'source_fingerprint' => $expectedSource, 'basis' => $evidence]);

            return $profile;
        });
    }

    public function effective(FragranceProfile $profile, Product $product): ?array
    {
        $builder = $this->builder();
        if ($profile->source_fingerprint !== ProfileBuilder::sourceFingerprint(self::source($product)) || $profile->parser_fingerprint !== $builder->parserFingerprint()) {
            return null;
        }
        $derived = $profile->derived;
        foreach ($profile->overrides ?? [] as $key => $override) {
            if (($override['source_fingerprint'] ?? '') !== $profile->source_fingerprint || ($override['parser_fingerprint'] ?? '') !== $profile->parser_fingerprint) {
                $derived['review_issues'][] = ['code' => 'stale_override', 'attribute' => $key];

                continue;
            }
            if ($key === 'identity') {
                $derived['verified_identity'] = ['key' => $override['value'], 'status' => 'verified', 'evidence' => $override['evidence'], 'actor_id' => $override['actor_id']];

                continue;
            }
            $derived['attributes'][$key] = $override['value'];
            if ($key === 'aroma_target') {
                // Detected notes remain conservative avoid evidence, even after correcting dominant families.
                $derived['attributes']['families'] = array_values(array_unique([...$derived['attributes']['families'], ...$override['value']]));
                foreach ($override['value'] as $family) {
                    $derived['evidence']['families.'.$family][] = ['source' => 'review', 'raw' => $override['evidence'], 'actor_id' => $override['actor_id']];
                }
            } else {
                $derived['evidence'][$key] = [['source' => 'review', 'raw' => $override['evidence'], 'actor_id' => $override['actor_id']]];
            }
        }
        $derived['unknowns'] = $builder->unknowns($derived['attributes']);
        $derived['profile_revision'] = $profile->revision;

        return $derived;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->exists || User::whereKey($actor->id)->value('role') !== 'admin') {
            throw new DomainException('Existing admin access required.');
        }
    }

    private function validateCorrection(array $changes, string $evidence): void
    {
        if (! $changes || count($changes) > 6 || mb_strlen(trim($evidence)) < 8 || mb_strlen($evidence) > 2000 || array_diff(array_keys($changes), ['aroma_target', 'sweetness', 'projection', 'longevity', 'context', 'identity'])) {
            throw new InvalidArgumentException('Correction requires bounded attributes and source evidence.');
        }
        foreach ($changes as $key => $value) {
            $valid = match ($key) {
                'aroma_target' => is_array($value) && array_is_list($value) && count($value) > 0 && count($value) <= 12 && ! array_filter($value, fn ($v) => ! is_string($v) || ! in_array($v, PreferenceAnswers::FAMILIES, true)),
                'sweetness' => $value === null || in_array($value, ['none', 'light', 'medium', 'sweet'], true),
                'projection' => $value === null || in_array($value, ['close', 'medium', 'strong'], true),
                'context' => is_array($value) && array_is_list($value) && count($value) <= 9 && ! array_filter($value, fn ($v) => ! is_string($v) || ! in_array($v, ['daily', 'office', 'casual', 'event', 'ac', 'outdoor', 'mixed', 'day', 'night'], true)),
                'longevity' => $value === null || (is_array($value) && array_keys($value) === ['min_hours', 'max_hours'] && is_int($value['min_hours']) && ($value['max_hours'] === null || is_int($value['max_hours'])) && $value['min_hours'] >= 1 && $value['min_hours'] <= 48 && ($value['max_hours'] === null || ($value['max_hours'] >= $value['min_hours'] && $value['max_hours'] <= 48))),
                'identity' => $value === null || (is_string($value) && preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/D', $value)),
                default => false,
            };
            if (! $valid) {
                throw new InvalidArgumentException('Invalid correction: '.$key);
            }
        }
    }

    private function revision(FragranceProfile $profile, User $actor, string $operation, array $evidence): void
    {
        FragranceProfileRevision::create(['fragrance_profile_id' => $profile->id, 'actor_id' => $actor->id, 'revision' => $profile->revision, 'operation' => $operation, 'evidence' => $evidence]);
    }
}
