<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CatalogAvailability
{
    /**
     * Short public label. Connected products follow Qammaris App without downgrade; when the feed is stale the label
     * stops implying current stock (ORD-04, Owner 2026-10-10). Manual products show when they were checked.
     */
    public static function label(Product $product): string
    {
        if ($product->availability_source !== 'qammaris_app') {
            if ($product->effective_availability === Product::AVAILABILITY_AVAILABLE && $product->availability_checked_at) {
                return 'Tersedia · dicek '.$product->availability_checked_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M');
            }

            return self::legacyLabel($product->effective_availability);
        }

        return match ($product->effective_availability) {
            Product::AVAILABILITY_AVAILABLE => self::syncIsStale() ? 'Tersedia · stok belum diperbarui' : 'Tersedia',
            Product::AVAILABILITY_SOLD_OUT => $product->availability_restock_eta
                ? 'Habis · Restok segera' : 'Habis',
            default => 'Tanyakan ketersediaan',
        };
    }

    /** One sentence for the product page about where an "available" status comes from; empty when nothing needs saying. */
    public static function detail(Product $product): string
    {
        if ($product->effective_availability !== Product::AVAILABILITY_AVAILABLE) {
            return '';
        }
        if ($product->availability_source === 'qammaris_app') {
            return self::syncIsStale()
                ? 'Data stok dari sistem toko belum diperbarui. Admin mengonfirmasi stok saat memproses pesanan.'
                : '';
        }

        return 'Stok dicek manual oleh admin, bukan real-time. Admin mengonfirmasi ulang saat memproses pesanan.';
    }

    /**
     * The App feed is stale when it never synced, the last run failed, or the last success is older than the window
     * (webhook pushes plus a 30-minute reconcile normally keep it fresh).
     */
    public static function syncIsStale(): bool
    {
        return once(function (): bool {
            try {
                $state = DB::table('qammaris_app_sync_states')->where('id', 'products')->first();
            } catch (Throwable) {
                return true;
            }
            if (! $state || ! $state->last_synced_at || $state->last_error) {
                return true;
            }

            return Carbon::parse($state->last_synced_at)->lt(now()->subMinutes((int) config('qammaris_app.availability_stale_minutes', 120)));
        });
    }

    public static function legacyLabel(string $availability): string
    {
        return match ($availability) {
            Product::AVAILABILITY_AVAILABLE => 'Tersedia saat diperiksa',
            Product::AVAILABILITY_SOLD_OUT => 'Sold out',
            default => 'Konfirmasi stok',
        };
    }
}
