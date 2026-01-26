<?php

return [
    'questions' => [
        'activity' => [
            'label' => 'Aktivitas utama kamu?',
            'helper' => 'Biar aromanya cocok untuk rutinitas harian.',
            'options' => [
                'office' => 'Kerja/Kantor',
                'outdoor' => 'Outdoor/Seharian di luar',
                'event' => 'Acara malam/Date',
                'casual' => 'Santai/Rumahan',
            ],
        ],
        'time' => [
            'label' => 'Biasanya dipakai kapan?',
            'helper' => 'Waktu pemakaian berpengaruh pada karakter aroma.',
            'options' => [
                'morning' => 'Pagi/Siang',
                'afternoon' => 'Sore',
                'night' => 'Malam',
            ],
        ],
        'intensity' => [
            'label' => 'Kamu suka wangi seperti apa?',
            'helper' => 'Pilih intensitas yang paling nyaman.',
            'options' => [
                'soft' => 'Soft (tidak menyengat)',
                'medium' => 'Sedang',
                'bold' => 'Bold (tahan lama & kuat)',
            ],
        ],
        'scent' => [
            'label' => 'Aroma yang paling kamu suka?',
            'helper' => 'Pilih salah satu keluarga aroma favorit.',
            'options' => [
                'fresh' => 'Fresh/Citrus',
                'fruity' => 'Fruity',
                'floral' => 'Floral',
                'woody' => 'Woody',
                'oud' => 'Oud',
                'sweet' => 'Sweet/Gourmand',
            ],
        ],
        'mood' => [
            'label' => 'Kamu ingin kesan apa?',
            'helper' => 'Biar karakter parfum sesuai mood.',
            'options' => [
                'clean' => 'Clean & Calm',
                'playful' => 'Playful',
                'elegant' => 'Elegant',
                'bold' => 'Bold/Statement',
            ],
        ],
        'gender' => [
            'label' => 'Preferensi gender parfum?',
            'helper' => 'Akan membantu menyaring rekomendasi.',
            'options' => [
                'all' => 'Bebas/Unisex',
                'pria' => 'Pria',
                'wanita' => 'Wanita',
            ],
        ],
    ],
    'option_tags' => [
        'activity' => [
            'office' => ['fresh' => 2, 'clean' => 1],
            'outdoor' => ['fresh' => 2, 'fruity' => 1],
            'event' => ['woody' => 2, 'oud' => 2, 'spicy' => 1],
            'casual' => ['sweet' => 1, 'floral' => 1],
        ],
        'time' => [
            'morning' => ['fresh' => 2, 'fruity' => 1],
            'afternoon' => ['floral' => 1, 'woody' => 1],
            'night' => ['woody' => 2, 'oud' => 1, 'sweet' => 1],
        ],
        'intensity' => [
            'soft' => ['fresh' => 1, 'floral' => 1],
            'medium' => ['fruity' => 1, 'woody' => 1],
            'bold' => ['oud' => 2, 'spicy' => 1, 'sweet' => 1],
        ],
        'scent' => [
            'fresh' => ['fresh' => 3],
            'fruity' => ['fruity' => 3],
            'floral' => ['floral' => 3],
            'woody' => ['woody' => 3],
            'oud' => ['oud' => 3],
            'sweet' => ['sweet' => 3],
        ],
        'mood' => [
            'clean' => ['fresh' => 2, 'clean' => 1],
            'playful' => ['fruity' => 2, 'sweet' => 1],
            'elegant' => ['floral' => 1, 'woody' => 1],
            'bold' => ['oud' => 2, 'spicy' => 1],
        ],
    ],
    'gender_map' => [
        'all' => null,
        'pria' => 'Pria',
        'wanita' => 'Wanita',
    ],
    'tag_labels' => [
        'fresh' => 'Fresh',
        'fruity' => 'Fruity',
        'floral' => 'Floral',
        'woody' => 'Woody',
        'oud' => 'Oud',
        'sweet' => 'Sweet',
        'spicy' => 'Spicy',
        'clean' => 'Clean',
    ],
    'tag_descriptions' => [
        'fresh' => 'Cocok untuk aktivitas harian dan cuaca panas.',
        'fruity' => 'Memberi kesan ceria dan approachable.',
        'floral' => 'Lembut, elegan, dan manis ringan.',
        'woody' => 'Hangat, dewasa, dan classy.',
        'oud' => 'Kuat, mewah, dan berkarakter.',
        'sweet' => 'Hangat dan comforting.',
        'spicy' => 'Bold dan penuh energi.',
        'clean' => 'Bersih, rapi, dan easy to wear.',
    ],
    'tag_keywords' => [
        'fresh' => ['fresh', 'citrus', 'bergamot', 'lemon', 'lime', 'mint', 'aquatic', 'marine', 'clean', 'soapy'],
        'fruity' => ['fruity', 'apple', 'pear', 'berry', 'peach', 'tropical', 'mango', 'pineapple'],
        'floral' => ['floral', 'rose', 'jasmine', 'lily', 'tuberose', 'ylang', 'iris'],
        'woody' => ['woody', 'wood', 'cedar', 'sandal', 'vetiver', 'patchouli'],
        'oud' => ['oud', 'agarwood'],
        'sweet' => ['sweet', 'vanilla', 'amber', 'caramel', 'chocolate', 'tonka', 'gourmand'],
        'spicy' => ['spicy', 'cinnamon', 'pepper', 'cardamom', 'clove'],
        'clean' => ['clean', 'soap', 'powdery', 'fresh'],
    ],
    'max_recommendations' => 4,
    'max_tags' => 3,
];
