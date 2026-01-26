<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Logic URL Gambar yang Stabil
     */
    public function getImageUrlAttribute()
    {
        // 1. Jika tidak ada path, tampilkan placeholder
        if (empty($this->image_path)) {
            return 'https://placehold.co/600x600/F5F5F5/333?text=No+Image';
        }

        // 2. Jika path adalah URL lengkap (misal dari internet/seeder), langsung kembalikan
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        // 3. Logic Utama: Tambahkan 'storage/' di depannya
        // Ini solusi paling aman karena kita simpan di disk 'public'
        return asset('storage/' . $this->image_path);
    }
}
