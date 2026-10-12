<?php

namespace App\Support;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\StorePaymentAccount;

/** Plain-text WhatsApp messages for the staff group and the customer; every stored value is flattened. */
final class OnlineOrderMessages
{
    public function __construct(private readonly InquiryWhatsApp $whatsApp) {}

    public function staffGroup(OnlineOrder $order, string $staffUrl): string
    {
        $courier = OnlineOrder::COURIERS[$order->courier] ?? null;
        $staffBooks = $order->courier_booked_by === 'staff' && $order->fulfillment !== 'pickup';

        if ($order->fulfillment === 'pickup') {
            $lines = ['*Pesanan online '.$order->code.' — DIAMBIL DI TOKO*'];
        } elseif ($staffBooks && $order->fulfillment === 'intercity') {
            $lines = ['*MOHON REQUEST PICKUP '.($courier ?? 'J&T').' — '.$order->code.'*'];
        } elseif ($staffBooks) {
            $lines = ['*MOHON DIPESANKAN '.mb_strtoupper($courier ?? 'driver').' — '.$order->code.'*'];
        } else {
            $lines = ['*Pesanan online '.$order->code.'*'];
        }

        $lines[] = 'Nama: '.$this->text($order->customer_name ?? '(belum diisi customer)');
        $lines[] = 'Pesanan: '.$order->items->map(fn (OnlineOrderItem $item) => $this->text($item->label()).' '.$item->quantity.'x')->implode(', ');
        $lines[] = 'Kemasan: '.(OnlineOrder::PACKAGING[$order->packaging] ?? '-');

        if ($staffBooks) {
            $lines[] = 'HP penerima: '.$this->text($order->customer_phone ?? '-');
            if ($order->fulfillment === 'intercity') {
                $lines[] = 'Alamat: '.$this->text(trim(($order->address ?? '').' '.($order->postcode ?? '')) ?: '-');
            } else {
                $lines[] = 'Lokasi: '.($order->location_url ?: 'sharelok diteruskan di grup ini');
                if ($order->address) {
                    $lines[] = 'Patokan: '.$this->text($order->address);
                }
            }
        } elseif ($order->fulfillment !== 'pickup') {
            $lines[] = 'Pengiriman: '.($courier ?? 'kurir belum dipilih').' (dipesan admin)';
        }

        $lines[] = '';
        $notes = [$this->paymentNote($order)];
        if ($shipping = $this->shippingNote($order)) {
            $notes[] = $shipping;
        }
        if ($order->staff_note) {
            $notes[] = $this->text($order->staff_note);
        }
        $lines[] = 'Note: '.implode(', ', $notes);
        if ($order->customer_note) {
            $lines[] = 'Catatan customer: '.$this->text($order->customer_note);
        }
        $lines[] = '';
        $lines[] = ($staffBooks ? 'Setelah dipesan, tandai di sini: ' : 'Tandai progres di sini: ').$staffUrl;

        return implode("\n", $lines);
    }

    /**
     * V2 group message (ORD-02d). Staff act in the Admin PWA after signing in, so the message links to the order
     * page there instead of a bearer task link (D6).
     */
    public function staffGroupV2(OnlineOrder $order, string $adminUrl, bool $inApp = false): string
    {
        $local = $order->fulfillment === 'local_delivery';
        $storeBooks = $local && $order->courier_booking_responsibility === 'store' && $order->courier_status === 'unassigned';
        $jntNeeded = $order->fulfillment === 'intercity' && $order->jnt_status === 'not_requested';
        $lines = [match (true) {
            $order->fulfillment === 'pickup' => '*Pesanan online '.$order->code.' — DIAMBIL DI TOKO*',
            $jntNeeded => '*MOHON REQUEST PICKUP J&T — '.$order->code.'*',
            $storeBooks => '*MOHON DIPESANKAN KURIR — '.$order->code.'*',
            $local && $order->courier_booking_responsibility === 'customer' => '*Pesanan online '.$order->code.' — KURIR DIPESAN CUSTOMER*',
            default => '*Pesanan online '.$order->code.'*',
        }];
        $lines[] = 'Nama: '.$this->text($order->customer_name ?? '(belum diisi customer)');
        $lines[] = 'Pesanan: '.$order->items->map(fn (OnlineOrderItem $item) => $this->text($item->label()).' '.$item->quantity.'x')->implode(', ');
        $lines[] = 'Kemasan: '.(OnlineOrder::PACKAGING[$order->packaging] ?? '-');
        if ($storeBooks || $order->fulfillment === 'intercity') {
            $lines[] = 'HP penerima: '.$this->text($order->customer_phone ?? '-');
            $lines[] = $order->fulfillment === 'intercity'
                ? 'Alamat: '.($this->text(trim(($order->address ?? '').' '.($order->postcode ?? ''))) ?: '-')
                : 'Lokasi: '.($order->location_url ?: 'sharelok diteruskan di grup ini');
            if ($local && $order->address) {
                $lines[] = 'Patokan: '.$this->text($order->address);
            }
        }
        if ($order->keep_status === 'active') {
            $lines[] = 'KEEP sampai '.$order->keep_until?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M, H.i')
                .($order->keep_stock_confirmed_at ? ' (stok sudah dipisahkan)' : ' — mohon pisahkan stok');
        }

        $lines[] = '';
        $notes = [$order->payment_status === 'paid'
            ? 'sudah lunas'.($order->payment_method ? ' ('.mb_strtolower(OnlineOrder::PAYMENT_METHODS[$order->payment_method]).')' : '')
            : 'belum lunas'];
        if ($shipping = $this->shippingNote($order)) {
            $notes[] = $shipping;
        }
        if ($order->staff_note) {
            $notes[] = $this->text($order->staff_note);
        }
        $lines[] = 'Note: '.implode(', ', $notes);
        if ($order->customer_note) {
            $lines[] = 'Catatan customer: '.$this->text($order->customer_note);
        }
        $lines[] = '';
        // ORD-04: straight to the App order page when App task links are on (see OrderTaskLink).
        $lines[] = ($inApp ? 'Buka di Qammaris App: ' : 'Buka tugas (login akun masing-masing): ').$adminUrl;

        return implode("\n", $lines);
    }

    public function customerInvite(OnlineOrder $order, string $customerUrl): string
    {
        return implode("\n", [
            'Halo Kak, terima kasih sudah memesan di Qammaris.',
            'Pesanan: '.$order->items->map(fn (OnlineOrderItem $item) => $this->text($item->label()).' '.$item->quantity.'x')->implode(', '),
            'Subtotal: '.Rupiah::format($order->subtotal()),
            '',
            'Mohon lengkapi data pengiriman di link berikut ya:',
            $customerUrl,
        ]);
    }

    /**
     * ORD-04: total and payment instructions, copied by staff once the total is final. Accounts come only from
     * verified Owner configuration; without one, the text says the account follows in the chat.
     */
    public function paymentInstructions(OnlineOrder $order): string
    {
        $received = OnlineOrderMoney::totals($order)['received'];
        $total = Rupiah::minorUnits($order->customerTotal());
        $lines = ['Pesanan *'.$order->code.'*', 'Subtotal produk: '.Rupiah::format($order->subtotal())];
        if (Rupiah::minorUnits($order->packagingFee()) > 0) {
            $lines[] = 'Paperbag: '.Rupiah::format($order->packagingFee());
        }
        $lines[] = match (true) {
            $order->fulfillment === 'pickup' => 'Ongkir: - (ambil di toko)',
            $order->shipping_payer === 'added_to_transfer' => 'Ongkir: '.Rupiah::format($order->shipping_fee),
            $order->shipping_payer === 'cash_to_driver' => 'Ongkir: '.Rupiah::format($order->shipping_fee).' (dibayar langsung ke driver)',
            $order->shipping_payer === 'store_free' => 'Ongkir: gratis',
            default => 'Ongkir: menunggu konfirmasi',
        };
        if (($adjustment = $order->approvedAdjustmentCents()) !== 0) {
            $lines[] = 'Penyesuaian: '.($adjustment < 0 ? '-' : '').Rupiah::format(Rupiah::decimal(abs($adjustment)));
        }
        $lines[] = '*Total pembayaran: '.Rupiah::format(Rupiah::decimal($total)).'*';
        if ($received > 0) {
            $lines[] = 'Sudah diterima: '.Rupiah::format(Rupiah::decimal($received)).' · sisa '.Rupiah::format(Rupiah::decimal(max(0, $total - $received)));
        }
        $lines[] = '';
        $accounts = StorePaymentAccount::query()->usable()->get();
        if ($accounts->isEmpty()) {
            $lines[] = 'Nomor rekening resmi dikirim admin di chat ini.';
        } else {
            $lines[] = 'Transfer ke rekening resmi Qammaris:';
            foreach ($accounts as $account) {
                $lines[] = '- '.$this->text($account->line());
            }
        }
        $lines[] = 'Untuk QRIS, minta kode QRIS ke admin di chat ini.';
        $lines[] = '';
        $lines[] = 'Setelah membayar, kirim foto atau screenshot bukti pembayaran di chat ini. Pesanan disiapkan setelah pembayaran kami konfirmasi.';

        return implode("\n", $lines);
    }

    public function customerLocation(OnlineOrder $order): string
    {
        return 'Halo Qammaris, ini lokasi pengiriman untuk pesanan '.$order->code
            .($order->customer_name ? ' a.n. '.$this->text($order->customer_name) : '').'.';
    }

    private function paymentNote(OnlineOrder $order): string
    {
        if ($order->stepIndex() < $order->stepIndex(OnlineOrder::STAGE_PAID) && $order->stage !== OnlineOrder::STAGE_CANCELLED) {
            return 'belum dibayar';
        }

        return 'sudah dibayar'.($order->payment_method ? ' ('.mb_strtolower(OnlineOrder::PAYMENT_METHODS[$order->payment_method]).')' : '');
    }

    private function shippingNote(OnlineOrder $order): ?string
    {
        if ($order->fulfillment === 'pickup' || $order->shipping_payer === null) {
            return null;
        }
        $fee = $order->shipping_fee !== null ? ' '.Rupiah::format($order->shipping_fee) : '';
        if ($order->shipping_payer === 'cash_to_driver') {
            return 'ongkir'.$fee.' dibayar customer langsung ke driver';
        }
        $prefix = $order->shipping_payer === 'store_free' ? 'gratis ongkir untuk customer' : 'ongkir'.$fee.' sudah termasuk transfer customer';

        return $prefix.match ($order->driver_funding) {
            'cashier_cash' => ', bayar driver pakai uang kasir',
            'owner_gopay' => ', ongkir driver dikirim admin ke GoPay staf',
            'staff_advance' => ', mohon ditalangi dulu lalu catat di link untuk diganti',
            default => '',
        };
    }

    private function text(string $value): string
    {
        return $this->whatsApp->plainText($value);
    }
}
