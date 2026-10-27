<?php

namespace App\Support;

/**
 * TOUR-01: data for the <x-virtual-tour> block (store location page and homepage),
 * built from config/store_virtual_tour.php.
 */
class StoreVirtualTour
{
    public static function data(): array
    {
        $config = config('store_virtual_tour');

        return [
            'start' => $config['start'],
            'spots' => collect($config['spots'])->map(fn (array $spot, string $id) => [
                'id' => $id,
                'name' => $spot['name'],
                'yaw' => $spot['yaw'],
                'images' => [
                    'small' => asset("images/virtual-tour/{$id}-4096.webp"),
                    'large' => asset("images/virtual-tour/{$id}-6144.webp"),
                ],
                'preview' => asset("images/virtual-tour/{$id}-preview.webp"),
                'links' => $spot['links'],
            ])->values()->all(),
        ];
    }
}
