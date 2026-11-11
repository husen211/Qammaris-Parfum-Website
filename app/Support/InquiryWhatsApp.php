<?php

namespace App\Support;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreInfo;

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

    /**
     * ORD-04: short message after a saved website checkout. The order already holds the recipient and address,
     * so the chat only names the order; no address or phone is repeated. ORD-11: it ends with the same task link
     * as the staff group (random public ID, admin login required), so staff open the order with one tap.
     */
    public function websiteOrderUrl(?string $number, OnlineOrder $order): ?string
    {
        return $this->url($number, $this->websiteOrderMessage($order));
    }

    /** The same text, shown for copying where no store number is available (test environments, ORD-07). */
    public function websiteOrderMessage(OnlineOrder $order): string
    {
        $quote = $order->shipping_quote;
        // ORD-13: the priced courier says more than the delivery type (J&T also delivers inside Palu).
        $delivery = match ($quote['kind'] ?? null) {
            'instant' => $quote['label'].' (kurir instan)',
            'jnt' => 'J&T ke '.($quote['destination']['area_label'] ?? '-'),
            default => config('orders.checkout_deliveries')[$order->fulfillment]['label'] ?? '-',
        };

        return implode("\n", [
            'Halo Qammaris, saya baru memesan lewat website.',
            '',
            'Nomor pesanan: *'.$order->code.'*',
            'Nama: '.$this->plainText((string) $order->customer_name),
            'Pengiriman: '.$delivery,
            'Subtotal produk: '.Rupiah::format($order->subtotal()),
            ...(Rupiah::minorUnits($order->packagingFee()) > 0 ? ['Paperbag: '.Rupiah::format($order->packagingFee())] : []),
            ...($order->fulfillment === 'local_delivery' && $order->shipping_payer === 'cash_to_driver' ? ['Ongkir: bayar langsung ke driver'] : []),
            ...($quote ? [
                'Ongkir '.$quote['label'].': '.Rupiah::format($order->shipping_fee).($quote['discount'] > 0 ? ' (potongan ongkir '.$quote['percent'].'% dari Qammaris)' : ''),
                'Total: '.Rupiah::format($order->customerTotal()),
            ] : []),
            '',
            $quote ? 'Mohon konfirmasi stok dan pembayarannya. Terima kasih.' : 'Mohon konfirmasi stok, ongkir, dan pembayarannya. Terima kasih.',
            '',
            'Cek status pesanan saya: '.route('orders.customer.show', $order->customer_token_encrypted),
            'Link pesanan untuk admin: '.OrderTaskLink::for($order)['url'],
        ]);
    }

    /** ORD-13: sent after paying; the proof itself is attached by the customer in the chat. */
    public function paymentProofMessage(OnlineOrder $order, string $method): string
    {
        return implode("\n", [
            'Halo Qammaris, saya sudah membayar pesanan saya.',
            '',
            'Nomor pesanan: *'.$order->code.'*',
            'Nama: '.$this->plainText((string) $order->customer_name),
            'Total: '.Rupiah::format($order->customerTotal()),
            'Dibayar lewat: '.(config('orders.payment_preferences')[$method] ?? $method),
            '',
            'Bukti pembayaran saya kirim di chat ini.',
            '',
            'Cek status pesanan saya: '.route('orders.customer.show', $order->customer_token_encrypted),
            'Link pesanan untuk admin: '.OrderTaskLink::for($order)['url'],
        ]);
    }

    /**
     * ORD-14: staff to the Biteship driver. The driver gets everything needed to reach the customer without the
     * courier app's chat: recipient, WhatsApp/phone number, address, note and the pin.
     */
    public function driverMessage(OnlineOrder $order): string
    {
        $booking = $order->courier_booking ?? [];

        return implode("\n", array_filter([
            'Halo '.$this->plainText((string) ($booking['driver_name'] ?? 'Kak')).', saya admin Qammaris Perfumes.',
            'Pesanan '.$order->code.' ('.OnlineOrderFulfillment::biteshipLabel($booking['courier'] ?? null).').',
            '',
            'Penerima: '.$this->plainText((string) $order->customer_name),
            'No. HP/WA penerima: '.self::localNumber($order->customer_phone),
            'Alamat: '.$this->plainText((string) $order->address),
            $order->customer_note ? 'Catatan: '.$this->plainText($order->customer_note) : null,
            $order->location_url ? 'Titik antar: '.$order->location_url : null,
            '',
            'Ada kendala? Balas chat ini. Terima kasih.',
        ], fn ($line) => $line !== null));
    }

    /** ORD-14: staff tell the customer who is coming, with Biteship's tracking link and their order page. */
    public function courierUpdateMessage(OnlineOrder $order): string
    {
        $booking = $order->courier_booking ?? [];
        $courier = OnlineOrderFulfillment::biteshipLabel($booking['courier'] ?? null);
        $onTheWay = in_array($booking['status'] ?? null, OnlineOrderFulfillment::BITESHIP_CARRYING, true);
        $driver = $this->plainText((string) ($booking['driver_name'] ?? '')).(! empty($booking['driver_plate']) ? ' ('.$this->plainText($booking['driver_plate']).')' : '');

        return implode("\n", array_filter([
            'Halo Kak '.$this->plainText((string) $order->customer_name).',',
            $onTheWay
                ? 'pesanan Qammaris '.$order->code.' sedang diantar '.$courier.'.'
                : 'kurir '.$courier.' untuk pesanan Qammaris '.$order->code.' sudah ditemukan dan sedang menuju toko.',
            '',
            $driver !== '' ? 'Driver: '.$driver : null,
            ! empty($booking['link']) ? 'Lacak driver: '.$booking['link'] : null,
            '',
            'Driver akan menghubungi nomor ini bila perlu. Status pesanan: '.route('orders.customer.show', $order->customer_token_encrypted),
        ], fn ($line) => $line !== null));
    }

    /** 62812… or +62 812… shown the way people type it: 0812…. */
    public static function localNumber(?string $number): string
    {
        $normalized = PhoneNumber::normalize($number);

        return $normalized === null ? (string) $number : (str_starts_with($normalized, '62') ? '0'.substr($normalized, 2) : $normalized);
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
        if (StoreInfo::usesTestWhatsapp() && ! str_starts_with($message, '[UAT]')) {
            $message = '[UAT] '.$message;
        }

        return 'https://wa.me/?'.http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);
    }

    private function url(?string $number, string $message): ?string
    {
        $normalized = $this->normalizeNumber($number);
        if ($normalized === null) {
            return null;
        }
        // ORD-04: a message from a test environment is always recognisable as such.
        if (StoreInfo::usesTestWhatsapp() && ! str_starts_with($message, '[UAT]')) {
            $message = '[UAT] '.$message;
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
