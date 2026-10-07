<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SearchMatcher
{
    public static function term(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }

    public static function normalize(?string $value): string
    {
        $value = mb_strtolower(Str::ascii(mb_substr($value ?? '', 0, 8000)));
        preg_match_all('/\p{L}+|\p{N}+/u', $value, $matches);

        return implode(' ', $matches[0]);
    }

    /** Lower scores are more relevant. Every query word must match. */
    public static function score(string $term, array $texts, array $identifiers = []): ?int
    {
        $needle = self::normalize(self::term($term));
        if ($needle === '') {
            return trim($term) === '' ? 0 : null;
        }
        foreach ($identifiers as $identifier) {
            if ($identifier !== null && mb_strtolower(trim((string) $identifier)) === mb_strtolower(trim($term))) {
                return 0;
            }
        }

        $fields = array_map(fn ($text) => self::normalize($text), $texts);
        $words = array_unique(explode(' ', implode(' ', $fields)));
        $score = 0;
        foreach (array_unique(explode(' ', $needle)) as $part) {
            $best = null;
            foreach ($words as $word) {
                if ($word === $part) {
                    $cost = 0;
                } elseif (! ctype_digit($part) && strlen($part) >= 2 && str_starts_with($word, $part)) {
                    $cost = 2;
                } elseif (! ctype_digit($part) && strlen($part) >= 3 && str_contains($word, $part)) {
                    $cost = 3;
                } else {
                    $limit = strlen($part) >= 8 ? 2 : (strlen($part) >= 4 ? 1 : 0);
                    if ($limit === 0 || ! ctype_alpha($part) || ! ctype_alpha($word) || abs(strlen($part) - strlen($word)) > $limit) {
                        continue;
                    }
                    $distance = levenshtein($part, $word);
                    // A swapped adjacent pair is a common single keyboard error.
                    if ($distance === 2 && strlen($part) === strlen($word)) {
                        for ($i = 0; $i < strlen($part) - 1; $i++) {
                            $swapped = substr_replace($part, $part[$i + 1].$part[$i], $i, 2);
                            if ($swapped === $word) {
                                $distance = 1;
                                break;
                            }
                        }
                    }
                    if ($distance > $limit) {
                        continue;
                    }
                    $cost = 10 + $distance;
                }
                $best = $best === null ? $cost : min($best, $cost);
                if ($best === 0) {
                    break;
                }
            }
            if ($best === null) {
                return null;
            }
            $score += $best;
        }

        return $score * 10 + (in_array($needle, $fields, true) ? 0 : 1);
    }

    public static function filter(Collection $rows, string $term, Closure $texts, ?Closure $identifiers = null): Collection
    {
        return $rows->map(fn ($row) => ['row' => $row,
            'score' => self::score($term, $texts($row), $identifiers ? $identifiers($row) : []),
        ])->filter(fn ($entry) => $entry['score'] !== null)
            ->sortBy('score', SORT_NUMERIC)->pluck('row')->values();
    }

    /** Candidates must come from the same eligibility/authorization scope as the final query. */
    public static function constrain(Builder $query, string $term, Collection $candidates, Closure $texts, ?Closure $identifiers = null, bool $rank = true): Builder
    {
        $ranked = $candidates->mapWithKeys(fn ($row) => [$row->getKey() => self::score($term, $texts($row), $identifiers ? $identifiers($row) : [])])
            ->filter(fn ($score) => $score !== null);
        $query->whereIn($query->getModel()->getQualifiedKeyName(), $ranked->keys()->all());
        if ($rank && $ranked->isNotEmpty()) {
            $cases = implode(' ', array_fill(0, $ranked->count(), 'WHEN ? THEN ?'));
            $bindings = [];
            foreach ($ranked as $id => $score) {
                array_push($bindings, $id, $score);
            }
            $query->orderByRaw('CASE '.$query->getModel()->getQualifiedKeyName().' '.$cases.' ELSE 999999 END', $bindings);
        }

        return $query;
    }
}
