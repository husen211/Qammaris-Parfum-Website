<?php

namespace App\Imports\Products;

class CanonicalProductCsv
{
    public const VERSION = 'v1';

    public const MAX_ROWS = 1000;

    public const HEADERS = [
        'provider',
        'kode_produk',
        'nama_produk',
        'deskripsi_produk',
        'harga',
        'brand',
        'gender',
        'stok',
        'terlaris',
        'kategori',
        'ukuran_ml',
        'top_notes',
        'middle_notes',
        'base_notes',
        'foto_utama_url',
        'foto_2_url',
        'foto_3_url',
    ];

    public const EXAMPLE = [
        'shopee',
        'CONTOH-HAPUS-BARIS-INI',
        'Contoh Nama Produk',
        'Deskripsi produk hasil kurasi. Hapus baris contoh ini sebelum preview.',
        '599000',
        'Contoh Brand',
        'Unisex',
        '',
        'tidak',
        'Eau de Parfum (EDP)',
        '100',
        'Bergamot|Lemon',
        'Lavender',
        'Musk|Amber',
        'https://example.com/foto-utama.jpg',
        '',
        '',
    ];
}
