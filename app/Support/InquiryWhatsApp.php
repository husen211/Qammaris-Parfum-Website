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
        $managedByApp = $product->availability_source === 'qammaris_app';
        $intentLabel = $intent === 'restock' ? 'informasi restock' : 'konfirmasi stok';
        if ($managedByApp && $intent !== 'restock') {
            $intentLabel = 'informasi produk';
        }
        $opening = $intent === 'restock'
            ? 'Halo Admin Qammaris, saya ingin menanyakan restock produk ini:'
            : 'Halo Admin Qammaris, saya ingin menanyakan ketersediaan produk ini:';

        $message = implode("\n", [
            $opening,
            '',
            '*'.$this->plainText($product->brand?->name ?? 'Brand belum diisi').' - '.$this->plainText($product->name).'*',
            'Ukuran: '.$offer->volume.' ml',
            'Harga saat ini: '.Rupiah::format($offer->price),
            'Status website: '.CatalogAvailability::label($product),
            'Link produk: '.route('products.show', $product),
            'Intent: '.$intentLabel,
            '',
            'Terima kasih.',
        ]);

        return $this->url($number, $message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function orderUrl(?string $number, array $items, array $customer): ?string
    {
        if ($items === []) {
            return null;
        }
        $lines = ['Halo Qammaris, saya ingin memesan:', '', '*PESANAN QAMMARIS*'];
        $subtotal = 0;
        foreach ($items as $index => $item) {
            $lineTotal = Rupiah::minorUnits($item['price']) * (int) $item['quantity'];
            $subtotal += $lineTotal;
            $lines[] = '';
            $lines[] = ($index + 1).'. '.$this->plainText($item['brand_name']).' - '.$this->plainText($item['product_name']);
            $lines[] = 'Ukuran: '.$item['volume'].' ml';
            $lines[] = 'Jumlah: '.$item['quantity'];
            $lines[] = 'Harga: '.Rupiah::format($item['price']).' / item';
            $lines[] = 'Total produk: '.Rupiah::format(Rupiah::decimal($lineTotal));
            $lines[] = 'Link: '.$item['product_url'];
        }
        $lines[] = '';
        $lines[] = 'Subtotal: '.Rupiah::format(Rupiah::decimal($subtotal));
        $lines[] = '';
        $lines[] = '*DATA PENERIMA*';
        $lines[] = 'Nama: '.$this->plainText($customer['customer_name']);
        $lines[] = 'No. HP: '.$this->plainText($customer['customer_phone']);
        $lines[] = 'Alamat: '.$this->plainText($customer['customer_address']);
        foreach (['customer_postcode' => 'Kode pos', 'customer_note' => 'Catatan'] as $field => $label) {
            if (! empty($customer[$field])) {
                $lines[] = $label.': '.$this->plainText($customer[$field]);
            }
        }
        $lines[] = '';
        $lines[] = 'Ongkir dan pembayaran dilanjutkan di WhatsApp.';

        return $this->url($number, implode("\n", $lines));
    }

    public function availabilityLabel(string $availability): string
    {
        return CatalogAvailability::legacyLabel($availability);
    }

    public function listNotice(array $items): string
    {
        return 'Lengkapi data penerima saat checkout. Ongkir dan pembayaran dilanjutkan di WhatsApp.';
    }

    public function hasValidNumber(?string $number): bool
    {
        return $this->normalizeNumber($number) !== null;
    }

    /** Prefilled chat to a known number, e.g. the store or a customer. */
    public function textUrl(?string $number, string $message): ?string
    {
        return $this->url($number, $message);
    }

    /** Prefilled message without a recipient; WhatsApp lets the sender choose a chat or group. */
    public function shareUrl(string $message): string
    {
        return 'https://wa.me/?'.http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);
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
        return PhoneNumber::normalize($number);
    }

    public function plainText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
