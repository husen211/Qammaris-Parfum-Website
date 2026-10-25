<?php

// SEO-03 (Owner 2026-10-25): permanent catalog pages with their own address, title and short introduction, so a
// search like "parfum pria Palu" or "parfum Afnan" has a page to land on. Each page is the normal catalog with one
// filter fixed by its address; any extra filter, sort or search on it is noindex (see products/index).
// Intros state only what the catalog data or the Owner confirmed. "{store}" and "{unisex}" become links.
return [
    // /koleksi/{key}: catalog fixed to one Peruntukan value.
    'genders' => [
        'parfum-pria' => [
            'gender' => 'Pria',
            'name' => 'Parfum Pria',
            'title' => 'Parfum Pria Original di Palu - Qammaris Perfumes',
            'description' => 'Parfum pria Timur Tengah dan lokal di Qammaris Palu: aroma kayu, rempah, oud, sampai wangi segar harian. Coba tester di toko atau pesan online, kirim ke seluruh Indonesia.',
            'intro' => [
                'Kumpulan parfum pria di Qammaris, dari karakter kayu, rempah, dan oud khas Timur Tengah sampai wangi segar yang nyaman dipakai harian.',
                'Ingin mencium aromanya dulu? Semua parfum bisa dicoba langsung di {store}. Banyak aroma unisex juga cocok untuk pria, lihat {unisex}.',
            ],
        ],
        'parfum-wanita' => [
            'gender' => 'Wanita',
            'name' => 'Parfum Wanita',
            'title' => 'Parfum Wanita Original di Palu - Qammaris Perfumes',
            'description' => 'Parfum wanita Timur Tengah dan lokal di Qammaris Palu: floral, buah, vanila, dan musk. Coba tester di toko atau pesan online, kirim ke seluruh Indonesia.',
            'intro' => [
                'Parfum wanita pilihan Qammaris, dari floral yang lembut dan buah yang manis sampai vanila dan musk yang hangat, dari brand Timur Tengah dan lokal.',
                'Semua parfum bisa dicoba langsung di {store} sebelum membeli. Ingin pilihan yang lebih luas? Lihat juga {unisex}.',
            ],
        ],
        'parfum-unisex' => [
            'gender' => 'Unisex',
            'name' => 'Parfum Unisex',
            'title' => 'Parfum Unisex Original di Palu - Qammaris Perfumes',
            'description' => 'Parfum unisex Timur Tengah dan lokal di Qammaris Palu, dari musk dan aroma bersih untuk harian sampai oud dan rempah. Coba tester di toko atau pesan online.',
            'intro' => [
                'Parfum unisex bisa dipakai siapa saja, dan koleksi inilah yang paling banyak di Qammaris: dari musk dan aroma bersih untuk harian sampai oud dan rempah yang lebih berani.',
                'Bingung memilih? Coba beberapa aroma langsung di {store} dan diskusikan dengan staf kami. Pesan online juga bisa, kami kirim ke seluruh Indonesia.',
            ],
        ],
    ],

    // /brand/{slug}: catalog fixed to one brand (the stored brand slug). "name" is the display name.
    'brands' => [
        'frag-world' => [
            'name' => 'Fragrance World',
            'intro' => 'Fragrance World adalah rumah parfum dari Uni Emirat Arab dengan banyak lini aroma, dari gaya Timur Tengah sampai modern.',
        ],
        'mykonos' => [
            'name' => 'Mykonos',
            'intro' => 'Mykonos adalah brand parfum lokal Indonesia. Hampir semua koleksinya di Qammaris bergaya unisex dengan aroma yang mudah dipakai sehari-hari.',
        ],
        'armaf' => [
            'name' => 'Armaf',
            'intro' => 'Armaf adalah brand parfum dari Uni Emirat Arab di bawah Sterling Parfums, dengan koleksi pria, wanita, dan unisex.',
        ],
        'bali-surfer' => [
            'name' => "Bali Surfer's Perfume",
            'intro' => "Bali Surfer's Perfume adalah brand parfum lokal Indonesia dengan nuansa pantai dan budaya selancar Bali.",
        ],
        'zimaya-by-afnan' => [
            'name' => 'Zimaya by Afnan',
            'intro' => 'Zimaya adalah lini parfum dari Afnan, rumah parfum asal Uni Emirat Arab, dengan pilihan untuk pria, wanita, dan unisex.',
        ],
        'ahmed-al-maghribi' => [
            'name' => 'Ahmed Al Maghribi',
            'intro' => 'Ahmed Al Maghribi adalah rumah parfum dari Uni Emirat Arab yang dikenal dengan aroma oriental seperti oud dan musk.',
        ],
        'afnan' => [
            'name' => 'Afnan',
            'intro' => 'Afnan adalah rumah parfum dari Dubai, Uni Emirat Arab. Di Qammaris tersedia parfum Afnan untuk pria, wanita, dan unisex.',
        ],
        'french-avenue' => [
            'name' => 'French Avenue',
            'intro' => 'French Avenue adalah lini parfum dari Fragrance World, Uni Emirat Arab, dengan banyak aroma unisex bergaya modern.',
        ],
        'rasasi' => [
            'name' => 'Rasasi',
            'intro' => 'Rasasi adalah rumah parfum dari Dubai, Uni Emirat Arab, yang berdiri sejak 1979.',
        ],
        'khadlaj' => [
            'name' => 'Khadlaj',
            'intro' => 'Khadlaj adalah rumah parfum dari Uni Emirat Arab dengan koleksi pria, wanita, dan unisex.',
        ],
    ],
];
