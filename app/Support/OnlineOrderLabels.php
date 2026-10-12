<?php

namespace App\Support;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderEvent;

/** Indonesian labels for V2 order state shown in the Admin PWA (ORD-02d). */
final class OnlineOrderLabels
{
    public const QUEUE = [
        'needs_handling' => 'Perlu ditangani',
        'preparing' => 'Sedang disiapkan',
        'ready' => 'Siap diserahkan',
        'awaiting_pickup' => 'Menunggu pickup',
        'in_delivery' => 'Dalam pengiriman',
        'done' => 'Selesai ditangani',
        'has_issue' => 'Ada kendala',
    ];

    public const LIFECYCLE = [
        'draft' => 'Menunggu konfirmasi website',
        'awaiting_customer' => 'Menunggu data customer',
        'active' => 'Aktif',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public const PAYMENT = [
        'unpaid' => 'Belum lunas',
        'paid' => 'Lunas',
        'refund_pending' => 'Refund belum selesai',
        'refunded' => 'Sudah direfund',
    ];

    public const REFUND = [
        'needs_reconciliation' => 'Perlu rekonsiliasi',
        'not_required' => 'Tidak ada refund',
        'pending' => 'Refund belum dibayar',
        'partial' => 'Refund sebagian',
        'refunded' => 'Refund selesai',
    ];

    public const PREPARATION = ['not_started' => 'Belum disiapkan', 'preparing' => 'Sedang disiapkan', 'packed' => 'Sudah dipacking'];

    public const COURIER = ['not_needed' => 'Tidak perlu', 'unassigned' => 'Belum dipesan', 'requested' => 'Sudah dipesan', 'arrived' => 'Kurir tiba'];

    public const JNT = ['not_requested' => 'Belum request pickup', 'pickup_requested' => 'Pickup diminta', 'qr_available' => 'QR tersedia', 'picked_up' => 'Dipickup J&T'];

    public const HANDED_TO = ['customer' => 'customer', 'courier' => 'kurir', 'customer_courier' => 'kurir customer', 'jnt' => 'J&T'];

    public const PROVIDERS = ['maxim' => 'Maxim', 'gosend' => 'GoSend', 'grab' => 'GrabExpress', 'other' => 'Kurir lain'];

    public const CONFIRMATION_SOURCES = ['proof_in_chat' => 'Bukti di chat', 'proof_uploaded' => 'Bukti diunggah', 'majoo' => 'Majoo', 'admin_recorded' => 'Dicatat admin'];

    private const EVENTS = [
        'created' => 'membuat pesanan',
        'advance' => 'menandai data diterima',
        'details_updated' => 'mengubah detail',
        'link_regenerated' => 'membuat ulang link',
        'customer_linked' => 'menghubungkan pelanggan',
        'address_saved' => 'menyimpan alamat',
        'address_reused' => 'memakai alamat tersimpan',
        'payment_recorded' => 'mencatat pembayaran',
        'refund_decided' => 'memutuskan refund',
        'refund_corrected' => 'mengoreksi keputusan refund',
        'refund_recorded' => 'mencatat pengembalian dana',
        'ledger_reversed' => 'membatalkan entri keuangan',
        'reconciled' => 'merekonsiliasi pembayaran',
        'preparation_started' => 'mulai menyiapkan',
        'packed' => 'mengonfirmasi packing',
        'courier_responsibility' => 'mengatur pemesan kurir',
        'courier_requested' => 'memesan kurir',
        'courier_arrived' => 'menandai kurir tiba',
        'jnt_pickup_requested' => 'request pickup J&T',
        'jnt_qr_available' => 'menandai QR J&T tersedia',
        'jnt_picked_up' => 'menandai dipickup J&T',
        'jnt_tracking' => 'mengisi resi J&T',
        'handed_over' => 'menyerahkan pesanan',
        'delivered' => 'menandai diterima customer',
        'issue_opened' => 'mencatat kendala',
        'issue_resolved' => 'menyelesaikan kendala',
        'keep_started' => 'memulai keep',
        'keep_stock_set_aside' => 'memisahkan stok keep',
        'keep_extended' => 'memperpanjang keep',
        'keep_released' => 'melepas keep',
        'cancelled' => 'membatalkan pesanan',
        'completed' => 'pesanan selesai',
        'adjustment_requested' => 'mengajukan penyesuaian harga',
        'adjustment_approved' => 'menyetujui penyesuaian harga',
        'adjustment_rejected' => 'menolak penyesuaian harga',
        'change_requested' => 'mengajukan perubahan data',
        'change_approved' => 'menyetujui perubahan data',
        'change_rejected' => 'menolak perubahan data',
    ];

    public static function event(OnlineOrderEvent $event): string
    {
        return self::EVENTS[$event->kind] ?? $event->kind;
    }

    /** One short hint for what the order needs next, for the summary card. */
    public static function nextStep(OnlineOrder $order, ?string $queue, int $openIssues): ?string
    {
        return match (true) {
            $order->lifecycle === 'cancelled' => null,
            $order->refund_status === 'needs_reconciliation' => 'Super Admin perlu merekonsiliasi pembayaran.',
            in_array($order->refund_status, ['pending', 'partial'], true) => 'Ada refund yang belum dibayar.',
            $order->lifecycle === 'draft' => 'Cek chat WhatsApp customer, isi ongkir, lalu konfirmasi pesanan website.',
            $order->lifecycle === 'awaiting_customer' => 'Kirim link ke customer, atau lengkapi data di bagian Detail.',
            $openIssues > 0 => 'Selesaikan kendala dulu.',
            $order->keepState() === 'expired' => 'Keep sudah lewat batas: hubungi customer, perpanjang, atau lepas.',
            $order->lifecycle === 'completed' => null,
            $queue === 'needs_handling' => 'Mulai siapkan pesanan.',
            $queue === 'preparing' => 'Konfirmasi packing setiap barang.',
            $queue === 'ready' && $order->fulfillment === 'pickup' => 'Serahkan saat customer datang.',
            $queue === 'ready' && $order->fulfillment === 'intercity' => 'Request pickup J&T.',
            $queue === 'ready' && $order->courier_booking_responsibility === 'customer' => 'Tunggu kurir dari customer, lalu serahkan.',
            $queue === 'ready' => 'Pesan kurir.',
            $queue === 'awaiting_pickup' => 'Serahkan saat kurir/J&T datang.',
            $order->payment_status === 'unpaid' => 'Pembayaran belum lunas.',
            $queue === 'in_delivery' => 'Dalam pengiriman. Tandai diterima bila customer konfirmasi.',
            default => null,
        };
    }
}
