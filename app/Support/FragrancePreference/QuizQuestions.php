<?php

namespace App\Support\FragrancePreference;

final class QuizQuestions
{
    public static function all(): array
    {
        $families = ['citrus' => 'Citrus / segar', 'aquatic' => 'Aquatic / nuansa air', 'green_herbal' => 'Hijau / herbal', 'fruit' => 'Buah', 'floral' => 'Bunga', 'wood' => 'Kayu', 'gourmand' => 'Dessert / makanan (gourmand)', 'amber_resin' => 'Amber / resin', 'oud' => 'Oud', 'leather_smoky' => 'Kulit / asap (leather)', 'musk' => 'Musk', 'powdery' => 'Bedak (powdery)'];

        return [
            'budget_max' => ['label' => 'Berapa budget untuk satu botol?', 'helper' => 'Pilih rentang harga atau tulis batas maksimalmu.', 'type' => 'budget', 'options' => []],
            'use' => ['label' => 'Paling sering dipakai untuk apa?', 'helper' => 'Pilih kegiatan yang paling sering kamu lakukan saat memakai parfum.', 'type' => 'radio', 'options' => ['daily' => 'Harian', 'office' => 'Kantor / kuliah', 'event' => 'Acara / date']],
            'environment' => ['label' => 'Biasanya dipakai di mana?', 'helper' => 'Pilih tempat pemakaian yang paling sering.', 'type' => 'radio', 'options' => ['ac' => 'Ruangan ber-AC', 'outdoor' => 'Luar ruangan', 'mixed' => 'Dalam dan luar ruangan']],
            'time' => ['label' => 'Biasanya dipakai kapan?', 'helper' => 'Pilih waktu pemakaian yang paling sering.', 'type' => 'radio', 'options' => ['day' => 'Pagi / siang', 'night' => 'Sore / malam', 'both' => 'Pagi hingga malam']],
            'likes' => ['label' => 'Aroma apa yang kamu suka?', 'helper' => 'Pilih maksimal tiga. Kalau belum mengenali seleramu, pilih belum tahu.', 'type' => 'multi', 'options' => $families],
            'avoid' => ['label' => 'Aroma apa yang tidak kamu suka?', 'helper' => 'Boleh pilih lebih dari satu. Tidak suka wangi manis? Pilih Aroma manis.', 'type' => 'multi', 'options' => $families],
            'sweetness' => ['label' => 'Kamu suka aroma yang manis?', 'helper' => 'Pilih yang terasa paling nyaman buatmu.', 'type' => 'radio', 'options' => ['none' => 'Tidak manis', 'light' => 'Sedikit manis', 'medium' => 'Manis sedang', 'sweet' => 'Manis terasa jelas', 'any' => 'Tidak punya pilihan khusus']],
            'projection' => ['label' => 'Mau wanginya tercium seperti apa?', 'helper' => 'Bayangkan saat kamu berada di dekat orang lain.', 'type' => 'radio', 'options' => ['close' => 'Tercium saat orang dekat', 'medium' => 'Tercium oleh orang di sekitar', 'strong' => 'Lebih kuat dan mudah tercium', 'unknown' => 'Belum tahu']],
            'longevity' => ['label' => 'Saat memilih parfum, ketahanan jadi pertimbangan?', 'helper' => 'Ketahanan bisa berbeda saat dipakai. Kami melihat informasi yang tersedia di katalog.', 'type' => 'radio', 'options' => ['all_day' => 'Ya, cari yang punya info tahan lama', 'not_priority' => 'Aroma yang cocok lebih penting', 'unknown' => 'Belum tahu']],
            'gender' => ['label' => 'Mau cari parfum pria, wanita, atau unisex?', 'helper' => 'Boleh dilewati. Parfum unisex juga bisa masuk pilihan pria atau wanita.', 'type' => 'radio', 'optional' => true, 'options' => ['pria' => 'Pria', 'wanita' => 'Wanita', 'unisex' => 'Unisex', 'all' => 'Tidak membatasi']],
            'favorite_product_id' => ['label' => 'Ada parfum di katalog kami yang pernah kamu suka?', 'helper' => 'Boleh dilewati. Pilihan aroma yang kamu jawab sekarang tetap jadi acuan utama.', 'type' => 'favorite', 'optional' => true, 'options' => []],
        ];
    }
}
