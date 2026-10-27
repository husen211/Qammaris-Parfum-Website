<?php

/*
 * TOUR-01: walkable 360 tour of the Palu store.
 *
 * Panoramas are retouched Teleport 360 captures under public/images/virtual-tour/ (equirectangular,
 * 4096 and 6144 px wide; the viewer picks the larger one only when the device can draw it).
 * Yaw follows the panorama: 0 = image centre, positive to the right.
 * - yaw: view on arrival from the area buttons;
 * - plan: [x, y] on the floor plan in camera-distance units, y toward the counter;
 *   theta: plan heading of the panorama's yaw 0. Both come from relative camera poses
 *   (feature matches between the four panoramas), not from a measured floor plan.
 * - links: walk arrows; yaw/pitch place the arrow in this panorama, arrive is the yaw faced in
 *   the target so the walking direction carries over.
 */

return [
    'start' => 'pintu-masuk',

    'spots' => [
        'pintu-masuk' => [
            'name' => 'Pintu Masuk',
            'yaw' => -140,
            'theta' => 168.6,
            'plan' => [-0.68, -1.06],
            'links' => [
                ['to' => 'tengah', 'yaw' => -136.2, 'pitch' => -24, 'arrive' => 32.4],
            ],
        ],
        'tengah' => [
            'name' => 'Tengah',
            'yaw' => 0,
            'theta' => 0,
            'plan' => [0, 0],
            'links' => [
                ['to' => 'dalam-tengah', 'yaw' => -3.7, 'pitch' => -24, 'arrive' => 14.3],
                ['to' => 'pintu-masuk', 'yaw' => -147.3, 'pitch' => -24, 'arrive' => 43.8],
            ],
        ],
        'dalam-tengah' => [
            'name' => 'Dalam Tengah',
            'yaw' => 0,
            'theta' => -18.6,
            'plan' => [0, 0.95],
            'links' => [
                ['to' => 'depan-kasir', 'yaw' => 39.1, 'pitch' => -16, 'arrive' => 22.6],
                ['to' => 'tengah', 'yaw' => -165.7, 'pitch' => -24, 'arrive' => 176.3],
            ],
        ],
        'depan-kasir' => [
            'name' => 'Depan Kasir',
            'yaw' => 0,
            'theta' => 0.2,
            'plan' => [0.93, 2.91],
            'links' => [
                ['to' => 'dalam-tengah', 'yaw' => -157.4, 'pitch' => -16, 'arrive' => -140.9],
            ],
        ],
    ],
];
