<?php

namespace App\Support\OrderApi;

use LogicException;
use stdClass;

/**
 * Order API v1 baseline schemas (r4.1, compiled from docs/integrations/qammaris-order-api-v1.openapi.yaml into
 * resources/order-api/openapi-v1.json). Strict JSON Schema subset: an unknown keyword is an error, not ignored.
 * Data is decoded with json_decode(..., false) so JSON objects and arrays stay distinguishable.
 */
final class OrderApiSchema
{
    private const KEYWORDS = ['$ref', 'type', 'enum', 'const', 'required', 'properties', 'additionalProperties', 'items', 'minItems',
        'maxItems', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum', 'minProperties', 'allOf', 'oneOf', 'if', 'then',
        'format', 'description', 'default', 'x-error-status'];

    private static ?array $spec = null;

    public static function spec(): array
    {
        return self::$spec ??= json_decode(file_get_contents(resource_path('order-api/openapi-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> human-readable violations; empty when valid */
    public static function errors(mixed $data, string $schemaName): array
    {
        $errors = [];
        self::validate($data, ['$ref' => '#/components/schemas/'.$schemaName], '', $errors);

        return $errors;
    }

    /**
     * Field-keyed violations for the `validation_failed` error (`details.fields`).
     *
     * @return array<string, string>
     */
    public static function fieldErrors(mixed $data, string $schemaName): array
    {
        $fields = [];
        foreach (self::errors($data, $schemaName) as $error) {
            [$path, $message] = explode(': ', $error, 2) + [1 => 'tidak valid'];
            $fields[ltrim($path, '.') ?: 'body'] ??= $message;
        }

        return $fields;
    }

    private static function resolve(string $ref): array
    {
        $node = self::spec();
        foreach (explode('/', substr($ref, 2)) as $part) {
            $node = $node[$part] ?? throw new LogicException("Unresolvable \$ref {$ref}");
        }

        return $node;
    }

    private static function valid(mixed $data, array $schema): bool
    {
        $errors = [];
        self::validate($data, $schema, '', $errors);

        return $errors === [];
    }

    private static function validate(mixed $data, array $schema, string $path, array &$errors): void
    {
        foreach (array_keys($schema) as $keyword) {
            if (! in_array($keyword, self::KEYWORDS, true)) {
                throw new LogicException("Unsupported schema keyword {$keyword}");
            }
        }
        if (isset($schema['$ref'])) {
            $schema = self::resolve($schema['$ref']) + array_diff_key($schema, ['$ref' => true]);
        }
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            if (! array_filter($types, fn ($type) => self::hasType($data, $type))) {
                $errors[] = "{$path}: harus ".implode('|', $types);

                return;
            }
        }
        if (array_key_exists('enum', $schema) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = "{$path}: nilai tidak dikenal";
        }
        if (array_key_exists('const', $schema) && $data !== $schema['const']) {
            $errors[] = "{$path}: harus ".json_encode($schema['const']);
        }
        if (is_string($data)) {
            $length = mb_strlen($data);
            if (isset($schema['minLength']) && $length < $schema['minLength']) {
                $errors[] = "{$path}: minimal {$schema['minLength']} karakter";
            }
            if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
                $errors[] = "{$path}: maksimal {$schema['maxLength']} karakter";
            }
            if (isset($schema['pattern']) && ! preg_match('~'.str_replace('~', '\~', $schema['pattern']).'~u', $data)) {
                $errors[] = "{$path}: format tidak valid";
            }
            if (($schema['format'] ?? null) === 'date-time' && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $data)) {
                $errors[] = "{$path}: harus waktu ISO 8601";
            }
        }
        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: minimal {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: maksimal {$schema['maximum']}";
            }
        }
        if ($data instanceof stdClass) {
            $props = get_object_vars($data);
            foreach ($schema['required'] ?? [] as $required) {
                if (! array_key_exists($required, $props)) {
                    $errors[] = "{$path}.{$required}: wajib diisi";
                }
            }
            if (isset($schema['minProperties']) && count($props) < $schema['minProperties']) {
                $errors[] = "{$path}: kurang lengkap";
            }
            foreach ($props as $name => $value) {
                if (isset($schema['properties'][$name])) {
                    self::validate($value, $schema['properties'][$name], "{$path}.{$name}", $errors);
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = "{$path}.{$name}: field tidak dikenal";
                }
            }
        }
        if (is_array($data)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "{$path}: minimal {$schema['minItems']} item";
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = "{$path}: maksimal {$schema['maxItems']} item";
            }
            foreach ($data as $i => $item) {
                if (isset($schema['items'])) {
                    self::validate($item, $schema['items'], "{$path}.{$i}", $errors);
                }
            }
        }
        foreach ($schema['allOf'] ?? [] as $sub) {
            self::validate($data, $sub, $path, $errors);
        }
        if (isset($schema['oneOf'])) {
            $matches = count(array_filter($schema['oneOf'], fn ($sub) => self::valid($data, $sub)));
            if ($matches !== 1) {
                $errors[] = "{$path}: bentuk tidak valid";
            }
        }
        if (isset($schema['if'], $schema['then']) && self::valid($data, $schema['if'])) {
            self::validate($data, $schema['then'], $path, $errors);
        }
    }

    private static function hasType(mixed $data, string $type): bool
    {
        return match ($type) {
            'object' => $data instanceof stdClass,
            'array' => is_array($data) && array_is_list($data),
            'string' => is_string($data),
            'integer' => is_int($data),
            'number' => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            default => throw new LogicException("Unknown type {$type}"),
        };
    }
}
