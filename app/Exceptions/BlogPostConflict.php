<?php

namespace App\Exceptions;

use RuntimeException;

class BlogPostConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Artikel berubah di sesi lain. Salin perubahan Anda, lalu muat ulang halaman edit sebelum menyimpan kembali.');
    }
}
