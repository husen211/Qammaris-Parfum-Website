<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

final class InquiryWhatsApp
{
    public function productUrl(
        ?string $number,
        Product $product,
        ProductVariant $offer,
        string $intent,
    ): ?string {
        $intentLabel = $intent === 'restock' ? 'informasi restock' : 'konfirmasi stok';
        $opening = $intent === 'restock'
            ? 'Halo Admin Qammaris, saya ingin menanyakan restock produk ini:'
            : 'Halo Admin Qammaris, saya ingin menanyakan ketersediaan produk ini:';

        $message = implode("\n", [
            $opening,
            '',
            '*'.$this->plainText($product->brand?->name ?? 'Brand belum diisi').' - '.$this->plainText($product->name).'*',
            'Ukuran: '.$offer->volume.' ml',
            'Harga saat ini: '.$this->rupiah($offer->price),
            'Status website: '.$this->availabilityLabel($product->effective_availability),
            'Link produk: '.route('products.show', $product),
            'Intent: '.$intentLabel,
            '',
            'Mohon konfirmasi informasi terbaru. Inquiry ini belum menjadi transaksi atau reservasi.',
        ]);

        return $this->url($number, $message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function listUrl(?string $number, array $items, ?string $note = null): ?string
    {
        if ($items === []) {
            return null;
        }

        $lines = [
            'Halo Admin Qammaris, saya ingin menanyakan ketersediaan produk berikut:',
            '',
            '*DAFTAR INQUIRY*',
        ];
        $estimate = 0;

        foreach ($items as $index => $item) {
            $lineTotal = (int) $item['price'] * (int) $item['quantity'];
            $estimate += $lineTotal;
            $lines[] = '';
            $lines[] = ($index + 1).'. *'.$this->plainText($item['brand_name']).' - '.$this->plainText($item['product_name']).'*';
            $lines[] = '   Ukuran: '.$item['volume'].' ml';
            $lines[] = '   Jumlah yang diminati: '.$item['quantity'];
            $lines[] = '   Harga saat ini: '.$this->rupiah($item['price']).' / item';
            $lines[] = '   Status website: '.$this->availabilityLabel($item['effective_availability']);
            $lines[] = '   Link: '.$item['product_url'];
        }

        $lines[] = '';
        $lines[] = 'Estimasi nilai produk: '.$this->rupiah($estimate);

        $normalizedNote = $this->plainText($note ?? '');
        if ($normalizedNote !== '') {
            $lines[] = 'Catatan: '.$normalizedNote;
        }

        $lines[] = '';
        $lines[] = 'Mohon konfirmasi stok dan harga terbaru. Daftar inquiry ini belum menjadi transaksi atau reservasi.';

        return $this->url($number, implode("\n", $lines));
    }

    public function availabilityLabel(string $availability): string
    {
        return match ($availability) {
            Product::AVAILABILITY_AVAILABLE => 'Tersedia saat diperiksa',
            Product::AVAILABILITY_SOLD_OUT => 'Sold out',
            default => 'Konfirmasi stok',
        };
    }

    public function hasValidNumber(?string $number): bool
    {
        return $this->normalizeNumber($number) !== null;
    }

    private function url(?string $number, string $message): ?string
    {
        $normalized = $this->normalizeNumber($number);
        if ($normalized === null) {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?'.http_build_query(
            ['text' => $message],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }

    private function normalizeNumber(?string $number): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return strlen($normalized) >= 8 && strlen($normalized) <= 15 ? $normalized : null;
    }

    private function plainText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function rupiah(int|float|string $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
