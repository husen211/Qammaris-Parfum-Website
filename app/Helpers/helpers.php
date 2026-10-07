<?php

use App\Models\StoreInfo;
use App\Support\Rupiah;

if (! function_exists('setting')) {
    /**
     * Mengambil nilai setting dari database StoreInfo
     *
     * @param  string|null  $key
     * @return mixed|null
     */
    function setting($key = null)
    {
        $storeInfo = StoreInfo::first();

        if (! $storeInfo) {
            return $key ? null : new StoreInfo;
        }

        if ($key) {
            return $storeInfo->$key ?? null;
        }

        return $storeInfo;
    }
}

if (! function_exists('format_rupiah')) {
    /**
     * Format angka ke rupiah
     *
     * @param  float|int|string|null  $amount
     * @return string
     */
    function format_rupiah($amount)
    {
        return Rupiah::format($amount ?? 0);
    }
}

if (! function_exists('cart_count')) {
    /**
     * Hitung total item di keranjang (session)
     *
     * @return int
     */
    function cart_count()
    {
        $cart = session('cart', []);
        $count = 0;

        foreach ($cart as $item) {
            $count += $item['quantity'];
        }

        return $count;
    }
}
