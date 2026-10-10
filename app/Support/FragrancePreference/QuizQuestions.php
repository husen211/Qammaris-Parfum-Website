<?php

namespace App\Support\FragrancePreference;

final class QuizQuestions
{
    public static function all(): array
    {
        $families = ['citrus' => 'Citrus / segar', 'aquatic' => 'Aquatic / nuansa air', 'green_herbal' => 'Hijau / herbal', 'fruit' => 'Buah', 'floral' => 'Bunga', 'wood' => 'Kayu', 'gourmand' => 'Gourmand / makanan manis', 'amber_resin' => 'Amber / resin', 'oud' => 'Oud', 'leather_smoky' => 'Leather / asap', 'musk' => 'Musk', 'powdery' => 'Powdery / bedak'];

        return [
            'budget_max' => ['label' => 'Budget maksimal kamu?', 'helper' => 'Harga untuk satu botol. Alternatif sampai 10% lebih tinggi akan dipisahkan.', 'type' => 'budget', 'options' => []],
            'use' => ['label' => 'Paling sering dipakai untuk apa?', 'helper' => 'Pilih pemakaian utamanya.', 'type' => 'radio', 'options' => ['daily' => 'Harian', 'office' => 'Kantor / kuliah', 'casual' => 'Santai', 'event' => 'Acara / date', 'any' => 'Bebas']],
            'environment' => ['label' => 'Biasanya dipakai di mana?', 'helper' => 'Lingkungan membantu menentukan pilihan yang nyaman.', 'type' => 'radio', 'options' => ['ac' => 'Ruangan ber-AC', 'outdoor' => 'Luar ruangan', 'mixed' => 'Dalam dan luar ruangan', 'any' => 'Bebas']],
            'likes' => ['label' => 'Aroma apa yang kamu suka?', 'helper' => 'Pilih maksimal tiga. Belum mengenali selera? Pilih belum tahu.', 'type' => 'multi', 'options' => $families],
            'avoid' => ['label' => 'Aroma apa yang ingin dihindari?', 'helper' => 'Karakter yang terdeteksi akan dikeluarkan. Ini bukan pemeriksaan bahan atau alergi.', 'type' => 'multi', 'options' => $families],
            'sweetness' => ['label' => 'Seberapa manis yang kamu suka?', 'helper' => 'Kemanisan dipisahkan dari kuat-lemahnya sebaran.', 'type' => 'radio', 'options' => ['light' => 'Ringan', 'medium' => 'Sedang', 'sweet' => 'Manis', 'any' => 'Bebas']],
            'projection' => ['label' => 'Sebaran seperti apa yang nyaman?', 'helper' => 'Seberapa jauh aromanya terasa dari badan.', 'type' => 'radio', 'options' => ['close' => 'Dekat badan', 'medium' => 'Sedang', 'strong' => 'Menyebar kuat', 'unknown' => 'Belum tahu']],
            'longevity' => ['label' => 'Apa harapan ketahanannya?', 'helper' => 'Ini kebutuhan kamu, bukan janji performa. Hasil mengikuti bukti katalog yang tersedia.', 'type' => 'radio', 'options' => ['not_priority' => 'Tidak diprioritaskan', 'few_hours' => 'Beberapa jam', 'all_day' => 'Seharian', 'unknown' => 'Belum tahu']],
            'gender' => ['label' => 'Preferensi peruntukan katalog?', 'helper' => 'Opsional. Unisex tetap bisa direkomendasikan.', 'type' => 'radio', 'optional' => true, 'options' => ['pria' => 'Pria', 'wanita' => 'Wanita', 'all' => 'Bebas / lewati']],
            'favorite_product_id' => ['label' => 'Pernah suka parfum di katalog kami?', 'helper' => 'Opsional. Cari nama parfum atau lewati. Aroma pilihanmu tetap diutamakan.', 'type' => 'favorite', 'optional' => true, 'options' => []],
        ];
    }
}
