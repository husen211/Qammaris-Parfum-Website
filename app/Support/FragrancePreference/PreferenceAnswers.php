<?php

namespace App\Support\FragrancePreference;

use InvalidArgumentException;

final class PreferenceAnswers
{
    public const FAMILIES = ['citrus', 'aquatic', 'green_herbal', 'fruit', 'floral', 'wood', 'gourmand', 'amber_resin', 'oud', 'leather_smoky', 'musk', 'powdery'];

    public static function validate(array $input): array
    {
        foreach (['budget_max', 'use', 'environment', 'likes', 'avoid', 'projection', 'longevity'] as $required) {
            if (! array_key_exists($required, $input)) {
                throw new InvalidArgumentException('Missing preference: '.$required);
            }
        }
        // Explicit PREF-01 spelling compatibility; normalize before contradiction checks.
        foreach (['likes', 'avoid'] as $key) {
            if (is_array($input[$key])) {
                $input[$key] = array_map(fn ($value) => is_string($value) ? (['fruity' => 'fruit', 'woody' => 'wood', 'green' => 'green_herbal'][$value] ?? $value) : $value, $input[$key]);
            }
        }
        if (($input['projection'] ?? null) === 'any') {
            $input['projection'] = 'unknown';
        }
        if (($input['longevity'] ?? null) === 'any') {
            $input['longevity'] = 'not_priority';
        }
        $choices = [
            'use' => ['daily', 'office', 'casual', 'event', 'any'],
            'environment' => ['ac', 'outdoor', 'mixed', 'any'],
            'sweetness' => ['none', 'light', 'medium', 'sweet', 'any'],
            'time' => ['day', 'night', 'both', 'any'],
            'projection' => ['close', 'medium', 'strong', 'unknown'],
            'longevity' => ['not_priority', 'few_hours', 'all_day', 'unknown'],
            'gender' => ['pria', 'wanita', 'unisex', 'all'],
        ];
        $allowed = ['budget_max', 'budget_min', 'avoid_sweet', 'likes', 'avoid', 'favorite_product_id', ...array_keys($choices)];
        $avoidSweet = $input['avoid_sweet'] ?? false;
        if (! is_bool($avoidSweet)) {
            throw new InvalidArgumentException('Invalid sweet avoidance.');
        }
        // Server owns branch semantics: an old hidden sweetness answer cannot win.
        if ($avoidSweet) {
            $input['sweetness'] = 'none';
        } elseif (is_array($input['avoid']) && in_array('gourmand', $input['avoid'], true)) {
            $input['sweetness'] = 'any';
        }
        if (array_diff(array_keys($input), $allowed)) {
            throw new InvalidArgumentException('Unknown preference fields.');
        }
        $result = [];
        foreach ($choices as $key => $values) {
            $value = $input[$key] ?? match ($key) {
                'gender' => 'all', 'time' => 'any', default => null
            };
            if (! is_string($value) || ! in_array($value, $values, true)) {
                throw new InvalidArgumentException('Invalid preference: '.$key);
            }
            $result[$key] = $value;
        }
        foreach (['likes' => 3, 'avoid' => 12] as $key => $max) {
            $values = $input[$key] ?? null;
            if (! is_array($values) || ! array_is_list($values) || count($values) > $max || array_filter($values, fn ($value) => ! is_string($value) || ! in_array($value, self::FAMILIES, true))) {
                throw new InvalidArgumentException('Invalid preference: '.$key);
            }
            $result[$key] = array_values(array_unique($values));
        }
        if (array_intersect($result['likes'], $result['avoid'])) {
            throw new InvalidArgumentException('Liked and avoided aromas conflict.');
        }
        $budget = $input['budget_max'] ?? null;
        $minimum = $input['budget_min'] ?? 0;
        if (! is_int($minimum) || $minimum < 0 || $minimum > 99999999 || ($budget !== null && is_int($budget) && $minimum > $budget)) {
            throw new InvalidArgumentException('Invalid budget range.');
        }
        $favorite = $input['favorite_product_id'] ?? null;
        if ($budget !== null && (! is_int($budget) || $budget < 1 || $budget > 99999999)) {
            throw new InvalidArgumentException('Budget must be a positive whole rupiah value or null.');
        }
        if ($favorite !== null && (! is_int($favorite) || $favorite < 1)) {
            throw new InvalidArgumentException('Invalid favorite product.');
        }
        $result['budget_max'] = $budget;
        $result['budget_min'] = $minimum;
        $result['avoid_sweet'] = $avoidSweet;
        $result['favorite_product_id'] = $favorite;

        return $result;
    }
}
