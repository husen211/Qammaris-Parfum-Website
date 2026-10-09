<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** Exact search groups for read-only audit; original labels remain the authority. */
final class FragranceNoteNormalizer
{
    private array $aliases = [];

    public function __construct(private readonly array $dictionary)
    {
        if (($dictionary['schema'] ?? '') !== 'qammaris-note-aliases-v1') {
            throw new InvalidArgumentException('Invalid note dictionary.');
        }
        foreach ($dictionary['notes'] as $canonical => $aliases) {
            foreach ([$canonical, ...$aliases] as $alias) {
                $alias = self::text($alias);
                if (isset($this->aliases[$alias]) && $this->aliases[$alias] !== $canonical) {
                    throw new InvalidArgumentException('Conflicting note alias.');
                }
                $this->aliases[$alias] = $canonical;
            }
        }
        uksort($this->aliases, fn ($a, $b) => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
    }

    public static function text(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));
        $value = preg_replace('/\b(?:and|dan)\b/u', ' ', $value);

        return trim(preg_replace('/[^a-z0-9]+/u', ' ', $value));
    }

    public function note(string $raw): array
    {
        $normalized = self::text($raw);
        $remaining = ' '.$normalized.' ';
        $placeholder = false;
        foreach ($this->dictionary['placeholders'] as $marker) {
            $marker = self::text($marker);
            if (str_contains($remaining, ' '.$marker.' ')) {
                $placeholder = true;
                $remaining = str_replace(' '.$marker.' ', ' ', $remaining);
            }
        }
        $notes = [];
        foreach ($this->aliases as $alias => $canonical) {
            $pattern = '/(?<![a-z0-9])'.preg_quote($alias, '/').'(?![a-z0-9])/';
            if (preg_match($pattern, $remaining)) {
                $notes[] = $canonical;
                $remaining = preg_replace($pattern, ' ', $remaining);
            }
        }
        // Remove only listed modifiers after a real note matched. Unknown names stay unknown.
        if ($notes) {
            foreach ($this->dictionary['modifiers'] as $modifier) {
                $remaining = preg_replace('/(?<![a-z0-9])'.preg_quote(self::text($modifier), '/').'(?![a-z0-9])/', ' ', $remaining);
            }
        }
        $notes = array_values(array_unique($notes));
        sort($notes);
        $remaining = trim(preg_replace('/\s+/', ' ', $remaining));

        return ['raw' => $raw, 'normalized' => $normalized, 'notes' => $notes,
            'placeholder' => $placeholder, 'unmapped' => $remaining,
            'status' => $placeholder ? 'placeholder' : ($remaining !== '' ? ($notes ? 'partial' : 'unmapped') : ($notes ? 'recognized' : 'empty'))];
    }

    public function layers(mixed $source): array
    {
        $layers = [];
        foreach (['top', 'middle', 'base'] as $layer) {
            $values = is_array($source) ? ($source[$layer] ?? []) : [];
            $valid = is_array($source) && is_array($values) && count($values) <= 100 && ! array_filter($values, fn ($value) => ! is_string($value) || mb_strlen($value) > 1000);
            if (! $valid) {
                $layers[$layer] = ['valid' => false, 'entries' => [], 'notes' => []];

                continue;
            }
            $entries = array_map(fn ($value) => $this->note($value), $values);
            $notes = array_values(array_unique(array_merge([], ...array_column($entries, 'notes'))));
            sort($notes);
            $layers[$layer] = ['valid' => true, 'entries' => $entries, 'notes' => $notes];
        }

        return $layers;
    }
}
