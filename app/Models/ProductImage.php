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

        $path = ltrim($this->image_path, '/');

        // 3. Jika sudah diawali storage/, langsung gunakan URL itu
        if (str_starts_with($path, 'storage/')) {
            $storagePath = substr($path, strlen('storage/'));
            if (!Storage::disk('public')->exists($storagePath)) {
                return 'https://placehold.co/600x600/F5F5F5/333?text=No+Image';
            }

            return asset($path);
        }

        // 4. Normalisasi jika disimpan dengan prefix public/
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        if (!Storage::disk('public')->exists($path)) {
            return 'https://placehold.co/600x600/F5F5F5/333?text=No+Image';
        }

        // 5. Logic utama: gunakan URL dari disk public
        return Storage::disk('public')->url($path);
    }
}
