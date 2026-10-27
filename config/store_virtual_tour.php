<?php

/*
 * TOUR-01: walkable 360 tour of the Palu store.
 *
 * Panoramas are retouched Teleport 360 captures under public/images/virtual-tour/ (equirectangular,
 * 4096 and 6144 px wide; the viewer picks the larger one only when the device can draw it).
 * Yaw follows the panorama: 0 = image centre, positive to the right.
 * - yaw: view on arrival from the area buttons;
 * - links: walk arrows; yaw/pitch place the arrow in this panorama, arrive is the yaw faced in the
 *   target so the walking direction carries over. Directions come from relative camera poses
 *   (feature matches between the panoramas), not from a measured floor plan.
 */

return [
    'start' => 'pintu-masuk',

    'spots' => [
        'pintu-masuk' => [
            'name' => 'Pintu Masuk',
            'yaw' => -140,
            'links' => [
                ['to' => 'tengah', 'yaw' => -136.2, 'pitch' => -24, 'arrive' => 32.4],
            ],
        ],
        'tengah' => [
            'name' => 'Tengah',
            'yaw' => 0,
            'links' => [
                ['to' => 'depan-kasir', 'yaw' => 18.6, 'pitch' => -16, 'arrive' => 19.2],
                ['to' => 'pintu-masuk', 'yaw' => -147.3, 'pitch' => -24, 'arrive' => 43.8],
            ],
        ],
        'depan-kasir' => [
            'name' => 'Depan Kasir',
            'yaw' => 0,
            'links' => [
                ['to' => 'tengah', 'yaw' => -160.8, 'pitch' => -16, 'arrive' => -161.4],
            ],
        ],
    ],
];
