<?php

namespace App\Services;

class ProductImportPayloadHasher
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    public function hash(array $data, array $issues, string $action): string
    {
        return hash('sha256', json_encode([
            'data' => $data,
            'issues' => $issues,
            'action' => $action,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
