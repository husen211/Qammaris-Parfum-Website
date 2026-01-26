<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'title' => 'Panduan Memilih Parfum Sesuai Kepribadian',
                'excerpt' => 'Temukan parfum yang cocok dengan karakter dan gaya hidup kamu. Pelajari cara memilih fragrance yang tepat.',
                'content' => '<p>Memilih parfum yang tepat adalah seni tersendiri. Setiap orang memiliki preferensi aroma yang berbeda tergantung pada kepribadian, gaya hidup, dan aktivitas sehari-hari.</p><h3>1. Kenali Jenis-Jenis Fragrance Notes</h3><p>Parfum terdiri dari tiga lapisan notes: Top Notes (aroma pertama), Middle Notes (jantung parfum), dan Base Notes (aroma yang bertahan lama).</p><h3>2. Sesuaikan dengan Aktivitas</h3><p>Untuk sehari-hari, pilih aroma fresh dan ringan. Untuk acara formal atau malam hari, gunakan parfum dengan aroma lebih intens.</p>',
                'category' => 'Panduan',
                'is_published' => true,
                'published_at' => now()->subDays(7),
            ],
            [
                'title' => 'Review: Afnan 9PM - Parfum Malam yang Sophisticated',
                'excerpt' => 'Review lengkap Afnan 9PM, salah satu best seller dari brand parfum UAE yang cocok untuk acara malam.',
                'content' => '<p>Afnan 9PM adalah salah satu parfum paling populer dari brand Afnan. Dengan aroma woody spicy yang maskulin, parfum ini sangat cocok untuk digunakan pada malam hari atau acara formal.</p><h3>Performa</h3><p>Longevity: 8-10 jam<br>Sillage: Kuat<br>Projection: Medium to Heavy</p><h3>Kapan Digunakan?</h3><p>Perfect untuk dinner, date night, atau acara formal malam hari. Tidak disarankan untuk cuaca panas siang hari.</p>',
                'category' => 'Review',
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => '5 Tips Agar Parfum Lebih Tahan Lama',
                'excerpt' => 'Cara mudah membuat parfum kamu bertahan sepanjang hari. Simak tips aplikasi yang benar!',
                'content' => '<p>Banyak orang mengeluh parfum mereka cepat hilang. Berikut tips agar parfum lebih awet:</p><ol><li><strong>Aplikasikan pada Pulse Points</strong> - Semprotkan di pergelangan tangan, leher, dan belakang telinga.</li><li><strong>Gunakan Setelah Mandi</strong> - Kulit yang lembab menyerap parfum lebih baik.</li><li><strong>Jangan Digosok</strong> - Spray lalu diamkan, jangan digosok-gosok.</li><li><strong>Layer dengan Produk Matching</strong> - Gunakan body lotion atau shower gel dengan aroma serupa.</li><li><strong>Simpan dengan Benar</strong> - Jauhkan dari sinar matahari langsung dan suhu panas.</li></ol>',
                'category' => 'Tips',
                'is_published' => true,
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Perbedaan EDP, EDT, dan Parfum Oil',
                'excerpt' => 'Bingung dengan istilah Eau de Parfum, Eau de Toilette? Yuk pelajari perbedaannya di sini.',
                'content' => '<p>Dalam dunia parfum, ada beberapa jenis konsentrasi yang perlu kamu ketahui:</p><h3>Eau de Parfum (EDP)</h3><p>Konsentrasi: 15-20%<br>Durasi: 6-8 jam<br>Cocok untuk: Daily use, acara formal</p><h3>Eau de Toilette (EDT)</h3><p>Konsentrasi: 5-15%<br>Durasi: 3-5 jam<br>Cocok untuk: Casual, cuaca panas</p><h3>Parfum Oil</h3><p>Konsentrasi: 20-30% (tanpa alkohol)<br>Durasi: 10+ jam<br>Cocok untuk: Yang menginginkan longevity maksimal</p>',
                'category' => 'Panduan',
                'is_published' => true,
                'published_at' => now()->subDays(1),
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::create($post);
        }
    }
}