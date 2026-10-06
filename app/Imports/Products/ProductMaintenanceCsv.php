<?php

namespace App\Imports\Products;

class ProductMaintenanceCsv
{
    public const VERSION = 'maintenance-v1';

    public const MAX_ROWS = 1000;

    public const HEADERS = [
        'product_id',
        'expected_updated_at',
        'expected_row_fingerprint',
        'nama_produk',
        'deskripsi_produk',
        'harga',
        'brand',
        'gender',
        'stok_snapshot',
        'terlaris',
        'kategori',
        'ukuran_ml',
        'top_notes',
        'middle_notes',
        'base_notes',
    ];

    public const EXAMPLE = [
        '123',
        '2026-09-16T12:00:00+00:00',
        '0000000000000000000000000000000000000000000000000000000000000000',
        'Contoh Nama Baru',
        '',
        '599000',
        '',
        '',
        '',
        '',
        '',
        '100',
        '',
        '',
        '',
    ];
}
